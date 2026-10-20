<?php

namespace App\Dto;

/**
 * Hasil satu kasus uji pada penilaian (submission atau kartu bug).
 */
final readonly class GradingCaseResult
{
    public function __construct(
        public int $caseId,
        public bool $isPassed,
        public ?string $actualValue,
    ) {}
}
