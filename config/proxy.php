<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proxy Functionality
    |--------------------------------------------------------------------------
    |
    | Enable or disable the NGINX reverse proxy functionality for subdomains.
    | When enabled, subdomains can forward traffic from a chosen port to the
    | container's actual port.
    |
    */

    'enabled' => env('PROXY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | NGINX Configuration Path
    |--------------------------------------------------------------------------
    |
    | The directory where NGINX stream configuration files will be stored.
    | This directory must be writable by the web server user.
    |
    */

    'nginx_config_path' => env('PROXY_NGINX_CONFIG_PATH', '/etc/nginx/stream.d'),

    /*
    |--------------------------------------------------------------------------
    | NGINX Reload Command
    |--------------------------------------------------------------------------
    |
    | The command to reload NGINX configuration after changes.
    | This command should be executable by the web server user without password.
    | Consider using sudoers configuration for secure execution.
    |
    */

    'nginx_reload_command' => env('PROXY_NGINX_RELOAD_COMMAND', 'nginx -s reload'),

    /*
    |--------------------------------------------------------------------------
    | Default Proxy Port
    |--------------------------------------------------------------------------
    |
    | The default port that will be used for proxying if not specified by the user.
    | This is typically 25565 for Minecraft servers.
    |
    */

    'default_proxy_port' => env('PROXY_DEFAULT_PORT', 25565),

    /*
    |--------------------------------------------------------------------------
    | Proxy Protocol
    |--------------------------------------------------------------------------
    |
    | The protocol to use for proxying. Options: 'tcp' or 'udp'.
    | Most game servers use TCP.
    |
    */

    'proxy_protocol' => env('PROXY_PROTOCOL', 'tcp'),

    /*
    |--------------------------------------------------------------------------
    | Proxy Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout for proxy connections in seconds.
    |
    */

    'proxy_timeout' => env('PROXY_TIMEOUT', 1),

    /*
    |--------------------------------------------------------------------------
    | Proxy Responses
    |--------------------------------------------------------------------------
    |
    | Number of responses expected from the upstream server.
    |
    */

    'proxy_responses' => env('PROXY_RESPONSES', 1),
];
