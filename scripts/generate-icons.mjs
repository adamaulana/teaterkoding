import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const sourceDir = join(root, 'node_modules', '@tabler', 'icons', 'icons', 'outline');
const outputDir = join(root, 'resources', 'views', 'components', 'ui', 'icon');

/**
 * Daftar ikon Tabler (outline) yang dipakai aplikasi.
 * Nama file = nama komponen Blade, contoh: <x-ui.icon.layout-dashboard />
 */
const icons = [
    // Menu sidebar
    'layout-dashboard',
    'school',
    'users',
    'users-group',
    'id-badge-2',
    'stairs',
    'target',
    'layout-grid',
    'bug',
    'broadcast',
    'history',
    'trophy',
    'clipboard-list',
    'user-circle',
    'user-cog',
    // Umum / UI
    'search',
    'plus',
    'pencil',
    'trash',
    'x',
    'check',
    'circle-x',
    'circle-check',
    'alert-triangle',
    'alert-circle',
    'info-circle',
    'chevron-down',
    'chevron-left',
    'chevron-right',
    'logout',
    'menu-2',
    'eye',
    'eye-off',
    'download',
    'refresh',
    'arrow-up',
    'arrow-down',
    'arrow-badge-right',
    'dots-vertical',
    'calendar',
    'link',
    'shield-check',
    'lock',
    'mail',
    'key',
    'external-link',
    'home',
    'book',
    'chart-bar',
    'list-check',
    'user',
    'user-plus',
    'user-minus',
    'user-check',
    'filter',
    'cards',
    'settings',
    'medal',
    'star',
    'code',
    'presentation',
    'table',
    'checkup-list',
    'clipboard-check',
    'rotate',
    // Ikon Kartu Konsep
    'variable',
    'math-function',
    'git-branch',
    'repeat',
    'braces',
    // Ikon Materi tambahan
    'arrows-shuffle',
];

rmSync(outputDir, { recursive: true, force: true });
mkdirSync(outputDir, { recursive: true });

let generated = 0;
const missing = [];

for (const name of icons) {
    let raw;

    try {
        raw = readFileSync(join(sourceDir, `${name}.svg`), 'utf8').trim();
    } catch {
        missing.push(name);
        continue;
    }

    const match = raw.match(/^<svg[^>]*>([\s\S]*)<\/svg>$/);

    if (!match) {
        missing.push(name);
        continue;
    }

    const inner = match[1]
        .trim()
        .split('\n')
        .map((line) => `    ${line.trim()}`)
        .join('\n');

    const blade = `{{-- Ikon di-generate dari paket npm @tabler/icons (MIT). Jangan edit manual. --}}
{{-- Jalankan ulang dengan: node scripts/generate-icons.mjs --}}
<svg {{ $attributes->merge([
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => 2,
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
    'class' => 'size-5 shrink-0',
]) }} aria-hidden="true">
${inner}
</svg>
`;

    writeFileSync(join(outputDir, `${name}.blade.php`), blade, 'utf8');
    generated += 1;
}

console.log(`Ikon berhasil digenerate: ${generated}`);

if (missing.length > 0) {
    console.error(`Ikon TIDAK ditemukan di @tabler/icons: ${missing.join(', ')}`);
    process.exitCode = 1;
}
