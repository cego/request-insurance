<?php
use \Cego\RequestInsurance\Enums\State;
use Jfcherng\Diff\DiffHelper;
?>
@extends("request-insurance::layouts.master")

@section("content")
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
        @keyframes riFlash { 0%{background:transparent} 45%{background:color-mix(in srgb, var(--accent) 18%, transparent)} 100%{background:transparent} }
        .backgroundAnimated{ animation: riFlash .8s ease-in-out; }
        <?= DiffHelper::getStyleSheet(); ?>
    </style>

    @php
        $btnPrimary = 'inline-flex items-center justify-center h-8 rounded-lg px-3.5 text-[13px] font-medium bg-accent text-white hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed';
        $btnWarning = 'inline-flex items-center justify-center h-8 rounded-lg px-3.5 text-[13px] font-medium border border-st-warning/45 text-st-warning hover:bg-st-warning/10 disabled:opacity-40 disabled:cursor-not-allowed';
        $btnDanger  = 'inline-flex items-center justify-center h-8 rounded-lg px-3.5 text-[13px] font-medium border border-st-danger/45 text-st-danger hover:bg-st-danger/10 disabled:opacity-40 disabled:cursor-not-allowed';
        $btnGhost   = 'inline-flex items-center justify-center h-8 rounded-lg px-3.5 text-[13px] font-medium border border-line text-ink-soft hover:text-ink hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed';
        $editInput  = 'w-full rounded-lg border border-line bg-surface-2 px-2.5 py-1.5 font-mono text-[12px] focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/25 disabled:opacity-70';
    @endphp

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <h1 class="text-[22px] font-semibold tracking-tight">Request insurance <span class="font-mono">#{{ $requestInsurance->id }}</span></h1>
        <x-request-insurance-status :requestInsurance="$requestInsurance" />
        <a href="{{ route('request-insurances.index') }}" class="ml-auto text-[13px] text-ink-soft no-underline hover:text-ink">← back to pipeline</a>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        {{-- Request --}}
        <section class="rounded-2xl border border-line bg-surface">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                <h3 class="font-semibold">Request</h3>
                <div class="flex gap-2">
                    @if ($requestInsurance->doesNotHaveState(State::COMPLETED) && $requestInsurance->doesNotHaveState(State::ABANDONED))
                        <form method="POST" action="{{ route('request-insurance-edits.create', $requestInsurance) }}">@csrf
                            <button class="{{ $btnPrimary }}" type="submit">Edit</button>
                        </form>
                    @endif
                    @php $appliedEdits = $requestInsurance->edits()->where('applied_at', '<>', null); @endphp
                    @if($appliedEdits->count() > 0)
                        <a href="{{ route('request-insurances.edit-history', $requestInsurance) }}" class="{{ $btnGhost }} no-underline">History</a>
                    @endif
                </div>
            </div>
            <div class="p-5">
                <table class="kv w-full font-mono text-[13px]">
                    <tbody>
                        <tr><td>Id</td><td>{{ $requestInsurance->id }}</td></tr>
                        <tr><td>Priority</td><td>{{ $requestInsurance->priority }}</td></tr>
                        <tr><td>Method</td><td>{{ mb_strtoupper($requestInsurance->method) }}</td></tr>
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
        <section class="rounded-2xl border border-line bg-surface">
            <div class="border-b border-line px-5 py-3"><h3 class="font-semibold">Response</h3></div>
            <div class="p-5">
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
        <details open class="group mt-5 rounded-2xl border border-line bg-surface">
            <summary class="flex cursor-pointer items-center gap-2 px-5 py-3 font-semibold marker:content-none">
                <svg class="size-4 text-ink-soft transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor"><path d="M6 4l4 4-4 4z"/></svg>
                Edits <span class="font-mono text-[13px] text-ink-soft">({{ $pendingEdits->count() }})</span>
            </summary>
            <div class="space-y-5 border-t border-line p-5">
                @foreach($pendingEdits->get() as $edit)
                    @php
                        $canModifyEdit = $edit->applied_at == null && $edit->admin_user == $user;
                        $canApproveEdit = $edit->applied_at == null && ! $canModifyEdit;
                        $canApplyEdit = $edit->applied_at == null && $edit->approvals->count() >= $edit->required_number_of_approvals && $canModifyEdit;
                    @endphp
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        {{-- Edit form --}}
                        <div class="rounded-xl border border-line bg-surface-2/40 {{ $edit->created_at->diffInSeconds(\Carbon\Carbon::now()) < 5 ? 'backgroundAnimated' : '' }}">
                            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                                <h4 class="font-semibold">Edit</h4>
                                @if($edit->approvals()->count() < $edit->required_number_of_approvals)
                                    <span class="chip rounded-full px-2 py-0.5 text-[12px] font-mono" style="--chip:var(--c-primary)">Pending</span>
                                @else
                                    <span class="chip rounded-full px-2 py-0.5 text-[12px] font-mono" style="--chip:var(--c-success)">Approved</span>
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
                                                    <span class="text-st-danger text-[12px]">{{ $errorMsg }}</span>
                                                @endif
                                            </td></tr>
                                        </tbody>
                                    </table>
                                    @if($canModifyEdit)
                                        <div class="mt-3"><input type="hidden" name="_method" value="post"><button class="{{ $btnPrimary }}" type="submit">Save</button></div>
                                    @endif
                                </form>

                                <div class="mt-4 border-t border-line pt-4">
                                    <h4 class="mb-2 font-semibold">Approvals <x-request-insurance-edit-approvals-status :requestInsuranceEdit="$edit" /></h4>
                                    <table class="w-full text-[13px] font-mono">
                                        <thead><tr class="text-left text-[11px] uppercase tracking-wider text-ink-soft [&>th]:pb-1"><th>Approver</th><th>Created</th></tr></thead>
                                        <tbody>
                                            @foreach($edit->approvals->sortBy('created_at') as $approval)
                                                <tr class="border-t border-line/60 [&>td]:py-1.5"><td>{{ $approval->approver_admin_user }}</td><td class="text-ink-soft"><x-request-insurance-timestamp :value="$approval->created_at" /></td></tr>
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
                                                <span class="self-center text-st-danger text-[12px]">{{ $errorMsg }}</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Differences --}}
                        <div class="rounded-xl border border-line bg-surface-2/40">
                            <div class="border-b border-line px-4 py-3"><h4 class="font-semibold">Differences</h4></div>
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
    <section class="mt-5 overflow-hidden rounded-2xl border border-line bg-surface">
        <div class="border-b border-line px-5 py-3"><h3 class="font-semibold">Logs</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left font-mono text-[11px] uppercase tracking-wider text-ink-soft [&>th]:px-4 [&>th]:py-2.5 [&>th]:font-medium border-b border-line bg-surface-2">
                        <th>id</th><th>code</th><th>response headers</th><th>response body</th><th>created</th><th class="text-right">ms</th>
                    </tr>
                </thead>
                <tbody class="font-mono [&>tr]:border-b [&>tr]:border-line/70 [&>tr:last-child]:border-0 [&>tr>td]:px-4 [&>tr>td]:py-2 [&>tr>td]:align-top">
                    @foreach ($requestInsurance->logs->sortByDesc('created_at') as $log)
                        <tr class="hover:bg-surface-2/60">
                            <td class="text-ink-soft">{{ $log->id }}</td>
                            <td><x-request-insurance-http-code httpCode="{{ $log->response_code }}" /></td>
                            <td class="max-w-[280px] truncate text-ink-soft" title="{{ $log->response_headers }}">{{ $log->response_headers }}</td>
                            <td class="max-w-[280px] truncate text-ink-soft" title="{{ $log->response_body }}">{{ $log->response_body }}</td>
                            <td class="text-[12px] text-ink-soft"><x-request-insurance-timestamp :value="$log->created_at" /></td>
                            <td class="text-right tabular-nums text-ink-soft">{{ $log->getTotalTime() < 0 ? '·' : number_format($log->getTotalTime()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
