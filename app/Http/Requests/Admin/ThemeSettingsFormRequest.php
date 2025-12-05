<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class ThemeSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'theme:primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo' => 'nullable|file|mimes:svg|max:2048',
            'remove_logo' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'theme:primary_color' => 'Primary Color',
            'logo' => 'Custom Logo',
        ];
    }
}

