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
                <h1 class="">Inspecting request insurance <strong>#{{ $requestInsurance->id }}</strong> <x-request-insurance-status :requestInsurance="$requestInsurance" /></h1>
            </div>
            <!-- Request -->
            <div class="col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title text-center">
                            <h3>Request</h3>
                            @if ($requestInsurance->doesNotHaveState(State::COMPLETED) && $requestInsurance->doesNotHaveState(State::ABANDONED))
                                <form method="POST" action="{{ route('request-insurances.edit', $requestInsurance) }}">
                                    <input type="hidden" name="_method" value="post">
                                    <button class="btn btn-primary" type="submit">Edit</button>
                                </form>
                            @endif
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
                                    <td>Next attempt at:</td>
                                    <td>{{ $requestInsurance->retry_at }}</td>
                                </tr>
                                <tr>
                                    <td>Attempts:</td>
                                    <td>{{ $requestInsurance->retry_count }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Response -->
            @if ($requestInsurance->hasState(State::COMPLETED))
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
                <!-- Edit(s) -->
            @elseif( ! empty($requestInsurance->edits()->first()))
                <?php $edit = $requestInsurance->edits()->first(); ?>
                {{--            TODO use identityProvider --}}
                @php
                    $canModifyEdit = $edit->applied_at == null;
                @endphp
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="card-title text-center">
                                <h3>
                                    Edit:
                                    @if($edit->applied_at == null)
                                        <div class="badge badge-primary">Pending</div>
                                    @else
                                        <div class="badge badge-success">Applied</div>
                                    @endif
                                </h3>
                                <hr>
                            </div>
                            <div class="card-text">
                                <table class="table-hover w-100 table-vertical table-striped">
                                    <tbody>
                                    <tr>
                                        <td>RequestInsurance Id:</td>
                                        <td>{{ $edit->request_insurance_id }}</td>
                                    </tr>
                                    <tr>
                                        <td>Editor:</td>
                                        <td>{{ $edit->admin_user }}</td>
                                    </tr>
                                    <tr>
                                        <td>Method:</td>
                                        <td>
                                            <select name="method" id="new_method" @disabled( ! $canModifyEdit)>
                                                <option value="GET" @selected(mb_strtoupper($edit->new_method) == "GET")>GET</option>
                                                <option value="POST" @selected(mb_strtoupper($edit->new_method) == "POST")>POST</option>
                                                <option value="PUT" @selected(mb_strtoupper($edit->new_method) == "PUT")>PUT</option>
                                            </select>
                                        </td>

                                    </tr>
                                    <tr>
                                        <td>Url:</td>
                                        <td>
                                            <input name="new_url" class="w-100"
                                                   @disabled( ! $canModifyEdit)
                                                   value="{{ urldecode($edit->new_url) }}"/>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Payload:</td>
                                        <!--<td><x-request-insurance-pretty-print :content="$requestInsurance->getPayloadWithMaskingApplied()"/></td>-->
                                        <!-- TODO pretty print from edit instead -->
                                        <td>
                                            @foreach(json_decode($edit->new_headers) as $key => $value)
                                                <div class="w-100">
                                                    <label>{{$key}}: </label>
                                                    @if(gettype($value) == 'string')
                                                        <input name="new_payload_{{$key}}" type="text" @disabled( ! $canModifyEdit) value='"{{$value}}"'>
                                                    @else
                                                        <input name="new_payload_{{$key}}" type="text" @disabled( ! $canModifyEdit) value="{{$value}}">
                                                    @endif

                                                    @php
                                                        $encryptedFields = json_decode($edit->new_encrypted_fields);
                                                        $fieldIsEncrypted = ! empty($encryptedFields) &&
                                                            property_exists($encryptedFields, 'payload') &&
                                                            in_array($key, $encryptedFields->payload);
                                                    @endphp
                                                    @if($fieldIsEncrypted)
                                                        <span class="badge badge-warning">ENCRYPTED</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Headers:</td>
                                        {{--                                        <td><x-request-insurance-pretty-print :content="$requestInsurance->getHeadersWithMaskingApplied()"/></td>--}}
                                        <td>
                                            @foreach(json_decode($edit->new_headers) as $key => $value)
                                                <div class="w-100">
                                                    <label>{{$key}}: </label>
                                                    @if(gettype($value) == 'string')
                                                        <input name="new_headers_{{$key}}" type="text" @disabled( ! $canModifyEdit) value='"{{$value}}"'>
                                                    @else
                                                        <input name="new_headers_{{$key}}" type="text" @disabled( ! $canModifyEdit) value="{{$value}}">
                                                    @endif

                                                    @php
                                                        $encryptedFields = json_decode($edit->new_encrypted_fields);
                                                        $fieldIsEncrypted = ! empty($encryptedFields) &&
                                                            property_exists($encryptedFields, 'headers') &&
                                                            in_array($key, $encryptedFields->headers);
                                                    @endphp
                                                    @if($fieldIsEncrypted)
                                                        <span class="badge badge-warning">ENCRYPTED</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </td>
                                        <!-- TODO pretty print from edit instead -->
                                    </tr>
                                    <!-- TODO new encrypted fields? -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-text">
                                <hr>
                                <h4>Approvals</h4>
                                <table class="table table-hover border bg-white">
                                    <thead>
                                    <tr>
                                        <th>Approver</th>
                                        <th style="width: 185px">Created at</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($edit->approvals->sortBy('created_at') as $approval)
                                        <tr>
                                            <td>{{ $approval->approver_admin_user }}</td>
                                            <td>{{ $approval->created_at }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                                <hr>
                                {{-- TODO fix conditions for disabled --}}
                                <form method="POST" action="{{ route('request-insurances.approve_edit', $requestInsurance) }}">
                                    <input type="hidden" name="_method" value="post">
                                    <button class="btn btn-primary" type="submit"
                                            @disabled($edit->applied_at != null && $edit->admin_user == 'jabj')>Approve</button>
                                </form>

                                <form method="POST" action="{{ route('request-insurances.apply_edit', $requestInsurance) }}">
                                    <input type="hidden" name="_method" value="post">
                                    <button class="btn btn-primary" type="submit"
                                            @disabled($edit->approvals()->count() < $edit->required_number_of_approvals)>Apply</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

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
