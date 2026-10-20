/**
 * Skrip landing page (kecil, tanpa dependensi).
 *
 * Isi:
 *   1. menu mobile yang aksesibel (aria-expanded, Esc, klik di luar),
 *   2. scroll-spy untuk panel penanda posisi (IntersectionObserver),
 *   3. gulir halus untuk tautan anchor (hormati prefers-reduced-motion),
 *   4. reveal saat bagian terlihat,
 *   5. bilah progres scroll untuk layar kecil.
 *
 * === YANG SENGAJA TIDAK ADA ===
 * CodeMirror, Pyodide, Livewire, dan bundel panel. Landing page tidak boleh
 * memuatnya — lihat layouts/landing.blade.php.
 *
 * === PROGRESSIVE ENHANCEMENT ===
 * Semua efek visual bergantung pada kelas `js` yang dipasang di <head>. Bila
 * skrip ini gagal dimuat, konten tetap tampil penuh (tidak ada yang permanen
 * tersembunyi).
 */

const kurangiGerak = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------------ *
 * 1. Menu mobile
 * ------------------------------------------------------------------ */
function siapkanMenu() {
    const tombol = document.querySelector('[data-landing-menu-tombol]');
    const panel = document.querySelector('[data-landing-menu]');
    const ikonBuka = document.querySelector('[data-landing-menu-ikon-buka]');
    const ikonTutup = document.querySelector('[data-landing-menu-ikon-tutup]');

    if (! tombol || ! panel) {
        return;
    }

    const setTerbuka = (terbuka) => {
        tombol.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
        tombol.setAttribute('aria-label', terbuka ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        panel.classList.toggle('hidden', ! terbuka);
        ikonBuka?.classList.toggle('hidden', terbuka);
        ikonTutup?.classList.toggle('hidden', ! terbuka);
    };

    tombol.addEventListener('click', () => {
        setTerbuka(tombol.getAttribute('aria-expanded') !== 'true');
    });

    // Tutup dengan Esc, lalu kembalikan fokus ke tombol.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && tombol.getAttribute('aria-expanded') === 'true') {
            setTerbuka(false);
            tombol.focus();
        }
    });

    // Tutup saat tautan di dalam panel diklik.
    panel.querySelectorAll('[data-landing-menu-tautan]').forEach((tautan) => {
        tautan.addEventListener('click', () => setTerbuka(false));
    });

    // Tutup saat klik di luar panel.
    document.addEventListener('click', (event) => {
        if (tombol.getAttribute('aria-expanded') !== 'true') {
            return;
        }

        if (! panel.contains(event.target) && ! tombol.contains(event.target)) {
            setTerbuka(false);
        }
    });

    // Layar diperbesar ke >= md: pastikan panel tidak tertinggal terbuka.
    window.matchMedia('(min-width: 768px)').addEventListener('change', (event) => {
        if (event.matches) {
            setTerbuka(false);
        }
    });
}

/* ------------------------------------------------------------------ *
 * 2. Scroll-spy (penanda posisi)
 * ------------------------------------------------------------------ */
function siapkanPenanda() {
    const titik = Array.from(document.querySelectorAll('[data-landing-titik]'));

    if (titik.length === 0 || typeof IntersectionObserver !== 'function') {
        return;
    }

    const bagian = titik
        .map((tautan) => {
            const id = tautan.getAttribute('data-landing-titik');
            const el = document.getElementById(id);

            return el ? { id, el, tautan } : null;
        })
        .filter(Boolean);

    const tandai = (id) => {
        for (const satu of bagian) {
            const aktif = satu.id === id;
            satu.tautan.setAttribute('aria-current', aktif ? 'true' : 'false');
            satu.tautan
                .querySelector('[data-landing-titik-tanda]')
                ?.setAttribute('data-aktif', aktif ? 'true' : 'false');
        }
    };

    const terlihat = new Map();

    const pengamat = new IntersectionObserver(
        (entri) => {
            for (const satu of entri) {
                terlihat.set(satu.target.id, satu.isIntersecting ? satu.intersectionRatio : 0);
            }

            // Bagian dengan rasio terlihat terbesar menjadi aktif.
            let terpilih = null;
            let rasioTerbaik = 0;

            for (const [id, rasio] of terlihat.entries()) {
                if (rasio > rasioTerbaik) {
                    rasioTerbaik = rasio;
                    terpilih = id;
                }
            }

            if (terpilih !== null) {
                tandai(terpilih);
            }
        },
        { rootMargin: '-20% 0px -55% 0px', threshold: [0, 0.15, 0.35, 0.6, 1] },
    );

    for (const satu of bagian) {
        pengamat.observe(satu.el);
    }

    // Saat berada paling atas, tandai bagian pertama.
    tandai(bagian[0].id);
}

