@extends('layouts.admin')

@section('title')
  Billing Settings
@endsection

@section('content-header')
  <h1>Billing Settings<small>Configure Stripe API keys and billing settings.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  @include('admin.billing.partials.settings')
@endsection

@section('footer-scripts')
  @parent
  <script>
    function saveSettings() {
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify({
          'cashier:key': $('input[name="cashier:key"]').val(),
          'cashier:secret': $('input[name="cashier:secret"]').val(),
          'cashier:webhook:secret': $('input[name="cashier:webhook:secret"]').val(),
          'cashier:currency': $('input[name="cashier:currency"]').val(),
          'cashier:currency_locale': $('input[name="cashier:currency_locale"]').val(),
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
@endsection
