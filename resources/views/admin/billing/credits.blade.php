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
  
  <style>
    #usersTable tbody tr:nth-child(odd) {
      background-color: #f4f4f4 !important;
    }
    #usersTable tbody tr:nth-child(even) {
      background-color: #f9f9f9 !important;
    }
    #usersTable tbody tr:hover {
      background-color: #e8e8e8 !important;
    }
    #userDetailsContent table tbody tr:nth-child(odd) {
      background-color: #f4f4f4 !important;
    }
    #userDetailsContent table tbody tr:nth-child(even) {
      background-color: #f9f9f9 !important;
    }
    #userDetailsContent table tbody tr:hover {
      background-color: #e8e8e8 !important;
    }
  </style>
@endsection

@section('footer-scripts')
  @parent
  @include('admin.billing.partials.credits-scripts')
@endsection
