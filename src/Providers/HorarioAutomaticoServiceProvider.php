<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Providers;

use iEducar\Packages\BuritiHorarioAutomatico\Console\HorarioDemoCommand;
use iEducar\Packages\BuritiHorarioAutomatico\Support\Identidade;
use iEducar\Packages\BuritiHorarioAutomatico\Support\MenuFluxo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class HorarioAutomaticoServiceProvider extends ServiceProvider
{
    public const string PACKAGE = 'buriti/i-educar-horario-automatico-package';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/horario.php', 'horario');
    }

    public function boot(): void
    {
        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\BuritiUi::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\BuritiUi::registrar();
        }
        $root = dirname(__DIR__, 2);
        $this->loadViewsFrom($root.'/resources/views', 'horario');

        if ($this->app->runningInConsole()) {
            if (\App\Support\Tenancy\PackageTenant::allows(self::PACKAGE)) {
                $this->loadMigrationsFrom($root.'/database/migrations');
            }
            $this->commands([HorarioDemoCommand::class]);
        }

        Route::middleware([
            'web',
            'ieducar.navigation',
            'ieducar.footer',
            'ieducar.xssbypass',
            'ieducar.suspended',
            'auth',
            'ieducar.checkresetpassword',
        ])->group($root.'/routes/web.php');

        View::composer('horario::*', function ($view): void {
            if (class_exists(\iEducar\Packages\BuritiRhEducacional\Support\RhAgregacao::class)
                && \iEducar\Packages\BuritiRhEducacional\Support\RhAgregacao::ativa()) {
                $view->with('identidade', \iEducar\Packages\BuritiRhEducacional\Support\RhAgregacao::identidade());
                $view->with('menuGrupos', \iEducar\Packages\BuritiRhEducacional\Support\RhAgregacao::menuGrupos());
                $view->with('homeModulo', url(\iEducar\Packages\BuritiRhEducacional\Support\RhAgregacao::homeModulo()));
                $view->with('nomeModulo', 'RH educacional');

                return;
            }
            $view->with('identidade', Identidade::tokens());
            $view->with('menuGrupos', MenuFluxo::grupos([
                'painel' => true,
                'diretivas' => true,
                'gerar' => true,
                'grade' => true,
                'choques' => true,
                'cargos' => true,
                'relatorio' => true,
                'matriz' => true,
                'validar' => true,
                'fazer-valer' => true,
            ]));
        });
    }
}
