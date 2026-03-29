<?php

namespace App\Domain\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackApprovalRemediationFeaturedInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clusterKeys = [
            'manual-check-required',
            'retry-ready',
            'cleanup-recovered',
            'review-error',
        ];

        return [
            'featured_cluster_key' => ['required', 'string', Rule::in($clusterKeys)],
            'acted_cluster_key' => ['required', 'string', Rule::in($clusterKeys)],
            'interaction_type' => [
                'required',
                'string',
                Rule::in([
                    'focus_cluster',
                    'jump_to_item',
                    'manual_check_completed',
                    'publish_retry',
                    'bulk_retry_publish',
                ]),
            ],
            'followed_featured' => ['required', 'boolean'],
            'attempted_count' => ['nullable', 'integer', 'min:0'],
            'success_count' => ['nullable', 'integer', 'min:0'],
            'failure_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
