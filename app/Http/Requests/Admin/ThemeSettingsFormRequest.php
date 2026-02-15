<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class ThemeSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'theme:primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:sidebar_background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:surface_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:surface_muted_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:border_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme:button_border_radius' => ['nullable', 'string', 'regex:#^\d+(\.\d+)?(px|rem|em)$#'],
            'logo' => 'nullable|file|mimes:svg,png,jpg,jpeg,webp,gif,bmp|max:5120',
            'remove_logo' => 'nullable|in:1,0,true,false',
        ];
    }

    public function attributes(): array
    {
        return [
            'theme:primary_color' => 'Primary Color',
            'theme:background_color' => 'Background Color',
            'theme:sidebar_background_color' => 'Sidebar Background Color',
            'theme:surface_color' => 'Surface Color',
            'theme:surface_muted_color' => 'Muted Surface Color',
            'theme:border_color' => 'Border Color',
            'theme:button_border_radius' => 'Button Border Radius',
            'logo' => 'Custom Logo',
        ];
    }
}

