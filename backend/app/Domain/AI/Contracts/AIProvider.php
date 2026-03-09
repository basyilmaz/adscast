<?php

namespace App\Domain\AI\Contracts;

interface AIProvider
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function generate(string $template, array $context): array;
}
