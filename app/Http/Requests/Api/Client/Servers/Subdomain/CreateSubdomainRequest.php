<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Subdomain;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Models\Domain;
use Pterodactyl\Models\ServerSubdomain;

class CreateSubdomainRequest extends ClientApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'subdomain' => [
                'required',
                'string',
                'min:1',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                function ($attribute, $value, $fail) {
                    if (preg_match('/[<>"\']/', $value)) {
                        $fail('Subdomain contains invalid characters.');
                        return;
                    }
                    
                    $reserved = ['www', 'mail', 'ftp', 'api', 'admin', 'root', 'panel', 
                                'localhost', 'wildcard', 'ns1', 'ns2', 'dns', 'smtp', 'pop', 
                                'imap', 'webmail', 'cpanel', 'whm', 'autodiscover', 'autoconfig'];
                    if (in_array(strtolower($value), $reserved)) {
                        $fail('This subdomain is reserved and cannot be used.');
                        return;
                    }

                    $domainId = $this->input('domain_id');
                    if ($domainId) {
                        $exists = ServerSubdomain::where('domain_id', $domainId)
                            ->where('subdomain', strtolower($value))
                            ->where('is_active', true)
                            ->exists();
                        
                        if ($exists) {
                            $fail('This subdomain is already taken.');
                        }
                    }
                },
            ],
            'domain_id' => [
                'required',
                'integer',
                'min:1',
                'exists:domains,id',
                function ($attribute, $value, $fail) {
                    $domain = Domain::where('id', $value)
                        ->where('is_active', true)
                        ->first();
                    if (!$domain) {
                        $fail('The selected domain is not available.');
                    }
                },
            ],
            'proxy_port' => [
                'nullable',
                'integer',
                'min:1024',
                'max:65535',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return; // Null is allowed
                    }

                    // Get server from route
                    $server = $this->route('server');
                    if (!$server) {
                        // If server is not available in route, try to get it from attributes
                        $server = $this->attributes->get('server');
                    }

                    $excludeSubdomainId = null;

                    // If editing an existing subdomain, exclude it from the check
                    if ($server) {
                        $currentSubdomain = $server->subdomains()->where('is_active', true)->first();
                        if ($currentSubdomain) {
                            $excludeSubdomainId = $currentSubdomain->id;
                        }
                    }

                    // Check if another active subdomain is already using this proxy port
                    $query = \Pterodactyl\Models\ServerSubdomain::where('proxy_port', $value)
                        ->where('is_active', true);

                    if ($excludeSubdomainId) {
                        $query->where('id', '!=', $excludeSubdomainId);
                    }

                    $existingSubdomain = $query->first();

                    if ($existingSubdomain) {
                        $fail("Port {$value} is already in use by another subdomain ({$existingSubdomain->full_domain}). Each subdomain must use a unique proxy port because NGINX cannot route TCP connections by domain name.");
                        return;
                    }

                    // Check if any server allocation is directly using this port
                    // This prevents conflicts where a server is directly listening on the proxy port
                    // Note: This checks ALL servers, not just on the same node, because NGINX listens
                    // on the panel server's IP, so any server using this port could conflict
                    $directAllocation = \Pterodactyl\Models\Allocation::where('port', $value)
                        ->whereNotNull('server_id')
                        ->when($server, function ($query) use ($server) {
                            // Exclude the current server's allocation
                            return $query->where('server_id', '!=', $server->id);
                        })
                        ->exists();

                    if ($directAllocation) {
                        $fail("Port {$value} is already in use by a server allocation. You cannot use a proxy port that matches a direct server port. Please choose a different port.");
                        return;
                    }
                },
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize and normalize subdomain
        if ($this->has('subdomain')) {
            $subdomain = $this->input('subdomain');
            // Remove any potential harmful characters and normalize
            $subdomain = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($subdomain)));
            // Remove multiple consecutive hyphens
            $subdomain = preg_replace('/-+/', '-', $subdomain);
            // Remove leading/trailing hyphens
            $subdomain = trim($subdomain, '-');
            
            $this->merge([
                'subdomain' => $subdomain,
            ]);
        }
    }
}