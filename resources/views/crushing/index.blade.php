@extends('layouts.app')

@section('title', 'Crushing')
@section('page', 'crushing')

@section('content')
  @php $firstTab = $canShift ? 'shift' : 'moisture'; @endphp

  <div class="page-head">
    <div>
      <h1 class="page-title">Crushing</h1>
      <p class="page-sub">Shift operations, Crushed Ore moisture and downtime.</p>
    </div>

    <div class="head-actions">
      <span class="status-chip approved">Section view</span>
      <a href="{{ route('dashboard', ['date' => $date]) }}" class="btn btn-ghost btn-sm">← Plant overview</a>
    </div>
  </div>

  @include('crushing._live', ['live' => $live])

  <div class="section-kpis">
    <div class="kpi">
      {{-- <div class="label">Shift status</div> --}}
      <div class="label">Shift in view</div>
      <div class="value">{{ $kpis['status']['value'] }}</div>
      <div class="sub">{{ $kpis['status']['sub'] }}</div>
    </div>
    <div class="kpi">
      <div class="label">Trucks this shift</div>
      <div class="value">{{ $kpis['trucks']['value'] }}</div>
      <div class="sub">{{ $kpis['trucks']['sub'] }}</div>
    </div>
    <div class="kpi">
      <div class="label">Downtime</div>
      <div class="value">{{ $kpis['downtime']['value'] }}</div>
      <div class="sub">{{ $kpis['downtime']['sub'] }}</div>
    </div>
    <div class="kpi">
      <div class="label">Moisture</div>
      <div class="value">{{ $kpis['moisture']['value'] }}</div>
      <div class="sub">{{ $kpis['moisture']['sub'] }}</div>
    </div>
  </div>

  <div class="section-layout">
    {{-- ============ ENTRY PANEL ============ --}}
    <div class="panel">
      <h3>Enter a reading</h3>
      <p class="panel-subtitle">
        @if($canShift && $canMoisture)
          Choose a report. Production and Lab submit independently.
        @elseif($canShift)
          Open the shift report at the start of the shift and close it at the end.
        @elseif($canMoisture)
          Capture the sample as taken. Production selects it at the end of the shift.
        @endif
      </p>

      @if(!$canShift && !$canMoisture)
        <div class="empty-state">Your role cannot enter Crushing readings. You can still view the records on this page.</div>
      @elseif($canShift && $canMoisture)
        <div id="report-tabs" class="tab-strip" role="tablist">
          <button type="button" role="tab" class="report-tab active" data-tab="shift" aria-selected="true">Shift report</button>
          <button type="button" role="tab" class="report-tab" data-tab="moisture" aria-selected="false">Moisture sample</button>
        </div>
      @endif

      {{-- ---------- Shift report (Production) ---------- --}}
      @if($canShift)
        <div id="panel-shift" class="form-wrap" @if($firstTab !== 'shift') hidden @endif>
          <div class="report-meta">
            <span class="team-badge production">● Production entry</span>
            Once per shift · <span class="mono">crushing_shift_reports</span>
          </div>

          <div class="subnav" id="stage-nav" role="tablist" aria-label="Shift report stage">
            <button type="button" class="active" data-stage="start">Start of shift</button>
            <button type="button" data-stage="end">
              End of shift
              @if($openReports->count())<span class="stage-count">{{ $openReports->count() }} open</span>@endif
            </button>
          </div>

          {{-- Start of shift --}}
          <div id="stage-start">
            <form id="form-start" data-form="start" data-url="{{ route('crushing.shift.open') }}" novalidate>
              <div class="form-card">
                <div class="form-grid">
                   @php $locked = ! $canBackdate; @endphp

                    @include('crushing._field', [
                    'form' => 'start', 'name' => 'crushing_reading_date', 'label' => 'Crushing reading date',
                    'kind' => 'date', 'required' => true, 'value' => $defaultDate,
                    'htmlMax' => $defaultDate, 'readonly' => $locked,
                    'hint' => $locked
                        ? 'Locked to today. Only an Admin can choose an earlier date.'
                        : 'Defaults to today. Earlier dates are allowed, future dates are not.',
                    ])
                    @include('crushing._field', [
                    'form' => 'start', 'name' => 'crushing_reading_time', 'label' => 'Crushing reading time',
                    'kind' => 'time', 'required' => true, 'value' => $defaultTime,
                    'readonly' => $locked,
                    'hint' => $locked
                        ? 'Locked to the current time. Only an Admin can change it.'
                        : 'Defaults to now. It cannot be later than the current time.',
                    ])
                    <div class="divider-label">Start of shift</div>

                    @include('crushing._field', ['form' => 'start', 'name' => 'crushing_ore_type', 'label' => 'Crushing ore type', 'kind' => 'select', 'required' => true, 'options' => $oreTypes])
                    @include('crushing._field', ['form' => 'start', 'name' => 'crushing_ore_name_id', 'label' => 'Crushing ore name / ID', 'kind' => 'text', 'required' => true, 'maxlength' => 100, 'placeholder' => 'e.g. Kipushi mixed with Matrix material'])
                    @include('crushing._field', ['form' => 'start', 'name' => 'crushing_start_time', 'label' => 'Crushing start time', 'kind' => 'time', 'required' => true, 'value' => $defaultTime])
                </div>

                <p id="derived-start" class="micro"></p>
                <p class="micro">
                  Shift is derived automatically from the reading time and cannot be chosen. Day and Night use
                  the boundaries {{ \App\Support\ShiftHelper::dayStart() }}–{{ \App\Support\ShiftHelper::dayEnd() }} (set in config/plant.php).
                </p>
                <p class="micro">
                  On submit the report is saved with stage <b>Open</b>. The moisture sample, trucks, stop time, hours,
                  stockpile and notes are entered at the end of the shift.
                </p>

                <div class="form-actions">
                  <button type="reset" class="btn btn-ghost">Clear</button>
                  <button type="submit" class="btn btn-primary">Review &amp; submit</button>
                </div>
              </div>
            </form>
          </div>

          {{-- End of shift --}}
          <div id="stage-end" hidden>
            @if($openReports->isEmpty())
              <div class="empty-state">There is no open shift report. Open one under “Start of shift” first.</div>
            @else
              <form id="form-end" data-form="end" data-url-template="{{ route('crushing.shift.close', '__ID__') }}" novalidate>
                <div class="form-card">
                  <div class="form-grid">
                    <div class="field fg-full">
                      <label for="end-report">Open shift report <span class="field-req">*</span></label>
                      <select id="end-report" name="shift_report_id">
                        @foreach($openReports as $r)
                          <option value="{{ $r['id'] }}">{{ $r['label'] }}</option>
                        @endforeach
                      </select>
                      <div class="field-hint">Reports still in stage Open. The start-of-shift fields below are read-only.</div>
                    </div>

                    <div class="readonly-box">
                      <div class="rb-title">Opened at start of shift · read-only</div>
                      <div id="end-summary" class="rb-grid"></div>
                    </div>

                    <div class="divider-label">End of shift</div>

                    @include('crushing._field', ['form' => 'end', 'name' => 'crushing_moisture_sample_id', 'label' => 'Crushing moisture sample ID', 'kind' => 'select', 'required' => true, 'options' => [], 'full' => true, 'hint' => "Moisture samples Lab captured for this shift; rejected samples are not listed"])
                    <div id="end-moisture-empty" class="fg-full field-warn" hidden>
                      No moisture sample has been captured for this shift yet. Ask Lab to capture one, then reload this page.
                    </div>

                    @include('crushing._field', ['form' => 'end', 'name' => 'crusher_trucks_crushed', 'label' => 'Crusher trucks crushed', 'kind' => 'integer', 'required' => true, 'unit' => 'trucks', 'min' => 0])
                    @include('crushing._field', ['form' => 'end', 'name' => 'crushing_stop_time', 'label' => 'Crushing stop time', 'kind' => 'time', 'required' => true, 'value' => $defaultTime])
                    @include('crushing._field', ['form' => 'end', 'name' => 'crusher_running_hours', 'label' => 'Crusher running hours', 'kind' => 'number', 'unit' => 'h', 'min' => 0, 'max' => 24])
                    @include('crushing._field', ['form' => 'end', 'name' => 'crusher_downtime_hours', 'label' => 'Crusher downtime', 'kind' => 'number', 'unit' => 'h', 'min' => 0, 'max' => 24])
                    @include('crushing._field', ['form' => 'end', 'name' => 'crusher_downtime_reason', 'label' => 'Crusher downtime reason', 'kind' => 'text', 'maxlength' => 255, 'placeholder' => 'Short reason only'])
                    @include('crushing._field', ['form' => 'end', 'name' => 'ore_stockpile_pct', 'label' => 'Ore stockpile %', 'kind' => 'number', 'unit' => '%', 'min' => 0, 'max' => 100])
                    @include('crushing._field', ['form' => 'end', 'name' => 'crushing_shift_notes', 'label' => 'Crushing report note', 'kind' => 'textarea', 'full' => true, 'placeholder' => 'Any unusual happening during the shift, e.g. breakdown, rainfall, accident'])
                  </div>

                  <p class="micro">
                    On submit the report is updated (not duplicated) and its stage changes to <b>Closed</b>.
                    The closing user and time are recorded.
                  </p>

                  <div class="form-actions">
                    <button type="reset" class="btn btn-ghost">Clear</button>
                    <button type="submit" class="btn btn-primary">Review &amp; submit</button>
                  </div>
                </div>
              </form>
            @endif
          </div>
        </div>
      @endif

        {{-- ---------- Moisture sample (Lab) ---------- --}}
        @if($canMoisture)
            <div id="panel-moisture" class="form-wrap" @if($firstTab !== 'moisture') hidden @endif>
            <div class="report-meta">
                <span class="team-badge lab">● Lab entry</span>
                As sampled · <span class="mono">crushing_moisture_samples</span>
            </div>

            <form id="form-moisture" data-form="moisture" data-url="{{ route('crushing.moisture.store') }}" novalidate>
                <div class="form-card">
                @if($moistureShifts->isEmpty())
                    <div class="field-warn" style="margin-bottom:14px">
                    No shift is open and no shift is waiting for a moisture sample. Production must open a shift report first.
                    </div>
                @endif

                <div class="form-grid">
                    @include('crushing._field', [
                    'form' => 'moisture', 'name' => 'crushing_shift_report_id', 'label' => 'Shift report',
                    'kind' => 'select', 'required' => true, 'full' => true,
                    'options' => $moistureShifts->pluck('label', 'id')->all(),
                    'placeholder' => 'Select the shift this sample belongs to...',
                    'hint' => 'Open shifts, or shifts without a moisture sample assigned',
                    ])

                    @include('crushing._field', [
                    'form' => 'moisture', 'name' => 'moisture_reading_time', 'label' => 'Moisture reading time',
                    'kind' => 'time', 'required' => true, 'value' => $defaultTime,
                    'hint' => 'Time the sample was taken, not the time it is typed in. It cannot be in the future.',
                    ])

                    <div class="field capture-field">
                    <label for="f-moisture-ore">Sample stream (ore being crushed)</label>
                    <div class="capture-control">
                        <input id="f-moisture-ore" type="text" value="" readonly aria-readonly="true" placeholder="Select a shift first">
                    </div>
                    <div class="capture-help"><div class="field-hint">Taken from the shift report</div></div>
                    </div>

                    <div class="divider-label">Moisture sample</div>

                    @include('crushing._field', ['form' => 'moisture', 'name' => 'crushed_ore_moisture_pct', 'label' => 'Crushed ore moisture', 'kind' => 'number', 'required' => true, 'unit' => '%', 'min' => 0, 'max' => 60])
                    @include('crushing._field', ['form' => 'moisture', 'name' => 'moisture_notes', 'label' => 'Moisture notes', 'kind' => 'textarea', 'full' => true, 'placeholder' => 'Context or remarks, separate from numeric values'])
                </div>

                <p id="derived-moisture" class="micro"></p>
                <p class="micro">
                    The sample is saved against the shift you choose. Production then selects it in the
                    Moisture sample list when that shift report is closed.
                </p>

                <div class="form-actions">
                    <button type="reset" class="btn btn-ghost">Clear</button>
                    <button type="submit" class="btn btn-primary" @disabled($moistureShifts->isEmpty())>Review &amp; submit</button>
                </div>
                </div>
            </form>
            </div>
        @endif
    </div>

    {{-- ============ RECENT + TREND ============ --}}
    <div class="panel-stack">
      <div class="panel">
        <h3>Recent readings</h3>
        <p class="panel-subtitle">Operating date and shift filters apply. Pending entries appear normally.</p>

        <div class="reading-table-wrap">
          <table class="reading-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Report</th>
                <th>Reading</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="recent-rows">
              @forelse($rows as $row)
                @php $m = $row['m']; @endphp
                <tr>
                  <td>{{ $m->time_short }}<br><span class="micro">{{ $m->reading_date->format('d M Y') }}</span></td>
                  <td>{{ $row['label'] }}<br><span class="micro">{{ $row['team'] }}</span></td>
                  <td>{{ $m->summaryText() }}</td>
                  <td>
                    <span class="status-chip {{ strtolower($m->status) }}">{{ $m->status }}</span>
                    @if($row['kind'] === 'shift')
                      <br><span class="status-chip stage {{ strtolower($m->report_stage) }}">{{ $m->report_stage }}</span>
                    @endif
                  </td>
                  <td>
                    <button type="button" class="btn btn-ghost btn-sm" data-detail="{{ json_encode($m->detailPayload()) }}"
                            aria-label="View full reading details">View</button>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="empty-state">No readings for this operating date and shift.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <h3>Trend snapshot</h3>
        <p class="panel-subtitle">Trucks crushed · last {{ $trend->count() ?: 10 }} closed shifts</p>

        @if($trend->isEmpty())
          <div class="empty-state">No closed shifts yet.</div>
        @else
          <div class="trend-bars" aria-label="Trucks crushed per closed shift">
            @foreach($trend as $bar)
              <span style="height: {{ $bar['height'] }}%" title="{{ $bar['title'] }}"></span>
            @endforeach
          </div>
          <div class="trend-labels"><span>Earlier</span><span>Recent</span></div>
        @endif

        <p class="micro">Charts use timestamped approved and pending readings, with rejected records excluded.</p>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  {{-- Crushing script here  --}}
    <script>
        window.PLANT_NOW = {
        epoch: {{ now()->timestamp }},
        offset: {{ now()->utcOffset() }},
        loaded: Date.now()
        };
        window.CRUSHING = {
            openReports: {{ \Illuminate\Support\Js::from($openReports) }},
            moistureShifts: {{ \Illuminate\Support\Js::from($moistureShifts) }}
        };
    </script>
    <script src="{{ asset('js/live-status.js') }}"></script>
  <script src="{{ asset('js/crushing.js') }}"></script>
@endpush
