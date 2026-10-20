/**
 * Uji editor kode siswa (CodeMirror 6) TANPA browser.
 *
 * Latar belakang: langkah Koding sempat gagal total dengan
 *   "Unrecognized extension value in extension set ([object Object])"
 * karena `indentWithTab` (objek KeyBinding mentah) ditaruh langsung di
 * `extensions` alih-alih dibungkus `keymap.of([...])`.
 *
 * Yang diperiksa:
 *   (a) `buatEkstensi()` menghasilkan ekstensi SAH (EditorState.create tidak melempar)
 *   (b) indentUnit = 4 spasi & tabSize = 4 (bukan default 2 spasi CodeMirror)
 *   (c) Tab menyisipkan tepat 4 spasi; Shift-Tab menguranginya
 *   (d) perubahan dokumen diteruskan ke callback (dasar autosave)
 *   (e) `createCodeEditor` tidak melempar saat gagal: mengembalikan null,
 *       memanggil onGagal, dan mencatat console.error (dasar textarea cadangan)
 *
 * Jalankan: npm run uji:editor
 */
import { EditorState } from '@codemirror/state';
import { EditorView, keymap } from '@codemirror/view';
import { indentUnit } from '@codemirror/language';
import { indentLess } from '@codemirror/commands';
import { buatEkstensi, createCodeEditor, UKURAN_INDEN } from '../resources/js/code-editor.js';

let lulus = 0;
let gagal = 0;

/** Catat hasil satu pemeriksaan. */
function periksa(nama, kondisi, detail = '') {
    if (kondisi) {
        lulus += 1;
        console.log(`  OK    ${nama}`);

        return;
    }

    gagal += 1;
    console.log(`  GAGAL ${nama}${detail === '' ? '' : ` — ${detail}`}`);
}

/** Jalankan satu perintah keymap terhadap state dan kembalikan dokumen hasilnya. */
function jalankanPerintah(perintah, state) {
    let transaksi = null;
    const ditangani = perintah({
        state,
        dispatch: (tr) => {
            transaksi = tr;
        },
    });

    return { ditangani, dokumen: transaksi === null ? null : transaksi.state.doc.toString() };
}

/** Ambil binding 'Tab' dari keymap berprioritas tertinggi. */
function bindingTab(state) {
    for (const daftar of state.facet(keymap)) {
        const binding = daftar.find((b) => b.key === 'Tab');

        if (binding) {
            return binding;
        }
    }

    return null;
}

// ---------- (a) Ekstensi sah ----------
console.log('(a) buatEkstensi() menghasilkan ekstensi yang sah');

let state = null;
let galatState = null;

try {
    state = EditorState.create({ doc: 'print("halo")', extensions: buatEkstensi() });
} catch (galat) {
    galatState = galat;
}

periksa(
    'EditorState.create tidak melempar (regresi "Unrecognized extension value")',
    galatState === null,
    galatState?.message ?? '',
);

periksa('dokumen awal terpasang', state?.doc.toString() === 'print("halo")', state?.doc.toString() ?? 'state null');

// ---------- (b) Ukuran inden ----------
console.log('\n(b) Inden 4 spasi (default CodeMirror 2 spasi)');

periksa('UKURAN_INDEN berisi tepat 4 spasi', UKURAN_INDEN === '    ', JSON.stringify(UKURAN_INDEN));
periksa('facet indentUnit = 4 spasi', state?.facet(indentUnit) === '    ', JSON.stringify(state?.facet(indentUnit)));
periksa('facet tabSize = 4', state?.facet(EditorState.tabSize) === 4, String(state?.facet(EditorState.tabSize)));

// ---------- (c) Tab / Shift-Tab ----------
console.log('\n(c) Tab menyisipkan 4 spasi, Shift-Tab menguranginya');

const tab = state === null ? null : bindingTab(state);

periksa('binding Tab terdaftar di keymap', tab !== null);

