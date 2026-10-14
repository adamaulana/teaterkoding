<?php

namespace App\Livewire\Forms;

use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Form tambah/ubah siswa.
 *
 * NIS bersifat opsional; bila diisi harus unik di dalam kelas yang sama
 * (aturan unik per-kelas karena DB hanya menjamin unik global bila ditambah
 * indeks — aturan bisnisnya adalah unik per kelas).
 */
class SiswaForm extends Form
{
    public ?int $studentId = null;

    /** Kelas siswa saat ini (tidak berubah lewat form ubah). */
    public ?int $class_id = null;

    public string $full_name = '';

    public string $student_no = '';

    public function setSiswa(Student $siswa): void
    {
        $this->studentId = $siswa->student_id;
        $this->class_id = $siswa->class_id;
        $this->full_name = $siswa->full_name;
        $this->student_no = $siswa->student_no ?? '';
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
        $uniqueNis = Rule::unique('students', 'student_no')
            ->where('class_id', $this->class_id);

        if ($this->studentId !== null) {
            $uniqueNis->ignore($this->studentId, 'student_id');
        }

        return [
            'class_id' => ['required', 'integer', Rule::exists('classes', 'class_id')],
            'full_name' => ['required', 'string', 'max:100'],
            'student_no' => ['nullable', 'string', 'max:30', $uniqueNis],
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
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.max' => 'Nama lengkap maksimal 100 karakter.',
            'student_no.max' => 'NIS/NISN maksimal 30 karakter.',
            'student_no.unique' => 'NIS ini sudah dipakai di kelas tersebut.',
        ];
    }
}
