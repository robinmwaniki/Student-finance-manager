@extends('layouts.app')
@section('title', 'Account settings')
@section('heading', 'Account settings')
@section('content')
<style>
.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.settings-card{min-width:0}.settings-card h2{font:600 16px 'Space Grotesk',sans-serif;margin:0 0 8px}.settings-help{font-size:11px;color:#8490a3;line-height:1.6;margin:0 0 16px}.settings-field{margin-bottom:13px}.settings-field label{display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:7px}.settings-field input,.settings-field select{width:100%;height:42px;border:1px solid #dfe5ee;border-radius:8px;padding:0 11px;font:13px 'DM Sans',sans-serif;color:#172033;background:#fff}.settings-check{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #edf0f5;font-size:12px;color:#475569}.settings-check input{margin-top:2px}.settings-check small{display:block;color:#8490a3;font-size:10px;margin-top:3px}.avatar-preview{width:76px;height:76px;border-radius:50%;object-fit:cover;border:1px solid #e5eaf1;background:#f1f4f9}.danger-card{border-color:#f1caca}.danger-card h2{color:#b42318}.settings-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:15px}
@media(max-width:760px){.settings-grid{grid-template-columns:1fr}}
</style>

<div class="settings-grid">
    <section class="card settings-card">
        <h2>Profile information</h2>
        <p class="settings-help">Update the name shown in your workspace and manage your profile image. Email address is your sign-in identity and cannot be changed here.</p>
        <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div style="display:flex;gap:15px;align-items:center;margin-bottom:18px">
                @if($avatarUrl)
                    <img class="avatar-preview" src="{{ $avatarUrl }}" alt="Current profile avatar">
                @else
                    <div class="avatar-preview" style="display:grid;place-items:center;font:600 23px 'Space Grotesk',sans-serif;color:#52617a">{{ mb_substr($user->fullname,0,1) }}</div>
                @endif
                <div class="settings-help" style="margin:0">JPG, PNG or WebP. Maximum 2 MB and 2048 × 2048 pixels.</div>
            </div>
            <div class="settings-field"><label for="fullname">Full name</label><input id="fullname" name="fullname" type="text" maxlength="100" value="{{ old('fullname', $user->fullname) }}" autocomplete="name" required></div>
            <div class="settings-field"><label for="email">Email address</label><input id="email" type="email" value="{{ $user->email }}" disabled></div>
            <div class="settings-field"><label for="avatar">Profile image</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"></div>
            @if($avatarUrl)
                <label class="settings-check"><input type="checkbox" name="remove_avatar" value="1"><span>Remove current profile image</span></label>
            @endif
            <div class="settings-actions"><button class="btn" type="submit">Save profile</button></div>
        </form>
    </section>

    <section class="card settings-card">
        <h2>Change password</h2>
        <p class="settings-help">Confirm your current password and choose a new password with at least 10 characters, including letters, uppercase and lowercase characters, and numbers. You will be signed out after changing it.</p>
        <form method="POST" action="{{ route('settings.password.update') }}">
            @csrf
            @method('PUT')
            <div class="settings-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="settings-field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required></div>
            <div class="settings-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <div class="settings-actions"><button class="btn" type="submit">Update password</button></div>
        </form>
    </section>

    <section class="card settings-card">
        <h2>Appearance & dashboard</h2>
        <p class="settings-help">Choose a theme and decide which optional cards appear on your financial overview. Your choices are saved to your account and apply on your next page load.</p>
        <form method="POST" action="{{ route('settings.preferences.update') }}">
            @csrf
            @method('PUT')
            <div class="settings-field"><label for="theme">Appearance</label><select id="theme" name="theme"><option value="light" @selected(old('theme', $user->theme) === 'light')>Light</option><option value="dark" @selected(old('theme', $user->theme) === 'dark')>Dark</option></select></div>
            <label class="settings-check"><input type="checkbox" name="show_daily_quote" value="1" @checked(old('show_daily_quote', $preferences['show_daily_quote']))><span>Show daily money reminder<small>Display the daily reminder card on the dashboard.</small></span></label>
            <label class="settings-check"><input type="checkbox" name="show_weather" value="1" @checked(old('show_weather', $preferences['show_weather']))><span>Show weather widget<small>Keep the optional, user-initiated weather card on the dashboard.</small></span></label>
            <label class="settings-check"><input type="checkbox" name="show_forecast" value="1" @checked(old('show_forecast', $preferences['show_forecast']))><span>Show cash-flow estimate<small>Display the three-month average forecast on the dashboard.</small></span></label>
            <div class="settings-actions"><button class="btn" type="submit">Save preferences</button></div>
        </form>
    </section>

    <section class="card settings-card danger-card">
        <h2>Delete account</h2>
        <p class="settings-help">This permanently deletes your account and all associated financial records, budgets, goals, reminders, journal entries, notifications, learning progress and webhook credentials. This action cannot be undone. Your profile image will also be deleted.</p>
        <form method="POST" action="{{ route('settings.account.destroy') }}" onsubmit="return confirm('Permanently delete your account and all associated financial data? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <div class="settings-field"><label for="delete_current_password">Confirm your password</label><input id="delete_current_password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="settings-field"><label for="delete_confirmation">Type DELETE to confirm</label><input id="delete_confirmation" name="confirmation" type="text" autocomplete="off" required></div>
            <div class="settings-actions"><button class="btn btn-danger" type="submit">Permanently delete account</button></div>
        </form>
    </section>
</div>
@endsection
