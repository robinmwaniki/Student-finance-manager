@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notification center')
@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:15px">
        <div><h2 class="section-heading" style="margin:0">Your notifications</h2><p class="muted" style="font-size:11px;margin:5px 0 0">{{ $unreadCount }} unread notification(s)</p></div>
        @if($unreadCount > 0)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-light btn-small" type="submit">Mark all as read</button></form>@endif
    </div>
    @forelse($notifications as $notification)
        <div style="display:flex;justify-content:space-between;gap:14px;padding:14px 0;border-bottom:1px solid #f0f2f6;align-items:flex-start">
            <div style="min-width:0"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><strong style="font-size:12px">{{ str_replace('_', ' ', ucfirst($notification->type)) }}</strong>@if($notification->status === 'unread')<span style="font-size:10px;color:#5365b5;font-weight:700">UNREAD</span>@endif</div><p style="font-size:13px;color:#475569;margin:6px 0">{{ $notification->message }}</p><small class="muted">{{ $notification->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small></div>
            @if($notification->status === 'unread')<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf @method('PATCH')<button class="btn btn-light btn-small" type="submit">Mark read</button></form>@else<span class="muted" style="font-size:11px">Read</span>@endif
        </div>
    @empty<p class="muted" style="font-size:12px">No notifications yet.</p>@endforelse
    {{ $notifications->links('pagination::simple-tailwind') }}
</div>
@endsection
