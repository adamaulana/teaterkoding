<?php

namespace App\Livewire\Forms;

use App\Models\SchoolClass;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Form tambah/ubah kelas.
 *
 * Dipakai halaman Kelas: guru otomatis menjadi pemilik (user_id),
 * admin boleh memilih pemilik dari daftar pengguna.
 */
class KelasForm extends Form
{
    public ?int $classId = null;

    public string $class_name = '';

    public string $school_year = '';

    /** Pemilik kelas (hanya relevan untuk admin). */
    public ?int $user_id = null;

    public function setKelas(SchoolClass $kelas): void
    {
        $this->classId = $kelas->class_id;
        $this->class_name = $kelas->class_name;
        $this->school_year = $kelas->school_year;
        $this->user_id = $kelas->user_id;
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
        return [
            'class_name' => ['required', 'string', 'max:50'],
            'school_year' => ['required', 'string', 'max:20'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_name.required' => 'Nama kelas wajib diisi.',
            'class_name.max' => 'Nama kelas maksimal 50 karakter.',
            'school_year.required' => 'Tahun ajaran wajib diisi.',
            'school_year.max' => 'Tahun ajaran maksimal 20 karakter.',
            'user_id.exists' => 'Pemilik kelas yang dipilih tidak ditemukan.',
        ];
    }
}
