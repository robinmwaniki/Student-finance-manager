<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JournalController extends Controller
{
    private const MOODS = ['great', 'good', 'okay', 'bad'];

    public function index(Request $request): View
    {
        $month = $request->query('month', now(config('app.timezone'))->format('Y-m'));
        abort_unless(is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 404);

        $entry = $request->user()->journalEntries()->where('note_month', $month)->first();

        return view('finance.journal.index', [
            'month' => $month,
            'entry' => $entry,
            'entries' => $request->user()->journalEntries()->latest('note_month')->paginate(12)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'content' => ['required', 'string', 'max:10000'],
            'mood' => ['required', Rule::in(self::MOODS)],
        ]);

        try {
            $request->user()->journalEntries()->create($data);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            return back()->withErrors(['note_month' => 'A journal entry already exists for this month. Open it from the journal list to edit it.'])->withInput();
        }

        return redirect()->route('journal.index', ['month' => $data['note_month']])->with('success', 'Monthly journal entry saved.');
    }

    public function update(Request $request, int $entry): RedirectResponse
    {
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'note_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'content' => ['required', 'string', 'max:10000'],
            'mood' => ['required', Rule::in(self::MOODS)],
        ]);

        $user = $request->user();
        $user->journalEntries()->whereKey($entry)->firstOrFail();
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);

        try {
            $updated = $user->journalEntries()->whereKey($entry)->where('lock_version', $version)
                ->update([...$data, 'lock_version' => DB::raw('lock_version + 1')]);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            return back()->withErrors(['note_month' => 'A journal entry already exists for that month.'])->withInput();
        }

        abort_unless($updated === 1, 409, 'This journal entry changed after you opened it. Refresh and try again.');

        return redirect()->route('journal.index', ['month' => $data['note_month']])->with('success', 'Journal entry updated.');
    }

    public function destroy(Request $request, int $entry): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $request->user()->journalEntries()->whereKey($entry)->firstOrFail();
        $deleted = $request->user()->journalEntries()->whereKey($entry)
            ->where('lock_version', (int) $data['lock_version'])->delete();

        abort_unless($deleted === 1, 409, 'This journal entry changed or is no longer available. Refresh before deleting it.');

        return back()->with('success', 'Journal entry deleted.');
    }
}
