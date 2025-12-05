<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
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
        
        return $this->view->make('admin.themes.index', [
            'primaryColor' => $primaryColor ?: '#fa4e49',
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
        
        foreach ($values as $key => $value) {
            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');
        $this->alert->success('Theme settings have been updated successfully.')->flash();

        return redirect()->route('admin.themes.index');
    }
}

