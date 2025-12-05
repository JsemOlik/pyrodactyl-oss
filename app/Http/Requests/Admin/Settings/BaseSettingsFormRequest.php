<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Illuminate\Validation\Rule;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BaseSettingsFormRequest extends AdminFormRequest
{
    use AvailableLanguages;

    public function rules(): array
    {
        // Check if Panel Settings fields are present in the request
        $hasPanelSettings = $this->hasAny([
            'app:name',
            'pterodactyl:auth:2fa_required',
            'app:locale',
        ]);

        // Panel Settings fields are only required if they're present in the request
        // This allows updating Proxmox settings independently without requiring Panel Settings
        $panelSettingsRule = $hasPanelSettings ? 'required' : 'nullable';

        return [
            'app:name' => "{$panelSettingsRule}|string|max:191",
            'pterodactyl:auth:2fa_required' => "{$panelSettingsRule}|integer|in:0,1,2",
            'app:locale' => [
                $panelSettingsRule,
                'string',
                Rule::in(array_keys($this->getAvailableLanguages())),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'app:name' => 'Company Name',
            'pterodactyl:auth:2fa_required' => 'Require 2-Factor Authentication',
            'app:locale' => 'Default Language',
        ];
    }
}
