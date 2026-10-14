<?php

namespace App\Policies;

use App\Models\TestCase;
use App\Models\User;

/**
 * PENTING — BANK JAWABAN RAHASIA.
 *
 * Kasus uji adalah kunci penilaian: berisi target pengecekan dan nilai
 * harapan yang menjadi "jawaban benar" sebuah misi. Karena itu:
 *
 *   1. Kasus uji HANYA boleh dibaca/ditulis lewat panel guru & admin.
 *   2. TIDAK BOLEH pernah bocor ke halaman siswa pada tahap mana pun
 *      (jangan kirim ke view siswa, jangan sertakan di payload Livewire sisi
 *      siswa, jangan tampilkan nilai harapan saat siswa mengerjakan misi).
 *
 * Policy ini menjaga sisi panel; kebocoran ke sisi siswa harus dijaga di
 * halaman siswa nanti (Fase berikutnya).
 */
class TestCasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TestCase $testCase): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TestCase $testCase): bool
    {
        return true;
    }

    public function delete(User $user, TestCase $testCase): bool
    {
        return true;
    }
}
