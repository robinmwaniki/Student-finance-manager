@extends('layouts.app')
@section('title','Sign in')
@section('content')
<div class="auth-page"><section class="auth-card"><div class="brand" style="padding:0 0 25px"><span class="brand-mark">SF</span>Student Finance</div><h1>Welcome back</h1><p>Sign in to manage your money with clarity.</p>
<form method="POST" action="{{ route('login') }}">@csrf
<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
<div class="field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
<label style="display:flex;gap:8px;align-items:center;font-size:12px;color:#64748b;margin:4px 0 18px"><input type="checkbox" name="remember" value="1"> Remember me</label><button class="btn" type="submit">Sign in</button></form>
<div class="auth-foot"><a href="{{ route('password.request') }}">Forgot password?</a><br><br>New here? <a href="{{ route('register') }}">Create an account</a></div></section></div>
@endsection
