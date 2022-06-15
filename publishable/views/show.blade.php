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
                                <div class="mt-2">
                                    <form method="POST" action="{{ route('request-insurance-edits.create', $requestInsurance) }}">
                                        <input type="hidden" name="_method" value="post">
                                        <button class="btn btn-primary" type="submit">Edit</button>
                                    </form>
                                </div>
                            @endif
                            @php
                                $appliedEdits = $requestInsurance->edits()->where('applied_at', '<>', null);
                            @endphp
                            @if($appliedEdits->count() > 0)
                                <div class="mt-2">
                                    <form method="GET" action="{{ route('request-insurances.edit_history', $requestInsurance) }}">
                                        <input type="hidden" name="_method" value="get">
                                        <button class="btn btn-warning" type="submit">History</button>
                                    </form>
                                </div>
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
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->getPayloadWithMaskingApplied()"/></td>
                                </tr>
                                <tr>
                                    <td>Headers:</td>
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->getHeadersWithMaskingApplied()"/></td>
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
                                    <td style="max-width:1px"><h4><x-request-insurance-http-code httpCode="{{ $requestInsurance->response_code }}"/></h4></td>
                                </tr>
                                <tr>
                                    <td>Response headers:</td>
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->response_headers"/></td>
                                </tr>
                                <tr>
                                    <td>Response body</td>
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->response_body"/></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Edit(s) -->
            @php
                $pendingEdits = $requestInsurance->edits()->where('applied_at', null)->orderBy('updated_at', 'DESC');
            @endphp
            @if($pendingEdits->count() > 0)
                <div class="col-12 mt-2">
                    <div class="card">
                        <div class="card-body">
                            <div class="card-title text-center">
                                <h3>Edits
                                    <span class="custom-control custom-switch">
                                    <input type="checkbox" checked class="custom-control-input" id="toggleCollapseEdits" data-toggle="collapse" data-target="#collapseEdits">
                                    <label class="custom-control-label" for="toggleCollapseEdits"></label>
                                </span>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="collapse show" id="collapseEdits">
                        @foreach($pendingEdits->get() as $edit)
                            @php
                                $canModifyEdit = $edit->applied_at == null && $edit->admin_user == $user;
                                $canApproveEdit = $edit->applied_at == null && ! $canModifyEdit;
                                $canApplyEdit = $edit->applied_at == null && $edit->approvals->count() >= $edit->required_number_of_approvals;
                            @endphp
                            <div class="col-6 mt-2">
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
                                            <form method="POST" action="{{ route('request-insurance-edits.update', $edit) }}">
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
                                                                <option value="PATCH" @selected(mb_strtoupper($edit->new_method) == "PATCH")>PATCH</option>
                                                                <option value="DELETE" @selected(mb_strtoupper($edit->new_method) == "DELETE")>DELETE</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr class="w-100">
                                                        <td>Url:</td>
                                                        <td><input name="new_url" class="w-100"
                                                                   @disabled( ! $canModifyEdit)
                                                                   value="{{ urldecode($edit->new_url) }}"/></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Payload:</td>
                                                        <td style="max-width:1px"><!-- Makes the pretty printed code wrap lines -->
                                                            @if($canModifyEdit)
                                                                <x-request-insurance-pretty-print-text-area :name='"new_payload"' :content="$edit->new_payload" :disabled=" ! $canModifyEdit"/>
                                                            @else
                                                                <x-request-insurance-pretty-print :content="$edit->new_payload"/>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Headers:</td>
                                                        <td style="max-width:1px"><!-- Makes the pretty printed code wrap lines -->
                                                            @if($canModifyEdit)
                                                                <x-request-insurance-pretty-print-text-area :name='"new_headers"' :content="$edit->new_headers" :disabled=" ! $canModifyEdit"/>
                                                            @else
                                                                <x-request-insurance-pretty-print :content="$edit->new_headers"/>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Encrypted fields:</td>
                                                        <td style="max-width:1px"><!-- Makes the pretty printed code wrap lines -->
                                                            @if($canModifyEdit)
                                                                <x-request-insurance-pretty-print-text-area :name='"new_encrypted_fields"' :content="$edit->new_encrypted_fields" :disabled=" ! $canModifyEdit"/>
                                                            @else
                                                                <x-request-insurance-pretty-print :content="$edit->new_encrypted_fields"/>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                <div class="m-2">
                                                    @if ( $canModifyEdit )
                                                        <input type="hidden" name="_method" value="post">
                                                        <button class="btn btn-primary" type="submit">Save</button>
                                                    @endif
                                                </div>
                                            </form>
                                            <form method="POST" action="{{ route('request-insurance-edits.destroy', $edit) }}">
                                                <div class="m-2">
                                                    @if ( $canModifyEdit )
                                                        <input type="hidden" name="_method" value="post">
                                                        <button class="btn btn-danger" type="submit">Delete</button>
                                                    @endif
                                                </div>
                                            </form>
                                        </div>

                                        <div class="card-text">
                                            <hr>
                                            <h4>Approvals <x-request-insurance-edit-approvals-status :requestInsuranceEdit="$edit" /></h4>
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
                                            <table>
                                                <tr>
                                                    <td>
                                                        @if($canApproveEdit)
                                                            @php
                                                                $approvalsByUser = $edit->approvals()->where('approver_admin_user', $user);
                                                            @endphp
                                                            @if($approvalsByUser->count() == 0)
                                                                <form class="ml-2" method="POST" action="{{ route('request-insurance-edit-approvals.create', $edit) }}">
                                                                    <input type="hidden" name="_method" value="post">
                                                                    <button class="btn btn-primary" type="submit"
                                                                            @disabled( ! $canApproveEdit)>Approve</button>
                                                                </form>
                                                            @else
                                                                <form class="ml-2" method="POST" action="{{ route('request-insurance-edit-approvals.destroy', $approvalsByUser->first()) }}">
                                                                    <input type="hidden" name="_method" value="post">
                                                                    <button class="btn btn-secondary" type="submit">Remove approval</button>
                                                                </form>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <form class="ml-2" method="POST" action="{{ route('request-insurances.apply_edit', $edit) }}">
                                                            <input type="hidden" name="_method" value="post">
                                                            <button class="btn btn-primary" type="submit"
                                                                    @disabled( ! $canApplyEdit)>Apply</button>
                                                            @if($edit->applied_at != null)
                                                                <span class="ml-2">Applied at {{$edit->applied_at}}</span>
                                                            @endif
                                                        </form>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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
