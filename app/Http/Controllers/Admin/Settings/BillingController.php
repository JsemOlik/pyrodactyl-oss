<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\Response;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Providers\SettingsServiceProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\BillingSettingsFormRequest;

class BillingController extends Controller
{
    public function __construct(
        private ConfigRepository $config,
        private Encrypter $encrypter,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
        private ViewFactory $view,
    ) {
    }

    /**
     * Render UI for editing billing settings.
     */
    public function index(): View
    {
        // Determine which view to return based on route name
        $routeName = request()->route()->getName();
        
        if (str_contains($routeName, 'server-creation')) {
            return $this->view->make('admin.billing.server-creation');
        } elseif (str_contains($routeName, 'credits')) {
            return $this->view->make('admin.billing.credits');
        } elseif (str_contains($routeName, 'invoices')) {
            return $this->view->make('admin.billing.invoices');
        } elseif (str_contains($routeName, 'notifications')) {
            return $this->view->make('admin.billing.notifications');
        }
        
        // Default to settings
        return $this->view->make('admin.billing.settings');
    }

    /**
     * Get email template for a specific type.
     */
    public function getTemplate(string $type): Response
    {
        $settingKey = 'billing:email_template_' . $type;
        $template = $this->settings->get('settings::' . $settingKey);
        
        // If no template exists, return default
        if (!$template) {
            $template = $this->getDefaultTemplate($type);
        }
        
        return response()->json([
            'template' => $template,
        ]);
    }

    /**
     * Get default email template for a type.
     */
    private function getDefaultTemplate(string $type): string
    {
        $baseStyles = '
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
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }';

        $defaultTemplates = [
            'payment_success' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <style>' . $baseStyles . '
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
            <p>If you have any questions or concerns, please do not hesitate to contact our support team.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            'payment_failed' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>' . $baseStyles . '
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: #dc3545;">
            <h1>Payment Failed</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>We were unable to process your payment of <strong>{{amount}}</strong> for invoice {{invoice_number}}.</p>
            <div class="error-box">
                <p><strong>Payment Details:</strong></p>
                <ul>
                    <li>Amount: {{amount}}</li>
                    <li>Invoice Number: {{invoice_number}}</li>
                    <li>Attempt Date: {{payment_date}}</li>
                    <li>Transaction ID: {{transaction_id}}</li>
                </ul>
            </div>
            <p>Please update your payment method or contact our support team if you believe this is an error.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            'invoice_generated' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Generated</title>
    <style>' . $baseStyles . '
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Generated</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>A new invoice has been generated for your account.</p>
            <div class="info-box">
                <p><strong>Invoice Details:</strong></p>
                <ul>
                    <li>Invoice Number: {{invoice_number}}</li>
                    <li>Amount: {{amount}}</li>
                    <li>Due Date: {{payment_date}}</li>
                    <li>Status: Pending</li>
                </ul>
            </div>
            <p>Please make payment by the due date to avoid service interruption.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            'subscription_cancelled' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Cancelled</title>
    <style>' . $baseStyles . '
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: #6c757d;">
            <h1>Subscription Cancelled</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>Your subscription for <strong>{{subscription_name}}</strong> has been cancelled.</p>
            <div class="info-box">
                <p><strong>Subscription Details:</strong></p>
                <ul>
                    <li>Plan: {{subscription_name}}</li>
                    <li>Status: Cancelled</li>
                    <li>Cancellation Date: {{payment_date}}</li>
                    <li>Service will end on: {{expiry_date}}</li>
                </ul>
            </div>
            <p>Your service will remain active until {{expiry_date}}. After this date, your server will be suspended.</p>
            <p>If you have any questions, please contact our support team.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            'subscription_renewed' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Renewed</title>
    <style>' . $baseStyles . '
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: #28a745;">
            <h1>Subscription Renewed</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>Your subscription for <strong>{{subscription_name}}</strong> has been successfully renewed!</p>
            <div class="info-box">
                <p><strong>Renewal Details:</strong></p>
                <ul>
                    <li>Plan: {{subscription_name}}</li>
                    <li>Amount Paid: {{amount}}</li>
                    <li>Renewal Date: {{payment_date}}</li>
                    <li>Next Billing Date: {{expiry_date}}</li>
                    <li>Transaction ID: {{transaction_id}}</li>
                </ul>
            </div>
            <p>Thank you for your continued business. Your service will remain active until {{expiry_date}}.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            'subscription_expiring' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expiring Soon</title>
    <style>' . $baseStyles . '
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: #ffc107; color: #333;">
            <h1>Subscription Expiring Soon</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>Your subscription for <strong>{{subscription_name}}</strong> is expiring soon.</p>
            <div class="warning-box">
                <p><strong>Subscription Details:</strong></p>
                <ul>
                    <li>Plan: {{subscription_name}}</li>
                    <li>Current Status: {{subscription_status}}</li>
                    <li>Expiry Date: {{expiry_date}}</li>
                </ul>
            </div>
            <p>To ensure uninterrupted service, please renew your subscription before {{expiry_date}}.</p>
            <p>If you have any questions or need assistance, please contact our support team.</p>
            <p>Best regards,<br>{{company_name}}</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{year}} {{company_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
        ];
        
        return $defaultTemplates[$type] ?? $defaultTemplates['payment_success'];
    }

    /**
     * Handle request to update billing settings.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(BillingSettingsFormRequest $request): Response
    {
        $values = $request->normalize();

        foreach ($values as $key => $value) {
            // Handle boolean fields - convert '1'/'0' to 'true'/'false' strings
            $booleanFields = [
                'billing:enable_server_creation',
                'billing:show_status_page_button',
                'billing:show_logo_on_disabled_page',
                'billing:enable_credits',
                'billing:enable_tax',
                'billing:auto_renewal',
                'billing:enable_proration',
                'billing:enable_late_fees',
                'billing:email_payment_notifications',
                'billing:email_subscription_notifications',
                'billing:admin_notifications',
            ];
            if (in_array($key, $booleanFields)) {
                $value = ($value === '1' || $value === true || $value === 'true') ? 'true' : 'false';
            }

            // Skip empty values for encrypted fields to preserve existing values
            if (in_array($key, SettingsServiceProvider::getEncryptedKeys())) {
                if (empty($value)) {
                    continue;
                }
                $value = $this->encrypter->encrypt($value);
            }

            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');

        return response('', 204);
    }
}

