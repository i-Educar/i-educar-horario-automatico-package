<?php

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Packages\BuritiHorarioAutomatico\Support\Processos;
use iEducar\Packages\BuritiLagunaAmbiente\Support\PermissaoMatriz;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $raiz = Menu::query()->where('process', Processos::RAIZ)->first();
        if ($raiz === null) {
            return;
        }

        $operacao = Menu::query()->updateOrCreate(
            ['parent_id' => $raiz->getKey(), 'title' => 'Operação'],
            ['order' => 2, 'type' => 2, 'process' => null, 'active' => true, 'link' => null],
        );

        Menu::query()->updateOrCreate(['process' => Processos::FAZER_VALER], [
            'parent_id' => $operacao->getKey(),
            'title' => 'Fazer valer',
            'description' => 'Publicar grade vigente e propagar alocações/diário',
            'link' => '/horario-automatico',
            'order' => 8,
            'type' => 3,
            'active' => true,
        ]);
        Menu::query()->updateOrCreate(['process' => Processos::NOVA_VERSAO], [
            'parent_id' => $operacao->getKey(),
            'title' => 'Nova versão',
            'description' => 'Abrir rascunho clonando a vigente sem apagar histórico',
            'link' => '/horario-automatico/grade',
            'order' => 9,
            'type' => 3,
            'active' => true,
        ]);

        PermissaoMatriz::conceder(
            [Processos::FAZER_VALER, Processos::NOVA_VERSAO],
            [1, 2, 3, 4, 12],
        );

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        //
    }
};
