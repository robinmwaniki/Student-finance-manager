@extends('layouts.app')
@section('title', 'Financial learning')
@section('heading', 'Financial learning')
@section('content')
<div class="grid stats" style="margin-bottom:18px">
    <section class="card">
        <div class="stat-label">Total points</div>
        <div class="stat-value">{{ number_format($points) }}</div>
        <div class="stat-note">Points from verified learning and financial milestones</div>
    </section>
    <section class="card">
        <div class="stat-label">Current streak</div>
        <div class="stat-value">{{ $streak?->current_streak ?? 0 }} days</div>
        <div class="stat-note">Consecutive local calendar days with a completed daily quiz</div>
    </section>
    <section class="card">
        <div class="stat-label">Longest streak</div>
        <div class="stat-value">{{ $streak?->longest_streak ?? 0 }} days</div>
        <div class="stat-note">Your best learning streak</div>
    </section>
    <section class="card">
        <div class="stat-label">Unlocked achievements</div>
        <div class="stat-value">{{ $achievements->count() }}</div>
        <div class="stat-note">Achievements are evaluated from saved account activity</div>
    </section>
</div>

<div class="grid two-col" style="margin-top:0;margin-bottom:18px">
    <section class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <div><div class="eyebrow">Daily challenge · {{ $timezone }}</div><h2 class="section-heading" style="margin:5px 0 0">Test your money knowledge</h2></div>
            <span class="badge" style="background:#eef2ff;color:#3f51a5;padding:6px 9px;border-radius:20px;font-size:10px;font-weight:700">{{ ucfirst($question->topic) }}</span>
        </div>
        <p style="font-size:15px;font-weight:700;line-height:1.6;margin:18px 0">{{ $question->question }}</p>
        @if($attempt)
            <div class="alert {{ $attempt->is_correct ? 'alert-success' : 'alert-error' }}">
                {{ $attempt->is_correct ? 'Correct answer' : 'Your answer was not correct' }}
                · {{ $attempt->points_awarded }} points earned
            </div>
            <div style="display:grid;gap:9px">
                @foreach($question->options as $key => $option)
                    <div style="padding:11px 13px;border:1px solid {{ $key === $question->correct_option ? '#9bd8b8' : '#e8edf4' }};border-radius:9px;background:{{ $key === $question->correct_option ? '#f0fbf5' : '#fff' }};font-size:12px">
                        <strong>{{ $key }}.</strong> {{ $option }}
                        @if($key === $question->correct_option)<span style="float:right;color:#16835b;font-weight:700">Correct</span>@endif
                        @if($key === $attempt->selected_option && ! $attempt->is_correct)<span style="float:right;color:#b42318;font-weight:700">Your answer</span>@endif
                    </div>
                @endforeach
            </div>
            <p class="muted" style="font-size:12px;line-height:1.7;margin:14px 0 0">{{ $question->explanation }}</p>
            <p class="muted" style="font-size:11px;margin:12px 0 0">Come back after your next local calendar day for another question.</p>
        @else
            <form method="POST" action="{{ route('learning.quiz.submit') }}">
                @csrf
                <div style="display:grid;gap:9px">
                    @foreach($question->options as $key => $option)
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #e8edf4;border-radius:9px;font-size:12px;cursor:pointer">
                            <input type="radio" name="option" value="{{ $key }}" required style="margin-top:2px">
                            <span><strong>{{ $key }}.</strong> {{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="btn" type="submit" style="margin-top:15px">Submit answer</button>
            </form>
            <p class="muted" style="font-size:11px;margin:12px 0 0">One scored attempt is allowed per local calendar day. Your answer is checked on the server.</p>
        @endif
    </section>

    <section class="card">
        <h2 class="section-heading">How points and streaks work</h2>
        <ul style="padding-left:18px;color:#64748b;font-size:12px;line-height:1.9;margin:0">
            <li>A correct daily answer earns 10 points.</li>
            <li>Achievements award bonus points once when their server-side requirements are met.</li>
            <li>Completing a quiz advances your streak, whether your answer is right or wrong.</li>
            <li>Consecutive local calendar days extend the streak; a missed day resets the current streak to one on your next quiz.</li>
            <li>Your streak timezone is set on your first quiz and remains fixed, so changing the app timezone later does not rewrite your earlier streak days.</li>
            <li>Repeating a submission or refreshing this page cannot award duplicate daily points.</li>
        </ul>
    </section>
</div>

<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Achievement progress</h2>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        @foreach($achievementProgress as $progress)
            <article style="border:1px solid #e8edf4;border-radius:11px;padding:13px">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start"><strong style="font-size:12px">{{ $progress['title'] }}</strong><span style="font-size:10px;color:{{ $progress['unlocked'] ? '#16835b' : '#8490a3' }};font-weight:700">{{ $progress['unlocked'] ? 'UNLOCKED' : '+'.$progress['points'].' pts' }}</span></div>
                <p class="muted" style="font-size:11px;line-height:1.5;margin:7px 0">{{ $progress['description'] }}</p>
                <div style="height:6px;background:#edf0f5;border-radius:8px;overflow:hidden"><div style="height:100%;width:{{ min(100, ($progress['current'] / $progress['target']) * 100) }}%;background:{{ $progress['unlocked'] ? '#16835b' : '#5365b5' }}"></div></div>
                <small class="muted">{{ $progress['current'] }} / {{ $progress['target'] }}</small>
            </article>
        @endforeach
    </div>
</section>

<section class="card" style="margin-bottom:18px">
    <h2 class="section-heading">Achievement collection</h2>
    @if($achievements->isNotEmpty())
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            @foreach($achievements as $achievement)
                <article style="border:1px solid #e8edf4;border-radius:11px;padding:14px">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><strong style="font-size:12px">{{ $achievement->title }}</strong><span style="font-size:11px;color:#16835b;font-weight:700">+{{ $achievement->points }} pts</span></div>
                    <p class="muted" style="font-size:11px;line-height:1.6;margin:8px 0">{{ $achievement->description }}</p>
                    <small class="muted">Unlocked {{ $achievement->unlocked_at?->timezone(config('app.timezone'))->format('d M Y') }}</small>
                </article>
            @endforeach
        </div>
    @else
        <p class="muted" style="font-size:12px">No achievements unlocked yet. Record financial activity and complete daily quizzes to earn your first badge.</p>
    @endif
</section>

<section class="card">
    <h2 class="section-heading">Quiz history</h2>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Topic</th><th>Question</th><th>Result</th><th>Points</th></tr></thead><tbody>
        @forelse($attempts as $history)
            <tr><td>{{ $history->quiz_date->format('d M Y') }}</td><td>{{ ucfirst($history->question->topic) }}</td><td>{{ \Illuminate\Support\Str::limit($history->question->question, 70) }}</td><td style="color:{{ $history->is_correct ? '#16835b' : '#b42318' }};font-weight:700">{{ $history->is_correct ? 'Correct' : 'Incorrect' }}</td><td>{{ $history->points_awarded }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">Your completed quizzes will appear here.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
