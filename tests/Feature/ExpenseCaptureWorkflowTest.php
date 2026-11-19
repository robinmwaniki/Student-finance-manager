<?php

namespace Tests\Feature;

use App\Models\ExpenseCaptureWebhookCredential;
use App\Models\User;
use App\Services\MpesaSmsParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseCaptureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mpesa_parser_extracts_outgoing_payment_and_rejects_incoming_transfer(): void
    {
        $parser = app(MpesaSmsParser::class);
        $message = 'QK12ABCDEF Confirmed. Ksh1,500.00 sent to JOHN DOE 0712345678 on 19/11/26 at 10:30 AM.';

        $candidate = $parser->parse($message);

        $this->assertSame(1500.0, $candidate['amount']);
        $this->assertSame('QK12ABCDEF', $candidate['reference']);
        $this->assertSame('JOHN DOE', $candidate['merchant']);
        $this->assertSame('2026-11-19', $candidate['transaction_date']);
        $this->assertNull($parser->parse('QK12ABCDEF Confirmed. Ksh1,500.00 received from JANE DOE on 19/11/26.'));
        $this->assertNull($parser->parse('Your M-Pesa balance is Ksh1,500.00.'));
    }

    public function test_mpesa_import_creates_an_idempotent_draft_and_only_confirmation_creates_expense(): void
    {
        $user = $this->makeUser();
        $message = 'QK12ABCDEF Confirmed. Ksh1,500.00 sent to JOHN DOE 0712345678 on 19/11/26 at 10:30 AM.';

        $this->actingAs($user)->post(route('expense-capture.mpesa'), ['message' => $message])->assertRedirect();
        $this->actingAs($user)->post(route('expense-capture.mpesa'), ['message' => $message])->assertRedirect();

        $this->assertSame(1, $user->expenseCaptureDrafts()->count());
        $this->assertSame(0, $user->expenses()->count());

        $draft = $user->expenseCaptureDrafts()->firstOrFail();
        $this->actingAs($user)->post(route('expense-capture.confirm', $draft), [
            'lock_version' => 0,
            'category' => 'Transport',
            'amount' => '1500.00',
            'transaction_date' => '2026-11-19',
            'tag' => 'M-Pesa · JOHN DOE',
        ])->assertRedirect(route('expenses.index'));

        $this->assertSame(1, $user->expenses()->count());
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => '1500.00',
            'category' => 'Transport',
        ]);
        $this->assertSame('confirmed', $draft->fresh()->status);
    }

    public function test_capture_drafts_are_owner_scoped_and_expired_drafts_cannot_be_confirmed(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser('other-capture@example.test');
        $draft = $owner->expenseCaptureDrafts()->create([
            'source' => 'voice',
            'merchant' => 'Lunch',
            'category' => 'Food',
            'amount' => '300.00',
            'transaction_date' => now()->toDateString(),
            'fingerprint' => hash('sha256', 'owner-only'),
            'status' => 'pending',
            'submission_token' => (string) Str::uuid(),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($other)->post(route('expense-capture.confirm', $draft), [
            'lock_version' => 0,
            'category' => 'Food',
            'amount' => '300.00',
            'transaction_date' => now()->toDateString(),
        ])->assertNotFound();

        $draft->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($owner)->get(route('expense-capture.index'))->assertSee('This draft has expired');
        $this->actingAs($owner)->post(route('expense-capture.confirm', $draft), [
            'lock_version' => 0,
            'category' => 'Food',
            'amount' => '300.00',
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(410);

        $this->assertSame(0, $owner->expenses()->count());
    }

    public function test_voice_transcript_creates_a_reviewable_draft_without_booking_expense(): void
    {
        $user = $this->makeUser();
        $this->assertNull(app(\App\Services\VoiceExpenseParser::class)->parse('I went shopping in 2026.'));

        $this->actingAs($user)->post(route('expense-capture.voice'), [
            'transcript' => 'I spent 350 shillings on lunch today.',
        ])->assertRedirect();

        $draft = $user->expenseCaptureDrafts()->firstOrFail();
        $this->assertSame('voice', $draft->source);
        $this->assertSame('350.00', $draft->amount);
        $this->assertSame('Food', $draft->category);
        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_barcode_lookup_creates_a_product_draft_without_assuming_a_purchase_price(): void
    {
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response([
                'status' => 1,
                'product' => ['product_name' => 'Maize Flour', 'brands' => 'Example Brand'],
            ], 200),
        ]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expense-capture.barcode'), [
            'barcode' => '1234567890123',
        ])->assertRedirect();

        $draft = $user->expenseCaptureDrafts()->firstOrFail();
        $this->assertSame('barcode', $draft->source);
        $this->assertSame('Maize Flour', $draft->merchant);
        $this->assertSame('Groceries', $draft->category);
        $this->assertNull($draft->amount);
        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_receipt_ocr_extracts_a_candidate_but_waits_for_user_confirmation(): void
    {
        config(['ai.provider' => 'gemini', 'ai.api_key' => 'server-test-key']);
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'merchant' => 'Naivas',
                        'date' => '2026-11-19',
                        'amount' => 450.00,
                        'category' => 'Groceries',
                    ])]]],
                ]],
            ], 200),
        ]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expense-capture.receipt'), [
            'receipt' => $this->fakeReceipt(),
            'consent' => '1',
        ])->assertRedirect();

        $draft = $user->expenseCaptureDrafts()->firstOrFail();
        $this->assertSame('receipt', $draft->source);
        $this->assertSame('Naivas', $draft->merchant);
        $this->assertSame('450.00', $draft->amount);
        $this->assertSame('2026-11-19', $draft->transaction_date->format('Y-m-d'));
        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_barcode_no_match_returns_manual_entry_fallback(): void
    {
        Http::fake(['*' => Http::response(['status' => 0], 200)]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expense-capture.barcode'), [
            'barcode' => '1234567890123',
        ])->assertSessionHasErrors('barcode');

        $this->assertSame(0, $user->expenseCaptureDrafts()->count());
    }

    public function test_receipt_ocr_requires_explicit_consent_and_provider_failure_keeps_no_image_or_expense(): void
    {
        config(['ai.provider' => 'gemini', 'ai.api_key' => 'server-test-key']);
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expense-capture.receipt'), [
            'receipt' => $this->fakeReceipt(),
        ])->assertSessionHasErrors('consent');

        $this->actingAs($user)->post(route('expense-capture.receipt'), [
            'receipt' => UploadedFile::fake()->create('receipt.png', 50, 'image/png'),
            'consent' => '1',
        ])->assertSessionHasErrors('receipt');

        $this->assertSame(0, $user->expenseCaptureDrafts()->count());
        $this->assertSame(0, $user->expenses()->count());
    }

    public function test_signed_mpesa_webhook_rejects_bad_signatures_and_deduplicates_event_ids(): void
    {
        $user = $this->makeUser();
        $token = 'webhook-token-'.str_repeat('a', 24);
        $secret = 'webhook-signing-secret-'.str_repeat('b', 20);
        ExpenseCaptureWebhookCredential::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'signing_secret' => $secret,
        ]);

        $payload = [
            'event_id' => 'event-20261119-001',
            'sms' => 'QK12ABCDEF Confirmed. Ksh1,500.00 sent to JOHN DOE 0712345678 on 19/11/26 at 10:30 AM.',
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $badHeaders = [...$headers, 'HTTP_X_WEBHOOK_SIGNATURE' => str_repeat('0', 64)];
        $this->call('POST', '/api/expense-capture/mpesa-webhook', [], [], [], $badHeaders, $body)->assertStatus(401);

        $this->call('POST', '/api/expense-capture/mpesa-webhook', [], [], [], $headers, $body)
            ->assertStatus(202)
            ->assertJson(['accepted' => true, 'duplicate' => false]);

        $this->call('POST', '/api/expense-capture/mpesa-webhook', [], [], [], $headers, $body)
            ->assertOk()
            ->assertJson(['accepted' => false, 'duplicate' => true]);

        $this->assertSame(1, $user->expenseCaptureDrafts()->count());
        $this->assertSame(0, $user->expenses()->count());
        $this->assertDatabaseCount('expense_capture_webhook_events', 1);
    }

    public function test_capture_page_keeps_manual_voice_fallback_visible(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('expense-capture.index'))
            ->assertOk()
            ->assertSee('Browser speech recognition requires microphone permission')
            ->assertSee('Nothing enters your expense ledger until you review and confirm');
    }

    public function test_webhook_rotation_keeps_previous_credentials_valid_for_the_grace_period(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('expense-capture.webhook.rotate'))->assertRedirect();
        $previous = session('webhook_credentials');
        $this->actingAs($user)->post(route('expense-capture.webhook.rotate'))->assertRedirect();

        $credential = $user->expenseCaptureWebhookCredential()->firstOrFail();
        $this->assertSame(hash('sha256', $previous['token']), $credential->previous_token_hash);
        $this->assertTrue($credential->previous_expires_at->isFuture());

        $payload = [
            'event_id' => 'rotation-grace-event-001',
            'sms' => 'QK12ABCDEF Confirmed. Ksh1,500.00 sent to JOHN DOE 0712345678 on 19/11/26 at 10:30 AM.',
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $previous['secret']);
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$previous['token'],
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $this->call('POST', '/api/expense-capture/mpesa-webhook', [], [], [], $headers, $body)
            ->assertStatus(202)
            ->assertJson(['accepted' => true, 'duplicate' => false]);
    }

    private function fakeReceipt(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r1sAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('receipt.png', $png);
    }

    private function makeUser(string $email = 'capture@example.test'): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'fullname' => 'Capture User',
            'email' => $counter === 1 ? $email : 'capture-'.$counter.'@example.test',
            'password' => 'StrongPassword123',
        ]);
    }
}
