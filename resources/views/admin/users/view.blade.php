@extends('layouts.admin')

@section('title')
    Manage User: {{ $user->username }}
@endsection

@section('content-header')
    <h1>{{ $user->name_first }} {{ $user->name_last}}<small>{{ $user->username }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.users') }}">Users</a></li>
        <li class="active">{{ $user->username }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form action="{{ route('admin.users.view', $user->id) }}" method="post">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Identity</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="email" class="control-label">Email</label>
                        <div>
                            <input type="email" name="email" value="{{ $user->email }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">Username</label>
                        <div>
                            <input type="text" name="username" value="{{ $user->username }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">Client First Name</label>
                        <div>
                            <input type="text" name="name_first" value="{{ $user->name_first }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">Client Last Name</label>
                        <div>
                            <input type="text" name="name_last" value="{{ $user->name_last }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Default Language</label>
                        <div>
                            <select name="language" class="form-control">
                                @foreach($languages as $key => $value)
                                    <option value="{{ $key }}" @if($user->language === $key) selected @endif>{{ $value }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted"><small>The default language to use when rendering the Panel for this user.</small></p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <input type="submit" value="Update User" class="btn btn-primary btn-sm">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Password</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-success" style="display:none;margin-bottom:10px;" id="gen_pass"></div>
                    <div class="form-group no-margin-bottom">
                        <label for="password" class="control-label">Password <span class="field-optional"></span></label>
                        <div>
                            <input type="password" id="password" name="password" class="form-control form-autocomplete-stop">
                            <p class="text-muted small">Leave blank to keep this user's password the same. User will not receive any notification if password is changed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Permissions</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="root_admin" class="control-label">Administrator</label>
                        <div>
                            <select name="root_admin" class="form-control">
                                <option value="0">@lang('strings.no')</option>
                                <option value="1" {{ $user->root_admin ? 'selected="selected"' : '' }}>@lang('strings.yes')</option>
                            </select>
                            <p class="text-muted"><small>Setting this to 'Yes' gives a user full administrative access.</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">User Servers</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                @if($user->servers->count() > 0)
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Server Name</th>
                                <th>UUID</th>
                                <th>Node</th>
                                <th>Connection</th>
                                <th>Subscription</th>
                                <th>Subscription Status</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                            @foreach ($user->servers as $server)
                                <tr data-server="{{ $server->uuidShort }}">
                                    <td><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></td>
                                    <td><code title="{{ $server->uuid }}">{{ $server->uuid }}</code></td>
                                    <td>
                                        @if($server->node)
                                            <a href="{{ route('admin.nodes.view', $server->node->id) }}">{{ $server->node->name }}</a>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($server->allocation)
                                            <code>{{ $server->allocation->alias }}:{{ $server->allocation->port }}</code>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($server->subscription)
                                            @php
                                                $priceInfo = $server->subscription->getMonthlyPriceInfo();
                                            @endphp
                                            @if($priceInfo['monthly_price'] !== null)
                                                <strong>{{ number_format($priceInfo['monthly_price'], 2) }} {{ $priceInfo['currency'] }}</strong> / month<br>
                                                <small class="text-muted">{{ $priceInfo['billing_cycle'] }}</small>
                                            @else
                                                <span class="text-muted">Custom Plan</span>
                                            @endif
                                        @else
                                            <span class="text-muted">No subscription</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($server->subscription)
                                            @php
                                                $statusInfo = $server->subscription->getSubscriptionStatusInfo();
                                            @endphp
                                            @if($statusInfo['is_pending_cancellation'])
                                                <span class="label label-warning">Pending Cancellation</span>
                                            @elseif($statusInfo['status'] === 'active')
                                                <span class="label label-success">Active</span>
                                            @elseif($statusInfo['status'] === 'past_due')
                                                <span class="label label-danger">Past Due</span>
                                            @elseif($statusInfo['status'] === 'canceled')
                                                <span class="label bg-maroon">Canceled</span>
                                            @else
                                                <span class="label label-default">{{ ucfirst($statusInfo['status']) }}</span>
                                            @endif
                                            @if($statusInfo['next_billing_date'])
                                                <br><small class="text-muted">Next: {{ $statusInfo['next_billing_date']->format('M j, Y') }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($server->isSuspended())
                                            <span class="label bg-maroon">Suspended</span>
                                        @elseif(! $server->isInstalled())
                                            <span class="label label-warning">Installing</span>
                                        @else
                                            <span class="label label-success">Active</span>
                                        @endif
                                        
                                        @if($server->exclude_from_resource_calculation)
                                            <br><small><span class="label label-info" title="Excluded from resource calculations">Excluded</span></small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a class="btn btn-xs btn-default" href="/server/{{ $server->uuidShort }}" title="Manage Server"><i class="fa fa-wrench"></i></a>
                                        @if($server->subscription && $server->subscription->stripe_id)
                                            <a class="btn btn-xs" href="https://dashboard.stripe.com/subscriptions/{{ $server->subscription->stripe_id }}" target="_blank" title="Open in Stripe" style="background-color: #635bff; border-color: #635bff; color: white; margin-left: 5px;">
                                                <i class="fa fa-credit-card"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="box-body">
                        <p class="text-muted">This user has no servers.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xs-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Delete User</h3>
            </div>
            <div class="box-body">
                <p class="no-margin">There must be no servers associated with this account in order for it to be deleted.</p>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.users.view', $user->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <input id="delete" type="submit" class="btn btn-sm btn-danger pull-right" {{ $user->servers->count() < 1 ?: 'disabled' }} value="Delete User" />
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
