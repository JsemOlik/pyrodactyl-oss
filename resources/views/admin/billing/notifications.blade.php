@extends('layouts.admin')

@section('title')
  Notification Settings
@endsection

@section('content-header')
  <h1>Notification Settings<small>Configure email notification preferences for billing events.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  @include('admin.billing.partials.notifications')
@endsection

@section('footer-scripts')
  @parent
  <script>
    function saveNotificationSettings() {
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify({
          'billing:email_payment_notifications': $('input[name="billing:email_payment_notifications"]').is(':checked') ? '1' : '0',
          'billing:email_subscription_notifications': $('input[name="billing:email_subscription_notifications"]').is(':checked') ? '1' : '0',
          'billing:admin_notifications': $('input[name="billing:admin_notifications"]').is(':checked') ? '1' : '0',
          'billing:admin_notification_email': $('input[name="billing:admin_notification_email"]').val(),
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
        text: 'An error occurred while attempting to ' + verb + ' notification settings: ' + errorText,
        type: 'error'
      });
    }

    $(document).ready(function () {
      $('#billingSettingsForm').on('submit', function (e) {
        e.preventDefault();
      });
      $('#saveButton').on('click', function () {
        saveNotificationSettings().done(function () {
          swal({
            title: 'Success',
            text: 'Notification settings have been updated successfully and the queue worker was restarted to apply these changes.',
            type: 'success'
          });
        });
      });
    });
  </script>
@endsection
