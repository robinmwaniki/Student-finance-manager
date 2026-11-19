<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('finance.notifications.index', [
            'notifications' => $user->userNotifications()->latest('created_at')->paginate(20),
            'unreadCount' => $user->userNotifications()->where('status', 'unread')->count(),
        ]);
    }

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        $updated = $request->user()->userNotifications()->whereKey($notification)
            ->where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now(config('app.timezone'))]);

        abort_unless($updated === 1 || $request->user()->userNotifications()->whereKey($notification)->where('status', 'read')->exists(), 404);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->userNotifications()->where('status', 'unread')
            ->update(['status' => 'read', 'read_at' => now(config('app.timezone'))]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
