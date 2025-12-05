<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
        $logoPath = $this->settings->get('settings::theme:logo_path');
        
        return $this->view->make('admin.themes.index', [
            'primaryColor' => $primaryColor ?: '#fa4e49',
            'logoPath' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
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
            $path = $file->store('themes/logo', 'public');
            $this->settings->set('settings::theme:logo_path', $path);
        }
        
        // Handle logo removal
        if ($request->input('remove_logo')) {
            $existingLogo = $this->settings->get('settings::theme:logo_path');
            if ($existingLogo && Storage::disk('public')->exists($existingLogo)) {
                Storage::disk('public')->delete($existingLogo);
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

