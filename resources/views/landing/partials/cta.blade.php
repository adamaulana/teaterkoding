@php
    // Tombol dinamis dari satu sumber (App\Support\AksiLanding) agar navbar,
    // hero, dan CTA penutup tidak pernah berbeda.
    $aksi = \App\Support\AksiLanding::tombol();
    $aksiUtama = $aksi['utama'];
    $aksiSekunder = $aksi['sekunder'];
@endphp

{{-- CTA penutup --}}
<section class="relative overflow-hidden border-t border-line bg-primary-50/70">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
        <svg class="absolute inset-0 size-full opacity-40" aria-hidden="true">
            <defs>
                <pattern id="pola-titik-cta" width="24" height="24" patternUnits="userSpaceOnUse">
                    <circle cx="1.5" cy="1.5" r="1.5" fill="#bde7cd"></circle>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#pola-titik-cta)"></rect>
        </svg>
    </div>

    <div class="mx-auto w-full max-w-screen-xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="reveal mx-auto max-w-2xl text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white px-3 py-1 text-xs font-semibold text-primary-700">
                <x-ui.icon.presentation class="size-3.5" />
                Teater Koding
            </span>

            <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
                Siap memulai misi pertamamu?
            </h2>

            <p class="mt-3 text-base text-slate-500">
                Masuk dengan kode akses dari gurumu, atau buka dashboard bila kamu mengajar.
            </p>

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.tautan-tombol :href="$aksiUtama['url']" variant="primary" size="lg">
                    {{ $aksiUtama['label'] }}
                    <x-dynamic-component :component="'ui.icon.'.$aksiUtama['ikon']" class="size-4" />
                </x-ui.tautan-tombol>

                @if ($aksiSekunder !== null)
                    <x-ui.tautan-tombol :href="$aksiSekunder['url']" variant="outline" size="lg">
                        {{ $aksiSekunder['label'] }}
                    </x-ui.tautan-tombol>
                @endif
            </div>
        </div>
    </div>
</section>
