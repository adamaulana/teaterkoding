<?php

namespace App\Dto;

use App\Models\Submission;

/**
 * Hasil keseluruhan penilaian dari GradingRunner.
 *
 * status memakai nilai yang sama dengan submissions.status / bug_attempts
 * .grading_status: menunggu | dinilai | galat | waktu_habis.
 */
final readonly class GradingResult
{
    /**
     * @param  array<int, GradingCaseResult>  $cases
     */
    public function __construct(
        public string $status,
        public array $cases = [],
        public ?string $errorMessage = null,
        public ?int $runtimeMs = null,
    ) {}

    public static function menunggu(): self
    {
        return new self(Submission::STATUS_MENUNGGU);
    }

    public static function dinilai(array $cases, ?int $runtimeMs = null): self
    {
        return new self(Submission::STATUS_DINILAI, $cases, null, $runtimeMs);
    }

    public static function galat(?string $pesan = null): self
    {
        return new self(Submission::STATUS_GALAT, [], $pesan);
    }

    public static function waktuHabis(?string $pesan = null): self
    {
        return new self(Submission::STATUS_WAKTU_HABIS, [], $pesan);
    }
}
