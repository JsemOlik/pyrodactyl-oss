<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class ThemeSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'theme:primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:button_border_radius' => ['nullable', 'string', 'regex:#^\d+(\.\d+)?(px|rem|em)$#'],
            'logo' => 'nullable|file|mimes:svg,png,jpg,jpeg,webp,gif,bmp|max:5120',
            'remove_logo' => 'nullable|in:1,0,true,false',
        ];
    }

    public function attributes(): array
    {
        return [
            'theme:primary_color' => 'Primary Color',
            'theme:button_border_radius' => 'Button Border Radius',
            'logo' => 'Custom Logo',
        ];
    }
}

