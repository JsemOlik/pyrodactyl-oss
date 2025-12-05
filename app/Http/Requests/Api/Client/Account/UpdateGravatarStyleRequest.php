<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateGravatarStyleRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'gravatar_style' => ['required', 'string', 'in:identicon,monsterid,wavatar,retro,robohash'],
        ];
    }
}

