<?php

namespace App\Providers;

use App\Contracts\GradingRunner;
use App\Grading\FakeGradingRunner;
use App\Grading\NullGradingRunner;
use App\Models\User;
use App\Services\AntreanVerifikasi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerGradingRunner();

        // Antrean verifikasi menyimpan cache per pengguna; `scoped` menjamin
        // cache itu hidup hanya selama satu request (aman untuk queue/Octane
        // dan tidak bocor antar-test).
        $this->app->scoped(AntreanVerifikasi::class, fn () => new AntreanVerifikasi);
    }

    /**
     * Pilih penilai otomatis sesuai config('teater.runner').
     *
     *   'fake'    → FakeGradingRunner (HANYA local/testing)
     *   'pyodide' → NullGradingRunner; penilaian terjadi di BROWSER siswa.
     *               Tidak ada kode siswa yang dijalankan di server.
     *   null      → NullGradingRunner (tidak menilai)
     */
    protected function registerGradingRunner(): void
    {
        $this->app->bind(GradingRunner::class, function () {
            return config('teater.runner') === 'fake'
                ? new FakeGradingRunner
                : new NullGradingRunner;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $this->configureAuthorization();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Admin memiliki akses penuh ke semua resource yang dijaga Policy.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->role === 'admin' ? true : null;
        });
    }
}
