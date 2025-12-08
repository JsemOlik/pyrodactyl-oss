@extends('layouts.admin')

@section('title')
  Invoice Settings
@endsection

@section('content-header')
  <h1>Invoice Settings<small>Configure invoice generation and formatting settings.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  @include('admin.billing.partials.invoices')
@endsection

@section('footer-scripts')
  @parent
  <script>
    function saveInvoiceSettings() {
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify({
          'billing:invoice_prefix': $('input[name="billing:invoice_prefix"]').val(),
          'billing:invoice_starting_number': $('input[name="billing:invoice_starting_number"]').val(),
          'billing:invoice_terms': $('textarea[name="billing:invoice_terms"]').val(),
          'billing:invoice_footer': $('textarea[name="billing:invoice_footer"]').val(),
          'billing:enable_tax': $('input[name="billing:enable_tax"]').is(':checked') ? '1' : '0',
          'billing:tax_rate': $('input[name="billing:tax_rate"]').val(),
          'billing:tax_id': $('input[name="billing:tax_id"]').val(),
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
        text: 'An error occurred while attempting to ' + verb + ' invoice settings: ' + errorText,
        type: 'error'
      });
    }

    $(document).ready(function () {
      $('#billingSettingsForm').on('submit', function (e) {
        e.preventDefault();
      });
      $('#saveButton').on('click', function () {
        saveInvoiceSettings().done(function () {
          swal({
            title: 'Success',
            text: 'Invoice settings have been updated successfully and the queue worker was restarted to apply these changes.',
            type: 'success'
          });
        });
      });
    });
  </script>
@endsection
