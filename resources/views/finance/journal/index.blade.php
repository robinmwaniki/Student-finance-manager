@extends('layouts.app')
@section('title', 'Financial journal')
@section('heading', 'Financial journal')
@section('content')
<div class="grid two-col" style="margin-top:0">
    <section class="card">
        <h2 class="section-heading">{{ $entry ? 'Edit monthly reflection' : 'Add monthly reflection' }}</h2>
        <form method="POST" action="{{ $entry ? route('journal.update', $entry) : route('journal.store') }}">
            @csrf
            @if($entry) @method('PUT')<input type="hidden" name="lock_version" value="{{ $entry->lock_version }}">@endif
            <div class="field" style="margin-bottom:12px"><label for="note_month">Month</label><input id="note_month" name="note_month" type="month" value="{{ old('note_month', $entry?->note_month ?? $month) }}" required></div>
            <div class="field" style="margin-bottom:12px"><label for="mood">How did your finances feel this month?</label><select id="mood" name="mood" required><option value="great" @selected(old('mood', $entry?->mood) === 'great')>Great</option><option value="good" @selected(old('mood', $entry?->mood) === 'good')>Good</option><option value="okay" @selected(old('mood', $entry?->mood) === 'okay')>Okay</option><option value="bad" @selected(old('mood', $entry?->mood) === 'bad')>Bad</option></select></div>
            <div class="field" style="margin-bottom:12px"><label for="content">Monthly notes and reflections</label><textarea id="content" name="content" rows="8" maxlength="10000" required style="width:100%;border:1px solid #dfe5ee;border-radius:8px;padding:11px;font:13px 'DM Sans',sans-serif">{{ old('content', $entry?->content) }}</textarea></div>
            <button class="btn" type="submit">{{ $entry ? 'Update reflection' : 'Save reflection' }}</button>
            @if($entry)<a class="btn btn-light" href="{{ route('journal.index', ['month' => $month]) }}">Cancel</a>@endif
        </form>
    </section>
    <section class="card">
        <h2 class="section-heading">Choose a month</h2>
        <form method="GET" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
            <div class="field"><label for="month">Journal month</label><input id="month" name="month" type="month" value="{{ $month }}" required></div>
            <button class="btn btn-small" type="submit">Open month</button>
        </form>
        <p class="muted" style="font-size:12px;line-height:1.6;margin-top:16px">One private journal entry is allowed per calendar month. Reopen a month from the history list to edit its reflection and mood.</p>
    </section>
</div>
<div class="card" style="margin-top:18px">
    <h2 class="section-heading">Journal history</h2>
    @forelse($entries as $item)
        <div style="display:flex;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #f0f2f6;align-items:flex-start">
            <div style="min-width:0"><strong style="font-size:13px">{{ \Carbon\CarbonImmutable::parse($item->note_month.'-01')->format('F Y') }}</strong><span class="muted" style="font-size:11px;margin-left:8px">{{ ucfirst($item->mood) }}</span><p class="muted" style="font-size:12px;white-space:pre-line;margin:6px 0 0">{{ \Illuminate\Support\Str::limit($item->content, 180) }}</p></div>
            <div style="display:flex;gap:6px;flex-shrink:0"><a class="btn btn-light btn-small" href="{{ route('journal.index', ['month' => $item->note_month]) }}">Open</a><form method="POST" action="{{ route('journal.destroy', $item) }}" onsubmit="return confirm('Delete this journal entry?')">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $item->lock_version }}"><button class="btn btn-danger btn-small" type="submit">Delete</button></form></div>
        </div>
    @empty<p class="muted" style="font-size:12px">No journal entries yet.</p>@endforelse
    {{ $entries->links('pagination::simple-tailwind') }}
</div>
@endsection
