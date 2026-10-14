<?php

namespace App\Livewire\Forms;

use App\Actions\ValidasiKasusUji;
use App\Models\TestCase;
use Livewire\Form;

/**
 * Form tambah/ubah kasus uji.
 *
 * Validasi format dititipkan ke App\Actions\ValidasiKasusUji (murni) agar
 * aturan sandbox terpusat dan mudah diuji.
 */
class KasusUjiForm extends Form
{
    public ?int $testCaseId = null;

    public ?int $mission_id = null;

    public string $label = '';

    public string $setup_code = '';

    public string $check_target = '';

    public string $expected_value = '';

    public string $tolerance = '';

    public function setKasusUji(TestCase $kasus): void
    {
        $this->testCaseId = $kasus->test_case_id;
        $this->mission_id = $kasus->mission_id;
        $this->label = $kasus->label;
        $this->setup_code = (string) ($kasus->setup_code ?? '');
        $this->check_target = $kasus->check_target;
        $this->expected_value = $kasus->expected_value;
        $this->tolerance = $kasus->tolerance === null ? '' : (string) $kasus->tolerance;
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
        $batas = ValidasiKasusUji::batas();

        return [
            'mission_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:'.$batas['label']],
            'setup_code' => ['nullable', 'string', 'max:'.$batas['setup_code']],
            'check_target' => ['required', 'string', 'max:'.$batas['check_target']],
            'expected_value' => ['required', 'string', 'max:'.$batas['expected_value']],
            'tolerance' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Label kasus uji wajib diisi.',
            'check_target.required' => 'Target pengecekan wajib diisi.',
            'expected_value.required' => 'Nilai diharapkan wajib diisi.',
            'tolerance.numeric' => 'Toleransi harus berupa angka.',
            'tolerance.min' => 'Toleransi tidak boleh negatif.',
        ];
    }
}
