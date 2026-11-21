<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_csv_is_previewed_then_imported_only_after_confirmation(): void
    {
        $user = $this->makeUser();
        $csv = "type,date,description,amount,category,tag\n".
            "income,2026-11-01,Part-time work,15000.00,,Work\n".
            "expense,2026-11-02,Groceries,1250.50,Food,Home\n";

        $response = $this->actingAs($user)->post(route('imports.preview.store'), [
            'file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('income', 0);
        $this->assertDatabaseCount('expenses', 0);

        $previewUrl = $response->headers->get('Location');
        $this->get($previewUrl)->assertOk()->assertSee('Part-time work')->assertSee('Groceries');

        $this->post($previewUrl.'/confirm')->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('income', [
            'user_id' => $user->id,
            'source' => 'Part-time work',
            'amount' => '15000.00',
        ]);
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category' => 'Food',
            'amount' => '1250.50',
            'original_currency' => 'KES',
        ]);
    }

    public function test_invalid_csv_row_rejects_the_entire_file_without_writing_records(): void
    {
        $user = $this->makeUser();
        $csv = "type,date,description,amount\n".
            "income,2026-11-01,Salary,1000.00\n".
            "expense,not-a-date,Food,200.00\n";

        $this->actingAs($user)->from(route('imports.index'))->post(route('imports.preview.store'), [
            'file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ])->assertRedirect(route('imports.index'))->assertSessionHasErrors('file');

        $this->assertDatabaseCount('income', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_import_preview_cannot_be_opened_by_another_user(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $csv = "type,date,description,amount\nincome,2026-11-01,Salary,1000.00\n";

        $response = $this->actingAs($owner)->post(route('imports.preview.store'), [
            'file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ]);
        $previewUrl = $response->headers->get('Location');

        $this->actingAs($other)->get($previewUrl)->assertNotFound();
        $this->assertDatabaseCount('income', 0);
    }

    private function makeUser(string $email = 'importer@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'CSV Importer',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