if (tab) {
    const awal = EditorState.create({ doc: 'x = 1', selection: { anchor: 0 }, extensions: buatEkstensi() });
    const hasil = jalankanPerintah(tab.run, awal);

    periksa('Tab di awal baris menyisipkan 4 spasi', hasil.dokumen === '    x = 1', JSON.stringify(hasil.dokumen));
    periksa('Tab ditandai ditangani (fokus tidak berpindah)', hasil.ditangani === true);

    const seleksi = EditorState.create({
        doc: 'a\nb',
        selection: { anchor: 0, head: 3 },
        extensions: buatEkstensi(),
    });
    const hasilSeleksi = jalankanPerintah(tab.run, seleksi);

    periksa(
        'Tab pada seleksi meng-inden semua baris terpilih',
        hasilSeleksi.dokumen === '    a\n    b',
        JSON.stringify(hasilSeleksi.dokumen),
    );

    const berinden = EditorState.create({ doc: '    x = 1', extensions: buatEkstensi() });
    const hasilKurang = jalankanPerintah(indentLess, berinden);

    periksa(
        'Shift-Tab mengurangi satu tingkat inden',
        hasilKurang.dokumen === 'x = 1',
        JSON.stringify(hasilKurang.dokumen),
    );
}

// ---------- (d) Callback perubahan ----------
console.log('\n(d) Perubahan dokumen diteruskan ke callback (dasar autosave)');

let nilaiTerakhir = null;
const denganListener = EditorState.create({
    doc: '',
    extensions: buatEkstensi((value) => {
        nilaiTerakhir = value;
    }),
});

const listener = denganListener.facet(EditorView.updateListener);

periksa('updateListener terdaftar', listener.length === 1, `jumlah=${listener.length}`);

listener.forEach((fn) => fn({ docChanged: true, state: { doc: { toString: () => 'print(1)' } } }));
periksa('callback menerima dokumen terbaru', nilaiTerakhir === 'print(1)', String(nilaiTerakhir));

nilaiTerakhir = 'BELUM';
listener.forEach((fn) => fn({ docChanged: false, state: { doc: { toString: () => 'diabaikan' } } }));
periksa('callback tidak dipanggil bila dokumen tidak berubah', nilaiTerakhir === 'BELUM', String(nilaiTerakhir));

periksa(
    'updateListener tidak ditambahkan tanpa callback',
    EditorState.create({ extensions: buatEkstensi() }).facet(EditorView.updateListener).length === 0,
);

// ---------- (e) Jalur gagal ----------
console.log('\n(e) Kegagalan membuat editor tidak melempar (dasar textarea cadangan)');

const consoleErrorAsli = console.error;
const pesanConsole = [];
console.error = (...args) => {
    pesanConsole.push(args.map(String).join(' '));
};

let onGagalDipanggil = false;
let hasilGagal = 'tidak-dipanggil';
let melempar = null;

try {
    // `el` tidak sah (bukan elemen DOM) → EditorView gagal dibuat di Node.
    hasilGagal = createCodeEditor({}, 'x = 1', () => {}, {
        onGagal: () => {
            onGagalDipanggil = true;
        },
    });
} catch (galat) {
    melempar = galat;
}

console.error = consoleErrorAsli;

periksa('createCodeEditor tidak melempar galat', melempar === null, melempar?.message ?? '');
periksa('createCodeEditor mengembalikan null saat gagal', hasilGagal === null, String(hasilGagal));
periksa('opsi.onGagal dipanggil', onGagalDipanggil === true);
periksa(
    'galat dicatat lewat console.error',
    pesanConsole.some((p) => p.includes('Editor kode gagal dimuat')),
    pesanConsole.join(' | '),
);

// ---------- Ringkasan ----------
console.log(`\n${lulus} lulus, ${gagal} gagal`);

if (gagal > 0) {
    console.log('HASIL: GAGAL');
    process.exitCode = 1;
} else {
    console.log('HASIL: LULUS');
}
