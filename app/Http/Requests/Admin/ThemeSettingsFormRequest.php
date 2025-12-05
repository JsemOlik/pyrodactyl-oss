<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class ThemeSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'theme:primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }

    public function attributes(): array
    {
        return [
            'theme:primary_color' => 'Primary Color',
        ];
    }
}

