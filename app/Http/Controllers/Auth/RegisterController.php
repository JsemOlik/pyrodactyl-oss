<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Auth\RegisterRequest;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Facades\Activity;

class RegisterController extends Controller
{
    public function __construct(
        private UserCreationService $creationService,
    ) {
    }

    /**
     * Handle a registration request to the application.
     *
     * @throws \Exception
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Remove password_confirmation as it's not needed for user creation
        unset($data['password_confirmation']);

        // Set default language if not provided
        $data['language'] = $data['language'] ?? 'en';

        // Create the user
        $user = $this->creationService->handle($data);

        Activity::event('auth:register')
            ->withRequestMetadata()
            ->subject($user)
            ->log('user registered');

        return new JsonResponse([
            'success' => true,
            'message' => 'Registration successful. You can now log in.',
        ], 201);
    }

    /**
     * Handle all incoming requests for the registration route and render the
     * base authentication view component. React will take over at this point.
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('templates/auth.core');
    }
}
