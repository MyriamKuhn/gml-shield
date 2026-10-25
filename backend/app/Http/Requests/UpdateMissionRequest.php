<?php

namespace App\Http\Requests;

use App\Models\Mission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMissionRequest extends FormRequest
{
    /**
     * Only ADMIN and DISPATCHER can update pending missions.
     */
    public function authorize(): bool
    {
        $mission = $this->route('mission');

        return $mission instanceof Mission
            && Gate::allows('update', $mission);
    }

    /**
     * Validate editable mission fields.
     */
    public function rules(): array
    {
        $mission = $this->route('mission');

        return [
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('missions', 'reference')
                    ->ignore($mission instanceof Mission ? $mission->id : null),
            ],
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'pickup_address' => [
                'sometimes',
                'required',
                'string',
                'max:500',
            ],
            'delivery_address' => [
                'sometimes',
                'required',
                'string',
                'max:500',
            ],
            'scheduled_at' => [
                'sometimes',
                'required',
                'date',
                'after:now',
            ],
            'secret_code' => [
                'prohibited',
            ],

            // Sensitive fields must use dedicated business operations.
            'status' => ['prohibited'],
            'driver_id' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }
}
