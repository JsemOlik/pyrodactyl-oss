<form id="billingSettingsForm">
  {{ csrf_field() }}
  
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
        <div class="box-footer">
          <div class="pull-right">
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save Notification Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Email Template Editor -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Email Template Editor</h3>
          <div class="box-tools pull-right">
            <select id="templateSelector" class="form-control" style="width: auto; display: inline-block; margin-right: 10px;">
              <option value="payment_success">Payment Success</option>
              <option value="payment_failed">Payment Failed</option>
              <option value="invoice_generated">Invoice Generated</option>
              <option value="subscription_cancelled">Subscription Cancelled</option>
              <option value="subscription_renewed">Subscription Renewed</option>
              <option value="subscription_expiring">Subscription Expiring</option>
            </select>
          </div>
        </div>
        <div class="box-body" style="padding: 0;">
          <div class="row" style="margin: 0;">
            <!-- Code Editor (Left) -->
            <div class="col-md-6" style="padding: 15px; border-right: 1px solid #ddd;">
              <div style="margin-bottom: 10px;">
                <label class="control-label">Email Template Code</label>
                <p class="text-muted small">Edit the HTML template below. Use variables like @{{name}}, @{{amount}}, @{{invoice_number}}, etc.</p>
              </div>
              <textarea id="emailTemplateEditor" class="form-control" rows="20" style="font-family: 'Courier New', monospace; font-size: 13px; resize: vertical; background-color: #f5f5f5; border: 1px solid #ddd;" placeholder="Loading template..."></textarea>
            </div>
            <!-- Preview (Right) -->
            <div class="col-md-6" style="padding: 15px; background-color: #f9f9f9;">
              <div style="margin-bottom: 10px;">
                <label class="control-label">Email Preview</label>
                <p class="text-muted small">Live preview of how the email will appear to recipients.</p>
              </div>
              <div id="emailPreview" style="background-color: white; border: 1px solid #ddd; border-radius: 4px; padding: 0; overflow: auto; max-height: 600px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <!-- Preview will be rendered here -->
              </div>
            </div>
          </div>
        </div>
        <div class="box-footer">
          <div class="pull-right">
            <button type="button" id="saveTemplateButton" class="btn btn-sm btn-primary">Save Email Template</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
