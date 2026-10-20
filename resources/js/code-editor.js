import { EditorView, keymap } from '@codemirror/view';
import { EditorState, Prec } from '@codemirror/state';
import { basicSetup } from 'codemirror';
import { python } from '@codemirror/lang-python';
import { indentWithTab } from '@codemirror/commands';
import { indentUnit } from '@codemirror/language';

/**
 * Lebar satu tingkat inden Python di materi ini: 4 spasi.
 *
 * PENTING: `indentUnit` CodeMirror default-nya DUA spasi. Tanpa facet ini,
 * Tab akan menyisipkan 2 spasi meskipun `tabSize` sudah 4.
 */
export const UKURAN_INDEN = '    ';

/**
 * Tema terang yang selaras dengan warna aplikasi.
 * Dibuat sekali di level modul supaya tidak dialokasikan ulang tiap editor.
 */
const TEMA = EditorView.theme({
    '&': {
        fontSize: '14px',
        fontFamily: "'JetBrains Mono', 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
        backgroundColor: '#f6fbf8',
        color: '#1f2937',
    },
    '.cm-content': { caretColor: '#3fa56b' },
    '.cm-gutters': {
        backgroundColor: '#eef6f0',
        color: '#94a3b8',
        border: 'none',
    },
    '.cm-activeLine': { backgroundColor: '#eaf5ee' },
    '.cm-activeLineGutter': { backgroundColor: '#e2f0e7', color: '#3fa56b' },
});

/**
 * Merakit daftar ekstensi CodeMirror 6.
 *
 * Dipisah dari pembuatan `EditorView` agar bisa diuji di Node tanpa DOM
 * (`npm run uji:editor`). Setiap elemen HARUS berupa `Extension` yang sah.
 *
 * Jebakan yang pernah terjadi: `indentWithTab` diekspor `@codemirror/commands`
 * sebagai objek `KeyBinding` MENTAH (`{ key: 'Tab', run, shift }`), bukan
 * ekstensi. Menaruhnya langsung di `extensions` memicu
 * "Unrecognized extension value in extension set ([object Object])".
 * Karena itu wajib dibungkus `keymap.of([...])`.
 *
 * `Prec.high` menaikkan prioritas keymap ini di atas `defaultKeymap` milik
 * `basicSetup`, sehingga Tab selalu menang (tidak pindah fokus).
 *
 * @param {(value: string) => void} [onChange] dipanggil tiap dokumen berubah
 * @returns {import('@codemirror/state').Extension[]}
 */
export function buatEkstensi(onChange) {
    const ekstensi = [
        basicSetup,
        python(),
        EditorState.tabSize.of(4),
        indentUnit.of(UKURAN_INDEN),
        Prec.high(keymap.of([indentWithTab])),
        TEMA,
    ];

    if (typeof onChange === 'function') {
        ekstensi.push(
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    onChange(update.state.doc.toString());
                }
            }),
        );
    }

    return ekstensi;
}

/**
 * Inisialisasi editor CodeMirror 6 untuk sisi siswa.
 *
 * - Bahasa Python, nomor baris, tema terang (selaras warna aplikasi).
 * - Tab menyisipkan 4 spasi, Shift-Tab mengurangi satu tingkat.
 * - Dibungkus wire:ignore di Blade; perubahan diteruskan ke komponen
 *   Livewire lewat callback (autosave debounce ada di sisi komponen).
 *
 * TIDAK PERNAH melempar galat. Bila editor gagal dibuat (mis. ekstensi tidak
 * sah, atau browser tidak mendukung), galat dicatat lewat `console.error`,
 * `opsi.onGagal` dipanggil, dan fungsi mengembalikan `null` supaya pemanggil
 * menampilkan textarea cadangan. Siswa tidak boleh kehilangan tempat mengetik.
 *
 * @param {HTMLElement} el  elemen container
 * @param {string} initial teks awal
 * @param {(value: string) => void} onChange dipanggil tiap perubahan
 * @param {{ onGagal?: (galat: unknown) => void }} [opsi]
 * @returns {{ setValue: (v: string) => void, view: EditorView, adalahCadangan: boolean } | null}
 */
export function createCodeEditor(el, initial, onChange, opsi = {}) {
    const { onGagal } = opsi;

    try {
        const state = EditorState.create({
            doc: initial ?? '',
            extensions: buatEkstensi(onChange),
        });

        const view = new EditorView({
            state,
            parent: el,
        });

        return {
            view,
            adalahCadangan: false,
            setValue(value) {
                view.dispatch({
                    changes: { from: 0, to: view.state.doc.length, insert: value ?? '' },
                });
            },
        };
    } catch (galat) {
        console.error('[teater] Editor kode gagal dimuat:', galat);

        // Bersihkan sisa DOM agar container tidak menampilkan editor separuh jadi.
        try {
            el?.replaceChildren?.();
        } catch {
            /* abaikan: container mungkin sudah tidak ada di DOM */
        }

        if (typeof onGagal === 'function') {
            onGagal(galat);
        }

        return null;
    }
}
