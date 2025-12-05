<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class ProxmoxSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
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
            'proxmox:url' => 'Proxmox API URL',
            'proxmox:api_token' => 'Proxmox API Token',
            'proxmox:realm' => 'Proxmox Authentication Realm',
            'proxmox:node' => 'Proxmox Node Name',
            'proxmox:storage' => 'Proxmox Storage Pool',
            'proxmox:template' => 'Proxmox Template',
        ];
    }
}

