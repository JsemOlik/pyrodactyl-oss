<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BillingSettingsFormRequest extends AdminFormRequest
{
    /**
     * Return rules to validate billing settings POST data against.
     */
    public function rules(): array
    {
        return [
            'cashier:key' => 'nullable|string|max:191',
            'cashier:secret' => 'nullable|string|max:191',
            'cashier:webhook:secret' => 'nullable|string|max:191',
            'cashier:currency' => 'nullable|string|size:3',
            'cashier:currency_locale' => 'nullable|string|max:10',
            'billing:enable_server_creation' => 'nullable|boolean',
            'billing:server_creation_disabled_message' => 'nullable|string|max:1000',
            'billing:status_page_url' => 'nullable|url|max:255',
            'billing:show_status_page_button' => 'nullable|boolean',
            'billing:show_logo_on_disabled_page' => 'nullable|boolean',
            'billing:enable_credits' => 'nullable|boolean',
            'billing:enable_tax' => 'nullable|boolean',
            'billing:tax_rate' => 'nullable|numeric|min:0|max:100',
            'billing:tax_id' => 'nullable|string|max:100',
            'billing:invoice_prefix' => 'nullable|string|max:20',
            'billing:invoice_starting_number' => 'nullable|integer|min:1',
            'billing:invoice_terms' => 'nullable|string|max:2000',
            'billing:invoice_footer' => 'nullable|string|max:1000',
            'billing:credit_conversion_rate' => 'nullable|numeric|min:0.01',
            'billing:min_credit_purchase' => 'nullable|numeric|min:0',
            'billing:max_credit_balance' => 'nullable|numeric|min:0',
            'billing:credit_expiration_days' => 'nullable|integer|min:0',
            'billing:grace_period_days' => 'nullable|integer|min:0',
            'billing:default_billing_cycle' => 'nullable|string|in:month,quarter,half-year,year',
            'billing:auto_renewal' => 'nullable|boolean',
            'billing:enable_proration' => 'nullable|boolean',
            'billing:cancellation_policy' => 'nullable|string|max:2000',
            'billing:payment_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'billing:payment_fee_fixed' => 'nullable|numeric|min:0',
            'billing:enable_late_fees' => 'nullable|boolean',
            'billing:late_fee_amount' => 'nullable|numeric|min:0',
            'billing:late_fee_days' => 'nullable|integer|min:1',
            'billing:email_payment_notifications' => 'nullable|boolean',
            'billing:email_subscription_notifications' => 'nullable|boolean',
            'billing:admin_notifications' => 'nullable|boolean',
            'billing:admin_notification_email' => 'nullable|email|max:255',
        ];
    }

    /**
     * Override the default normalization function for this type of request
     * as we need to accept empty values on the keys.
     */
    public function normalize(?array $only = null): array
    {
        $keys = array_flip(array_keys($this->rules()));

        return $this->only(array_flip($keys));
    }
}

