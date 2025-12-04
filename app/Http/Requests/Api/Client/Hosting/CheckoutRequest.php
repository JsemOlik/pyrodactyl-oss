<?php

namespace Pterodactyl\Http\Requests\Api\Client\Hosting;

use Pterodactyl\Models\Plan;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Egg;
use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CheckoutRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            // Either plan_id OR custom plan fields must be provided
            'plan_id' => ['sometimes', 'required_without:custom', 'integer', 'exists:plans,id'],
            'custom' => ['sometimes', 'required_without:plan_id', 'boolean'],
            'memory' => ['required_if:custom,true', 'integer', 'min:512', 'max:32768'],
            'interval' => ['required_if:custom,true', 'string', 'in:month,quarter,half-year,year'],
            
            // Server configuration
            'nest_id' => ['required', 'integer', 'exists:nests,id'],
            'egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'server_name' => ['required', 'string', 'max:191', 'regex:/^[a-zA-Z0-9_\-\.\s]+$/'],
            'server_description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validate that egg belongs to the selected nest
            if ($this->has('egg_id') && $this->has('nest_id')) {
                $egg = Egg::find($this->input('egg_id'));
                if ($egg && $egg->nest_id !== (int) $this->input('nest_id')) {
                    $validator->errors()->add('egg_id', 'The selected egg does not belong to the selected nest.');
                }
            }

            // Validate that plan is active if provided
            if ($this->has('plan_id')) {
                $plan = Plan::find($this->input('plan_id'));
                if ($plan && !$plan->is_active) {
                    $validator->errors()->add('plan_id', 'The selected plan is not available.');
                }
                if ($plan && $plan->is_custom) {
                    $validator->errors()->add('plan_id', 'Custom plans cannot be selected directly.');
                }
            }

            // Validate that custom plan has required fields
            if ($this->boolean('custom') && !$this->has('memory')) {
                $validator->errors()->add('memory', 'Memory is required for custom plans.');
            }
        });
    }
}
