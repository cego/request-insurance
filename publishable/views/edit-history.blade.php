<?php use Cego\RequestInsurance\Enums\State; ?>
@extends("request-insurance::layouts.master")

@section("content")
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <h1 class="text-[22px] font-semibold tracking-tight">Edit history <span class="font-mono">#{{ $requestInsurance->id }}</span></h1>
        <x-request-insurance-status :requestInsurance="$requestInsurance" />
        <a href="{{ route('request-insurances.show', $requestInsurance) }}" class="ml-auto text-[13px] text-ink-soft no-underline hover:text-ink">← back to request</a>
    </div>

    <section class="rounded-2xl border border-line bg-surface">
        <div class="border-b border-line px-5 py-3"><h3 class="font-semibold">Current request</h3></div>
        <div class="p-5">
            <table class="kv w-full font-mono text-[13px]">
                <tbody>
                    <tr><td>Id</td><td>{{ $requestInsurance->id }}</td></tr>
                    <tr><td>Priority</td><td>{{ $requestInsurance->priority }}</td></tr>
                    <tr><td>Method</td><td>{{ mb_strtoupper($requestInsurance->method) }}</td></tr>
                    <tr><td>Url</td><td class="break-all">{{ urldecode($requestInsurance->url) }}</td></tr>
                    <tr><td>Payload</td><td><x-request-insurance-pretty-print :content="$requestInsurance->getOriginal('payload')"/></td></tr>
                    <tr><td>Headers</td><td><x-request-insurance-pretty-print :content="$requestInsurance->getOriginal('headers')"/></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @php $appliedEdits = $requestInsurance->edits()->where('applied_at', '<>', null)->orderBy('applied_at', 'DESC'); @endphp
    @foreach($appliedEdits->get() as $edit)
        <section class="mt-4 rounded-2xl border border-line bg-surface">
            <div class="flex items-center gap-2 border-b border-line px-5 py-3">
                <h3 class="font-semibold">Applied</h3>
                <span class="font-mono text-[13px] text-ink-soft"><x-request-insurance-timestamp :value="$edit->applied_at" /></span>
            </div>
            <div class="p-5">
                <table class="kv w-full font-mono text-[13px]">
                    <tbody>
                        <tr><td>Id</td><td>{{ $requestInsurance->id }}</td></tr>
                        <tr><td>Priority</td><td>{{ $edit->old_priority }}</td></tr>
                        <tr><td>Method</td><td>{{ mb_strtoupper($edit->old_method) }}</td></tr>
                        <tr><td>Url</td><td class="break-all">{{ urldecode($edit->old_url) }}</td></tr>
                        <tr><td>Payload</td><td><x-request-insurance-pretty-print :content="$edit->old_payload"/></td></tr>
                        <tr><td>Headers</td><td><x-request-insurance-pretty-print :content="$edit->old_headers"/></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
