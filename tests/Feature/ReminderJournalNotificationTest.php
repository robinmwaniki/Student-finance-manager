<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateBillReminderNotifications;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReminderJournalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['app.timezone' => 'UTC']);
        parent::tearDown();
    }

    public function test_user_can_create_update_pause_resume_and_delete_a_bill_reminder(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Electricity',
            'category' => 'Utilities',
            'amount' => '2500.00',
            'due_date' => '2026-12-31',
            'recurrence' => 'monthly',
        ])->assertRedirect();

        $reminder = $user->billReminders()->firstOrFail();
        $this->assertSame(31, $reminder->due_day);
        $this->assertSame(12, $reminder->due_month);

        $this->actingAs($user)->put(route('reminders.update', $reminder), [
            'lock_version' => 0,
            'title' => 'Electricity bill',
            'category' => 'Utilities',
            'amount' => '3000.00',
            'due_date' => '2027-01-15',
            'recurrence' => 'weekly',
        ])->assertRedirect();
        $this->assertDatabaseHas('bill_reminders', ['id' => $reminder->id, 'title' => 'Electricity bill', 'lock_version' => 1]);

        $this->actingAs($user)->patch(route('reminders.toggle', $reminder))->assertRedirect();
        $this->assertDatabaseHas('bill_reminders', ['id' => $reminder->id, 'status' => 'paused']);
        $this->actingAs($user)->patch(route('reminders.toggle', $reminder))->assertRedirect();
        $this->assertDatabaseHas('bill_reminders', ['id' => $reminder->id, 'status' => 'active']);

        $this->actingAs($user)->delete(route('reminders.destroy', $reminder), ['lock_version' => 1])->assertRedirect();
        $this->assertDatabaseMissing('bill_reminders', ['id' => $reminder->id]);
    }

    public function test_due_soon_notification_is_created_once_across_repeated_scheduler_runs(): void
    {
        Carbon::setTestNow('2026-11-16 08:00:00');
        $user = $this->makeUser();
        $user->billReminders()->create([
            'title' => 'Internet',
            'amount' => '3000.00',
            'due_date' => '2026-11-19',
            'recurrence' => 'once',
            'due_day' => 19,
            'due_month' => 11,
            'status' => 'active',
        ]);

        Artisan::call('finance:generate-reminders');
        Artisan::call('finance:generate-reminders');

        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'bill_reminder',
            'status' => 'unread',
            'deduplication_key' => 'bill-reminder:1:2026-11-19:due_soon',
        ]);
    }

    public function test_overdue_monthly_reminders_catch_up_with_short_month_anchor_preserved(): void
    {
        Carbon::setTestNow('2026-04-02 08:00:00');
        $user = $this->makeUser();
        $reminder = $user->billReminders()->create([
            'title' => 'Rent',
            'amount' => '15000.00',
            'due_date' => '2026-02-28',
            'recurrence' => 'monthly',
            'due_day' => 31,
            'due_month' => 2,
            'status' => 'active',
        ]);

        Artisan::call('finance:generate-reminders');

        $this->assertDatabaseHas('bill_reminders', ['id' => $reminder->id, 'due_date' => '2026-04-30', 'status' => 'active']);
        $this->assertDatabaseHas('user_notifications', ['deduplication_key' => 'bill-reminder:1:2026-02-28:overdue']);
        $this->assertDatabaseHas('user_notifications', ['deduplication_key' => 'bill-reminder:1:2026-03-31:overdue']);
        $this->assertDatabaseCount('user_notifications', 2);
    }

    public function test_yearly_leap_day_anchor_is_preserved_across_non_leap_years(): void
    {
        Carbon::setTestNow('2025-03-02 08:00:00');
        $user = $this->makeUser();
        $reminder = $user->billReminders()->create([
            'title' => 'Annual membership',
            'amount' => '1200.00',
            'due_date' => '2024-02-29',
            'recurrence' => 'yearly',
            'due_day' => 29,
            'due_month' => 2,
            'status' => 'active',
        ]);

        Artisan::call('finance:generate-reminders');

        $this->assertDatabaseHas('bill_reminders', ['id' => $reminder->id, 'due_date' => '2026-02-28']);
    }

    public function test_one_time_overdue_reminder_is_completed_and_paused_reminders_are_ignored(): void
    {
        Carbon::setTestNow('2026-11-20 08:00:00');
        $user = $this->makeUser();
        $once = $user->billReminders()->create([
            'title' => 'One-time fee', 'amount' => '500.00', 'due_date' => '2026-11-19',
            'recurrence' => 'once', 'due_day' => 19, 'due_month' => 11, 'status' => 'active',
        ]);
        $paused = $user->billReminders()->create([
            'title' => 'Paused bill', 'amount' => '600.00', 'due_date' => '2026-11-19',
            'recurrence' => 'once', 'due_day' => 19, 'due_month' => 11, 'status' => 'paused',
        ]);

        Artisan::call('finance:generate-reminders');

        $this->assertDatabaseHas('bill_reminders', ['id' => $once->id, 'status' => 'completed']);
        $this->assertDatabaseHas('bill_reminders', ['id' => $paused->id, 'status' => 'paused']);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_scheduler_uses_configured_local_timezone_for_due_date_comparison(): void
    {
        config(['app.timezone' => 'Africa/Nairobi']);
        Carbon::setTestNow(Carbon::parse('2026-11-18 22:30:00', 'UTC'));
        $user = $this->makeUser();
        $user->billReminders()->create([
            'title' => 'Morning payment',
            'amount' => '100.00',
            'due_date' => '2026-11-19',
            'recurrence' => 'once',
            'due_day' => 19,
            'due_month' => 11,
            'status' => 'active',
        ]);

        Artisan::call('finance:generate-reminders');

        $this->assertDatabaseHas('user_notifications', ['deduplication_key' => 'bill-reminder:1:2026-11-19:due_soon']);
    }

    public function test_monthly_journal_entry_is_unique_and_can_be_updated(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('journal.store'), [
            'note_month' => '2026-11',
            'content' => 'I tracked my spending carefully.',
            'mood' => 'good',
        ])->assertRedirect();

        $entry = $user->journalEntries()->firstOrFail();
        $this->actingAs($user)->post(route('journal.store'), [
            'note_month' => '2026-11',
            'content' => 'Duplicate month',
            'mood' => 'bad',
        ])->assertSessionHasErrors('note_month');

        $this->actingAs($user)->put(route('journal.update', $entry), [
            'lock_version' => 0,
            'note_month' => '2026-11',
            'content' => 'I improved my savings habit.',
            'mood' => 'great',
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'content' => 'I improved my savings habit.', 'mood' => 'great', 'lock_version' => 1]);
    }

    public function test_user_cannot_view_or_modify_another_users_reminders_journal_or_notifications(): void
    {
        $owner = $this->makeUser('owner@example.test');
        $other = $this->makeUser('other@example.test');
        $reminder = $owner->billReminders()->create([
            'title' => 'Private bill', 'amount' => '100.00', 'due_date' => '2026-12-01',
            'recurrence' => 'once', 'due_day' => 1, 'due_month' => 12, 'status' => 'active',
        ]);
        $entry = $owner->journalEntries()->create(['note_month' => '2026-11', 'content' => 'Private note', 'mood' => 'okay']);
        $notification = $owner->userNotifications()->create(['message' => 'Private notification', 'status' => 'unread', 'type' => 'general']);

        $this->actingAs($other)->put(route('reminders.update', $reminder), [
            'lock_version' => 0, 'title' => 'No access', 'amount' => '1.00', 'due_date' => '2026-12-01', 'recurrence' => 'once',
        ])->assertNotFound();
        $this->actingAs($other)->put(route('journal.update', $entry), [
            'lock_version' => 0, 'note_month' => '2026-11', 'content' => 'No access', 'mood' => 'bad',
        ])->assertNotFound();
        $this->actingAs($other)->patch(route('notifications.read', $notification))->assertNotFound();

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'content' => 'Private note']);
        $this->assertDatabaseHas('user_notifications', ['id' => $notification->id, 'status' => 'unread']);
    }

    public function test_notification_center_marks_one_or_all_notifications_read(): void
    {
        $user = $this->makeUser();
        $one = $user->userNotifications()->create(['message' => 'First', 'status' => 'unread', 'type' => 'general']);
        $user->userNotifications()->create(['message' => 'Second', 'status' => 'unread', 'type' => 'general']);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('2 unread');
        $this->actingAs($user)->patch(route('notifications.read', $one))->assertRedirect();
        $this->assertDatabaseHas('user_notifications', ['id' => $one->id, 'status' => 'read']);
        $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $user->id, 'status' => 'unread']);
    }

    private function makeUser(string $email = 'reminders@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Reminder Owner',
            'email' => $counter === 1 ? $email : str_replace('@', $counter.'@', $email),
            'password' => 'StrongPassword123',
        ]);
    }
}
