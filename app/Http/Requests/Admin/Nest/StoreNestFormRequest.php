<?php

namespace Pterodactyl\Http\Requests\Admin\Nest;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class StoreNestFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:191|regex:/^[\w\- ]+$/',
            'description' => 'string|nullable',
            'dashboard_type' => 'nullable|string|in:game-server,database,website,s3-storage,vps',
        ];
    }
}
