<?php

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Packages\BuritiHorarioAutomatico\Support\Processos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $servidores = Menu::query()
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->where('process', 71)->orWhere('title', 'Servidores');
            })
            ->first();

        if ($servidores === null) {
            return;
        }

        // Após Cadastros (1); Relatórios sobe para 4.
        Menu::query()
            ->where('parent_id', $servidores->getKey())
            ->where('title', 'Relatórios')
            ->update(['order' => 4]);

        $raiz = Menu::query()->updateOrCreate(['process' => Processos::RAIZ], [
            'parent_id' => $servidores->getKey(),
            'title' => 'Quadro de horários',
            'description' => 'Atribuição por cargo/componente e detecção de conflitos de professor e turma',
            'link' => '/horario-automatico',
            'icon' => 'fa-calendar',
            'order' => 2,
            'type' => 2,
            'active' => true,
        ]);

        // Remove grupo intermediário "Operação" se existir (instalação anterior).
        $operacaoIds = Menu::query()
            ->where('parent_id', $raiz->getKey())
            ->where('title', 'Operação')
            ->whereNull('process')
            ->pluck('id');

        $links = [
            [Processos::PAINEL, 'Painel', '/horario-automatico', 'Visão da grade', 1],
            [Processos::GRADE, 'Grade semanal', '/horario-automatico/grade', 'Quadro por turma', 2],
            [Processos::CHOQUES, 'Conflitos', '/horario-automatico/choques', 'Choques detectados', 3],
            [Processos::CARGOS, 'Por cargo', '/horario-automatico/cargos', 'Carga conforme cargo', 4],
        ];

        foreach ($links as [$processo, $titulo, $link, $descricao, $ordem]) {
            Menu::query()->updateOrCreate(['process' => $processo], [
                'parent_id' => $raiz->getKey(),
                'title' => $titulo,
                'description' => $descricao,
                'link' => $link,
                'order' => $ordem,
                'type' => 3,
                'active' => true,
            ]);
        }

        if ($operacaoIds->isNotEmpty()) {
            Menu::query()->whereIn('id', $operacaoIds)->delete();
        }

        $processos = [Processos::RAIZ, Processos::PAINEL, Processos::GRADE, Processos::CHOQUES, Processos::CARGOS];
        $tipos = DB::table('pmieducar.tipo_usuario')->where('ativo', 1)->get(['cod_tipo_usuario']);
        foreach ($tipos as $tipo) {
            foreach ($processos as $processo) {
                $menuId = Menu::query()->where('process', $processo)->value('id');
                if ($menuId === null) {
                    continue;
                }
                DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                    ['ref_cod_tipo_usuario' => $tipo->cod_tipo_usuario, 'menu_id' => $menuId],
                    ['cadastra' => 1, 'visualiza' => 1, 'exclui' => 0],
                );
            }
        }

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        $raiz = Menu::query()->where('process', Processos::RAIZ)->first();
        if ($raiz === null) {
            return;
        }
        $folhas = Menu::query()->where('parent_id', $raiz->getKey())->pluck('id');
        $ids = $folhas->push($raiz->getKey());
        DB::table('pmieducar.menu_tipo_usuario')->whereIn('menu_id', $ids)->delete();
        Menu::query()->whereIn('id', $folhas)->delete();
        $raiz->delete();
        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }
};
