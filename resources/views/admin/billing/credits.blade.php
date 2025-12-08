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
      background-color: #f4f4f4;
    }
    #usersTable tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    #usersTable tbody tr:hover {
      background-color: #e8e8e8;
    }
    #userDetailsContent table tbody tr:nth-child(odd) {
      background-color: #f4f4f4;
    }
    #userDetailsContent table tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    #userDetailsContent table tbody tr:hover {
      background-color: #e8e8e8;
    }
  </style>
@endsection

@section('footer-scripts')
  @parent
  @include('admin.billing.partials.credits-scripts')
@endsection
