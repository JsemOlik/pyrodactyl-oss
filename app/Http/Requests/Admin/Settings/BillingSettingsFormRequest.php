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

