<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>Login · SMZL Plant Data</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/crushing.css') }}" />
  </head>
  <body class="login-page" data-page="index">
    <div id="screen-login" class="screen active">
      <svg class="flowline-bg" viewBox="0 0 800 500" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <defs>
          <linearGradient id="lg1" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#B5451A" stop-opacity=".35" />
            <stop offset="1" stop-color="#3E7690" stop-opacity=".12" />
          </linearGradient>
        </defs>
        <g fill="none" stroke="url(#lg1)" stroke-width="1.5">
          <path d="M-20 120 H120 V220 H260 V90 H420 V300 H580 V150 H900" />
          <path d="M-20 380 H180 V320 H340 V420 H520 V260 H700 V380 H900" />
          <path d="M-20 260 H90 V60 H240" />
        </g>
        <g fill="#B5451A" opacity=".4">
          <circle cx="120" cy="120" r="3" />
          <circle cx="260" cy="220" r="3" />
          <circle cx="420" cy="90" r="3" />
          <circle cx="580" cy="300" r="3" />
        </g>
      </svg>

      <form class="login-card" method="POST" action="{{ route('login.attempt') }}">
        @csrf

        <div class="login-mark">
          <div class="sq"></div>
          <div>
            <div class="t1">SMZL Plant Data</div>
            <div class="t2">Capture &amp; Tracking</div>
          </div>
        </div>

        @if($errors->any())
          <div class="login-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="field">
          <label for="login-email">Email</label>
          <input type="email" id="login-email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
        </div>

        <div class="field">
          <label for="login-password">Password</label>
          <input type="password" id="login-password" name="password" required autocomplete="current-password" />
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
      </form>
    </div>
  </body>
</html>
