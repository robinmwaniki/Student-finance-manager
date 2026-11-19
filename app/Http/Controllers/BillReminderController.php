<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillReminderController extends Controller
{
    private const RECURRENCES = ['once', 'weekly', 'monthly', 'yearly'];

    public function index(Request $request): View
    {
        $reminders = $request->user()->billReminders()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'paused' THEN 1 ELSE 2 END")
            ->orderBy('due_date')->paginate(15);

        return view('finance.reminders.index', [
            'reminders' => $reminders,
            'summary' => [
                'active' => $request->user()->billReminders()->where('status', 'active')->count(),
                'paused' => $request->user()->billReminders()->where('status', 'paused')->count(),
                'completed' => $request->user()->billReminders()->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'due_date' => ['required', 'date'],
            'recurrence' => ['required', Rule::in(self::RECURRENCES)],
        ]);

        $due = CarbonImmutable::parse($data['due_date']);
        $data['due_day'] = $due->day;
        $data['due_month'] = $due->month;
        $data['status'] = 'active';
        $request->user()->billReminders()->create($data);

        return back()->with('success', 'Bill reminder created.');
    }

    public function update(Request $request, int $reminder): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999'],
            'due_date' => ['required', 'date'],
            'recurrence' => ['required', Rule::in(self::RECURRENCES)],
        ]);

        $user = $request->user();
        $record = $user->billReminders()->whereKey($reminder)->firstOrFail();
        abort_if($record->status === 'completed', 422, 'A completed one-time reminder cannot be edited.');

        $version = (int) $data['lock_version'];
        unset($data['lock_version']);
        $due = CarbonImmutable::parse($data['due_date']);
        $data['due_day'] = $due->day;
        $data['due_month'] = $due->month;

        $updated = $user->billReminders()->whereKey($reminder)->where('lock_version', $version)
            ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);

        abort_unless($updated === 1, 409, 'This reminder changed after you opened it. Refresh and try again.');

        return back()->with('success', 'Bill reminder updated.');
    }

    public function toggle(Request $request, int $reminder): RedirectResponse
    {
        $record = $request->user()->billReminders()->whereKey($reminder)->firstOrFail();
        abort_if($record->status === 'completed', 422, 'A completed one-time reminder cannot be resumed.');
        $record->forceFill([
            'status' => $record->status === 'active' ? 'paused' : 'active',
            'lock_version' => $record->lock_version + 1,
        ])->save();

        return back()->with('success', $record->status === 'active' ? 'Reminder resumed.' : 'Reminder paused.');
    }

    public function destroy(Request $request, int $reminder): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $deleted = $request->user()->billReminders()->whereKey($reminder)
            ->where('lock_version', (int) $data['lock_version'])->delete();

        abort_unless($deleted === 1, 409, 'This reminder changed or is no longer available. Refresh before deleting it.');

        return back()->with('success', 'Bill reminder deleted.');
    }
}
