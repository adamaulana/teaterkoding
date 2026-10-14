<?php

namespace App\Livewire\Forms;

use App\Models\Mission;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Form tambah/ubah misi.
 *
 * Kode unlock dinormalisasi ke huruf besar; hanya huruf, angka, dan tanda
 * hubung; maksimal 30 karakter; unik.
 */
class MisiForm extends Form
{
    public ?int $missionId = null;

    public ?int $level_id = null;

    public string $unlock_code = '';

    public string $client_brief = '';

    public function setMisi(Mission $mission): void
    {
        $this->missionId = $mission->mission_id;
        $this->level_id = $mission->level_id;
        $this->unlock_code = $mission->unlock_code;
        $this->client_brief = $mission->client_brief;
    }

    public function resetForm(): void
    {
        $this->reset();
    }

    public function updatedUnlockCode(string $value): void
    {
        $this->unlock_code = $this->normalisasi($value);
    }

    public function normalisasi(string $kode): string
    {
        $kode = mb_strtoupper(trim($kode));

        return mb_substr((string) preg_replace('/[^A-Z0-9-]/', '', $kode), 0, 30);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $unik = Rule::unique('missions', 'unlock_code');

        if ($this->missionId !== null) {
            $unik->ignore($this->missionId, 'mission_id');
        }

        return [
            'level_id' => ['required', 'integer', Rule::exists('levels', 'level_id')],
            'unlock_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', $unik],
            'client_brief' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'level_id.required' => 'Level wajib dipilih.',
            'level_id.exists' => 'Level yang dipilih tidak ditemukan.',
            'unlock_code.required' => 'Kode unlock wajib diisi.',
            'unlock_code.max' => 'Kode unlock maksimal 30 karakter.',
            'unlock_code.regex' => 'Kode unlock hanya boleh berisi huruf besar, angka, dan tanda hubung.',
            'unlock_code.unique' => 'Kode unlock ini sudah dipakai misi lain.',
            'client_brief.required' => 'Brief klien wajib diisi.',
            'client_brief.max' => 'Brief klien maksimal 5000 karakter.',
        ];
    }
}
