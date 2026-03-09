<?php

namespace App\Domain\Rules\DTO;

class RuleSignal
{
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $summary,
        public readonly string $explanation,
        public readonly string $recommendedAction,
        public readonly float $confidence,
        public readonly string $entityType,
        public readonly ?string $entityId,
        public readonly string $dateDetected,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'summary' => $this->summary,
            'explanation' => $this->explanation,
            'recommended_action' => $this->recommendedAction,
            'confidence' => $this->confidence,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'date_detected' => $this->dateDetected,
        ];
    }
}
