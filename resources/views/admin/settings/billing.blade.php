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
  <form id="billingSettingsForm">
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Stripe Configuration</h3>
        </div>
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
      </div>
    </div>
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Server Creation Control</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox">
                <label>
                  @php
                    $enableServerCreation = old('billing:enable_server_creation', config('billing.enable_server_creation', true));
                    $enableServerCreationValue = is_bool($enableServerCreation) ? $enableServerCreation : ($enableServerCreation === 'true' || $enableServerCreation === true || $enableServerCreation === '1');
                  @endphp
                  <input type="checkbox" name="billing:enable_server_creation" value="1"
                    {{ $enableServerCreationValue ? 'checked' : '' }} />
                  Enable server creation through billing process
                </label>
              </div>
              <p class="text-muted small">When disabled, customers will be unable to create new servers through the billing process. Admins can still create servers manually through the admin dashboard.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Disabled Message <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:server_creation_disabled_message" rows="4"
                  placeholder="We're currently scaling our infrastructure to provide better service. Server creation is temporarily disabled. Please check back soon!">{{ old('billing:server_creation_disabled_message', config('billing.server_creation_disabled_message', '')) }}</textarea>
                <p class="text-muted small">This message will be displayed to users when server creation is disabled.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Status Page URL <span class="field-optional"></span></label>
              <div>
                <input type="url" class="form-control" name="billing:status_page_url"
                  value="{{ old('billing:status_page_url', config('billing.status_page_url', '')) }}" placeholder="https://status.example.com" />
                <p class="text-muted small">The URL to your status page. Users will see a button linking to this page when server creation is disabled.</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <div class="checkbox" style="margin-top: 25px;">
                <label>
                  @php
                    $showStatusButton = old('billing:show_status_page_button', config('billing.show_status_page_button', false));
                    $showStatusButtonValue = is_bool($showStatusButton) ? $showStatusButton : ($showStatusButton === 'true' || $showStatusButton === true || $showStatusButton === '1');
                  @endphp
                  <input type="checkbox" name="billing:show_status_page_button" value="1"
                    {{ $showStatusButtonValue ? 'checked' : '' }} />
                  Show status page button
                </label>
              </div>
              <p class="text-muted small">Display a "View our status page" button on the server creation disabled page.</p>
            </div>
          </div>
        </div>
        <div class="box-footer">
          {{ csrf_field() }}
          <div class="pull-right">
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save All Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
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
          'cashier:currency_locale': $('input[name="cashier:currency_locale"]').val(),
          'billing:enable_server_creation': $('input[name="billing:enable_server_creation"]').is(':checked') ? '1' : '0',
          'billing:server_creation_disabled_message': $('textarea[name="billing:server_creation_disabled_message"]').val(),
          'billing:status_page_url': $('input[name="billing:status_page_url"]').val(),
          'billing:show_status_page_button': $('input[name="billing:show_status_page_button"]').is(':checked') ? '1' : '0'
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

