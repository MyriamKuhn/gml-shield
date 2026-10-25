<?php

namespace App\Http\Requests;

use App\Models\Mission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMissionRequest extends FormRequest
{
    /**
     * Only authorized users can create missions.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Mission::class);
    }

    /**
     * Validate mission creation data.
     */
    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:50',
                Rule::unique('missions', 'reference'),
            ],
            'title' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'pickup_address' => [
                'required',
                'string',
                'max:500',
            ],
            'delivery_address' => [
                'required',
                'string',
                'max:500',
            ],
            'scheduled_at' => [
                'required',
                'date',
                'after:now',
            ],
            'status' => [
                'prohibited',
            ],
            'driver_id' => [
                'prohibited',
            ],
            'created_by' => [
                'prohibited',
            ],
            'secret_code' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
