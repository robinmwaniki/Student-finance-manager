<?php
namespace App\Services;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
class FinancialReportService
{
    /** Build a bounded report using only the authenticated user's relationships. */
    public function build(User $user, CarbonImmutable $start, CarbonImmutable $end, string $type = 'all'): array
    {
        $from=$start->toDateString(); $to=$end->toDateString();
        $include=fn(string $candidate): bool => $type==='all' || $type===$candidate;
        $income=$include('income')?$user->income()->whereBetween('transaction_date',[$from,$to])->orderBy('transaction_date')->get():collect();
        $expenses=$include('expenses')?$user->expenses()->whereBetween('transaction_date',[$from,$to])->orderBy('transaction_date')->get():collect();
        $savings=$include('savings')?$user->savings()->whereBetween('transaction_date',[$from,$to])->orderBy('transaction_date')->get():collect();
        $budgets=$include('budgets')?$user->budgets()->whereBetween('budget_month',[$start->format('Y-m'),$end->format('Y-m')])->orderBy('budget_month')->orderBy('category')->get():collect();
        $incomeTotal=(float)$income->sum(fn($r):float=>(float)$r->amount);
        $expenseTotal=(float)$expenses->sum(fn($r):float=>(float)$r->amount);
        $savingsTotal=(float)$savings->sum(fn($r):float=>(float)$r->amount);
        return ['start'=>$start,'end'=>$end,'type'=>$type,'income'=>$income,'expenses'=>$expenses,'savings'=>$savings,'budgets'=>$budgets,
            'incomeTotal'=>$incomeTotal,'expenseTotal'=>$expenseTotal,'savingsTotal'=>$savingsTotal,
            'budgetTotal'=>(float)$budgets->sum(fn($r):float=>(float)$r->amount),
            'netBalance'=>$incomeTotal-$expenseTotal-$savingsTotal,
            'expenseByCategory'=>$expenses->groupBy('category')->map(fn(Collection $rows):float=>(float)$rows->sum(fn($r):float=>(float)$r->amount))];
    }
}
