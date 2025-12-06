<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proxy Functionality
    |--------------------------------------------------------------------------
    |
    | Enable or disable the reverse proxy functionality for subdomains.
    | When enabled, subdomains can forward traffic from a chosen port to the
    | container's actual port.
    |
    */

    'enabled' => env('PROXY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Proxy Type
    |--------------------------------------------------------------------------
    |
    | The proxy implementation to use. Options: 'nginx' or 'haproxy'.
    | - 'nginx': Uses NGINX stream module (requires unique ports per subdomain)
    | - 'haproxy': Uses HAProxy with TCP content inspection (allows same port for multiple subdomains)
    |
    */

    'proxy_type' => env('PROXY_TYPE', 'haproxy'),

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

    'nginx_reload_command' => env('PROXY_NGINX_RELOAD_COMMAND', 'sudo /usr/sbin/nginx -s reload'),

    /*
    |--------------------------------------------------------------------------
    | HAProxy Configuration Path
    |--------------------------------------------------------------------------
    |
    | The path to the HAProxy configuration file.
    | This file must be writable by the web server user.
    |
    */

    'haproxy_config_path' => env('PROXY_HAPROXY_CONFIG_PATH', '/etc/haproxy/haproxy.cfg'),

    /*
    |--------------------------------------------------------------------------
    | HAProxy Reload Command
    |--------------------------------------------------------------------------
    |
    | The command to reload HAProxy configuration after changes.
    | This command should be executable by the web server user without password.
    | Consider using sudoers configuration for secure execution.
    |
    */

    'haproxy_reload_command' => env('PROXY_HAPROXY_RELOAD_COMMAND', 'sudo systemctl reload haproxy'),

    /*
    |--------------------------------------------------------------------------
    | HAProxy Inspect Delay
    |--------------------------------------------------------------------------
    |
    | The time in seconds to wait for the first packet before routing.
    | This allows HAProxy to inspect the packet content for hostname extraction.
    | Recommended: 2 seconds for Minecraft protocol (reduced from 5s for better performance).
    |
    */

    'haproxy_inspect_delay' => env('PROXY_HAPROXY_INSPECT_DELAY', 2),

    /*
    |--------------------------------------------------------------------------
    | HAProxy Default Backend
    |--------------------------------------------------------------------------
    |
    | The default backend to use if hostname cannot be extracted or doesn't match.
    | Set to null to reject connections that don't match any subdomain.
    |
    */

    'haproxy_default_backend' => env('PROXY_HAPROXY_DEFAULT_BACKEND', null),

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
