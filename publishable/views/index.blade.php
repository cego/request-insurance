@extends('request-insurance::layouts.master')

@section('content')
    <div class="container-flex">
        <div class="row">
            <div class="col-12">

                <div class="pt-5">
                    <div class="form-group form-inline">
                        <h1 class="">Request Insurance: </h1>
                    </div>
                    <div class="pb-5">
                        <div class="mr-5">
                            <div class="badge mr-2">{{ $requestInsurances->total() }}</div><span class="mr-5"><strong>Requests in total</strong></span>
                            <span id="ajax-managed-request-count">
                                <div id="active-request-count" class="badge mr-2">
                                    <div class="spinner-grow spinner-grow-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                                <span class="mr-5"><strong>Active requests</strong></span>
                                <div id="completed-request-count" class="badge badge-success mr-2">
                                    <div class="spinner-grow spinner-grow-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                                <span class="mr-4"><strong>Completed</strong></span>
                                <div id="abandoned-request-count" class="badge badge-warning mr-2">
                                    <div class="spinner-grow spinner-grow-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div><span class="mr-4"><strong>Abandoned</strong></span>
                                <div id="failed-request-count" class="badge badge-danger mr-2">
                                    <div class="spinner-grow spinner-grow-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div><span class="mr-4"><strong>Failed</strong></span>
                                <div id="locked-request-count" class="badge badge-secondary mr-2">
                                    <div class="spinner-grow spinner-grow-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </div><span class="mr-4"><strong>Locked</strong></span>
                                <script type="text/javascript">
                                    $(document).ready(function () {
                                        fetch('dsd{{ route('request-insurances.monitor_segmented') }}')
                                            .then(response => response.json())
                                            .then(function (response) {
                                                $('#active-request-count').text(response.active);
                                                $('#completed-request-count').text(response.completed);
                                                $('#abandoned-request-count').text(response.abandoned);
                                                $('#failed-request-count').text(response.failed);
                                                $('#locked-request-count').text(response.locked);
                                            })
                                            .catch(function (error) {
                                                // set #ajax-managed-request-count children to alert box
                                                $('#ajax-managed-request-count').html(`<span class="alert alert-danger" role="alert">Could not fetch segmented request statistics: ${error}</span>`);
                                                console.log(error);
                                            });
                                    });
                                </script>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="clearfix mb-3">
                            <form method="get" class="float-right form-inline">
                                <div class="form-group mr-5">
                                    <label class="form-check-label mr-3">
                                        Trace ID: <input class="form-control ml-2" type="text" name="trace_id" style="width: 250px" placeholder="trace_id" value="{{ old("trace_id") }}">
                                    </label>
                                    <label class="form-check-label mr-3">
                                        Url: <input class="form-control ml-2" type="text" name="url" style="width: 250px" placeholder="% (SQL LIKE)" value="{{ old("url") }}">
                                    </label>
                                    <label class="form-check-label mr-3">
                                        From: <input class="form-control ml-2" type="date" name="from" style="width: 200px" placeholder="dd-mm-yyyy" value="{{ old("from") }}">
                                    </label>
                                    <label class="form-check-label">
                                        To: <input class="form-control ml-2" type="date" name="to" style="width: 200px" placeholder="dd-mm-yyyy" value="{{ old("to") }}">
                                    </label>
                                </div>

                                <span class="mr-3">State:</span>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input check-lg" type="checkbox" name="Active" {{ old("Active") == "on" ? "checked" : "" }}> Active
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input check-lg" type="checkbox" name="Completed" {{ old("Completed") == "on" ? "checked" : "" }}> Completed
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input check-lg" type="checkbox" name="Abandoned" {{ old("Abandoned") == "on" ? "checked" : "" }}> Abandoned
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input check-lg" type="checkbox" name="Failed" {{ old("Failed") == "on" ? "checked" : "" }}> Failed
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <label class="form-check-label">
                                        <input class="form-check-input check-lg" type="checkbox" name="Locked" {{ old("Locked") == "on" ? "checked" : "" }}> Locked
                                    </label>
                                </div>

                                <button class="btn btn-primary" type="submit">Filter</button>
                                <a href="{{ url()->current() }}" class="btn btn-secondary ml-2">Clear Filters</a>
                            </form>
                        </div>

                        <table class="table table-hover border bg-white">
                            <thead>
                            <tr>
                                <th>id</th>
                                <th>Priority</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Url</th>
                                <th>Payload</th>
                                <th>Retries</th>
                                <th style="width: 185px">Next retry at</th>
                                <th style="width: 185px">Completed at</th>
                                <th style="width: 185px">Paused at</th>
                                <th style="width: 185px">Abandoned at</th>
                                <th style="width: 185px">Locked at</th>
                                <th style="width: 185px">Created at</th>
                                <th>Inspect</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($requestInsurances as $requestInsurance)
                                <tr>
                                    <td>{{ $requestInsurance->id }}</td>
                                    <td>{{ $requestInsurance->priority }}</td>
                                    <td>{{ mb_strtoupper($requestInsurance->method) }}</td>
                                    <td><x-request-insurance-http-code httpCode="{{ $requestInsurance->response_code }}" /></td>
                                    <td>{{ urldecode($requestInsurance->url) }}</td>
                                    <td><x-request-insurance-inline-print :content="$requestInsurance->getShortenedPayload()" /></td>
                                    <td>{{ $requestInsurance->retry_count }}</td>
                                    <td>{{ $requestInsurance->retry_at }}</td>
                                    <td>{{ $requestInsurance->completed_at }}</td>
                                    <td>{{ $requestInsurance->paused_at }}</td>
                                    <td>{{ $requestInsurance->abandoned_at }}</td>
                                    <td>{{ $requestInsurance->locked_at }}</td>
                                    <td>{{ $requestInsurance->created_at }}</td>
                                    <td>
                                        <a href="{{ route('request-insurances.show', $requestInsurance) }}" class="btn btn-sm btn-outline-primary">Inspect</a>

                                        @if ($requestInsurance->isNotCompleted() && $requestInsurance->isNotAbandoned())
                                            <form method="POST" action="{{ route('request-insurances.destroy', $requestInsurance) }}">
                                                <input type="hidden" name="_method" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Abandon</button>
                                            </form>
                                        @endif

                                        @if ($requestInsurance->isRetryable())
                                            <form method="POST" action="{{ route('request-insurances.retry', $requestInsurance) }}">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Retry</button>
                                            </form>
                                        @endif

                                        @if ($requestInsurance->isLocked())
                                            <form method="POST" action="{{ route('request-insurances.unlock', $requestInsurance) }}">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Unlock</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div>
                            <strong>NB: </strong>Priority is zero based, 0 is the highest priority
                        </div>

                        <div class="d-flex justify-content-center">
                            {{ $requestInsurances->appends(Arr::except(Request::query(), "page"))->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
