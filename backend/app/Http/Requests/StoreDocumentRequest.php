<?php

namespace App\Http\Requests;

use App\Models\Mission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Only the assigned driver of an in-progress mission
     * may upload a delivery proof.
     */
    public function authorize(): bool
    {
        $mission = $this->route('mission');

        return $mission instanceof Mission
            && Gate::allows('uploadDocument', $mission);
    }

    /**
     * Validate the uploaded file using its detected MIME type.
     *
     * Client-provided extensions and MIME headers are not
     * trusted as proof of the actual file format.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])
                    ->max('10mb'),
            ],
        ];
    }
}
