<?php use Cego\RequestInsurance\Enums\State; ?>
@extends('request-insurance::layouts.master')

@section('title', 'Pipeline · Request Insurance')

@section('content')
    @php
        // Lifecycle stages shown in the header strip: the success path, then the
        // divergent exceptions branch. Counts are filled by monitor_segmented.
        $stages = [
            ['state' => State::WAITING,    'branch' => false],
            ['state' => State::READY,      'branch' => false],
            ['state' => State::PENDING,    'branch' => false],
            ['state' => State::PROCESSING, 'branch' => false],
            ['state' => State::COMPLETED,  'branch' => false],
            ['state' => State::FAILED,     'branch' => true],
            ['state' => State::ABANDONED,  'branch' => true],
        ];
    @endphp

    <div class="space-y-8">
        {{-- On wide screens the lifecycle strip sits beside the hero instead of
             below it, so the listing starts a full section higher. --}}
        <section class="grid items-center gap-6 xl:grid-cols-[minmax(24rem,0.9fr)_minmax(0,2.1fr)]">
            <div class="max-w-3xl">
                <p class="mb-3 font-mono text-xs font-semibold uppercase tracking-[0.2em] text-insurance">Request pipeline</p>
                <h1 class="text-3xl font-black tracking-[-0.04em] text-slate-950 sm:text-4xl dark:text-white">Every request lands.<br><span class="text-slate-400 dark:text-slate-500">Or you learn why.</span></h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">Guaranteed asynchronous HTTP delivery — with retries, edits, approvals, and a full audit trail for every request.</p>
            </div>

            {{-- Lifecycle flow strip: success path, then a divergent exceptions branch. --}}
            <section aria-label="Requests per state" class="overflow-hidden rounded-2xl border bg-white p-1 shadow-sm shadow-slate-900/5 dark:bg-slate-900">
            <div class="flex items-stretch overflow-x-auto">
                <div class="flex flex-1 items-stretch">
                    @foreach($stages as $i => $stage)
                        @if($stage['branch'] && ($stages[$i - 1]['branch'] ?? false) === false)
                            </div><div class="ml-2 flex items-stretch border-l border-dashed pl-2">
                        @endif
                        @php $tone = 'tone-' . State::getBootstrapColor($stage['state']); @endphp
                        <div class="min-w-[7.5rem] flex-1 px-5 py-4">
                            <div class="flex items-center gap-1.5 font-mono text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                                <span class="size-1.5 rounded-full bg-current {{ $tone }}"></span>{{ ucfirst(strtolower($stage['state'])) }}
                            </div>
                            <div id="count-{{ $stage['state'] }}" class="mt-1 font-mono text-[28px] leading-none tabular-nums {{ $tone }}">·</div>
                        </div>
                        @if( ! $stage['branch'] && ! ($stages[$i + 1]['branch'] ?? true))
                            <div class="select-none self-center text-slate-300 dark:text-slate-700" aria-hidden="true">→</div>
                        @endif
                    @endforeach
                    </div>
                </div>
            </section>
        </section>

        <section aria-labelledby="requests-heading">
            <h2 id="requests-heading" class="sr-only">Requests</h2>

            {{-- Filters --}}
            <form method="get" class="mb-4 flex flex-wrap items-center gap-2">
                <input name="trace_id" value="{{ old('trace_id') }}" placeholder="trace id" class="h-10 w-44 rounded-xl border bg-white px-3.5 font-mono text-sm text-slate-950 shadow-xs placeholder:text-slate-400 dark:bg-slate-900 dark:text-white">
                <input name="url" value="{{ old('url') }}" placeholder="url  %like%" class="h-10 w-56 rounded-xl border bg-white px-3.5 font-mono text-sm text-slate-950 shadow-xs placeholder:text-slate-400 dark:bg-slate-900 dark:text-white">
                <input type="datetime-local" name="from" value="{{ old('from') }}" title="From" class="h-10 rounded-xl border bg-white px-3.5 font-mono text-sm text-slate-500 shadow-xs dark:bg-slate-900 dark:text-slate-400">
                <input type="datetime-local" name="to" value="{{ old('to') }}" title="To" class="h-10 rounded-xl border bg-white px-3.5 font-mono text-sm text-slate-500 shadow-xs dark:bg-slate-900 dark:text-slate-400">
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach(State::getAll() as $state)
                        <label class="cursor-pointer select-none">
                            <input type="checkbox" name="{{ $state }}" @checked(old($state) == 'on') onchange="this.form.requestSubmit()" class="peer sr-only">
                            <span class="chip chip-{{ State::getBootstrapColor($state) }} opacity-60 transition peer-checked:opacity-100 peer-checked:ring-2 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-insurance">{{ ucfirst(strtolower($state)) }}</span>
                        </label>
                    @endforeach
                </div>
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="ml-auto flex gap-2">
                    <button type="submit" class="h-10 rounded-xl bg-insurance px-4 text-sm font-bold text-white shadow-sm shadow-indigo-900/20 transition-transform hover:-translate-y-0.5 hover:bg-indigo-700 active:translate-y-0 motion-reduce:transform-none">Filter</button>
                    <a href="{{ url()->current() }}" class="grid h-10 place-items-center rounded-xl px-4 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                </div>
            </form>

            {{-- Holds the CSRF token + method for the bulk actions; row checkboxes and the bulk
                 buttons associate with it via the form="ri-bulk" attribute (no nested forms). --}}
            <form id="ri-bulk" method="POST">@csrf</form>

            {{-- Bulk action bar — revealed by JS once at least one row is selected. --}}
            <div id="ri-bulkbar" class="mb-3 hidden items-center gap-3 rounded-xl border border-indigo-300 bg-indigo-50 px-4 py-2.5 dark:border-indigo-900 dark:bg-indigo-950/40">
                <span class="font-mono text-sm text-slate-600 dark:text-slate-300"><strong id="ri-selcount" class="text-slate-950 dark:text-white">0</strong> selected</span>
                <div class="ml-auto flex items-center gap-2">
                    <button type="submit" form="ri-bulk" formaction="{{ route('request-insurances.retry-selected') }}"
                            onclick="return confirm('Retry the selected request insurances?')"
                            class="h-8 rounded-lg bg-insurance px-3.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-indigo-700">Retry selected</button>
                    <button type="submit" form="ri-bulk" formaction="{{ route('request-insurances.abandon-selected') }}"
                            onclick="return confirm('Abandon the selected request insurances? Active requests will stop being processed.')"
                            class="h-8 rounded-lg border border-red-500/40 px-3.5 text-xs font-bold text-red-700 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30">Abandon selected</button>
                    <button type="button" id="ri-clearsel" class="rounded px-1 text-xs font-semibold text-slate-500 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white">Clear</button>
                </div>
            </div>

            {{-- Listing --}}
            <div class="overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
                @if($requestInsurances->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <p class="text-base font-bold text-slate-900 dark:text-white">No requests found</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Nothing matches the current filters.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[72rem] text-left text-sm">
                            <thead class="border-b bg-slate-50 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                                <tr>
                                    <th class="w-9 px-4 py-3.5"><input type="checkbox" id="ri-selectall" class="size-4 accent-insurance align-middle" title="Select all on this page"></th>
                                    <th class="px-4 py-3.5">ID</th>
                                    <th class="px-4 py-3.5">Pri</th>
                                    <th class="px-4 py-3.5">Method</th>
                                    <th class="px-4 py-3.5">Code</th>
                                    <th class="px-4 py-3.5">Url</th>
                                    <th class="px-4 py-3.5">State</th>
                                    <th class="px-4 py-3.5">Attempts</th>
                                    <th class="px-4 py-3.5">Next try</th>
                                    <th class="px-4 py-3.5">Created</th>
                                    <th class="px-4 py-3.5 text-right">ms</th>
                                    <th class="px-4 py-3.5 text-right"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y font-mono">
                                @foreach($requestInsurances as $requestInsurance)
                                    <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50 [&>td]:whitespace-nowrap [&>td]:px-4 [&>td]:py-2.5">
                                        <td>
                                            @if($requestInsurance->doesNotHaveState(State::COMPLETED))
                                                <input type="checkbox" name="ids[]" value="{{ $requestInsurance->id }}" form="ri-bulk" class="ri-select size-4 accent-insurance align-middle">
                                            @endif
                                        </td>
                                        <td class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $requestInsurance->id }}</td>
                                        <td class="tabular-nums">{{ $requestInsurance->priority }}</td>
                                        <td class="font-bold">{{ mb_strtoupper($requestInsurance->method) }}</td>
                                        <td><x-request-insurance-http-code httpCode="{{ $requestInsurance->response_code }}" /></td>
                                        <td class="w-full min-w-[20rem] max-w-0 !whitespace-normal text-slate-500 dark:text-slate-400" title="{{ urldecode($requestInsurance->url) }}"><span class="line-clamp-2 break-all">{{ urldecode($requestInsurance->url) }}</span></td>
                                        <td><x-request-insurance-status :requestInsurance="$requestInsurance" /></td>
                                        <td class="tabular-nums">{{ $requestInsurance->retry_count }}</td>
                                        <td class="text-xs"><x-request-insurance-timestamp :value="$requestInsurance->retry_at" /></td>
                                        <td class="text-xs text-slate-500 dark:text-slate-400"><x-request-insurance-timestamp :value="$requestInsurance->created_at" /></td>
                                        <td class="text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $requestInsurance->getTotalTime() < 0 ? '·' : number_format($requestInsurance->getTotalTime()) }}</td>
                                        <td>
                                            <div class="grid grid-cols-[4.3rem_4.6rem_5rem_4.7rem] items-center gap-1.5">
                                                @if($requestInsurance->isRetryable())
                                                    <form method="POST" action="{{ route('request-insurances.retry', $requestInsurance) }}" class="w-full">@csrf
                                                        <button type="submit" class="act border-amber-500/50 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/30">Retry</button>
                                                    </form>
                                                @else <span></span> @endif

                                                @if($requestInsurance->hasState(State::PENDING))
                                                    <form method="POST" action="{{ route('request-insurances.unlock', $requestInsurance) }}" class="w-full">@csrf
                                                        <button type="submit" class="act border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Unlock</button>
                                                    </form>
                                                @else <span></span> @endif

                                                @if($requestInsurance->doesNotHaveState(State::COMPLETED) && $requestInsurance->doesNotHaveState(State::ABANDONED))
                                                    <form method="POST" action="{{ route('request-insurances.destroy', $requestInsurance) }}" class="w-full" data-confirm="Abandon request insurance #{{ $requestInsurance->id }}?">@csrf
                                                        <input type="hidden" name="_method" value="delete">
                                                        <button type="submit" class="act border-red-500/40 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30">Abandon</button>
                                                    </form>
                                                @else <span></span> @endif

                                                <a href="{{ route('request-insurances.show', $requestInsurance) }}" class="act border-indigo-500/40 text-indigo-700 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/30">Inspect</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 font-mono text-xs text-slate-500 dark:text-slate-400">
                <span>priority is zero-based · 0 = highest</span>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2">rows
                        <select onchange="const u=new URL(location); u.searchParams.set('per_page', this.value); u.searchParams.delete('cursor'); location = u;"
                                class="h-9 rounded-lg border bg-white px-2 text-ink shadow-xs dark:bg-slate-900 dark:text-white">
                            @foreach([25, 50, 100, 250, 500, 1000] as $size)
                                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </label>
                    <span class="flex items-center gap-1.5">
                        @if($requestInsurances->previousPageUrl())
                            <a href="{{ $requestInsurances->previousPageUrl() }}" rel="prev" class="grid h-9 place-items-center rounded-lg border bg-white px-3 font-bold text-slate-700 transition-colors hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">‹ Newer</a>
                        @else
                            <span aria-disabled="true" class="grid h-9 place-items-center rounded-lg border bg-white px-3 text-slate-300 dark:bg-slate-900 dark:text-slate-600">‹ Newer</span>
                        @endif
                        @if($requestInsurances->nextPageUrl())
                            <a href="{{ $requestInsurances->nextPageUrl() }}" rel="next" class="grid h-9 place-items-center rounded-lg border bg-white px-3 font-bold text-slate-700 transition-colors hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Older ›</a>
                        @else
                            <span aria-disabled="true" class="grid h-9 place-items-center rounded-lg border bg-white px-3 text-slate-300 dark:bg-slate-900 dark:text-slate-600">Older ›</span>
                        @endif
                    </span>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
<script>
    // Fill the lifecycle strip counts.
    fetch('{{ route('request-insurances.monitor_segmented') }}')
        .then(r => r.json())
        .then(counts => Object.entries(counts).forEach(([state, n]) => {
            const el = document.getElementById('count-' + state);
            if (el) el.textContent = Number(n).toLocaleString();
        }))
        .catch(() => document.querySelectorAll('[id^="count-"]').forEach(el => el.textContent = '–'));

    // Row selection + bulk action bar.
    (function () {
        const bar = document.getElementById('ri-bulkbar');
        const countEl = document.getElementById('ri-selcount');
        const selectAll = document.getElementById('ri-selectall');
        const boxes = () => Array.from(document.querySelectorAll('.ri-select'));

        if (!selectAll) return;

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
