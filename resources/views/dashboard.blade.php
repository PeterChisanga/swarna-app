@extends('layouts.app')

@section('title', 'Plant Overview')
@section('page', 'dashboard')

@section('content')
  @php
    $state   = $crusher['state'];
    $running = $state === 'running';
    $stopped = in_array($state, ['stopped', 'overdue'], true);
    $last    = $crusher['last'];
  @endphp

  <div class="page-head">
    <div>
      <h1 class="page-title">Plant Overview</h1>
      <p class="page-sub">Live crusher status and the most recent production summary.</p>
    </div>

    <div class="head-actions">
      <span class="status-chip pending">Pending data included · {{ $pendingCount }} for review</span>
      @if(auth()->user()->isAdmin())
        <a href="{{ route('approvals.index') }}" class="btn btn-ghost btn-sm">Review entries →</a>
      @endif
    </div>
  </div>

  <div class="section-grid">
    <a href="{{ $crusher['url'] }}"
       class="sec-card crusher-card {{ $running ? 'crusher-running' : '' }} {{ $stopped ? 'crusher-stopped' : '' }}"
       data-server-now="{{ $crusher['serverNow'] }}">

      <div class="crusher-card-top">
        <div class="crusher-title-wrap">
          <div class="crusher-icon {{ $running ? 'is-running' : '' }} {{ $stopped ? 'is-closed' : '' }}">
            <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
              <path fill="currentColor" d="M19.14 12.94a7.49 7.49 0 0 0 .05-.94 7.49 7.49 0 0 0-.05-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.61-.22l-2.39.96a7.28 7.28 0 0 0-1.62-.94L14.4 2.77A.5.5 0 0 0 13.91 2h-3.82a.5.5 0 0 0-.49.41l-.36 2.55c-.57.24-1.11.55-1.62.94l-2.39-.96a.5.5 0 0 0-.61.22L2.7 8.48a.5.5 0 0 0 .12.64l2.03 1.58a7.49 7.49 0 0 0-.05.94c0 .32.02.63.05.94L2.82 14.16a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .61.22l2.39-.96c.5.39 1.05.7 1.62.94l.36 2.55a.5.5 0 0 0 .49.41h3.82a.5.5 0 0 0 .49-.41l.36-2.55c.57-.24 1.11-.55 1.62-.94l2.39.96a.5.5 0 0 0 .61-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/>
            </svg>
          </div>

          <div>
            <div class="sc-name">Crusher</div>
            <div class="crusher-state">
              <span class="state-dot {{ $running ? 'running' : ($stopped ? 'stopped' : 'idle') }}"></span>
              {{ $crusher['status'] }}
            </div>
            @if($crusher['detail'])
              <div class="crusher-detail">{{ $crusher['detail'] }}</div>
            @endif
          </div>
        </div>

        @if($crusher['since'])
          <div class="crusher-live">
            <span class="live-label">{{ $crusher['sinceLabel'] }}</span>
            <span class="live-timer" data-since="{{ $crusher['since'] }}">--:--:--</span>
          </div>
        @endif
      </div>

      @if($state === 'overdue')
        <div class="crusher-note">
          This shift report has been open longer than expected. The end-of-shift entry may not have been submitted.
        </div>
      @endif

      <div class="crusher-metrics">
        <div class="crusher-metric">
          <span>Trucks · last shift</span>
          <strong>
            @if($last)
              <span data-countup="{{ $last['trucks'] }}">{{ number_format($last['trucks']) }}</span>
            @else
              —
            @endif
          </strong>
          @if($last)<small>{{ $last['when'] }}</small>@endif
        </div>

        <div class="crusher-metric">
          <span>Trucks · this month</span>
          <strong>
            @if($crusher['month'] !== null)
              <span data-countup="{{ $crusher['month'] }}">{{ number_format($crusher['month']) }}</span>
            @else
              —
            @endif
          </strong>
          <small>{{ now()->format('F Y') }}</small>
        </div>

        <div class="crusher-metric">
          <span>Trucks · total recorded</span>
          <strong>
            @if($crusher['total'] !== null)
              <span data-countup="{{ $crusher['total'] }}">{{ number_format($crusher['total']) }}</span>
            @else
              —
            @endif
          </strong>
          @if($crusher['firstDate'])<small>since {{ $crusher['firstDate'] }}</small>@endif
        </div>
      </div>

      <div class="sc-foot">
        <span class="micro">Closed shifts only · rejected shifts excluded · from shift reports, not a totalizer reading</span>
        <span class="go">View crushing →</span>
      </div>
    </a>
  </div>

  <div class="panel overview-readings">
    <div class="panel-heading-row">
        <div>
        <h3>Recent shifts</h3>
        <p class="panel-subtitle">Last 5 closed shift reports</p>
        </div>
        <a href="{{ $crusher['url'] }}" class="btn btn-ghost btn-sm">
        Open Crushing →
        </a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
        <thead>
            <tr>
            <th>Date / time</th>
            <th>Section</th>
            <th>Report</th>
            <th>Operational data</th>
            <th>Actions</th>
            </tr>
        </thead>

        <tbody id="overview-shift-rows">
            @forelse($recent as $r)
            @php
                $sample = $r->moistureSample;

                $moisture = $sample
                    && $sample->status !== 'Rejected'
                    && $sample->moisture_pct !== null
                        ? number_format($sample->moisture_pct, 2) . '%'
                        : '—';

                $trucks = $r->trucks_crushed !== null
                    ? number_format($r->trucks_crushed)
                    : '—';

                $running = $r->running_hours !== null
                    ? number_format($r->running_hours, 2) . ' h'
                    : '—';

                $downtime = $r->downtime_hours !== null
                    ? number_format($r->downtime_hours, 2) . ' h'
                    : '—';

                $details = [
                    'title' => 'Crushing shift report #' . $r->shift_report_id,
                    'rows' => [
                        'Operating date' => \Carbon\Carbon::parse($r->operating_date)->format('d M Y'),
                        'Reading date / time' => $r->reading_date->format('d M Y') . ' · ' . $r->time_short,
                        'Shift' => $r->shift,
                        'Ore type' => $r->ore_type,
                        'Ore name / ID' => $r->ore_name_id,
                        'Crushing start time' => $r->crushing_start_time
                            ? substr((string) $r->crushing_start_time, 0, 5) : '—',
                        'Crushing stop time' => $r->crushing_stop_time
                            ? substr((string) $r->crushing_stop_time, 0, 5) : '—',
                        'Trucks crushed' => $trucks,
                        'Crushed ore moisture' => $moisture,
                        'Running hours' => $running,
                        'Downtime hours' => $downtime,
                        'Downtime reason' => $r->downtime_reason ?: '—',
                        'Stockpile' => $r->stockpile_pct !== null
                            ? number_format($r->stockpile_pct, 2) . '%' : '—',
                    ],
                    'notes' => $r->notes,
                ];
            @endphp

            <tr>
                <td>
                {{ $r->reading_date->format('d M Y') }}
                <br>
                <span class="micro">{{ $r->time_short }}</span>
                </td>

                <td>Crushing</td>

                <td>
                <strong>Shift Report #{{ $r->shift_report_id }}</strong>
                <br>
                <span class="micro">{{ $r->shift }} shift</span>
                <br>
                <span class="micro">{{ $r->ore_name_id }}</span>
                </td>

                <td class="operations-cell">
                <div class="operation-primary">
                    {{ $trucks }}
                    <span class="micro">trucks crushed</span>
                </div>

                <div class="operation-details">
                    <span>Run: <b>{{ $running }}</b></span>
                    <span>Down: <b>{{ $downtime }}</b></span>
                </div>

                <div class="micro">
                    Moisture: <b>{{ $moisture }}</b>
                </div>
                </td>

                <td>
                <span class="row-actions">
                    <button
                    type="button"
                    class="icon-btn"
                    data-overview-detail="{{ json_encode($details) }}"
                    >
                    View
                    </button>
                </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="empty-state">
                No closed shifts yet.
                </td>
            </tr>
            @endforelse
        </tbody>
        </table>
    </div>
</div>

{{-- Read-only shift details popup --}}
<dialog id="overview-shift-dialog" aria-labelledby="overview-shift-title">
  <div class="modal-head">
    <h2 id="overview-shift-title">Crushing shift details</h2>
    <p>Recorded production and moisture readings.</p>
  </div>

  <div class="modal-body">
    <dl id="overview-shift-details"></dl>

    <div id="overview-shift-notes-wrap" hidden>
      <h3>Crushing report note</h3>
      <p id="overview-shift-notes"></p>
    </div>
  </div>

  <form method="dialog" class="modal-foot">
    <button type="submit" class="btn btn-ghost">Close</button>
  </form>
</dialog>
@endsection

@push('scripts')
  <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
