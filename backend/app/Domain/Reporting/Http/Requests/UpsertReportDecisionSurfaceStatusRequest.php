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
            'operator_note' => is_string($this->input('operator_note'))
                ? trim((string) $this->input('operator_note'))
                : $this->input('operator_note'),
            'defer_reason_code' => is_string($this->input('defer_reason_code'))
                ? trim((string) $this->input('defer_reason_code'))
                : $this->input('defer_reason_code'),
        ]);
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(['account', 'campaign'])],
            'entity_id' => ['required', 'uuid'],
            'surface_key' => ['required', 'string', Rule::in(ReportDecisionSurfaceStatusService::validSurfaceKeys())],
            'status' => ['required', 'string', Rule::in(ReportDecisionSurfaceStatusService::validStatuses())],
            'operator_note' => ['nullable', 'string', 'max:500'],
            'defer_reason_code' => ['nullable', 'string', Rule::in(ReportDecisionSurfaceStatusService::validDeferReasonCodes())],
        ];
    }
}
