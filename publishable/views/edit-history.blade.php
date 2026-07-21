@extends('request-insurance::layouts.master')

@section('title', 'Edit history #' . $requestInsurance->id . ' · Request Insurance')

@section('content')
    <div class="space-y-6">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <a href="{{ route('request-insurances.index') }}" class="rounded hover:text-insurance">Pipeline</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('request-insurances.show', $requestInsurance) }}" class="rounded hover:text-insurance">Request #{{ $requestInsurance->id }}</a>
            <span aria-hidden="true">/</span>
            <span class="font-medium text-slate-900 dark:text-white">Edit history</span>
        </nav>

        <header class="border-b pb-6">
            <p class="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-insurance">Audit trail</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-black tracking-[-0.04em] text-slate-950 sm:text-4xl dark:text-white">Edit history</h1>
                <x-request-insurance-status :requestInsurance="$requestInsurance" />
            </div>
        </header>

        <section aria-labelledby="current-heading" class="overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
            <div class="border-b px-5 py-4 sm:px-6">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.16em] text-slate-400">As it is now</p>
                <h2 id="current-heading" class="mt-1 text-lg font-extrabold text-slate-950 dark:text-white">Current request</h2>
            </div>
            <div class="p-5 sm:p-6">
                <table class="kv w-full font-mono text-[13px]">
                    <tbody>
                        <tr><td>Id</td><td>{{ $requestInsurance->id }}</td></tr>
                        <tr><td>Priority</td><td>{{ $requestInsurance->priority }}</td></tr>
                        <tr><td>Method</td><td class="font-bold">{{ mb_strtoupper($requestInsurance->method) }}</td></tr>
                        <tr><td>Url</td><td class="break-all">{{ urldecode($requestInsurance->url) }}</td></tr>
                        <tr><td>Payload</td><td><x-request-insurance-pretty-print :content="$requestInsurance->getOriginal('payload')"/></td></tr>
                        <tr><td>Headers</td><td><x-request-insurance-pretty-print :content="$requestInsurance->getOriginal('headers')"/></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        @php $appliedEdits = $requestInsurance->edits()->where('applied_at', '<>', null)->orderBy('applied_at', 'DESC'); @endphp
        @foreach($appliedEdits->get() as $edit)
            <section class="overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 dark:bg-slate-900">
                <div class="flex flex-wrap items-center gap-3 border-b px-5 py-4 sm:px-6">
                    <h2 class="text-lg font-extrabold text-slate-950 dark:text-white">Before edit applied</h2>
                    <span class="font-mono text-sm text-slate-500 dark:text-slate-400"><x-request-insurance-timestamp :value="$edit->applied_at" /></span>
                </div>
                <div class="p-5 sm:p-6">
                    <table class="kv w-full font-mono text-[13px]">
                        <tbody>
                            <tr><td>Id</td><td>{{ $requestInsurance->id }}</td></tr>
                            <tr><td>Priority</td><td>{{ $edit->old_priority }}</td></tr>
                            <tr><td>Method</td><td class="font-bold">{{ mb_strtoupper($edit->old_method) }}</td></tr>
                            <tr><td>Url</td><td class="break-all">{{ urldecode($edit->old_url) }}</td></tr>
                            <tr><td>Payload</td><td><x-request-insurance-pretty-print :content="$edit->old_payload"/></td></tr>
                            <tr><td>Headers</td><td><x-request-insurance-pretty-print :content="$edit->old_headers"/></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
