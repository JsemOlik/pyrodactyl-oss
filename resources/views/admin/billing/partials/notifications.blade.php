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
                <p class="text-muted small">Edit the HTML template below. Use variables like {{ '{{' }}name{{ '}}' }}, {{ '{{' }}amount{{ '}}' }}, {{ '{{' }}invoice_number{{ '}}' }}, etc.</p>
              </div>
              <textarea id="emailTemplateEditor" class="form-control" rows="20" style="font-family: 'Courier New', monospace; font-size: 13px; resize: vertical; background-color: #f5f5f5; border: 1px solid #ddd;">@php
$defaultTemplate = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #fa4e49;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
            margin: -30px -30px 20px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #fa4e49;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
        .info-box {
            background-color: #f9f9f9;
            border-left: 4px solid #fa4e49;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Confirmation</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>Thank you for your payment! We have successfully processed your payment of <strong>{{amount}}</strong>.</p>
            <div class="info-box">
                <p><strong>Payment Details:</strong></p>
                <ul>
                    <li>Amount: {{amount}}</li>
                    <li>Invoice Number: {{invoice_number}}</li>
                    <li>Payment Date: {{payment_date}}</li>
                    <li>Transaction ID: {{transaction_id}}</li>
                </ul>
            </div>
            <p>If you have any questions or concerns, please don\'t hesitate to contact our support team.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
$template = old('billing:email_template_payment_success', config('billing.email_template_payment_success', $defaultTemplate));
echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
@endphp</textarea>
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
