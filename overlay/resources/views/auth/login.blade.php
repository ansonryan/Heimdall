@extends('layouts.app')


@section('content')
@if(!$app['config']->get('app.auth_roles_enable', false))
<?php
$user = \App\User::currentUser();
?>
<form class="form-horizontal" method="POST" action="{{ route('login') }}">
    {{ csrf_field() }}
    <div class="userlist">
    
        <div class="user" href="{{ route('user.set', [$user->id]) }}">
            @if($user->avatar)
            <img class="user-img" src="{{ asset('/storage/'.$user->avatar) }}" />
            @else
            <img class="user-img" src="{{ asset('/img/heimdall-icon-small.png') }}" />
            @endif
            {{ $user->username }}
            <input id="password" type="password" class="form-control" name="password" autofocus required>
            <button type="submit" class="btn btn-primary">Login</button>
        </div>
    </div>
        
</form>

@php
    $casdoorOn = filter_var(rescue(fn () => \App\Setting::fetch('casdoor_enabled'), false), FILTER_VALIDATE_BOOLEAN);
@endphp
@if($casdoorOn)
<div class="sso-login">
    <a class="sso-btn" href="{{ route('auth.casdoor') }}">
        <i class="fas fa-right-to-bracket"></i>
        <span>{{ config('services.casdoor.button_label') }}</span>
    </a>
    @if($errors->any())
    <div class="sso-error">{{ $errors->first() }}</div>
    @endif
</div>
@endif
@else
<section class="module-container">
<header>
    <div class="section-title">
        {{ __('app.disabled_feature') }}
    </div>
</header>
</section>
@endif

@endsection
