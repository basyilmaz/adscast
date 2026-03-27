<?php

namespace App\Domain\Reporting\Http\Requests;

use App\Domain\Reporting\Services\ReportDecisionSurfaceStatusService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackReportDecisionQueueRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recommendation_code' => is_string($this->input('recommendation_code'))
                ? trim((string) $this->input('recommendation_code'))
                : $this->input('recommendation_code'),
            'recommendation_label' => is_string($this->input('recommendation_label'))
                ? trim((string) $this->input('recommendation_label'))
                : $this->input('recommendation_label'),
            'suggested_status' => is_string($this->input('suggested_status'))
                ? trim((string) $this->input('suggested_status'))
                : $this->input('suggested_status'),
            'guidance_message' => is_string($this->input('guidance_message'))
                ? trim((string) $this->input('guidance_message'))
                : $this->input('guidance_message'),
            'reason_codes' => collect($this->input('reason_codes', []))
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values()
                ->all(),
            'priority_group_keys' => collect($this->input('priority_group_keys', []))
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values()
                ->all(),
            'target_entity_types' => collect($this->input('target_entity_types', []))
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values()
                ->all(),
            'target_surface_keys' => collect($this->input('target_surface_keys', []))
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->values()
                ->all(),
            'targets' => collect($this->input('targets', []))
                ->filter(fn ($value): bool => is_array($value))
                ->map(function (array $target): array {
                    return [
                        'entity_type' => is_string($target['entity_type'] ?? null)
                            ? trim((string) $target['entity_type'])
                            : $target['entity_type'] ?? null,
                        'entity_id' => is_string($target['entity_id'] ?? null)
                            ? trim((string) $target['entity_id'])
                            : $target['entity_id'] ?? null,
                        'surface_key' => is_string($target['surface_key'] ?? null)
                            ? trim((string) $target['surface_key'])
                            : $target['surface_key'] ?? null,
                    ];
                })
                ->filter(fn (array $target): bool => filled($target['entity_type']) && filled($target['entity_id']) && filled($target['surface_key']))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'recommendation_code' => ['required', 'string', 'max:120'],
            'recommendation_label' => ['required', 'string', 'max:160'],
            'suggested_status' => ['nullable', 'string', Rule::in(ReportDecisionSurfaceStatusService::validStatuses())],
            'execution_mode' => ['required', 'string', Rule::in(['selection_only', 'bulk_status_applied'])],
            'guidance_variant' => ['nullable', 'string', Rule::in(['success', 'warning', 'danger', 'neutral'])],
            'guidance_message' => ['nullable', 'string', 'max:500'],
            'target_count' => ['required', 'integer', 'min:0'],
            'attempted_count' => ['nullable', 'integer', 'min:0'],
            'successful_count' => ['nullable', 'integer', 'min:0'],
            'failed_count' => ['nullable', 'integer', 'min:0'],
            'reason_codes' => ['nullable', 'array'],
            'reason_codes.*' => ['string', 'max:120'],
            'priority_group_keys' => ['nullable', 'array'],
            'priority_group_keys.*' => ['string', 'max:120'],
            'target_entity_types' => ['nullable', 'array'],
            'target_entity_types.*' => ['string', Rule::in(['account', 'campaign'])],
            'target_surface_keys' => ['nullable', 'array'],
            'target_surface_keys.*' => ['string', Rule::in(ReportDecisionSurfaceStatusService::validSurfaceKeys())],
            'targets' => ['nullable', 'array', 'max:50'],
            'targets.*.entity_type' => ['required', 'string', Rule::in(['account', 'campaign'])],
            'targets.*.entity_id' => ['required', 'uuid'],
            'targets.*.surface_key' => ['required', 'string', Rule::in(ReportDecisionSurfaceStatusService::validSurfaceKeys())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $executionMode = $this->string('execution_mode')->toString();
            $targetCount = (int) $this->integer('target_count');
            $attemptedCount = $this->filled('attempted_count') ? (int) $this->integer('attempted_count') : null;
            $successfulCount = $this->filled('successful_count') ? (int) $this->integer('successful_count') : null;
            $failedCount = $this->filled('failed_count') ? (int) $this->integer('failed_count') : null;

            if ($targetCount < 1) {
                $validator->errors()->add('target_count', 'En az bir hedef karar yuzeyi gerekli.');
            }

            if ($executionMode !== 'bulk_status_applied') {
                return;
            }

            if ($attemptedCount === null || $successfulCount === null || $failedCount === null) {
                $validator->errors()->add(
                    'attempted_count',
                    'Toplu uygulama izleme kaydinda attempted, successful ve failed sayilari zorunludur.',
                );

                return;
            }

            if ($attemptedCount < 1) {
                $validator->errors()->add('attempted_count', 'Toplu uygulama izleme kaydinda en az bir deneme olmalidir.');
            }

            if (($successfulCount + $failedCount) !== $attemptedCount) {
                $validator->errors()->add(
                    'successful_count',
                    'Successful ve failed sayilarinin toplami attempted count ile eslesmelidir.',
                );
            }
        });
    }
}
