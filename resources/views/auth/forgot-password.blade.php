@extends('layouts.app')
@section('title','Password recovery')
@section('content')
<div class="auth-page"><section class="auth-card"><div class="brand" style="padding:0 0 25px"><span class="brand-mark">SF</span>Student Finance</div><h1>Reset your password</h1><p>Enter your account email. If it exists, we'll send a reset link.</p>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}">@csrf<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></div><button class="btn" type="submit">Send reset link</button></form>
<div class="auth-foot"><a href="{{ route('login') }}">Back to sign in</a></div></section></div>
@endsection
