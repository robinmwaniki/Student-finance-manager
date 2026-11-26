<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MpesaSmsParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaSmsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_mpesa_sms_are_previewed_then_imported_once(): void
    {
        $user = $this->makeUser();
        $messages = "QHX123ABC4 Confirmed. Ksh1,000.00 sent to JOHN DOE 0712345678 on 26/11/26 at 10:15 AM. New M-PESA balance is Ksh2,000.00.\n".
            "QHY123ABC5 Confirmed. You have received Ksh5,000.00 from JANE DOE 0711111111 on 26/11/26 at 11:00 AM. New M-PESA balance is Ksh7,000.00.";

        $response = $this->actingAs($user)->post(route('imports.preview.store'), ['mpesa_sms' => $messages]);
        $preview = $response->headers->get('Location');
        $this->get($preview)->assertOk()->assertSee('M-PESA · QHX123ABC4')->assertSee('M-PESA received');
        $this->post($preview.'/confirm')->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('income', 1);

        $response = $this->actingAs($user)->post(route('imports.preview.store'), ['mpesa_sms' => $messages]);
        $this->post($response->headers->get('Location').'/confirm')->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('income', 1);
    }

    public function test_duplicate_receipt_lines_are_ignored_and_unknown_messages_are_skipped(): void
    {
        $message = 'QHX123ABC4 Confirmed. Ksh100.00 paid to Merchant on 26/11/26 at 10:15 AM.';
        $rows = app(MpesaSmsParser::class)->parseMany($message."\n".$message."\nNot an M-PESA transaction.", 42);

        $this->assertCount(1, $rows);
        $this->assertSame('expense', $rows[0]['type']);
        $this->assertSame('100.00', $rows[0]['amount']);
        $this->assertSame('M-Pesa', $rows[0]['category']);
        $this->assertSame($rows[0]['submission_token'], app(MpesaSmsParser::class)->parseMany($message, 42)[0]['submission_token']);
    }

    public function test_unrecognised_sms_does_not_write_financial_records(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->from(route('imports.index'))->post(route('imports.preview.store'), [
            'mpesa_sms' => 'This is not a transaction confirmation.',
        ])->assertRedirect(route('imports.index'))->assertSessionHasErrors('mpesa_sms');
        $this->assertDatabaseCount('income', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    private function makeUser(): User
    {
        return User::create(['fullname' => 'M-PESA Importer', 'email' => 'mpesa-import@example.test', 'password' => 'StrongPassword123']);
    }
}
