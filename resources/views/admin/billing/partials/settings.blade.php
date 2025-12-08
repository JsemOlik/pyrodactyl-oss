<form id="billingSettingsForm">
  {{ csrf_field() }}
  
  <!-- Stripe Configuration -->
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
      </div>
    </div>
  </div>



  <!-- Subscription Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Subscription Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Grace Period (Days) <span class="field-optional"></span></label>
              <div>
                <input type="number" min="0" class="form-control" name="billing:grace_period_days"
                  value="{{ old('billing:grace_period_days', config('billing.grace_period_days', 0)) }}" placeholder="0" />
                <p class="text-muted small">Number of days to allow service to continue after failed payment before suspension.</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Default Billing Cycle <span class="field-optional"></span></label>
              <div>
                <select class="form-control" name="billing:default_billing_cycle">
                  <option value="month" {{ old('billing:default_billing_cycle', config('billing.default_billing_cycle', 'month')) === 'month' ? 'selected' : '' }}>Monthly</option>
                  <option value="quarter" {{ old('billing:default_billing_cycle', config('billing.default_billing_cycle', 'month')) === 'quarter' ? 'selected' : '' }}>Quarterly</option>
                  <option value="half-year" {{ old('billing:default_billing_cycle', config('billing.default_billing_cycle', 'month')) === 'half-year' ? 'selected' : '' }}>Half-Yearly</option>
                  <option value="year" {{ old('billing:default_billing_cycle', config('billing.default_billing_cycle', 'month')) === 'year' ? 'selected' : '' }}>Yearly</option>
                </select>
                <p class="text-muted small">Default billing cycle for new subscriptions.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $autoRenewal = old('billing:auto_renewal', config('billing.auto_renewal', true));
                  $autoRenewalValue = is_bool($autoRenewal) ? $autoRenewal : ($autoRenewal === 'true' || $autoRenewal === true || $autoRenewal === '1');
                @endphp
                <input id="billingAutoRenewal" type="checkbox" name="billing:auto_renewal" value="1"
                  {{ $autoRenewalValue ? 'checked' : '' }} />
                <label for="billingAutoRenewal">Enable Auto-Renewal</label>
              </div>
              <p class="text-muted small">When enabled, subscriptions will automatically renew at the end of the billing period.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $proration = old('billing:enable_proration', config('billing.enable_proration', true));
                  $prorationValue = is_bool($proration) ? $proration : ($proration === 'true' || $proration === true || $proration === '1');
                @endphp
                <input id="billingProration" type="checkbox" name="billing:enable_proration" value="1"
                  {{ $prorationValue ? 'checked' : '' }} />
                <label for="billingProration">Enable Proration</label>
              </div>
              <p class="text-muted small">When enabled, plan upgrades/downgrades will prorate charges based on remaining time in billing period.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Cancellation Policy <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:cancellation_policy" rows="3"
                  placeholder="Cancellation policy terms...">{{ old('billing:cancellation_policy', config('billing.cancellation_policy', '')) }}</textarea>
                <p class="text-muted small">Cancellation policy text to display to users (e.g., "Cancellations take effect at the end of the billing period").</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Gateway Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Payment Gateway Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Payment Processing Fee (%) <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" max="100" class="form-control" name="billing:payment_fee_percentage"
                  value="{{ old('billing:payment_fee_percentage', config('billing.payment_fee_percentage', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">Additional percentage fee to add to all payments (e.g., 2.9 for 2.9%).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Fixed Payment Fee <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" class="form-control" name="billing:payment_fee_fixed"
                  value="{{ old('billing:payment_fee_fixed', config('billing.payment_fee_fixed', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">Fixed fee amount to add to all payments (in your currency).</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $lateFees = old('billing:enable_late_fees', config('billing.enable_late_fees', false));
                  $lateFeesValue = is_bool($lateFees) ? $lateFees : ($lateFees === 'true' || $lateFees === true || $lateFees === '1');
                @endphp
                <input id="billingLateFees" type="checkbox" name="billing:enable_late_fees" value="1"
                  {{ $lateFeesValue ? 'checked' : '' }} />
                <label for="billingLateFees">Enable Late Payment Fees</label>
              </div>
              <p class="text-muted small">When enabled, late payment fees will be applied to overdue invoices.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Late Fee Amount <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" class="form-control" name="billing:late_fee_amount"
                  value="{{ old('billing:late_fee_amount', config('billing.late_fee_amount', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">Fixed late fee amount to charge for overdue payments.</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Days Before Late Fee <span class="field-optional"></span></label>
              <div>
                <input type="number" min="1" class="form-control" name="billing:late_fee_days"
                  value="{{ old('billing:late_fee_days', config('billing.late_fee_days', 7)) }}" placeholder="7" />
                <p class="text-muted small">Number of days after due date before late fee is applied.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- Save Button -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-footer">
          <div class="pull-right">
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
