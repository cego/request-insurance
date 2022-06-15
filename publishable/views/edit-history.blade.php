<?php use \Cego\RequestInsurance\Enums\State; ?>
@extends("request-insurance::layouts.master")

@section("content")

    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/10.1.2/styles/zenburn.min.css">
    <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/10.1.2/highlight.min.js"></script>
    <script charset="UTF-8" src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/10.1.2/languages/json.min.js"></script>
    <script>hljs.initHighlightingOnLoad();</script>

    <div class="container-flex mt-5 col-12">
        <div class="row">
            <div class="col-12">
                <h1 class="">Inspecting request insurance edit history <strong>#{{ $requestInsurance->id }}</strong> <x-request-insurance-status :requestInsurance="$requestInsurance" /></h1>
            </div>
            <!-- Request -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title text-center">
                            <h3>Current Request</h3>
                            <hr>
                        </div>
                        <div class="card-text">
                            <table class="table-hover w-100 table-vertical table-striped">
                                <tbody>
                                <tr>
                                    <td>RequestInsurance Id:</td>
                                    <td>{{ $requestInsurance->id }}</td>
                                </tr>
                                <tr>
                                    <td>Method:</td>
                                    <td>{{ mb_strtoupper($requestInsurance->method) }}</td>
                                </tr>
                                <tr>
                                    <td>Url:</td>
                                    <td>{{ urldecode($requestInsurance->url) }}</td>
                                </tr>
                                <tr>
                                    <td>Payload:</td>
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->getOriginal('payload')"/></td>
                                </tr>
                                <tr>
                                    <td>Headers:</td>
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->getOriginal('headers')"/></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @php
                // Get edits from most recent to oldest
                $appliedEdits = $requestInsurance->edits()->where('applied_at', '<>', null)->orderBy('applied_at', 'DESC');
            @endphp
            @foreach($appliedEdits->get() as $edit)
                <!-- Earlier Request States -->
                <div class="col-12 mt-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="card-title text-center">
                                <h3>{{$edit->applied_at}}</h3>
                                <hr>
                            </div>
                            <div class="card-text">
                                <table class="table-hover w-100 table-vertical table-striped">
                                    <tbody>
                                    <tr>
                                        <td>RequestInsurance Id:</td>
                                        <td>{{ $requestInsurance->id }}</td>
                                    </tr>
                                    <tr>
                                        <td>Method:</td>
                                        <td>{{ mb_strtoupper($edit->old_method) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Url:</td>
                                        <td>{{ urldecode($edit->old_url) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Payload:</td>
                                        <td style="max-width:1px"><x-request-insurance-pretty-print :content="$edit->old_payload"/></td>
                                    </tr>
                                    <tr>
                                        <td>Headers:</td>
                                        <td style="max-width:1px"><x-request-insurance-pretty-print :content="$edit->old_headers"/></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
