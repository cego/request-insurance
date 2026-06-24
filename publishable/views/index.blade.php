<?php use Cego\RequestInsurance\Enums\State; ?>
@extends('request-insurance::layouts.master')

@section('content')
    @php
        // Lifecycle stages shown in the header strip. COMPLETED is intentionally absent:
        // it is the bulk of the partitioned table and is never counted.
        $stages = [
            ['state' => State::WAITING,    'branch' => false],
            ['state' => State::READY,      'branch' => false],
            ['state' => State::PENDING,    'branch' => false],
            ['state' => State::PROCESSING, 'branch' => false],
            ['state' => State::FAILED,     'branch' => true],
            ['state' => State::ABANDONED,  'branch' => true],
        ];
    @endphp

    <h1 class="mb-4 text-[26px] font-semibold tracking-tight">Request pipeline</h1>

    {{-- Lifecycle flow strip: success path, then a divergent exceptions branch. Counts filled by monitor_segmented. --}}
    <section class="mb-8 rounded-2xl border border-line bg-surface p-1">
        <div class="flex items-stretch overflow-x-auto">
            <div class="flex items-stretch flex-1 min-w-0">
                @foreach($stages as $i => $stage)
                    @if($stage['branch'] && ($stages[$i - 1]['branch'] ?? false) === false)
                        </div><div class="flex items-stretch border-l border-dashed border-line ml-2 pl-2">
                    @endif
                    @php $color = 'var(--c-' . State::getBootstrapColor($stage['state']) . ')'; @endphp
                    <div class="flex-1 px-5 py-4 min-w-[120px]">
                        <div class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wider text-ink-soft">
                            <span class="size-1.5 rounded-full" style="background:{{ $color }}"></span>{{ ucfirst(strtolower($stage['state'])) }}
                        </div>
                        <div id="count-{{ $stage['state'] }}" class="mt-1 font-mono text-[28px] leading-none tabular-nums" style="color:{{ $color }}">·</div>
                    </div>
                    @if( ! $stage['branch'] && ! ($stages[$i + 1]['branch'] ?? true))
                        <div class="self-center text-line select-none">→</div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- Filters --}}
    <form method="get" class="mb-4 flex flex-wrap items-center gap-2">
        <input name="trace_id" value="{{ old('trace_id') }}" placeholder="trace id" class="h-9 w-44 rounded-lg border border-line bg-surface px-3 font-mono text-[13px] placeholder:text-ink-soft/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/25">
        <input name="url" value="{{ old('url') }}" placeholder="url  %like%" class="h-9 w-56 rounded-lg border border-line bg-surface px-3 font-mono text-[13px] placeholder:text-ink-soft/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/25">
        <input type="datetime-local" name="from" value="{{ old('from') }}" title="From" class="h-9 rounded-lg border border-line bg-surface px-3 font-mono text-[13px] text-ink-soft focus:border-accent focus:outline-none">
        <input type="datetime-local" name="to" value="{{ old('to') }}" title="To" class="h-9 rounded-lg border border-line bg-surface px-3 font-mono text-[13px] text-ink-soft focus:border-accent focus:outline-none">
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach(State::getAll() as $state)
                <label class="cursor-pointer select-none">
                    <input type="checkbox" name="{{ $state }}" @checked(old($state) == 'on') class="peer sr-only">
                    <span class="chip inline-block rounded-full px-2.5 py-1 text-[12px] font-mono opacity-45 transition peer-checked:opacity-100 peer-checked:ring-1 peer-checked:ring-current peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-accent" style="--chip:var(--c-{{ State::getBootstrapColor($state) }})">{{ ucfirst(strtolower($state)) }}</span>
                </label>
            @endforeach
        </div>
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <div class="ml-auto flex gap-2">
            <button type="submit" class="h-9 rounded-lg bg-accent px-4 text-[13px] font-medium text-white hover:opacity-90">Filter</button>
            <a href="{{ url()->current() }}" class="grid h-9 place-items-center rounded-lg border border-line bg-surface px-4 text-[13px] text-ink-soft no-underline hover:text-ink">Clear</a>
        </div>
    </form>

    {{-- Holds the CSRF token + method for the bulk actions; row checkboxes and the bulk
         buttons associate with it via the form="ri-bulk" attribute (no nested forms). --}}
    <form id="ri-bulk" method="POST">@csrf</form>

    {{-- Bulk action bar — revealed by JS once at least one row is selected. --}}
    <div id="ri-bulkbar" class="mb-3 hidden items-center gap-3 rounded-xl border border-accent/30 bg-accent/5 px-4 py-2.5">
        <span class="font-mono text-[13px] text-ink-soft"><strong id="ri-selcount" class="text-ink">0</strong> selected</span>
        <div class="ml-auto flex items-center gap-2">
            <button type="submit" form="ri-bulk" formaction="{{ route('request-insurances.retry-selected') }}"
                    onclick="return confirm('Retry the selected request insurances?')"
                    class="h-8 rounded-lg bg-accent px-3.5 text-[12px] font-medium text-white hover:opacity-90">Retry selected</button>
            <button type="submit" form="ri-bulk" formaction="{{ route('request-insurances.abandon-selected') }}"
                    onclick="return confirm('Abandon the selected request insurances? Active requests will stop being processed.')"
                    class="h-8 rounded-lg border border-st-danger/45 px-3.5 text-[12px] font-medium text-st-danger hover:bg-st-danger/10">Abandon selected</button>
            <button type="button" id="ri-clearsel" class="text-[12px] text-ink-soft hover:text-ink">Clear</button>
        </div>
    </div>

    {{-- Listing --}}
    <div class="overflow-x-auto rounded-2xl border border-line bg-surface">
        <table class="w-full text-[13px]">
            <thead>
                <tr class="text-left font-mono text-[11px] uppercase tracking-wider text-ink-soft [&>th]:px-4 [&>th]:py-2.5 [&>th]:font-medium border-b border-line bg-surface-2">
                    <th class="w-9"><input type="checkbox" id="ri-selectall" class="accent-accent align-middle" title="Select all on this page"></th>
                    <th>id</th><th>pri</th><th>method</th><th>code</th><th>url</th><th>state</th><th>attempts</th><th>next try</th><th>created</th><th class="text-right">ms</th><th class="text-right pr-4">actions</th>
                </tr>
            </thead>
            <tbody class="font-mono [&>tr]:border-b [&>tr]:border-line/70 [&>tr:last-child]:border-0 [&>tr>td]:px-4 [&>tr>td]:py-2">
                @foreach($requestInsurances as $requestInsurance)
                    <tr class="hover:bg-surface-2/60">
                        <td>
                            @if($requestInsurance->doesNotHaveState(State::COMPLETED))
                                <input type="checkbox" name="ids[]" value="{{ $requestInsurance->id }}" form="ri-bulk" class="ri-select accent-accent align-middle">
                            @endif
                        </td>
                        <td class="text-ink-soft">{{ $requestInsurance->id }}</td>
                        <td class="tabular-nums">{{ $requestInsurance->priority }}</td>
                        <td>{{ mb_strtoupper($requestInsurance->method) }}</td>
                        <td><x-request-insurance-http-code httpCode="{{ $requestInsurance->response_code }}" /></td>
                        <td class="max-w-[240px] truncate text-ink-soft" title="{{ urldecode($requestInsurance->url) }}">{{ urldecode($requestInsurance->url) }}</td>
                        <td><x-request-insurance-status :requestInsurance="$requestInsurance" /></td>
                        <td class="tabular-nums">{{ $requestInsurance->retry_count }}</td>
                        <td class="text-[12px]"><x-request-insurance-timestamp :value="$requestInsurance->retry_at" /></td>
                        <td class="text-[12px] text-ink-soft"><x-request-insurance-timestamp :value="$requestInsurance->created_at" /></td>
                        <td class="text-right tabular-nums text-ink-soft">{{ $requestInsurance->getTotalTime() < 0 ? '·' : number_format($requestInsurance->getTotalTime()) }}</td>
                        <td>
                            <div class="grid grid-cols-[4.3rem_5rem_4.7rem] items-center gap-1.5">
                                @if($requestInsurance->isRetryable())
                                    <form method="POST" action="{{ route('request-insurances.retry', $requestInsurance) }}" class="w-full">@csrf
                                        <button type="submit" class="act border-st-warning/45 text-st-warning hover:bg-st-warning/10">Retry</button>
                                    </form>
                                @else <span></span> @endif

                                @if($requestInsurance->doesNotHaveState(State::COMPLETED) && $requestInsurance->doesNotHaveState(State::ABANDONED))
                                    <form method="POST" action="{{ route('request-insurances.destroy', $requestInsurance) }}" class="w-full">@csrf
                                        <input type="hidden" name="_method" value="delete">
                                        <button type="submit" class="act border-st-danger/40 text-st-danger hover:bg-st-danger/10"
                                                onclick="return confirm('Abandon request insurance #{{ $requestInsurance->id }}?')">Abandon</button>
                                    </form>
                                @else <span></span> @endif

                                <a href="{{ route('request-insurances.show', $requestInsurance) }}" class="act border-accent/40 text-accent no-underline hover:bg-accent/10">Inspect</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12px] text-ink-soft font-mono">
        <span>priority is zero-based · 0 = highest</span>
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-2">rows
                <select onchange="const u=new URL(location); u.searchParams.set('per_page', this.value); u.searchParams.delete('cursor'); location = u;"
                        class="h-8 rounded-md border border-line bg-surface px-2 text-ink focus:border-accent focus:outline-none">
                    @foreach([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
            <span class="flex items-center gap-1">
                @if($requestInsurances->previousPageUrl())
                    <a href="{{ $requestInsurances->previousPageUrl() }}" class="grid h-8 place-items-center rounded-md border border-line px-3 no-underline text-ink hover:bg-surface-2">‹ Newer</a>
                @else
                    <span class="grid h-8 place-items-center rounded-md border border-line px-3 text-ink-soft/40">‹ Newer</span>
                @endif
                @if($requestInsurances->nextPageUrl())
                    <a href="{{ $requestInsurances->nextPageUrl() }}" class="grid h-8 place-items-center rounded-md border border-line px-3 no-underline text-ink hover:bg-surface-2">Older ›</a>
                @else
                    <span class="grid h-8 place-items-center rounded-md border border-line px-3 text-ink-soft/40">Older ›</span>
                @endif
            </span>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Fill the lifecycle strip counts (COMPLETED is never requested/shown).
    fetch('{{ route('request-insurances.monitor_segmented') }}')
        .then(r => r.json())
        .then(counts => Object.entries(counts).forEach(([state, n]) => {
            const el = document.getElementById('count-' + state);
            if (el) el.textContent = n;
        }))
        .catch(() => document.querySelectorAll('[id^="count-"]').forEach(el => el.textContent = '–'));

    // Row selection + bulk action bar.
    (function () {
        const bar = document.getElementById('ri-bulkbar');
        const countEl = document.getElementById('ri-selcount');
        const selectAll = document.getElementById('ri-selectall');
        const boxes = () => Array.from(document.querySelectorAll('.ri-select'));

        function sync() {
            const checked = boxes().filter(b => b.checked).length;
            countEl.textContent = checked;
            bar.classList.toggle('hidden', checked === 0);
            bar.classList.toggle('flex', checked !== 0);
            const all = boxes();
            selectAll.checked = all.length > 0 && checked === all.length;
            selectAll.indeterminate = checked > 0 && checked < all.length;
        }
        selectAll.addEventListener('change', () => { boxes().forEach(b => b.checked = selectAll.checked); sync(); });
        boxes().forEach(b => b.addEventListener('change', sync));
        document.getElementById('ri-clearsel').addEventListener('click', () => { boxes().forEach(b => b.checked = false); sync(); });
        sync();
    })();
</script>
@endsection
