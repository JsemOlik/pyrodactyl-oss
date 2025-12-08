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
                <input type="text" class="form-control" name="cashier:secret"
                  value="{{ old('cashier:secret', config('cashier.secret')) }}" placeholder="sk_test_..." />
                <p class="text-muted small">Your Stripe secret key. This will be encrypted and stored securely. Leave blank to keep existing value.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Webhook Secret <span class="field-optional"></span></label>
              <div>
                <input type="text" class="form-control" name="cashier:webhook:secret"
                  value="{{ old('cashier:webhook:secret', config('cashier.webhook.secret')) }}" placeholder="whsec_..." />
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
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
