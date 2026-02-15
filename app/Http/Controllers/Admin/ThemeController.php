<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\ThemeSettingsFormRequest;

class ThemeController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
        private ViewFactory $view,
    ) {}

    /**
     * Render theme settings UI.
     */
    public function index(): View
    {
        $primaryColor = $this->settings->get('settings::theme:primary_color', '#fa4e49');
        $backgroundColor = $this->settings->get('settings::theme:background_color', '#000000');
        $sidebarBackgroundColor = $this->settings->get('settings::theme:sidebar_background_color', '#050505');
        $surfaceColor = $this->settings->get('settings::theme:surface_color', '#131313');
        $surfaceMutedColor = $this->settings->get('settings::theme:surface_muted_color', '#0f0f0f');
        $borderColor = $this->settings->get('settings::theme:border_color', '#ffffff11');
        $buttonBorderRadius = $this->settings->get('settings::theme:button_border_radius', '0.5rem');
        $logoPath = $this->settings->get('settings::theme:logo_path');
        
        // Generate URL for logo - if it's a relative path, prepend with /themes/logo/
        $logoUrl = null;
        if ($logoPath) {
            // If it's already a full URL, use it as is
            if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
                $logoUrl = $logoPath;
            } else {
                // Otherwise, assume it's a filename in public/themes/logo/
                $logoUrl = '/themes/logo/' . basename($logoPath);
            }
        }
        
        return $this->view->make('admin.themes.index', [
            'primaryColor' => $primaryColor ?: '#fa4e49',
            'backgroundColor' => $backgroundColor ?: '#000000',
            'sidebarBackgroundColor' => $sidebarBackgroundColor ?: '#050505',
            'surfaceColor' => $surfaceColor ?: '#131313',
            'surfaceMutedColor' => $surfaceMutedColor ?: '#0f0f0f',
            'borderColor' => $borderColor ?: '#ffffff11',
            'buttonBorderRadius' => $buttonBorderRadius ?: '0.5rem',
            'logoPath' => $logoUrl,
        ]);
    }

    /**
     * Update theme settings.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(ThemeSettingsFormRequest $request): RedirectResponse
    {
        $values = $request->normalize();
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            
            // Ensure the directory exists
            $logoDir = public_path('themes/logo');
            if (!File::isDirectory($logoDir)) {
                File::makeDirectory($logoDir, 0755, true);
            }
            
            // Delete old logo if it exists
            $existingLogo = $this->settings->get('settings::theme:logo_path');
            if ($existingLogo) {
                $oldLogoPath = public_path('themes/logo/' . basename($existingLogo));
                if (File::exists($oldLogoPath)) {
                    File::delete($oldLogoPath);
                }
            }
            
            // Generate a unique filename to avoid conflicts
            $extension = $file->getClientOriginalExtension();
            $filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
            $destinationPath = public_path('themes/logo');
            
            // Move the file to public/themes/logo/
            $file->move($destinationPath, $filename);
            
            // Store just the filename (relative path)
            $this->settings->set('settings::theme:logo_path', $filename);
        }
        
        // Handle logo removal
        if ($request->input('remove_logo') == '1' || $request->input('remove_logo') === true) {
            $existingLogo = $this->settings->get('settings::theme:logo_path');
            if ($existingLogo) {
                $logoPath = public_path('themes/logo/' . basename($existingLogo));
                if (File::exists($logoPath)) {
                    File::delete($logoPath);
                }
            }
            $this->settings->forget('settings::theme:logo_path');
        }
        
        foreach ($values as $key => $value) {
            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');
        $this->alert->success('Theme settings have been updated successfully.')->flash();

        return redirect()->route('admin.themes.index');
    }
}

