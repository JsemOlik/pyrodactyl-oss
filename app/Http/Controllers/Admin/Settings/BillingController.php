<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\Response;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Providers\SettingsServiceProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\BillingSettingsFormRequest;

class BillingController extends Controller
{
    public function __construct(
        private ConfigRepository $config,
        private Encrypter $encrypter,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
        private ViewFactory $view,
    ) {
    }

    /**
     * Render UI for editing billing settings.
     */
    public function index(): View
    {
        // Determine which view to return based on route name
        $routeName = request()->route()->getName();
        
        if (str_contains($routeName, 'server-creation')) {
            return $this->view->make('admin.billing.server-creation');
        } elseif (str_contains($routeName, 'credits')) {
            return $this->view->make('admin.billing.credits');
        } elseif (str_contains($routeName, 'invoices')) {
            return $this->view->make('admin.billing.invoices');
        } elseif (str_contains($routeName, 'notifications')) {
            return $this->view->make('admin.billing.notifications');
        }
        
        // Default to settings
        return $this->view->make('admin.billing.settings');
    }

    /**
     * Handle request to update billing settings.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(BillingSettingsFormRequest $request): Response
    {
        $values = $request->normalize();

        foreach ($values as $key => $value) {
            // Handle boolean fields - convert '1'/'0' to 'true'/'false' strings
            $booleanFields = [
                'billing:enable_server_creation',
                'billing:show_status_page_button',
                'billing:show_logo_on_disabled_page',
                'billing:enable_credits',
                'billing:enable_tax',
                'billing:auto_renewal',
                'billing:enable_proration',
                'billing:enable_late_fees',
                'billing:email_payment_notifications',
                'billing:email_subscription_notifications',
                'billing:admin_notifications',
            ];
            if (in_array($key, $booleanFields)) {
                $value = ($value === '1' || $value === true || $value === 'true') ? 'true' : 'false';
            }

            // Skip empty values for encrypted fields to preserve existing values
            if (in_array($key, SettingsServiceProvider::getEncryptedKeys())) {
                if (empty($value)) {
                    continue;
                }
                $value = $this->encrypter->encrypt($value);
            }

            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');

        return response('', 204);
    }
}

