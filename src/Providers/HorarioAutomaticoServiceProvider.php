<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Providers;

use Illuminate\Support\ServiceProvider;

class HorarioAutomaticoServiceProvider extends ServiceProvider
{
    public const string PACKAGE = 'buriti/i-educar-horario-automatico-package';

    public function register(): void
    {
        // Services são value objects — instanciar com `new` nos callers (não bind no container).
    }

    public function boot(): void
    {
    }
}
