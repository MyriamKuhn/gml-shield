<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Mission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignMissionRequest extends FormRequest
{
    /**
     * Only authorized managers can assign a driver
     * to a pending mission.
     */
    public function authorize(): bool
    {
        $mission = $this->route('mission');

        return $mission instanceof Mission
            && Gate::allows('assign', $mission);
    }

    /**
     * Ensure the assigned user is an actual DRIVER.
     */
    public function rules(): array
    {
        return [
            'driver_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::DRIVER->value)
                    ->where('is_active', true),
            ],
        ];
    }
}
