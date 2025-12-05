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
        return [
            'app:name' => 'required|string|max:191',
            'pterodactyl:auth:2fa_required' => 'required|integer|in:0,1,2',
            'app:locale' => ['required', 'string', Rule::in(array_keys($this->getAvailableLanguages()))],
            'proxmox:url' => 'nullable|url|max:255',
            'proxmox:api_token' => 'nullable|string|max:500',
            'proxmox:realm' => 'nullable|string|max:191',
            'proxmox:node' => 'nullable|string|max:191',
            'proxmox:storage' => 'nullable|string|max:191',
            'proxmox:template' => 'nullable|string|max:191',
        ];
    }

    public function attributes(): array
    {
        return [
            'app:name' => 'Company Name',
            'pterodactyl:auth:2fa_required' => 'Require 2-Factor Authentication',
            'app:locale' => 'Default Language',
            'proxmox:url' => 'Proxmox API URL',
            'proxmox:api_token' => 'Proxmox API Token',
            'proxmox:realm' => 'Proxmox Authentication Realm',
            'proxmox:node' => 'Proxmox Node Name',
            'proxmox:storage' => 'Proxmox Storage Pool',
            'proxmox:template' => 'Proxmox Template',
        ];
    }
}
