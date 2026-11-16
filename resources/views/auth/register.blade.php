@extends('layouts.app')
@section('title','Create account')
@section('content')
<div class="auth-page"><section class="auth-card"><div class="brand" style="padding:0 0 25px"><span class="brand-mark">SF</span>Student Finance</div><h1>Create your account</h1><p>Start building better money habits.</p>
<form method="POST" action="{{ route('register') }}">@csrf
<div class="field"><label for="fullname">Full name</label><input id="fullname" name="fullname" value="{{ old('fullname') }}" autocomplete="name" maxlength="100" required></div>
<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></div>
<div class="field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="new-password" required><small class="muted">At least 10 characters, including uppercase, lowercase and numbers.</small></div>
<div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
<button class="btn" type="submit">Create account</button></form><div class="auth-foot">Already registered? <a href="{{ route('login') }}">Sign in</a></div></section></div>
@endsection
