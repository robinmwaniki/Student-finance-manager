@extends('layouts.app')
@section('title', 'My profile')
@section('heading', 'My profile')
@section('content')
<style>
.profile-card{max-width:760px}.profile-head{display:flex;align-items:center;gap:18px;padding-bottom:20px;border-bottom:1px solid #edf0f5;margin-bottom:20px}.profile-avatar{width:88px;height:88px;object-fit:cover;border-radius:50%;border:1px solid #e5eaf1;background:#f1f4f9}.profile-initial{display:grid;place-items:center;font:700 30px 'Space Grotesk',sans-serif;color:#52617a}.profile-meta h2{font:600 20px 'Space Grotesk',sans-serif;margin:0 0 5px}.profile-meta p{font-size:12px;color:#8490a3;margin:0 0 4px}.profile-form{max-width:520px}.profile-form .field{margin-bottom:15px}.profile-form input[type=text],.profile-form input[type=file]{width:100%;min-height:42px;border:1px solid #dfe5ee;border-radius:8px;padding:10px;font:13px 'DM Sans',sans-serif}.profile-help{font-size:11px;color:#8490a3;margin:6px 0 14px}
</style>
<section class="card profile-card">
    <div class="profile-head">
        @if($avatarUrl)<img class="profile-avatar" src="{{ $avatarUrl }}" alt="Profile picture">
        @else<div class="profile-avatar profile-initial">{{ mb_substr($user->fullname, 0, 1) }}</div>@endif
        <div class="profile-meta"><h2>{{ $user->fullname }}</h2><p>{{ $user->email }}</p><p>Member since {{ $user->created_at?->format('F Y') }}</p></div>
    </div>
    <form class="profile-form" method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="field"><label for="fullname">Full name</label><input id="fullname" name="fullname" type="text" maxlength="100" value="{{ old('fullname', $user->fullname) }}" autocomplete="name" required></div>
        <div class="field"><label for="email">Email address</label><input id="email" type="email" value="{{ $user->email }}" disabled></div>
        <div class="field"><label for="avatar">Change profile picture</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"><p class="profile-help">JPG, PNG or WebP. Maximum 2 MB and 2048 × 2048 pixels.</p></div>
        @if($avatarUrl)<label class="settings-check"><input type="checkbox" name="remove_avatar" value="1"><span>Remove current profile picture</span></label>@endif
        <div class="settings-actions"><button class="btn" type="submit">Save profile</button><a class="btn btn-light" href="{{ route('settings.index') }}">Account settings</a></div>
    </form>
</section>
@endsection
