<?php

namespace App\Domain\Reporting\Http\Requests;

use App\Domain\Reporting\Services\ReportDecisionSurfaceStatusService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertReportDecisionSurfaceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entity_type' => $this->route('entityType'),
            'entity_id' => $this->route('entityId'),
            'surface_key' => $this->route('surfaceKey'),
        ]);
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(['account', 'campaign'])],
            'entity_id' => ['required', 'uuid'],
            'surface_key' => ['required', 'string', Rule::in(ReportDecisionSurfaceStatusService::validSurfaceKeys())],
            'status' => ['required', 'string', Rule::in(ReportDecisionSurfaceStatusService::validStatuses())],
        ];
    }
}
