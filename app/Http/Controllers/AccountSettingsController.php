<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function profile(Request $request): View
    {
        $user = $request->user();
        return view('profile.show', ['user' => $user, 'avatarUrl' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $preferences = array_merge([
            'show_daily_quote' => true,
            'show_weather' => true,
            'show_forecast' => true,
        ], $user->dashboard_preferences ?? []);

        return view('settings.index', [
            'user' => $user,
            'preferences' => $preferences,
            'avatarUrl' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('remove_avatar') && $request->hasFile('avatar')) {
            return back()->withErrors(['avatar' => 'Upload a new avatar or remove the current one, not both.'])->withInput();
        }

        $user = $request->user();
        $oldAvatar = $user->avatar;
        $newAvatar = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : ($request->boolean('remove_avatar') ? null : $oldAvatar);

        $user->fullname = $validated['fullname'];
        $user->avatar = $newAvatar;
        $user->save();

        if ($oldAvatar && $oldAvatar !== $newAvatar && str_starts_with($oldAvatar, 'avatars/') && ! str_contains($oldAvatar, '..')) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return back()->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        // The auth.session middleware also invalidates other browser sessions
        // when they next make a request with the previous password hash.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your password has been changed. Sign in again with your new password.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark,blue,red'],
            'show_daily_quote' => ['sometimes', 'boolean'],
            'show_weather' => ['sometimes', 'boolean'],
            'show_forecast' => ['sometimes', 'boolean'],
        ]);

        $request->user()->forceFill([
            'theme' => $validated['theme'],
            'dashboard_preferences' => [
                'show_daily_quote' => $request->boolean('show_daily_quote'),
                'show_weather' => $request->boolean('show_weather'),
                'show_forecast' => $request->boolean('show_forecast'),
            ],
        ])->save();

        return back()->with('success', 'Your appearance and dashboard preferences have been saved.');
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:web'], 'confirmation' => ['required', 'in:DEACTIVATE']]);
        $request->user()->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Your account has been deactivated. Contact support if you want to reactivate it.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        $user = $request->user();
        $avatar = $user->avatar;

        DB::transaction(function () use ($user): void {
            // This legacy table uses SET NULL for user_id, so remove personal
            // achievement rows explicitly before the account is deleted.
            $user->achievements()->delete();
            $user->delete();
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($avatar && str_starts_with($avatar, 'avatars/') && ! str_contains($avatar, '..')) {
            Storage::disk('public')->delete($avatar);
        }

        return redirect()->route('home')->with('success', 'Your account and all associated financial records have been permanently deleted.');
    }
}
