@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'billing'])

@section('title')
  Billing Settings
@endsection

@section('content-header')
  <h1>Billing Settings<small>Configure billing, payments, and credits.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Settings</li>
  </ol>
@endsection

@section('content')
  @yield('settings::nav')
  
  <div class="row">
    <div class="col-xs-12">
      <div class="nav-tabs-custom nav-tabs-floating">
        <ul class="nav nav-tabs">
          <li @if($activeTab === 'settings')class="active"@endif><a href="{{ route('admin.settings.billing') }}?tab=settings">Settings</a></li>
          <li @if($activeTab === 'server-creation')class="active"@endif><a href="{{ route('admin.settings.billing') }}?tab=server-creation">Server Creation</a></li>
          <li @if($activeTab === 'payment-method')class="active"@endif><a href="{{ route('admin.settings.billing') }}?tab=payment-method">Payment Method</a></li>
          <li @if($activeTab === 'credits')class="active"@endif><a href="{{ route('admin.settings.billing') }}?tab=credits">Credits</a></li>
        </ul>
        <div class="tab-content">
          @if($activeTab === 'settings')
            @include('admin.settings.billing.partials.settings')
          @elseif($activeTab === 'server-creation')
            @include('admin.settings.billing.partials.server-creation')
          @elseif($activeTab === 'payment-method')
            @include('admin.settings.billing.partials.payment-method')
          @elseif($activeTab === 'credits')
            @include('admin.settings.billing.partials.credits')
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

@section('footer-scripts')
  @parent
  @if($activeTab === 'settings' || $activeTab === 'server-creation' || $activeTab === 'payment-method')
    <script>
      function saveSettings() {
        return $.ajax({
          method: 'PATCH',
          url: '/admin/settings/billing',
          contentType: 'application/json',
          data: JSON.stringify({
            'cashier:key': $('input[name="cashier:key"]').val(),
            'cashier:secret': $('input[name="cashier:secret"]').val(),
            'cashier:webhook:secret': $('input[name="cashier:webhook:secret"]').val(),
            'cashier:currency': $('input[name="cashier:currency"]').val(),
            'cashier:currency_locale': $('input[name="cashier:currency_locale"]').val(),
            'billing:enable_server_creation': $('input[name="billing:enable_server_creation"]').is(':checked') ? '1' : '0',
            'billing:server_creation_disabled_message': $('textarea[name="billing:server_creation_disabled_message"]').val(),
            'billing:status_page_url': $('input[name="billing:status_page_url"]').val(),
            'billing:show_status_page_button': $('input[name="billing:show_status_page_button"]').is(':checked') ? '1' : '0',
            'billing:show_logo_on_disabled_page': $('input[name="billing:show_logo_on_disabled_page"]').is(':checked') ? '1' : '0',
            'billing:enable_credits': $('input[name="billing:enable_credits"]').is(':checked') ? '1' : '0'
          }),
          headers: { 'X-CSRF-Token': $('input[name="_token"]').val() }
        }).fail(function (jqXHR) {
          showErrorDialog(jqXHR, 'save');
        });
      }

      function showErrorDialog(jqXHR, verb) {
        console.error(jqXHR);
        var errorText = '';
        if (!jqXHR.responseJSON) {
          errorText = jqXHR.responseText;
        } else if (jqXHR.responseJSON.error) {
          errorText = jqXHR.responseJSON.error;
        } else if (jqXHR.responseJSON.errors) {
          $.each(jqXHR.responseJSON.errors, function (i, v) {
            if (v.detail) {
              errorText += v.detail + ' ';
            }
          });
        }

        swal({
          title: 'Whoops!',
          text: 'An error occurred while attempting to ' + verb + ' billing settings: ' + errorText,
          type: 'error'
        });
      }

      $(document).ready(function () {
        $('#billingSettingsForm').on('submit', function (e) {
          e.preventDefault();
        });
        $('#saveButton').on('click', function () {
          saveSettings().done(function () {
            swal({
              title: 'Success',
              text: 'Billing settings have been updated successfully and the queue worker was restarted to apply these changes.',
              type: 'success'
            });
          });
        });
      });
    </script>
  @endif
  @if($activeTab === 'credits')
    @include('admin.settings.billing.partials.credits-scripts')
  @endif
@endsection
