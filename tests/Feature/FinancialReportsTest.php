<?php
namespace Tests\Feature;
use App\Mail\FinancialReportMail;
use App\Models\User;
use App\Services\SimplePdfReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;
    public function test_report_totals_and_rows_are_owner_and_period_scoped(): void
    {
        $owner=$this->makeUser();$other=$this->makeUser('other-reports@example.test');
        $owner->income()->create(['source'=>'Salary','amount'=>'50000.00','transaction_date'=>'2026-11-05']);
        $owner->expenses()->create(['category'=>'Food','amount'=>'12000.00','transaction_date'=>'2026-11-10']);
        $owner->savings()->create(['goal'=>'Emergency','amount'=>'5000.00','transaction_date'=>'2026-11-12','savings_month'=>'2026-11']);
        $owner->income()->create(['source'=>'Outside period','amount'=>'900.00','transaction_date'=>'2026-10-31']);
        $other->income()->create(['source'=>'Private salary','amount'=>'999999.00','transaction_date'=>'2026-11-05']);
        $this->actingAs($owner)->get(route('reports.index',['start_date'=>'2026-11-01','end_date'=>'2026-11-19']))
            ->assertOk()->assertViewHas('incomeTotal',50000.0)->assertViewHas('expenseTotal',12000.0)->assertViewHas('savingsTotal',5000.0)
            ->assertViewHas('netBalance',33000.0)->assertSee('Salary')->assertDontSee('Private salary')->assertDontSee('Outside period');
    }
    public function test_csv_export_escapes_formula_leading_cells(): void
    {
        $user=$this->makeUser();$user->income()->create(['source'=>'=HYPERLINK("https://bad.test")','amount'=>'10.00','transaction_date'=>'2026-11-05']);
        $response=$this->actingAs($user)->get(route('reports.csv',['start_date'=>'2026-11-01','end_date'=>'2026-11-19']));
        $response->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=HYPERLINK",$response->streamedContent());
    }
    public function test_pdf_export_returns_a_pdf_document(): void
    {
        $user=$this->makeUser();$user->income()->create(['source'=>'Salary','amount'=>'1250.00','transaction_date'=>'2026-11-05']);
        $response=$this->actingAs($user)->get(route('reports.pdf',['start_date'=>'2026-11-01','end_date'=>'2026-11-19']));
        $response->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertStringStartsWith('%PDF-1.4',$response->getContent());$this->assertStringContainsString('%%EOF',$response->getContent());
    }
    public function test_ranges_longer_than_366_days_and_future_dates_are_rejected(): void
    {
        $user=$this->makeUser();
        $this->actingAs($user)->get(route('reports.index',['start_date'=>'2025-01-01','end_date'=>'2026-11-01']))->assertUnprocessable();
        $this->actingAs($user)->get(route('reports.index',['start_date'=>'2026-11-01','end_date'=>'2099-01-01']))->assertSessionHasErrors('end_date');
    }
    public function test_email_report_is_queued(): void
    {
        Mail::fake();$user=$this->makeUser();
        $this->actingAs($user)->post(route('reports.email'),['start_date'=>'2026-11-01','end_date'=>'2026-11-19','type'=>'all','email'=>'recipient@example.test'])
            ->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertQueued(FinancialReportMail::class);
    }
    public function test_report_routes_require_authentication(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.csv'))->assertRedirect(route('login'));
        $this->get(route('reports.pdf'))->assertRedirect(route('login'));
    }
    public function test_pdf_renderer_splits_long_reports_into_pages(): void
    {
        $pdf=app(SimplePdfReport::class)->render(array_fill(0,100,'Financial row'));
        $this->assertStringContainsString('/Count 3',$pdf);$this->assertStringContainsString('%%EOF',$pdf);
    }
    private function makeUser(string $email='reports@example.test'): User
    {
        static $counter=0;$counter++;
        return User::create(['fullname'=>'Report Owner','email'=>$counter===1?$email:str_replace('@',$counter.'@',$email),'password'=>'StrongPassword123']);
    }
}
