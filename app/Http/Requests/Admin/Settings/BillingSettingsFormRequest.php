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

