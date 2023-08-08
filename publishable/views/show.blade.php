<?php
use \Cego\RequestInsurance\Enums\State;
use Jfcherng\Diff\DiffHelper;

?>
@extends("request-insurance::layouts.master")

@section("content")

    @php
        function getEditErrorMessage($editId, $field) {
            $requestInsuranceEdit = Session::get('requestInsuranceEdit');
            $requestErrors = Session::get('requestErrors');
            if ( empty($requestInsuranceEdit)){
                return "";
            }
            if ($requestInsuranceEdit->id != $editId){
                return "";
            }
            if ( empty($requestErrors[$field])){
                return "";
            }
            return $requestErrors[$field];
        }
    @endphp
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
                                    <form method="GET" action="{{ route('request-insurances.edit-history', $requestInsurance) }}">
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
                                    <td>Priority:</td>
                                    <td>{{ $requestInsurance->priority }}</td>
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
                                    <td>Timings: </td>
                                    <td style="max-width:1px"><x-request-insurance-pretty-print :content="$requestInsurance->timings"/></td>
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
            <style>
                @-o-keyframes fadeIt {
                    0%   { background-color: #FFFFFF; }
                    50%  { background-color: #AD301B; }
                    100% { background-color: #FFFFFF; }
                }
                @keyframes fadeIt {
                    0%   { background-color: #FFFFFF; }
                    50%  { background-color: #AD301B; }
                    100% { background-color: #FFFFFF; }
                }

                .backgroundAnimated{
                    background-image:none !important;
                    -o-animation: fadeIt .5s ease-in-out;
                    animation: fadeIt .5s ease-in-out;
                }
                <?= DiffHelper::getStyleSheet(); ?>
            </style>
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
                                $canApplyEdit = $edit->applied_at == null && $edit->approvals->count() >= $edit->required_number_of_approvals && $canModifyEdit;
                            @endphp

                            <div class="row">
                                <div class="col-6 mt-2">
                                    <div class="card {{$edit->created_at->diffInSeconds(\Carbon\Carbon::now()) < 5 ? 'backgroundAnimated' : ''}}">
                                        <div class="card-body">
                                            <div class="card-title text-center">
                                                <h3>
                                                    Edit
                                                    @if($edit->approvals()->count() < $edit->required_number_of_approvals)
                                                        <div class="badge badge-primary">Pending</div>
                                                    @else
                                                        <div class="badge badge-success">Approved</div>
                                                    @endif
                                                </h3>
                                                <hr>
                                            </div>
                                            <div class="card-text">
                                                <form method="POST" action="{{ route('request-insurance-edits.update', $edit) }}">
                                                    <table class="table-hover w-100 table-vertical table-striped">
                                                        <tbody>
                                                        <tr>
                                                            <td>Editor:</td>
                                                            <td>{{ $edit->admin_user }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td>RequestInsurance Id:</td>
                                                            <td>{{ $edit->request_insurance_id }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Priority:</td>
                                                            <td><input name="new_priority" class="w-100" type="number" min="0" max="9999"
                                                                       onchange="(() => {this.value=this.value < 0 ? 0 : this.value > 9999 ? 9999 : this.value;})()"
                                                                       onkeyup="(() => {this.value=this.value < 0 ? 0 : this.value > 9999 ? 9999 : this.value;})()"
                                                                       @disabled( ! $canModifyEdit)
                                                                       value="{{ $edit->new_priority }}"/></td>
                                                        </tr>
                                                        <tr>
                                                            <td>Method:</td>
                                                            <td>
                                                                <select name="new_method" @disabled( ! $canModifyEdit)>
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
                                                                @if( ! empty($errorMsg = getEditErrorMessage($edit->id, 'header')))
                                                                    <span class="text-danger">{{$errorMsg}}</span>
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
                                                            <input type="hidden" name="_method" value="delete">
                                                            <button class="btn btn-danger" type="submit">Delete</button>
                                                        @endif
                                                    </div>
                                                </form>
                                            </div>
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
                                                                    <input type="hidden" name="_method" value="delete">
                                                                    <button class="btn btn-secondary" type="submit">Remove approval</button>
                                                                </form>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($canModifyEdit)
                                                            <form class="ml-2" method="POST" action="{{ route('request-insurances-edits.apply', $edit) }}">
                                                                <input type="hidden" name="_method" value="post">
                                                                <button class="btn btn-primary" type="submit"
                                                                        @disabled( ! $canApplyEdit)>Apply</button>
                                                                @if( ! empty($errorMsg = getEditErrorMessage($edit->id, 'approval')))
                                                                    <span class="text-danger">{{$errorMsg}}</span>
                                                                @endif
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mt-2">
                                    <div class="card">'
                                        <div class="card-body">
                                            <div class="card-title text-center">
                                                <h3>
                                                    Edit differences
                                                </h3>
                                                <hr>
                                            </div>
                                            <div class="card-text">
                                                <table class="table-hover w-100 table-vertical table-striped">
                                                    <tbody>
                                                    <tr>
                                                        <td>Editor: </td>
                                                        <td>{{ $edit->admin_user }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td>RequestInsurance Id: </td>
                                                        <td>{{ $edit->request_insurance_id }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Priority:</td>
                                                        <td>
                                                            <x-request-insurance-pretty-print-difference :oldValues="strval($edit->old_priority)" :newValues="strval($edit->new_priority)"/>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Method:</td>
                                                        <td>
                                                            <x-request-insurance-pretty-print-difference :oldValues="strtoupper($edit->old_method)" :newValues="strtoupper($edit->new_method)" />
                                                        </td>
                                                    </tr>
                                                    <tr class="w-100">
                                                        <td>Url:</td>
                                                        <td>
                                                            <x-request-insurance-pretty-print-difference :oldValues="$edit->old_url" :newValues="$edit->new_url"/>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Payload:</td>
                                                        <td style="max-width:1px"><!-- Makes the pretty printed code wrap lines -->
                                                            <x-request-insurance-pretty-print-difference :oldValues="$edit->old_payload" :newValues="$edit->new_payload" />
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Headers:</td>
                                                        <td style="max-width:1px"><!-- Makes the pretty printed code wrap lines -->
                                                            <x-request-insurance-pretty-print-difference :oldValues="$edit->old_headers" :newValues="$edit->new_headers" />
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
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
                                    <th style="width: 100px">Total time (ms) </th>
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
                                        <td>{{$log->getTotalTime()}}</td>
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
