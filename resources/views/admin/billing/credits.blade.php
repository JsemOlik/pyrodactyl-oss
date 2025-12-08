@extends('layouts.admin')

@section('title')
  Credits Management
@endsection

@section('content-header')
  <h1>Credits Management<small>Manage user credits and view transaction history.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  @include('admin.billing.partials.credits')
  
@endsection

@section('footer-scripts')
  @parent
  @include('admin.billing.partials.credits-scripts')
@endsection
