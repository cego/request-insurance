<?php use Cego\RequestInsurance\Enums\State; ?>
@extends('request-insurance::layouts.master')

@section('title', 'Request #' . $requestInsurance->id . ' · Request Insurance')

@section('content')
    @php
        if(!function_exists('getEditErrorMessage')){
            function getEditErrorMessage($editId, $field) {
                $requestInsuranceEdit = Session::get('requestInsuranceEdit');
                $requestErrors = Session::get('requestErrors');
                if ( empty($requestInsuranceEdit)){ return ""; }
                if ($requestInsuranceEdit->id != $editId){ return ""; }
                if ( empty($requestErrors[$field])){ return ""; }
                return $requestErrors[$field];
            }
        }
    @endphp

    <style>
        @keyframes riFlash { 0%{background:transparent} 45%{background:rgba(79,70,229,.18)} 100%{background:transparent} }
        .backgroundAnimated{ animation: riFlash .8s ease-in-out; }

        /* Theme-aware diff viewer (markup produced by jfcherng/php-diff). */
        :root{
            --diff-ink:#172033; --diff-soft:#5c6779; --diff-line:#e2e8f0; --diff-surface:#f8fafc;
            --diff-del:#dc2626; --diff-ins:#059669;
        }
        @media (prefers-color-scheme: dark){
            :root{
                --diff-ink:#e2e8f0; --diff-soft:#94a3b8; --diff-line:#1e293b; --diff-surface:#020617;
                --diff-del:#f87171; --diff-ins:#34d399;
            }
        }
        .diff-wrapper.diff{
            width:100%; border-collapse:collapse; background:transparent; color:var(--diff-ink);
            font-family:var(--font-mono); font-size:12px; line-height:1.55; word-break:break-all;
            border:1px solid var(--diff-line); border-radius:.5rem; overflow:hidden;
        }
        .diff-wrapper.diff thead th{
            background:var(--diff-surface); color:var(--diff-soft); white-space:nowrap;
            font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.06em;
            text-align:left; padding:.4rem .6rem; border-bottom:1px solid var(--diff-line);
        }
        .diff-wrapper.diff td, .diff-wrapper.diff th{ padding:.12rem .6rem; vertical-align:top; border:0; }
        .diff-wrapper.diff tbody th{
            width:1%; white-space:nowrap; text-align:right; font-weight:400; user-select:none;
            color:var(--diff-soft); background:var(--diff-surface);
        }
        .diff-wrapper.diff tbody th.sign{ padding:0 .4rem; text-align:center; }
        .diff-wrapper.diff tbody th.sign.del{ color:var(--diff-del); }
        .diff-wrapper.diff tbody th.sign.ins{ color:var(--diff-ins); }
        .diff-wrapper.diff td.old, .diff-wrapper.diff td.rep{ background:color-mix(in srgb, var(--diff-del) 9%, transparent); }
        .diff-wrapper.diff td.new{ background:color-mix(in srgb, var(--diff-ins) 10%, transparent); }
        .diff-wrapper.diff td.none{ background:transparent; }
        .diff-wrapper.diff .change-eq td{ color:var(--diff-soft); }
        .diff-wrapper.diff tbody.skipped td, .diff-wrapper.diff tbody.skipped th{
            background:var(--diff-surface); color:var(--diff-soft); text-align:center;
        }
        /* inline word/char-level changes */
        .diff-wrapper.diff.diff-html .change ins{
            text-decoration:none; border-radius:2px; padding:0 1px;
            background:color-mix(in srgb, var(--diff-ins) 30%, transparent);
            color:color-mix(in srgb, var(--diff-ins) 75%, var(--diff-ink));
        }
        .diff-wrapper.diff.diff-html .change del{
            text-decoration:none; border-radius:2px; padding:0 1px;
            background:color-mix(in srgb, var(--diff-del) 30%, transparent);
            color:color-mix(in srgb, var(--diff-del) 75%, var(--diff-ink));
        }
    </style>

    @php
        $btnPrimary = 'inline-flex h-9 cursor-pointer items-center justify-center rounded-xl bg-insurance px-4 text-sm font-bold text-white shadow-sm shadow-indigo-900/20 transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-insurance';
        $btnDanger  = 'inline-flex h-9 cursor-pointer items-center justify-center rounded-xl border border-red-500/40 px-4 text-sm font-bold text-red-700 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-400 dark:hover:bg-red-950/30';
        $btnGhost   = 'inline-flex h-9 cursor-pointer items-center justify-center rounded-xl border bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
        $editInput  = 'w-full rounded-xl border bg-white px-3 py-2 font-mono text-xs text-slate-950 shadow-xs disabled:opacity-60 dark:bg-slate-950 dark:text-white';
    @endphp

    <div class="space-y-6">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <a href="{{ route('request-insurances.index') }}" class="rounded hover:text-insurance">Pipeline</a>
            <span aria-hidden="true">/</span>
            <span class="font-medium text-slate-900 dark:text-white">Request #{{ $requestInsurance->id }}</span>
        </nav>

        <header class="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
            <div>
                <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-insurance">Inspection</p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-black tracking-[-0.04em] text-slate-950 sm:text-4xl dark:text-white">Request #{{ $requestInsurance->id }}</h1>
                    <x-request-insurance-status :requestInsurance="$requestInsurance" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($requestInsurance->hasState(State::FAILED))
                    <form method="POST" action="{{ route('request-insurance-edits.create', $requestInsurance) }}">@csrf
                        <button class="{{ $btnPrimary }}" type="submit">Edit request</button>
                    </form>
                @endif
                @php $appliedEdits = $requestInsurance->edits()->where('applied_at', '<>', null); @endphp
                @if($appliedEdits->count() > 0)
                    <a href="{{ route('request-insurances.edit-history', $requestInsurance) }}" class="{{ $btnGhost }}">Edit history</a>
                @endif
            </div>
        </header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- Request --}}
            <section aria-labelledby="request-heading" class="overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
                <div class="border-b px-5 py-4 sm:px-6">
                    <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400">Outbound</p>
                    <h2 id="request-heading" class="mt-1 text-lg font-extrabold text-slate-950 dark:text-white">Request</h2>
                </div>
                <div class="p-5 sm:p-6">
                    <table class="kv w-full font-mono text-[13px]">
                        <tbody>
                            <tr><td>Id</td><td>{{ $requestInsurance->id }}</td></tr>
                            <tr><td>Trace id</td><td>
                                @if(filled($requestInsurance->trace_id))
                                    <a href="{{ route('request-insurances.index', ['trace_id' => $requestInsurance->trace_id]) }}" title="Show all requests with this trace id" class="break-all rounded text-insurance hover:underline">{{ $requestInsurance->trace_id }}</a>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td></tr>
                            <tr><td>Priority</td><td>{{ $requestInsurance->priority }}</td></tr>
                            <tr><td>Method</td><td class="font-bold">{{ mb_strtoupper($requestInsurance->method) }}</td></tr>
                            <tr><td>Url (decoded)</td><td class="break-all">{{ urldecode($requestInsurance->url) }}</td></tr>
                            <tr><td>Url</td><td class="break-all">{{ $requestInsurance->url }}</td></tr>
                            <tr><td>Payload</td><td><x-request-insurance-pretty-print :content="$requestInsurance->getPayloadWithMaskingApplied()"/></td></tr>
                            <tr><td>Headers</td><td><x-request-insurance-pretty-print :content="$requestInsurance->getHeadersWithMaskingApplied()"/></td></tr>
                            <tr><td>Timings</td><td><x-request-insurance-pretty-print :content="$requestInsurance->timings"/></td></tr>
                            <tr><td>Next attempt</td><td><x-request-insurance-timestamp :value="$requestInsurance->retry_at" /></td></tr>
                            <tr><td>State changed</td><td><x-request-insurance-timestamp :value="$requestInsurance->state_changed_at" /></td></tr>
                            <tr><td>Attempts</td><td>{{ $requestInsurance->retry_count }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Response --}}
            <section aria-labelledby="response-heading" class="overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
                <div class="border-b px-5 py-4 sm:px-6">
                    <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400">Inbound</p>
                    <h2 id="response-heading" class="mt-1 text-lg font-extrabold text-slate-950 dark:text-white">Response</h2>
                </div>
                <div class="p-5 sm:p-6">
                    <table class="kv w-full font-mono text-[13px]">
                        <tbody>
                            <tr><td>Code</td><td><x-request-insurance-http-code httpCode="{{ $requestInsurance->response_code }}"/></td></tr>
                            <tr><td>Headers</td><td><x-request-insurance-pretty-print :content="$requestInsurance->response_headers"/></td></tr>
                            <tr><td>Body</td><td><x-request-insurance-pretty-print :content="$requestInsurance->response_body"/></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- Edits --}}
        @php $pendingEdits = $requestInsurance->edits()->where('applied_at', null)->orderBy('updated_at', 'DESC'); @endphp
        @if($pendingEdits->count() > 0)
            <details open class="group rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
                <summary class="flex cursor-pointer items-center gap-2 px-5 py-4 marker:content-none sm:px-6">
                    <svg class="size-4 text-slate-400 transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 4l4 4-4 4z"/></svg>
                    <span class="text-lg font-extrabold text-slate-950 dark:text-white">Pending edits</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $pendingEdits->count() }}</span>
                </summary>
                <div class="space-y-6 border-t p-5 sm:p-6">
                    @foreach($pendingEdits->get() as $edit)
                        @php
                            $canModifyEdit = $edit->applied_at == null && $edit->admin_user == $user;
                            $canApproveEdit = $edit->applied_at == null && ! $canModifyEdit;
                            $canApplyEdit = $edit->applied_at == null && $edit->approvals->count() >= $edit->required_number_of_approvals && $canModifyEdit;
                        @endphp
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            {{-- Edit form --}}
                            <div class="rounded-xl border bg-slate-50/60 dark:bg-slate-950/40 {{ $edit->created_at->diffInSeconds(\Carbon\Carbon::now()) < 5 ? 'backgroundAnimated' : '' }}">
                                <div class="flex items-center justify-between border-b px-4 py-3">
                                    <h3 class="font-extrabold text-slate-950 dark:text-white">Edit</h3>
                                    @if($edit->approvals()->count() < $edit->required_number_of_approvals)
                                        <span class="chip chip-primary">Pending approval</span>
                                    @else
                                        <span class="chip chip-success">Approved</span>
                                    @endif
                                </div>
                                <div class="p-4">
                                    <form method="POST" action="{{ route('request-insurance-edits.update', $edit) }}">@csrf
                                        <table class="kv w-full font-mono text-[13px]">
                                            <tbody>
                                                <tr><td>Editor</td><td>{{ $edit->admin_user }}</td></tr>
                                                <tr><td>Id</td><td>{{ $edit->request_insurance_id }}</td></tr>
                                                <tr><td>Priority</td><td><input name="new_priority" type="number" min="0" max="9999" class="{{ $editInput }}"
                                                    onchange="(()=>{this.value=this.value<0?0:this.value>9999?9999:this.value;})()"
                                                    onkeyup="(()=>{this.value=this.value<0?0:this.value>9999?9999:this.value;})()"
                                                    @disabled( ! $canModifyEdit) value="{{ $edit->new_priority }}"/></td></tr>
                                                <tr><td>Method</td><td>
                                                    <select name="new_method" class="{{ $editInput }}" @disabled( ! $canModifyEdit)>
                                                        @foreach(['GET','POST','PUT','PATCH','DELETE'] as $m)
                                                            <option value="{{ $m }}" @selected(mb_strtoupper($edit->new_method) == $m)>{{ $m }}</option>
                                                        @endforeach
                                                    </select></td></tr>
                                                <tr><td>Url</td><td><input name="new_url" class="{{ $editInput }}" @disabled( ! $canModifyEdit) value="{{ urldecode($edit->new_url) }}"/></td></tr>
                                                <tr><td>Payload</td><td>
                                                    @if($canModifyEdit)
                                                        <x-request-insurance-pretty-print-text-area :name='"new_payload"' :content="$edit->new_payload" :disabled=" ! $canModifyEdit"/>
                                                    @else
                                                        <x-request-insurance-pretty-print :content="$edit->new_payload"/>
                                                    @endif
                                                </td></tr>
                                                <tr><td>Headers</td><td>
                                                    @if($canModifyEdit)
                                                        <x-request-insurance-pretty-print-text-area :name='"new_headers"' :content="$edit->new_headers" :disabled=" ! $canModifyEdit"/>
                                                    @else
                                                        <x-request-insurance-pretty-print :content="$edit->new_headers"/>
                                                    @endif
                                                    @if( ! empty($errorMsg = getEditErrorMessage($edit->id, 'header')))
                                                        <span class="text-xs font-semibold text-red-600 dark:text-red-400">{{ $errorMsg }}</span>
                                                    @endif
                                                </td></tr>
                                            </tbody>
                                        </table>
                                        @if($canModifyEdit)
                                            <div class="mt-3"><input type="hidden" name="_method" value="post"><button class="{{ $btnPrimary }}" type="submit">Save</button></div>
                                        @endif
                                    </form>

                                    <div class="mt-4 border-t pt-4">
                                        <h4 class="mb-2 flex items-center gap-2 font-extrabold text-slate-950 dark:text-white">Approvals <x-request-insurance-edit-approvals-status :requestInsuranceEdit="$edit" /></h4>
                                        <table class="w-full font-mono text-[13px]">
                                            <thead><tr class="text-left text-[0.68rem] font-bold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400 [&>th]:pb-1.5"><th>Approver</th><th>Created</th></tr></thead>
                                            <tbody class="divide-y">
                                                @foreach($edit->approvals->sortBy('created_at') as $approval)
                                                    <tr class="[&>td]:py-1.5"><td>{{ $approval->approver_admin_user }}</td><td class="text-slate-500 dark:text-slate-400"><x-request-insurance-timestamp :value="$approval->created_at" /></td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if($canApproveEdit)
                                                @php $approvalsByUser = $edit->approvals()->where('approver_admin_user', $user); @endphp
                                                @if($approvalsByUser->count() == 0)
                                                    <form method="POST" action="{{ route('request-insurance-edit-approvals.create', $edit) }}">@csrf
                                                        <input type="hidden" name="_method" value="post">
                                                        <button class="{{ $btnPrimary }}" type="submit" @disabled( ! $canApproveEdit)>Approve</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('request-insurance-edit-approvals.destroy', $approvalsByUser->first()) }}">@csrf
                                                        <input type="hidden" name="_method" value="delete">
                                                        <button class="{{ $btnGhost }}" type="submit">Remove approval</button>
                                                    </form>
                                                @endif
                                            @endif
                                            @if($canModifyEdit)
                                                <form method="POST" action="{{ route('request-insurances-edits.apply', $edit) }}">@csrf
                                                    <input type="hidden" name="_method" value="post">
                                                    <button class="{{ $btnPrimary }}" type="submit" @disabled( ! $canApplyEdit)>Apply</button>
                                                </form>
                                                <form method="POST" action="{{ route('request-insurance-edits.destroy', $edit) }}">@csrf
                                                    <input type="hidden" name="_method" value="delete">
                                                    <button class="{{ $btnDanger }}" type="submit">Delete</button>
                                                </form>
                                                @if( ! empty($errorMsg = getEditErrorMessage($edit->id, 'approval')))
                                                    <span class="self-center text-xs font-semibold text-red-600 dark:text-red-400">{{ $errorMsg }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Differences --}}
                            <div class="rounded-xl border bg-slate-50/60 dark:bg-slate-950/40">
                                <div class="border-b px-4 py-3"><h3 class="font-extrabold text-slate-950 dark:text-white">Differences</h3></div>
                                <div class="p-4">
                                    <table class="kv w-full font-mono text-[13px]">
                                        <tbody>
                                            <tr><td>Editor</td><td>{{ $edit->admin_user }}</td></tr>
                                            <tr><td>Id</td><td>{{ $edit->request_insurance_id }}</td></tr>
                                            <tr><td>Priority</td><td><x-request-insurance-pretty-print-difference :oldValues="strval($edit->old_priority)" :newValues="strval($edit->new_priority)"/></td></tr>
                                            <tr><td>Method</td><td><x-request-insurance-pretty-print-difference :oldValues="strtoupper($edit->old_method)" :newValues="strtoupper($edit->new_method)" /></td></tr>
                                            <tr><td>Url</td><td><x-request-insurance-pretty-print-difference :oldValues="$edit->old_url" :newValues="$edit->new_url"/></td></tr>
                                            <tr><td>Payload</td><td><x-request-insurance-pretty-print-difference :oldValues="$edit->old_payload" :newValues="$edit->new_payload" /></td></tr>
                                            <tr><td>Headers</td><td><x-request-insurance-pretty-print-difference :oldValues="$edit->old_headers" :newValues="$edit->new_headers" /></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        {{-- Logs --}}
        <section aria-labelledby="logs-heading" class="overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
            <div class="border-b px-5 py-4 sm:px-6">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400">Attempt history</p>
                <h2 id="logs-heading" class="mt-1 text-lg font-extrabold text-slate-950 dark:text-white">Logs</h2>
            </div>
            @if($requestInsurance->logs->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="font-bold text-slate-900 dark:text-white">No attempts logged yet</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Each processing attempt will be recorded here.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-left text-sm">
                        <thead class="border-b bg-slate-50 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3.5">ID</th>
                                <th class="px-4 py-3.5">Code</th>
                                <th class="px-4 py-3.5">Response headers</th>
                                <th class="px-4 py-3.5">Response body</th>
                                <th class="px-4 py-3.5">Created</th>
                                <th class="px-4 py-3.5 text-right">ms</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-mono">
                            @foreach ($requestInsurance->logs->sortByDesc('created_at') as $log)
                                <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50 [&>td]:px-4 [&>td]:py-2.5 [&>td]:align-top">
                                    <td class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $log->id }}</td>
                                    <td><x-request-insurance-http-code httpCode="{{ $log->response_code }}" /></td>
                                    <td class="max-w-[280px] truncate text-slate-500 dark:text-slate-400" title="{{ $log->response_headers }}">{{ $log->response_headers }}</td>
                                    <td class="max-w-[280px] truncate text-slate-500 dark:text-slate-400" title="{{ $log->response_body }}">{{ $log->response_body }}</td>
                                    <td class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400"><x-request-insurance-timestamp :value="$log->created_at" /></td>
                                    <td class="text-right tabular-nums text-slate-500 dark:text-slate-400">{{ $log->getTotalTime() < 0 ? '·' : number_format($log->getTotalTime()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
