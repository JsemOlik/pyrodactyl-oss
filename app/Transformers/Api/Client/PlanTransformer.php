<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Plan;

class PlanTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Plan::RESOURCE_NAME;
    }

    /**
     * Transform a Plan model into a representation that can be consumed by the
     * client API.
     */
    public function transform(Plan $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'price' => (float) $model->price,
            'currency' => $model->currency,
            'interval' => $model->interval,
            'memory' => $model->memory,
            'disk' => $model->disk,
            'cpu' => $model->cpu,
            'io' => $model->io,
            'swap' => $model->swap,
            'is_custom' => $model->is_custom,
            'sort_order' => $model->sort_order,
            'pricing' => [
                'monthly' => $model->getPriceForInterval('month'),
                'quarterly' => $model->getPriceForInterval('quarter'),
                'half_year' => $model->getPriceForInterval('half-year'),
                'yearly' => $model->getPriceForInterval('year'),
            ],
            'created_at' => $this->formatTimestamp($model->created_at),
            'updated_at' => $this->formatTimestamp($model->updated_at),
        ];
    }
}
