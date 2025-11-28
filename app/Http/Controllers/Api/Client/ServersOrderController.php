<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Pterodactyl\Models\UserServerOrder;
use Pterodactyl\Http\Controllers\Controller;

class ServersOrderController extends Controller
{
    /**
     * Return the current user's server preferences (order and sort option).
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $preferences = $user->serverOrder;

        return response()->json([
            'order' => $preferences->order ?? [],
            'sort_option' => $preferences->sort_option ?? 'default',
        ]);
    }

    /**
     * Update the current user's server preferences.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'order' => ['sometimes', 'array'],
            'order.*' => ['string'], // server UUIDs
            'sort_option' => ['sometimes', 'string', 'in:default,name_asc,custom'],
        ]);

        // Upsert for this user
        $record = UserServerOrder::updateOrCreate(
            ['user_id' => $user->id],
            array_filter([
                'order' => $data['order'] ?? null,
                'sort_option' => $data['sort_option'] ?? null,
            ])
        );

        return response()->json([
            'order' => $record->order,
            'sort_option' => $record->sort_option,
        ]);
    }
}
