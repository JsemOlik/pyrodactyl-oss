<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\Server;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServerFormRequest extends AdminFormRequest
{
    /**
     * Rules to be applied to this request.
     */
    public function rules(): array
    {
        $rules = Server::getRules();
        $rules['description'][] = 'nullable';
        $rules['custom_image'] = 'sometimes|nullable|string';

        return $rules;
    }

    /**
     * Run validation after the rules above have been applied.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $validator->sometimes('node_id', 'required|numeric|bail|exists:nodes,id', function ($input) {
                return !$input->auto_deploy;
            });

            $validator->sometimes('allocation_id', [
                'required',
                'numeric',
                'bail',
                Rule::exists('allocations', 'id')->where(function ($query) {
                    $query->where('node_id', $this->input('node_id'));
                    $query->whereNull('server_id');
                }),
            ], function ($input) {
                return !$input->auto_deploy;
            });

            // Validate that allocation is allowed for the selected nest/egg
            $validator->after(function ($validator) {
                $nestId = $this->input('nest_id');
                $eggId = $this->input('egg_id');
                $allocationId = $this->input('allocation_id');

                if ($nestId && $eggId && $allocationId && !$this->input('auto_deploy')) {
                    $allocation = \Pterodactyl\Models\Allocation::with(['allowedNests', 'allowedEggs'])
                        ->find($allocationId);

                    if ($allocation && !$allocation->isAllowedForServer((int) $nestId, (int) $eggId)) {
                        $validator->errors()->add(
                            'allocation_id',
                            'This allocation is not allowed for the selected nest/egg combination.'
                        );
                    }
                }
            });

            $validator->sometimes('allocation_additional.*', [
                'sometimes',
                'required',
                'numeric',
                Rule::exists('allocations', 'id')->where(function ($query) {
                    $query->where('node_id', $this->input('node_id'));
                    $query->whereNull('server_id');
                }),
            ], function ($input) {
                return !$input->auto_deploy;
            });

            // Validate that additional allocations are allowed for the selected nest/egg
            $validator->after(function ($validator) {
                $nestId = $this->input('nest_id');
                $eggId = $this->input('egg_id');
                $additionalAllocations = $this->input('allocation_additional', []);

                if ($nestId && $eggId && !empty($additionalAllocations) && !$this->input('auto_deploy')) {
                    foreach ($additionalAllocations as $key => $allocationId) {
                        $allocation = \Pterodactyl\Models\Allocation::with(['allowedNests', 'allowedEggs'])
                            ->find($allocationId);

                        if ($allocation && !$allocation->isAllowedForServer((int) $nestId, (int) $eggId)) {
                            $validator->errors()->add(
                                "allocation_additional.{$key}",
                                'This allocation is not allowed for the selected nest/egg combination.'
                            );
                        }
                    }
                }
            });
        });
    }
}
