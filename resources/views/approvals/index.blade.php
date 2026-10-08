@extends('layouts.app')

@section('title', 'Approvals')
@section('page', 'approvals')

@section('content')
  <div class="page-head">
    <div>
      <h1 class="page-title">Approvals</h1>
      <p class="page-sub">Approve, reject or edit submitted Crushing records. Pending is a normal state, not an error.</p>
    </div>
  </div>

  <div class="subnav" id="admin-tabs">
    <button type="button" class="active" data-admin="review">Review</button>
    <button type="button" data-admin="lists">Dropdown lists</button>
  </div>

  {{-- ============ REVIEW ============ --}}
  <div id="admin-review">
    <div class="info-note">
      A shift report that is still <b>Open</b> is a shift in progress. Approve and Reject are offered once it is Closed.
    </div>

    <form method="GET" action="{{ route('approvals.index') }}" class="filter-bar" id="filter-form">
      <select name="type" aria-label="Report">
        <option value="all" @selected($type === 'all')>All reports</option>
        <option value="shift" @selected($type === 'shift')>Shift report</option>
        <option value="moisture" @selected($type === 'moisture')>Moisture sample</option>
      </select>
      <select name="status" aria-label="Status">
        <option value="all" @selected($status === 'all')>All statuses</option>
        @foreach(['Pending', 'Approved', 'Rejected'] as $s)
          <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
        @endforeach
      </select>
      <input type="date" name="date" value="{{ $dateFilter }}" aria-label="Reading date" />
      @if($type !== 'all' || $status !== 'all' || $dateFilter !== '')
        <a href="{{ route('approvals.index') }}" class="btn btn-ghost btn-sm">Clear filters</a>
      @endif
    </form>

    <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date / time</th>
                        <th>Section</th>
                        <th>Report</th>
                        <th>Operational data</th>
                        <th>Entered by</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="admin-rows" data-base="{{ url('approvals') }}">
                    @forelse($rows as $row)
                        @php
                            $m = $row['m'];
                        @endphp

                        <tr>
                            {{-- DATE / TIME --}}
                            <td>
                                {{ $m->reading_date->format('d M Y') }}
                                <br>
                                <span class="micro">{{ $m->time_short }}</span>
                            </td>

                            {{-- SECTION --}}
                            <td>
                                Crushing
                            </td>

                            {{-- REPORT TYPE --}}
                            <td>
                                <strong>
                                    {{ $row['kind'] === 'shift'
                                        ? 'Shift Report'
                                        : 'Moisture Sample' }}
                                </strong>

                                @if($row['kind'] === 'shift')
                                    <br>
                                    <span class="micro">
                                        {{ $m->shift }} shift
                                    </span>
                                @endif

                                @if($m->review_note)
                                    <br>
                                    <span class="micro">
                                        Reason: {{ $m->review_note }}
                                    </span>
                                @endif

                                @if($m->last_edited_at)
                                    <br>
                                    <span class="micro">
                                        Edited {{ $m->last_edited_at->format('d M Y H:i') }}
                                    </span>
                                @endif
                            </td>

                            {{-- OPERATIONAL DATA --}}
                            <td class="operations-cell">

                                @if($row['kind'] === 'moisture')

                                    <div class="operation-primary">
                                        {{ number_format($m->moisture_pct, 2) }}%
                                        <span class="micro">moisture</span>
                                    </div>

                                    <div class="micro">
                                        {{ $m->stream_name }}
                                    </div>

                                @elseif($row['kind'] === 'shift')

                                    @if($m->report_stage === 'Open')

                                        <div class="operation-primary">
                                            Shift in progress
                                        </div>

                                        <div class="micro">
                                            Ore: {{ $m->ore_type }}
                                        </div>

                                        @if($m->crushing_start_time)
                                            <div class="micro">
                                                Started {{ $m->crushing_start_time }}
                                            </div>
                                        @endif

                                    @else

                                        <div class="operation-primary">
                                            {{ $m->trucks_crushed ?? '—' }}
                                            <span class="micro">trucks crushed</span>
                                        </div>

                                        <div class="operation-details">
                                            <span>
                                                Run:
                                                <b>
                                                    {{ $m->running_hours !== null
                                                        ? number_format($m->running_hours, 2) . 'h'
                                                        : '—' }}
                                                </b>
                                            </span>

                                            <span>
                                                Down:
                                                <b>
                                                    {{ $m->downtime_hours !== null
                                                        ? number_format($m->downtime_hours, 2) . 'h'
                                                        : '—' }}
                                                </b>
                                            </span>
                                        </div>

                                        @if($m->moistureSample)
                                            <div class="micro">
                                                Moisture:
                                                <b>
                                                    {{ number_format($m->moistureSample->moisture_pct, 2) }}%
                                                </b>
                                            </div>
                                        @endif

                                    @endif

                                @endif

                            </td>

                            {{-- ENTERED BY --}}
                            <td>
                                {{ $m->enteredBy?->name ?? '—' }}

                                <br>

                                <span class="micro">
                                    {{ $row['team'] }}
                                </span>
                            </td>

                            {{-- STATUS --}}
                            <td>
                                <span class="status-chip {{ strtolower($m->status) }}">
                                    {{ $m->status }}
                                </span>

                                @if($row['kind'] === 'shift')
                                    <br>
                                    <span class="status-chip stage {{ strtolower($m->report_stage) }}">
                                        {{ $m->report_stage }}
                                    </span>
                                @endif
                            </td>

                            {{-- ACTIONS --}}
                            <td>
                                <span class="row-actions">

                                    @if($m->status === 'Pending' && $row['canReview'])

                                        <button
                                            type="button"
                                            class="icon-btn approve"
                                            data-action="approve"
                                            data-kind="{{ $row['kind'] }}"
                                            data-id="{{ $row['id'] }}"
                                        >
                                            Approve
                                        </button>

                                        <button
                                            type="button"
                                            class="icon-btn reject"
                                            data-action="reject"
                                            data-kind="{{ $row['kind'] }}"
                                            data-id="{{ $row['id'] }}"
                                        >
                                            Reject
                                        </button>

                                    @elseif($m->status === 'Pending')

                                        <span class="micro">
                                            In progress
                                        </span>

                                    @endif

                                    <button
                                        type="button"
                                        class="icon-btn edit"
                                        data-action="edit"
                                        data-kind="{{ $row['kind'] }}"
                                        data-id="{{ $row['id'] }}"
                                        data-edit="{{ json_encode($m->editPayload()) }}"
                                    >
                                        Edit
                                    </button>

                                </span>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="empty-state">
                                No matching records.
                            </td>
                        </tr>

                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

  {{-- ============ DROPDOWN LISTS ============ --}}
  <div id="admin-lists" hidden>
    <div class="settings-grid">
      <div class="panel">
        <h3>Crushing ore types</h3>
        @foreach($oreTypes as $o)
          <div class="setting-row">
            <span>{{ $o->ore_type }}</span>
            <button type="button" data-master-toggle="ore-types" data-id="{{ $o->ore_type_id }}" aria-pressed="{{ $o->is_active ? 'true' : 'false' }}">{{ $o->is_active ? 'Active' : 'Inactive' }}</button>
          </div>
        @endforeach
        <form class="master-add" data-master-add="ore-types">
          <input type="text" name="name" maxlength="100" placeholder="Add an ore type" aria-label="New ore type" />
          <button type="submit" class="btn btn-ghost btn-sm">Add</button>
        </form>
      </div>

      <div class="panel">
        <h3>Sample streams</h3>
        @foreach($streams as $s)
          <div class="setting-row">
            <span>{{ $s->stream_name }}</span>
            <button type="button" data-master-toggle="streams" data-id="{{ $s->stream_id }}" aria-pressed="{{ $s->is_active ? 'true' : 'false' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</button>
          </div>
        @endforeach
        <form class="master-add" data-master-add="streams">
          <input type="text" name="name" maxlength="100" placeholder="Add a stream" aria-label="New stream" />
          <button type="submit" class="btn btn-ghost btn-sm">Add</button>
        </form>
      </div>
    </div>
    <p class="micro">Inactive names are no longer offered on the entry pages. Existing records keep their value.</p>
  </div>
@endsection

@push('scripts')
  <script>
    window.LISTS = {{ \Illuminate\Support\Js::from($lists) }};
  </script>
  <script src="{{ asset('js/approvals.js') }}"></script>
@endpush
