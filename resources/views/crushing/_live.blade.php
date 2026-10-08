@php
  $state   = $live['state'];
  $running = $state === 'running';
  $stopped = in_array($state, ['stopped', 'overdue'], true);
  $n       = $live['moistureCount'];
@endphp

<div id="live-banner"
     class="live-banner {{ $running ? 'crusher-running' : '' }} {{ $stopped ? 'crusher-stopped' : '' }}"
     data-server-now="{{ $live['serverNow'] }}"
     data-live-state="{{ $state }}"
     data-live-report="{{ $live['reportId'] }}"
     data-live-moisture="{{ $live['moistureCount'] }}"
     data-status-url="{{ route('crushing.status') }}">

  <div class="live-main">
    <div class="crusher-icon {{ $running ? 'is-running' : '' }} {{ $stopped ? 'is-closed' : '' }}">
      <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
        <path fill="currentColor" d="M19.14 12.94a7.49 7.49 0 0 0 .05-.94 7.49 7.49 0 0 0-.05-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.61-.22l-2.39.96a7.28 7.28 0 0 0-1.62-.94L14.4 2.77A.5.5 0 0 0 13.91 2h-3.82a.5.5 0 0 0-.49.41l-.36 2.55c-.57.24-1.11.55-1.62.94l-2.39-.96a.5.5 0 0 0-.61.22L2.7 8.48a.5.5 0 0 0 .12.64l2.03 1.58a7.49 7.49 0 0 0-.05.94c0 .32.02.63.05.94L2.82 14.16a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .61.22l2.39-.96c.5.39 1.05.7 1.62.94l.36 2.55a.5.5 0 0 0 .49.41h3.82a.5.5 0 0 0 .49-.41l.36-2.55c.57-.24 1.11-.55 1.62-.94l2.39.96a.5.5 0 0 0 .61-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/>
      </svg>
    </div>

    <div>
      <div class="sc-name">Crusher</div>
      <div class="crusher-state">
        <span class="state-dot {{ $running ? 'running' : ($stopped ? 'stopped' : 'idle') }}"></span>
        {{ $live['status'] }}
      </div>

      <div class="live-chips">
        @if($live['detail'])
          <span class="live-chip">{{ $live['detail'] }}</span>
        @endif

        @if($live['openedBy'])
          <span class="live-chip">Opened {{ $live['openedAt'] }} by {{ $live['openedBy'] }}</span>
        @endif

        @if($n !== null)
          @if($n > 0)
            <span class="live-chip ok">Moisture: {{ $n }} {{ $n === 1 ? 'sample' : 'samples' }} ready for end of shift</span>
          @else
            <span class="live-chip wait">Moisture: no sample captured yet</span>
          @endif
        @endif

        @if($state === 'stopped' && $live['last'])
          <span class="live-chip">Last shift · {{ $live['last']['when'] }} · {{ number_format($live['last']['trucks']) }} trucks</span>
        @endif

        @if($state === 'overdue')
          <span class="live-chip bad">End-of-shift entry not submitted</span>
        @endif
      </div>
    </div>
  </div>

  @if($live['since'])
    <div class="crusher-live">
      <span class="live-label">{{ $live['sinceLabel'] }}</span>
      <span class="live-timer" data-since="{{ $live['since'] }}">--:--:--</span>
    </div>
  @endif

  <div id="live-refresh" class="live-refresh" hidden>
    <span>The shift status changed. Reload to update the forms.</span>
    <button type="button" id="live-reload" class="btn btn-ghost btn-sm">Reload</button>
  </div>
</div>