/* ------------------------------------------------------------------ *
 * 3. Gulir halus + 4. reveal + 5. progres scroll
 * ------------------------------------------------------------------ */
function siapkanGulirDanReveal() {
    // Gulir halus: hanya untuk tautan anchor internal.
    document.querySelectorAll('a[href^="#"]').forEach((tautan) => {
        tautan.addEventListener('click', (event) => {
            const id = tautan.getAttribute('href')?.slice(1);

            if (! id) {
                return;
            }

            const target = document.getElementById(id);

            if (! target) {
                return;
            }

            event.preventDefault();

            target.scrollIntoView({
                behavior: kurangiGerak() ? 'auto' : 'smooth',
                block: 'start',
            });

            // Perbarui riwayat tanpa melompat (tetap bisa di-bookmark).
            history.replaceState(null, '', `#${id}`);

            // Pindahkan fokus agar pembaca layar ikut berpindah bagian.
            target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
        });
    });

    // Reveal saat bagian terlihat.
    const elemenReveal = Array.from(document.querySelectorAll('.reveal'));

    if (elemenReveal.length === 0) {
        return;
    }

    if (typeof IntersectionObserver !== 'function' || kurangiGerak()) {
        elemenReveal.forEach((el) => el.classList.add('reveal-tampil'));

        return;
    }

    const pengamat = new IntersectionObserver(
        (entri) => {
            for (const satu of entri) {
                if (! satu.isIntersecting) {
                    continue;
                }

                satu.target.classList.add('reveal-tampil');
                pengamat.unobserve(satu.target);
            }
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.08 },
    );

    elemenReveal.forEach((el) => pengamat.observe(el));
}

function siapkanProgres() {
    const bilah = document.querySelector('[data-landing-progres]');

    if (! bilah) {
        return;
    }

    const perbarui = () => {
        const tinggi = document.documentElement.scrollHeight - window.innerHeight;
        const persen = tinggi > 0 ? Math.min(100, Math.max(0, (window.scrollY / tinggi) * 100)) : 0;

        bilah.style.width = `${persen}%`;
    };

    perbarui();
    window.addEventListener('scroll', perbarui, { passive: true });
    window.addEventListener('resize', perbarui);
}

/* ------------------------------------------------------------------ *
 * 6. Segmen grafik penilaian terisi saat terlihat
 * ------------------------------------------------------------------ */
function siapkanSegmen() {
    const segmen = Array.from(document.querySelectorAll('[data-landing-segmen]'));

    if (segmen.length === 0) {
        return;
    }

    // Tanpa JS / tanpa IntersectionObserver / reduced motion: isi langsung.
    if (typeof IntersectionObserver !== 'function' || kurangiGerak()) {
        segmen.forEach((el) => {
            el.style.width = `${el.dataset.persen ?? 0}%`;
        });

        return;
    }

    const pengamat = new IntersectionObserver(
        (entri) => {
            for (const satu of entri) {
                if (! satu.isIntersecting) {
                    continue;
                }

                // Beri jeda kecil berurutan agar terlihat seperti grafik "mengisi".
                const daftar = Array.from(satu.target.parentElement?.children ?? []);

                daftar.forEach((el, index) => {
                    window.setTimeout(() => {
                        el.style.width = `${el.dataset.persen ?? 0}%`;
                    }, index * 90);
                });

                pengamat.unobserve(satu.target);
            }
        },
        { threshold: 0.4 },
    );

    segmen.forEach((el) => pengamat.observe(el));
}

/* ------------------------------------------------------------------ *
 * Inisialisasi
 * ------------------------------------------------------------------ */
function mulai() {
    siapkanMenu();
    siapkanPenanda();
    siapkanGulirDanReveal();
    siapkanProgres();
    siapkanSegmen();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mulai, { once: true });
} else {
    mulai();
}
