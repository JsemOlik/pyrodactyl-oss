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
      var formData = {
        'cashier:key': $('input[name="cashier:key"]').val(),
        'cashier:secret': $('input[name="cashier:secret"]').val(),
        'cashier:webhook:secret': $('input[name="cashier:webhook:secret"]').val(),
        'cashier:currency': $('input[name="cashier:currency"]').val(),
        'cashier:currency_locale': $('input[name="cashier:currency_locale"]').val(),
        'billing:enable_tax': $('input[name="billing:enable_tax"]').is(':checked') ? '1' : '0',
        'billing:tax_rate': $('input[name="billing:tax_rate"]').val(),
        'billing:tax_id': $('input[name="billing:tax_id"]').val(),
        'billing:invoice_prefix': $('input[name="billing:invoice_prefix"]').val(),
        'billing:invoice_starting_number': $('input[name="billing:invoice_starting_number"]').val(),
        'billing:invoice_terms': $('textarea[name="billing:invoice_terms"]').val(),
        'billing:invoice_footer': $('textarea[name="billing:invoice_footer"]').val(),
        'billing:credit_conversion_rate': $('input[name="billing:credit_conversion_rate"]').val(),
        'billing:min_credit_purchase': $('input[name="billing:min_credit_purchase"]').val(),
        'billing:max_credit_balance': $('input[name="billing:max_credit_balance"]').val(),
        'billing:credit_expiration_days': $('input[name="billing:credit_expiration_days"]').val(),
        'billing:grace_period_days': $('input[name="billing:grace_period_days"]').val(),
        'billing:default_billing_cycle': $('select[name="billing:default_billing_cycle"]').val(),
        'billing:auto_renewal': $('input[name="billing:auto_renewal"]').is(':checked') ? '1' : '0',
        'billing:enable_proration': $('input[name="billing:enable_proration"]').is(':checked') ? '1' : '0',
        'billing:cancellation_policy': $('textarea[name="billing:cancellation_policy"]').val(),
        'billing:payment_fee_percentage': $('input[name="billing:payment_fee_percentage"]').val(),
        'billing:payment_fee_fixed': $('input[name="billing:payment_fee_fixed"]').val(),
        'billing:enable_late_fees': $('input[name="billing:enable_late_fees"]').is(':checked') ? '1' : '0',
        'billing:late_fee_amount': $('input[name="billing:late_fee_amount"]').val(),
        'billing:late_fee_days': $('input[name="billing:late_fee_days"]').val(),
        'billing:email_payment_notifications': $('input[name="billing:email_payment_notifications"]').is(':checked') ? '1' : '0',
        'billing:email_subscription_notifications': $('input[name="billing:email_subscription_notifications"]').is(':checked') ? '1' : '0',
        'billing:admin_notifications': $('input[name="billing:admin_notifications"]').is(':checked') ? '1' : '0',
        'billing:admin_notification_email': $('input[name="billing:admin_notification_email"]').val(),
      };
      
      return $.ajax({
        method: 'PATCH',
        url: '/admin/billing',
        contentType: 'application/json',
        data: JSON.stringify(formData),
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
