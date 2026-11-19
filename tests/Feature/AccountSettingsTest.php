<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_profile_and_replace_avatar_safely(): void
    {
        Storage::fake('public');
        $oldAvatar = UploadedFile::fake()->image('old-avatar.png')->store('avatars', 'public');
        $user = $this->makeUser(['avatar' => $oldAvatar]);

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'fullname' => 'Updated Student',
            'avatar' => UploadedFile::fake()->image('new-avatar.png', 400, 400),
        ])->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Updated Student', $user->fullname);
        $this->assertNotSame($oldAvatar, $user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
        Storage::disk('public')->assertMissing($oldAvatar);
    }

    public function test_profile_avatar_rejects_svg_and_non_image_uploads(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->from(route('settings.index'))->put(route('settings.profile.update'), [
            'fullname' => 'Student User',
            'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ])->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar);
        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    public function test_profile_validation_rejects_invalid_name_without_changing_email(): void
    {
        $user = $this->makeUser();
        $originalEmail = $user->email;

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'fullname' => str_repeat('x', 101),
            'email' => 'changed@example.test',
        ])->assertSessionHasErrors('fullname');

        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->assertSame('Student User', $user->fresh()->fullname);
    }

    public function test_password_change_requires_current_password_and_invalidates_current_session(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'StrongPassword123',
            'password' => 'NewStrongPassword456',
            'password_confirmation' => 'NewStrongPassword456',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertTrue(Hash::check('NewStrongPassword456', $user->fresh()->password));
        $this->assertFalse(Hash::check('StrongPassword123', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->from(route('settings.index'))->put(route('settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewStrongPassword456',
            'password_confirmation' => 'NewStrongPassword456',
        ])->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('current_password');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('StrongPassword123', $user->fresh()->password));
    }

    public function test_theme_and_dashboard_preferences_are_persisted_and_applied(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put(route('settings.preferences.update'), [
            'theme' => 'dark',
        ])->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('dark', $user->theme);
        $this->assertSame([
            'show_daily_quote' => false,
            'show_weather' => false,
            'show_forecast' => false,
        ], $user->dashboard_preferences);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Daily reminder')
            ->assertDontSee('Weather · optional')
            ->assertDontSee('Cash-flow estimate');

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Appearance & dashboard')
            ->assertSee('Permanently delete account');
    }

    public function test_settings_page_requires_authentication(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    public function test_account_deletion_requires_password_and_exact_confirmation(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->from(route('settings.index'))->delete(route('settings.account.destroy'), [
            'current_password' => 'StrongPassword123',
            'confirmation' => 'delete',
        ])->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_deletion_removes_owned_financial_records_avatar_and_orphanable_achievements(): void
    {
        Storage::fake('public');
        $avatar = 'avatars/account-delete-test.jpg';
        Storage::disk('public')->put($avatar, 'fake-image');
        $user = $this->makeUser(['avatar' => $avatar]);
        $other = $this->makeUser(['email' => 'other-settings@example.test']);

        DB::table('income')->insert([
            'user_id' => $user->id,
            'amount' => '1000.00',
            'source' => 'Salary',
            'transaction_date' => '2026-11-19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('expenses')->insert([
            'user_id' => $user->id,
            'category' => 'Food',
            'amount' => '250.00',
            'transaction_date' => '2026-11-19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('budgets')->insert([
            'user_id' => $user->id,
            'category' => 'Food',
            'amount' => '500.00',
            'budget_month' => '2026-11',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->achievements()->create([
            'code' => 'first_income',
            'title' => 'First income',
            'description' => 'Recorded income',
            'points' => 15,
            'unlocked_at' => now(),
        ]);
        DB::table('user_notifications')->insert([
            'user_id' => $user->id,
            'message' => 'Private notification',
            'status' => 'unread',
            'type' => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('income')->insert([
            'user_id' => $other->id,
            'amount' => '900.00',
            'source' => 'Other salary',
            'transaction_date' => '2026-11-19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('settings.account.destroy'), [
            'current_password' => 'StrongPassword123',
            'confirmation' => 'DELETE',
        ])->assertRedirect(route('home'))
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('income', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('expenses', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('budgets', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('achievements', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $user->id]);
        $this->assertDatabaseHas('income', ['user_id' => $other->id, 'source' => 'Other salary']);
        Storage::disk('public')->assertMissing($avatar);
    }

    private function makeUser(array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'fullname' => 'Student User',
            'email' => 'settings-'.$counter.'-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ], $overrides));
    }
}
