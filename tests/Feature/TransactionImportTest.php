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

        // A different account represents a separate browser session; do not reuse the owner's session.
        $this->flushSession();
        $this->actingAs($other)->get($previewUrl)->assertNotFound();
        $this->assertDatabaseCount('income', 0);
    }

    public function test_bank_style_headers_and_debit_credit_columns_are_mapped_automatically(): void
    {
        $user = $this->makeUser();
        $csv = "Transaction Date,Details,Debit,Credit,Balance\\n".
            "26/11/2026,Naivas Supermarket,1250.50,,8750.00\\n".
            "2026-11-25,Salary payment,,25000.00,10000.00\\n";

        $response = $this->actingAs($user)->post(route('imports.preview.store'), [
            'file' => UploadedFile::fake()->createWithContent('bank-statement.csv', $csv),
        ]);

        $previewUrl = $response->headers->get('Location');
        $this->assertNotNull($previewUrl);
        $this->get($previewUrl)->assertOk()
            ->assertSee('Food & Groceries')
            ->assertSee('Naivas Supermarket')
            ->assertSee('Salary payment');

        $this->post($previewUrl.'/confirm', ['categories' => [0 => 'Shopping']])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category' => 'Shopping',
            'amount' => '1250.50',
        ]);
        $this->assertDatabaseHas('income', [
            'user_id' => $user->id,
            'source' => 'Salary payment',
            'amount' => '25000.00',
        ]);
    }

    public function test_bank_csv_supports_signed_amounts_and_common_type_labels(): void
    {
        $user = $this->makeUser();
        // Quoted amount fields preserve thousands separators in normal CSV exports.
        $csv = "Value Date,Narration,Transaction Type,Transaction Amount\\n".
            "25-11-2026,Monthly salary,CR,\"25,000.00\"\\n".
            "24/11/2026,Shop purchase,DR,(500.00)\\n";

        $response = $this->actingAs($user)->post(route('imports.preview.store'), [
            'file' => UploadedFile::fake()->createWithContent('bank-statement.csv', $csv),
        ]);

        $response->assertRedirect();
        $previewUrl = $response->headers->get('Location');
        $this->get($previewUrl)->assertOk()->assertSee('Monthly salary')->assertSee('Shop purchase');
        $this->post($previewUrl.'/confirm')->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('income', ['user_id' => $user->id, 'amount' => '25000.00']);
        $this->assertDatabaseHas('expenses', ['user_id' => $user->id, 'amount' => '500.00']);
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
