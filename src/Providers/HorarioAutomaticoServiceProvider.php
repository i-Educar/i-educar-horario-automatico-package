<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Providers;

use iEducar\Packages\BuritiHorarioAutomatico\Services\DetectorChoque;
use iEducar\Packages\BuritiHorarioAutomatico\Services\GradeSemanal;
use Illuminate\Support\ServiceProvider;

class HorarioAutomaticoServiceProvider extends ServiceProvider
{
    public const string PACKAGE = 'buriti/i-educar-horario-automatico-package';

    public function register(): void
    {
        $this->app->singleton(DetectorChoque::class);
        $this->app->singleton(GradeSemanal::class);
    }

    public function boot(): void
    {
    }
}
