@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'billing'])

@section('title')
  Billing Settings
@endsection

@section('content-header')
  <h1>Billing Settings<small>Configure Stripe API keys and billing settings.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Settings</li>
  </ol>
@endsection

@section('content')
  @yield('settings::nav')
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Stripe Configuration</h3>
        </div>
        <form>
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-6">
                <label class="control-label">Stripe Publishable Key <span class="field-optional"></span></label>
                <div>
                  <input type="text" class="form-control" name="cashier:key"
                    value="{{ old('cashier:key', config('cashier.key')) }}" placeholder="pk_test_..." />
                  <p class="text-muted small">Your Stripe publishable key. This is safe to expose to the client-side.</p>
                </div>
              </div>
              <div class="form-group col-md-6">
                <label class="control-label">Stripe Secret Key <span class="field-optional"></span></label>
                <div>
                  <input type="password" class="form-control" name="cashier:secret"
                    value="{{ old('cashier:secret', config('cashier.secret') ? '***' : '') }}" placeholder="sk_test_..." />
                  <p class="text-muted small">Your Stripe secret key. This will be encrypted and stored securely. Leave blank to keep existing value.</p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-6">
                <label class="control-label">Webhook Secret <span class="field-optional"></span></label>
                <div>
                  <input type="password" class="form-control" name="cashier:webhook:secret"
                    value="{{ old('cashier:webhook:secret', config('cashier.webhook.secret') ? '***' : '') }}" placeholder="whsec_..." />
                  <p class="text-muted small">The webhook signing secret from your Stripe dashboard. Leave blank to keep existing value.</p>
                </div>
              </div>
              <div class="form-group col-md-3">
                <label class="control-label">Currency <span class="field-optional"></span></label>
                <div>
                  <input type="text" class="form-control" name="cashier:currency"
                    value="{{ old('cashier:currency', config('cashier.currency', 'usd')) }}" placeholder="usd" maxlength="3" />
                  <p class="text-muted small">Three-letter ISO currency code (e.g., usd, eur, gbp).</p>
                </div>
              </div>
              <div class="form-group col-md-3">
                <label class="control-label">Currency Locale <span class="field-optional"></span></label>
                <div>
                  <input type="text" class="form-control" name="cashier:currency_locale"
                    value="{{ old('cashier:currency_locale', config('cashier.currency_locale', 'en')) }}" placeholder="en" maxlength="10" />
                  <p class="text-muted small">Locale for currency formatting (e.g., en, en_US).</p>
                </div>
              </div>
            </div>
          </div>
          <div class="box-footer">
            {{ csrf_field() }}
            <div class="pull-right">
              <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@section('footer-scripts')
  @parent

  <script>
    function saveSettings() {
      return $.ajax({
        method: 'PATCH',
        url: '/admin/settings/billing',
        contentType: 'application/json',
        data: JSON.stringify({
          'cashier:key': $('input[name="cashier:key"]').val(),
          'cashier:secret': $('input[name="cashier:secret"]').val() === '***' ? '' : $('input[name="cashier:secret"]').val(),
          'cashier:webhook:secret': $('input[name="cashier:webhook:secret"]').val() === '***' ? '' : $('input[name="cashier:webhook:secret"]').val(),
          'cashier:currency': $('input[name="cashier:currency"]').val(),
          'cashier:currency_locale': $('input[name="cashier:currency_locale"]').val()
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

