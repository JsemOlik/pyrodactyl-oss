<?php

namespace Pterodactyl\Http\Requests\Auth;

use Pterodactyl\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userRules = User::getRules();

        return [
            'email' => $userRules['email'],
            'username' => $userRules['username'],
            'name_first' => $userRules['name_first'],
            'name_last' => $userRules['name_last'],
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
