<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Services\Proxy\HaproxyService;

class GenerateHaproxyConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'proxy:generate-haproxy-config 
                            {--validate : Only validate the config, do not write it}
                            {--preview : Show the generated config without writing}';

    /**
     * The console command description.
     */
    protected $description = 'Generate HAProxy configuration for all active subdomains';

    /**
     * Execute the console command.
     */
    public function handle(HaproxyService $haproxyService): int
    {
        if (!config('proxy.enabled', false)) {
            $this->error('Proxy functionality is disabled. Set PROXY_ENABLED=true in .env');
            return 1;
        }

        $proxyType = config('proxy.proxy_type', 'haproxy');
        if ($proxyType !== 'haproxy') {
            $this->error("Proxy type is set to '{$proxyType}', not 'haproxy'. Set PROXY_TYPE=haproxy in .env");
            return 1;
        }

        try {
            $config = $haproxyService->generateFullConfig();

            if ($this->option('preview')) {
                $this->info('Generated HAProxy Configuration:');
                $this->info('================================');
                $this->line('');
                $this->line($config);
                return 0;
            }

            if ($this->option('validate')) {
                $this->info('Validating HAProxy configuration...');
                if ($haproxyService->validateConfig($config)) {
                    $this->info('✅ Configuration is valid!');
                    return 0;
                } else {
                    $this->error('❌ Configuration validation failed!');
                    return 1;
                }
            }

            // Write the config
            $this->info('Generating HAProxy configuration...');
            $haproxyService->writeConfig();
            
            $this->info('✅ Configuration generated successfully!');
            $this->info('');
            $this->info('Next steps:');
            $this->line('1. Validate the config: sudo haproxy -c -f /etc/haproxy/haproxy.cfg');
            $this->line('2. Reload HAProxy: sudo systemctl reload haproxy');
            $this->line('3. Check if HAProxy is listening: sudo ss -tlnp | grep 25565');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to generate HAProxy configuration: ' . $e->getMessage());
            return 1;
        }
    }
}
