@extends('layouts.admin')

@section('title')
  Server Creation Settings
@endsection

@section('content-header')
  <h1>Server Creation Settings<small>Configure server creation control settings.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  @include('admin.billing.partials.server-creation')
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
          'billing:enable_server_creation': $('input[name="billing:enable_server_creation"]').is(':checked') ? '1' : '0',
          'billing:server_creation_disabled_message': $('textarea[name="billing:server_creation_disabled_message"]').val(),
          'billing:status_page_url': $('input[name="billing:status_page_url"]').val(),
          'billing:show_status_page_button': $('input[name="billing:show_status_page_button"]').is(':checked') ? '1' : '0',
          'billing:show_logo_on_disabled_page': $('input[name="billing:show_logo_on_disabled_page"]').is(':checked') ? '1' : '0',
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
            text: 'Server creation settings have been updated successfully and the queue worker was restarted to apply these changes.',
            type: 'success'
          });
        });
      });
    });
  </script>
@endsection
