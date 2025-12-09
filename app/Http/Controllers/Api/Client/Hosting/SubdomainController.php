<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Domain;
use Pterodactyl\Services\Subdomain\SubdomainManagementService;

class SubdomainController extends Controller
{
    public function __construct(
        private SubdomainManagementService $subdomainService
    ) {}

    /**
     * Get available domains for subdomain creation (for checkout flow).
     */
    public function getAvailableDomains(): JsonResponse
    {
        try {
            $domains = $this->subdomainService->getAvailableDomains();
            
            return response()->json([
                'object' => 'list',
                'data' => $domains,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [[
                    'code' => 'SubdomainError',
                    'status' => '500',
                    'detail' => 'Unable to retrieve available domains.',
                ]],
            ], 500);
        }
    }

    /**
     * Check if a subdomain is available (for checkout flow, before server creation).
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'subdomain' => 'required|string|min:1|max:63|regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
            'domain_id' => 'required|integer|exists:domains,id',
        ]);

        try {
            $domain = Domain::where('id', $request->input('domain_id'))
                ->where('is_active', true)
                ->first();

            if (!$domain) {
                return response()->json([
                    'errors' => [[
                        'code' => 'InvalidDomain',
                        'status' => '422',
                        'detail' => 'Selected domain is not available.',
                    ]],
                ], 422);
            }

            $subdomain = strtolower(trim($request->input('subdomain')));
            $availabilityResult = $this->subdomainService->checkSubdomainAvailability($subdomain, $domain);

            return response()->json([
                'object' => 'subdomain_availability',
                'attributes' => [
                    'available' => $availabilityResult['available'],
                    'message' => $availabilityResult['message'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [[
                    'code' => 'SubdomainError',
                    'status' => '500',
                    'detail' => 'Unable to check subdomain availability.',
                ]],
            ], 500);
        }
    }
}
