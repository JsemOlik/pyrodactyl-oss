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

  <!-- Tax Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Tax Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $enableTax = old('billing:enable_tax', config('billing.enable_tax', false));
                  $enableTaxValue = is_bool($enableTax) ? $enableTax : ($enableTax === 'true' || $enableTax === true || $enableTax === '1');
                @endphp
                <input id="billingEnableTax" type="checkbox" name="billing:enable_tax" value="1"
                  {{ $enableTaxValue ? 'checked' : '' }} />
                <label for="billingEnableTax">Enable Tax Calculation</label>
              </div>
              <p class="text-muted small">When enabled, tax will be calculated and added to all invoices and payments.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Tax Rate (%) <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" max="100" class="form-control" name="billing:tax_rate"
                  value="{{ old('billing:tax_rate', config('billing.tax_rate', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">The tax rate percentage to apply (e.g., 8.5 for 8.5%).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Tax ID / VAT Number <span class="field-optional"></span></label>
              <div>
                <input type="text" class="form-control" name="billing:tax_id"
                  value="{{ old('billing:tax_id', config('billing.tax_id', '')) }}" placeholder="VAT123456789" />
                <p class="text-muted small">Your business tax ID or VAT number to display on invoices.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Invoice Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Invoice Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Invoice Prefix <span class="field-optional"></span></label>
              <div>
                <input type="text" class="form-control" name="billing:invoice_prefix"
                  value="{{ old('billing:invoice_prefix', config('billing.invoice_prefix', 'INV-')) }}" placeholder="INV-" maxlength="20" />
                <p class="text-muted small">Prefix for invoice numbers (e.g., INV- will create INV-0001, INV-0002, etc.).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Starting Invoice Number <span class="field-optional"></span></label>
              <div>
                <input type="number" min="1" class="form-control" name="billing:invoice_starting_number"
                  value="{{ old('billing:invoice_starting_number', config('billing.invoice_starting_number', 1)) }}" placeholder="1" />
                <p class="text-muted small">The starting number for invoice numbering.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Invoice Terms <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:invoice_terms" rows="3"
                  placeholder="Payment terms and conditions...">{{ old('billing:invoice_terms', config('billing.invoice_terms', '')) }}</textarea>
                <p class="text-muted small">Terms and conditions to display on invoices (e.g., "Payment due within 30 days").</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Invoice Footer <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:invoice_footer" rows="2"
                  placeholder="Footer text...">{{ old('billing:invoice_footer', config('billing.invoice_footer', '')) }}</textarea>
                <p class="text-muted small">Footer text to display at the bottom of invoices (e.g., company address, contact info).</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Credits System Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Credits System Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Credit Conversion Rate <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0.01" class="form-control" name="billing:credit_conversion_rate"
                  value="{{ old('billing:credit_conversion_rate', config('billing.credit_conversion_rate', 1)) }}" placeholder="1.00" />
                <p class="text-muted small">How many credits equal 1 unit of currency (e.g., 1.00 = 1 credit = $1.00).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Minimum Credit Purchase <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" class="form-control" name="billing:min_credit_purchase"
                  value="{{ old('billing:min_credit_purchase', config('billing.min_credit_purchase', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">Minimum amount of credits a user must purchase at once.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Maximum Credit Balance <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" class="form-control" name="billing:max_credit_balance"
                  value="{{ old('billing:max_credit_balance', config('billing.max_credit_balance', 0)) }}" placeholder="0.00 (0 = unlimited)" />
                <p class="text-muted small">Maximum credit balance a user can have (0 = unlimited).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Credit Expiration Days <span class="field-optional"></span></label>
              <div>
                <input type="number" min="0" class="form-control" name="billing:credit_expiration_days"
                  value="{{ old('billing:credit_expiration_days', config('billing.credit_expiration_days', 0)) }}" placeholder="0 (0 = never expire)" />
                <p class="text-muted small">Number of days before credits expire (0 = never expire).</p>
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

  <!-- Notification Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Notification Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $emailPayments = old('billing:email_payment_notifications', config('billing.email_payment_notifications', true));
                  $emailPaymentsValue = is_bool($emailPayments) ? $emailPayments : ($emailPayments === 'true' || $emailPayments === true || $emailPayments === '1');
                @endphp
                <input id="billingEmailPayments" type="checkbox" name="billing:email_payment_notifications" value="1"
                  {{ $emailPaymentsValue ? 'checked' : '' }} />
                <label for="billingEmailPayments">Send Email Notifications for Payments</label>
              </div>
              <p class="text-muted small">When enabled, users will receive email notifications for successful payments and invoices.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $emailSubscriptions = old('billing:email_subscription_notifications', config('billing.email_subscription_notifications', true));
                  $emailSubscriptionsValue = is_bool($emailSubscriptions) ? $emailSubscriptions : ($emailSubscriptions === 'true' || $emailSubscriptions === true || $emailSubscriptions === '1');
                @endphp
                <input id="billingEmailSubscriptions" type="checkbox" name="billing:email_subscription_notifications" value="1"
                  {{ $emailSubscriptionsValue ? 'checked' : '' }} />
                <label for="billingEmailSubscriptions">Send Email Notifications for Subscription Changes</label>
              </div>
              <p class="text-muted small">When enabled, users will receive email notifications for subscription cancellations, renewals, and status changes.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $adminNotifications = old('billing:admin_notifications', config('billing.admin_notifications', false));
                  $adminNotificationsValue = is_bool($adminNotifications) ? $adminNotifications : ($adminNotifications === 'true' || $adminNotifications === true || $adminNotifications === '1');
                @endphp
                <input id="billingAdminNotifications" type="checkbox" name="billing:admin_notifications" value="1"
                  {{ $adminNotificationsValue ? 'checked' : '' }} />
                <label for="billingAdminNotifications">Send Admin Notifications for Failed Payments</label>
              </div>
              <p class="text-muted small">When enabled, administrators will receive email notifications when payments fail or subscriptions are canceled.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Admin Notification Email <span class="field-optional"></span></label>
              <div>
                <input type="email" class="form-control" name="billing:admin_notification_email"
                  value="{{ old('billing:admin_notification_email', config('billing.admin_notification_email', '')) }}" placeholder="admin@example.com" />
                <p class="text-muted small">Email address to receive admin notifications (leave empty to use default admin email).</p>
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
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save All Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
