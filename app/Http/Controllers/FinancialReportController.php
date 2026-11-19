<?php
namespace App\Http\Controllers;
use App\Mail\FinancialReportMail;
use App\Services\FinancialReportService;
use App\Services\SimplePdfReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
class FinancialReportController extends Controller
{
    public function __construct(private readonly FinancialReportService $reports, private readonly SimplePdfReport $pdf) {}
    public function index(Request $request): View
    {
        $filters=$this->validatedFilters($request);
        $report=$this->reports->build($request->user(),$filters['start'],$filters['end'],$filters['type'],$filters['category']);
        return view('finance.reports.index',[...$report,'filters'=>$filters,'categories'=>$request->user()->expenses()->select('category')->distinct()->orderBy('category')->pluck('category')]);
    }
    public function csv(Request $request)
    {
        $filters=$this->validatedFilters($request);$report=$this->reports->build($request->user(),$filters['start'],$filters['end'],$filters['type']);
        $filename='financial-report-'.$filters['start']->format('Ymd').'-'.$filters['end']->format('Ymd').'.csv';
        return response()->streamDownload(function() use($report):void {
            $output=fopen('php://output','w');fwrite($output,"\xEF\xBB\xBF");
            fputcsv($output,['Type','Date','Description','Category','Amount (KES)']);
            foreach(['income'=>$report['income'],'expenses'=>$report['expenses'],'savings'=>$report['savings']] as $type=>$rows){
                foreach($rows as $row){$description=$type==='income'?$row->source:($type==='expenses'?$row->category:$row->goal);
                    fputcsv($output,[$type,$row->transaction_date->toDateString(),$this->safeCsvCell($description),$type==='expenses'?$row->category:'',number_format((float)$row->amount,2,'.','')]);}
            }
            foreach($report['budgets'] as $budget)fputcsv($output,['budget',$budget->budget_month,$this->safeCsvCell($budget->category),$budget->category,number_format((float)$budget->amount,2,'.','')]);
            fclose($output);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
    public function pdf(Request $request)
    {
        $filters=$this->validatedFilters($request);$report=$this->reports->build($request->user(),$filters['start'],$filters['end'],$filters['type']);
        return response($this->pdf->render($this->pdfLines($report)),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="financial-report-'.$filters['start']->format('Ymd').'-'.$filters['end']->format('Ymd').'.pdf"','Cache-Control'=>'private, no-store']);
    }
    public function email(Request $request): RedirectResponse
    {
        $data=$request->validate(['start_date'=>['required','date','before_or_equal:today'],'end_date'=>['required','date','after_or_equal:start_date','before_or_equal:today'],'type'=>['required','in:all,income,expenses,savings,budgets'],'category'=>['nullable','string','max:50'],'email'=>['required','email:rfc','max:254']]);
        $start=CarbonImmutable::parse($data['start_date'])->startOfDay();$end=CarbonImmutable::parse($data['end_date'])->endOfDay();
        abort_if($start->diffInDays($end)>366,422,'Choose a reporting period of no more than 366 days.');
        $report=$this->reports->build($request->user(),$start,$end,$data['type'],$data['category']??null);
        Mail::to($data['email'])->queue(new FinancialReportMail($start->format('d M Y').' – '.$end->format('d M Y'),$this->pdfLines($report)));
        return back()->with('success','Your report has been queued for email delivery.');
    }
    private function validatedFilters(Request $request): array
    {
        $data=$request->validate(['start_date'=>['nullable','date','before_or_equal:today'],'end_date'=>['nullable','date','after_or_equal:start_date','before_or_equal:today'],'type'=>['nullable','in:all,income,expenses,savings,budgets'],'category'=>['nullable','string','max:50']]);
        $end=isset($data['end_date'])?CarbonImmutable::parse($data['end_date'])->endOfDay():CarbonImmutable::now(config('app.timezone'))->endOfDay();
        $start=isset($data['start_date'])?CarbonImmutable::parse($data['start_date'])->startOfDay():$end->startOfMonth();
        abort_if($start->diffInDays($end)>366,422,'Choose a reporting period of no more than 366 days.');
        return ['start'=>$start,'end'=>$end,'type'=>$data['type']??'all','category'=>$data['category']??null];
    }
    /** @param array<string,mixed> $report @return array<int,string> */
    private function pdfLines(array $report): array
    {
        $lines=['STUDENT FINANCE MANAGER - FINANCIAL REPORT','Period: '.$report['start']->format('d M Y').' to '.$report['end']->format('d M Y'),'Generated: '.now(config('app.timezone'))->format('d M Y H:i T'),'','SUMMARY (KES)','Income: '.number_format($report['incomeTotal'],2),'Expenses: '.number_format($report['expenseTotal'],2),'Savings: '.number_format($report['savingsTotal'],2),'Budget limits: '.number_format($report['budgetTotal'],2),'Net balance: '.number_format($report['netBalance'],2),'','TRANSACTIONS'];
        $rowCount=0;
        foreach(['income'=>$report['income'],'expenses'=>$report['expenses'],'savings'=>$report['savings']] as $type=>$rows)foreach($rows as $row){
            $label=$type==='income'?$row->source:($type==='expenses'?$row->category:$row->goal);
            $lines[]=Str::limit(strtoupper($type).' | '.$row->transaction_date->format('Y-m-d').' | '.$label.' | KES '.number_format((float)$row->amount,2),105,'');$rowCount++;
        }
        foreach($report['budgets'] as $budget){$lines[]=Str::limit('BUDGET | '.$budget->budget_month.' | '.$budget->category.' | KES '.number_format((float)$budget->amount,2),105,'');$rowCount++;}
        if($rowCount===0)$lines[]='No records found for the selected period.';
        return $lines;
    }
    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+@\-]/u',$value)===1?"'".$value:$value;
    }
}
