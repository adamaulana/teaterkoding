<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Form tambah/ubah kelompok.
 *
 * Nama kelompok unik per kelas (aturan bisnis; DB hanya unik global bila
 * indeks ditambahkan).
 */
class KelompokForm extends Form
{
    public ?int $teamId = null;

    public ?int $class_id = null;

    public string $team_name = '';

    public function setKelompok(Team $team): void
    {
        $this->teamId = $team->team_id;
        $this->class_id = $team->class_id;
        $this->team_name = $team->team_name;
    }

    public function resetForm(): void
    {
        $this->reset();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $uniqueName = Rule::unique('teams', 'team_name')
            ->where('class_id', $this->class_id);

        if ($this->teamId !== null) {
            $uniqueName->ignore($this->teamId, 'team_id');
        }

        return [
            'class_id' => ['required', 'integer', Rule::exists('classes', 'class_id')],
            'team_name' => ['required', 'string', 'max:50', $uniqueName],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_id.required' => 'Kelas wajib dipilih.',
            'class_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'team_name.required' => 'Nama kelompok wajib diisi.',
            'team_name.max' => 'Nama kelompok maksimal 50 karakter.',
            'team_name.unique' => 'Nama kelompok ini sudah dipakai di kelas tersebut.',
        ];
    }
}
