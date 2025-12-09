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
  <script>
    function saveCreditsSettings() {
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify({
          'billing:enable_credits': $('input[name="billing:enable_credits"]').is(':checked') ? '1' : '0',
          'billing:credit_conversion_rate': $('input[name="billing:credit_conversion_rate"]').val(),
          'billing:min_credit_purchase': $('input[name="billing:min_credit_purchase"]').val(),
          'billing:max_credit_balance': $('input[name="billing:max_credit_balance"]').val(),
          'billing:credit_expiration_days': $('input[name="billing:credit_expiration_days"]').val(),
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
      $('#saveCreditsSettingsButton').on('click', function () {
        saveCreditsSettings().done(function () {
          swal({
            title: 'Success',
            text: 'Credits settings have been updated successfully and the queue worker was restarted to apply these changes.',
            type: 'success'
          });
        });
      });
    });
  </script>
  @include('admin.billing.partials.credits-scripts')
@endsection
