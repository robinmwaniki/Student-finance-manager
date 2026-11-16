@extends('layouts.app')
@section('title','Choose a new password')
@section('content')
<div class="auth-page"><section class="auth-card"><div class="brand" style="padding:0 0 25px"><span class="brand-mark">SF</span>Student Finance</div><h1>Choose a new password</h1><p>Use a strong password you haven't used elsewhere.</p>
<form method="POST" action="{{ route('password.store') }}">@csrf<input type="hidden" name="token" value="{{ $request->route('token') }}">
<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required></div>
<div class="field"><label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" required></div>
<div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div><button class="btn" type="submit">Update password</button></form></section></div>
@endsection
