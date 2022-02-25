@extends("request-insurance::layouts.master")

@section("content")

    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/10.1.2/styles/zenburn.min.css">
    <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/10.1.2/highlight.min.js"></script>
    <script charset="UTF-8" src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/10.1.2/languages/json.min.js"></script>
    <script>hljs.initHighlightingOnLoad();</script>

    <div class="container-flex mt-5 col-12">
        <div class="row">
            <div class="col-12">
                <h1 class="">Inspecting request insurance <strong>#{{ $requestInsurance->id }}</strong> <x-request-insurance-status :requestInsurance="$requestInsurance" /></h1>
            </div>
            <!-- Request -->
            <div class="col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title text-center">
                            <h3>Request</h3>
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
                                    <td><x-request-insurance-pretty-print :content="$requestInsurance->getPayloadWithMaskingApplied()"/></td>
                                </tr>
                                <tr>
                                    <td>Headers:</td>
                                    <td><x-request-insurance-pretty-print :content="$requestInsurance->getHeadersWithMaskingApplied()"/></td>
                                </tr>
                                <tr>
                                    <td>Completed at:</td>
                                    <td>{{ $requestInsurance->completed_at }}</td>
                                </tr>
                                <tr>
                                    <td>Paused at:</td>
                                    <td>{{ $requestInsurance->paused_at }}</td>
                                </tr>
                                <tr>
                                    <td>Abandoned at:</td>
                                    <td>{{ $requestInsurance->abandoned_at }}</td>
                                </tr>
                                <tr>
                                    <td>Locked at:</td>
                                    <td>{{ $requestInsurance->locked_at }}</td>
                                </tr>
                                <tr>
                                    <td>Next retry at:</td>
                                    <td>{{ $requestInsurance->retry_at }}</td>
                                </tr>
                                <tr>
                                    <td>Retry count:</td>
                                    <td>{{ $requestInsurance->retry_count }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Response -->
            <div class="col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title text-center">
                            <h3>Response:</h3>
                            <hr>
                        </div>
                        <div class="card-text">
                            <table class="table-hover w-100 table-vertical table-striped">
                                <tbody>
                                <tr>
                                    <td>Response code:</td>
                                    <td><h4><x-request-insurance-http-code httpCode="{{ $requestInsurance->response_code }}"/></h4></td>
                                </tr>
                                <tr>
                                    <td>Response headers:</td>
                                    <td><x-request-insurance-pretty-print :content="$requestInsurance->response_headers"/></td>
                                </tr>
                                <tr>
                                    <td>Response body</td>
                                    <td><x-request-insurance-pretty-print :content="$requestInsurance->response_body"/></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs -->
            <div class="col-12 mt-2">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title text-center">
                            <h3>Logs:</h3>
                            <hr>
                        </div>
                        <div class="card-text">
                            <table class="table table-hover border bg-white">
                                <thead>
                                <tr>
                                    <th>id</th>
                                    <th>Response code</th>
                                    <th>Response headers</th>
                                    <th>Response body</th>
                                    <th style="width: 185px">Created at</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($requestInsurance->logs->sortByDesc('created_at') as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td><x-request-insurance-http-code httpCode="{{ $log->response_code }}" /></td>
                                        <td><x-request-insurance-inline-print :content="$log->response_headers"/></td>
                                        <td><x-request-insurance-inline-print :content="$log->response_body"/></td>
                                        <td>{{ $log->created_at }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
