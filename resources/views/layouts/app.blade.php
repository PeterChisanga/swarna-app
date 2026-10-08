<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title') · SMZL Plant Data</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/crushing.css') }}" />
  </head>
  <body data-page="@yield('page')">
    @php
      $authUser = auth()->user();
      $pendingCount = $authUser->isAdmin() ? \App\Support\Review::pendingCount() : null;
    @endphp

    <div id="drawer-backdrop"></div>

    <div id="app-shell">
      <aside id="sidebar" aria-label="Plant sections">
        <div class="side-brand">
          SMZL Plant Data
          <span>Crushing MVP</span>
        </div>

        <div class="side-group-label">Process</div>

        <a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
           href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
          <span class="dot"></span>Overview
        </a>

        <a class="side-link {{ request()->routeIs('crushing.*') ? 'active' : '' }}"
           href="{{ route('crushing.index') }}" @if(request()->routeIs('crushing.*')) aria-current="page" @endif>
          <span class="dot"></span>Crushing
        </a>

        @if($authUser->isAdmin())
          <div class="side-group-label">Plant-wide</div>

          <a class="side-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}"
             href="{{ route('approvals.index') }}" @if(request()->routeIs('approvals.*')) aria-current="page" @endif>
            <span class="dot"></span>Approvals
            <span class="badge" id="pending-count">{{ $pendingCount }}</span>
          </a>
        @endif
      </aside>

      <div id="main">
        <header id="topbar">
          <button type="button" class="mobile-menu" id="mobile-menu" aria-label="Open sections" aria-expanded="false">☰</button>

          @isset($date)
            <div class="filt">
              <label for="global-date">Operating date</label>
              <input id="global-date" type="date" value="{{ $date }}" />
            </div>

            <div class="filt">
              <label for="global-shift">Shift</label>
              <select id="global-shift">
                @foreach(['all' => 'All shifts', 'Day' => 'Day', 'Night' => 'Night'] as $value => $label)
                  <option value="{{ $value }}" @selected(($shift ?? 'all') === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
          @endisset

          <div class="top-actions">
            <form method="POST" action="{{ route('logout') }}" class="logout-form">
              @csrf
              <button type="submit" class="top-link">Sign out</button>
            </form>

            <span class="user-pill">
              <span class="av">{{ $authUser->initials() }}</span>
              <span>{{ $authUser->name }} <span class="role-tag">{{ $authUser->role }}</span></span>
            </span>
          </div>
        </header>

        <main class="content">
          @yield('content')
        </main>
      </div>
    </div>

    <div id="overlay" role="dialog" aria-modal="true" aria-labelledby="pv-title">
      <div class="modal">
        <div class="modal-head">
          <h2 id="pv-title">Review submission</h2>
          <p>Check the reading before confirming.</p>
        </div>

        <div id="pv-meta" class="pv-meta"></div>
        <div id="pv-body" class="modal-body"></div>
        <div id="pv-error" class="modal-error" role="alert"></div>

        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" id="preview-back">Go back and edit</button>
          <button type="button" class="btn btn-primary" id="preview-confirm">Confirm and submit</button>
        </div>
      </div>
    </div>

    <nav id="bottom-nav" aria-label="Quick navigation">
      <a class="bn-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
        <span class="ic">⌂</span>Overview
      </a>
      <a class="bn-item {{ request()->routeIs('crushing.*') ? 'active' : '' }}" href="{{ route('crushing.index') }}">
        <span class="ic">✎</span>Enter
      </a>
      @if($authUser->isAdmin())
        <a class="bn-item {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">
          <span class="ic">✓</span>Review
        </a>
      @endif
      <button type="button" class="bn-item" id="mobile-more"><span class="ic">☷</span>Sections</button>
    </nav>

    <div id="toast" role="status" aria-live="polite"></div>

    <script>
      window.PLANT = {
        dayStart: @json(\App\Support\ShiftHelper::dayStart()),
        dayEnd: @json(\App\Support\ShiftHelper::dayEnd())
      };
    </script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
  </body>
</html>
