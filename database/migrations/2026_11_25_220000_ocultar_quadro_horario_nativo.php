<?php

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Packages\BuritiHorarioAutomatico\Support\Processos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Oculta o quadro de horários nativo (Servidores → Cadastros → Quadros de horários)
 * e o atalho /new/quadro-de-horarios, deixando apenas o módulo Buriti.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Legado intranet (process 641)
        $legadoIds = Menu::query()
            ->where(function ($q) {
                $q->where('process', 641)
                    ->orWhere('link', '/intranet/educar_quadro_horario_lst.php')
                    ->orWhere('link', 'like', '%educar_quadro_horario_lst.php');
            })
            ->pluck('id');

        if ($legadoIds->isNotEmpty()) {
            DB::table('pmieducar.menu_tipo_usuario')->whereIn('menu_id', $legadoIds)->delete();
            Menu::query()->whereIn('id', $legadoIds)->delete();
        }

        // Atalho Portabilis /new/quadro-de-horarios (process 1028) → módulo Buriti
        Menu::query()
            ->where('process', 1028)
            ->orWhere('link', '/new/quadro-de-horarios')
            ->orWhere('link', 'like', '/new/quadro-de-horarios%')
            ->update([
                'link' => '/horario-automatico',
                'title' => 'Quadro de horários',
                'description' => 'Geração automática e edição do quadro (módulo municipal)',
                'active' => true,
            ]);

        // Garante menus do gerador / diretivas / relatório
        $raiz = Menu::query()->where('process', Processos::RAIZ)->first();
        if ($raiz !== null) {
            $extras = [
                [Processos::DIRETIVAS, 'Diretivas', '/horario-automatico/diretivas', 'Regras da geração automática', 5],
                [Processos::GERAR, 'Gerar quadro', '/horario-automatico/gerar', 'Montagem automática por escola/rede', 6],
                [Processos::RELATORIO, 'Relatórios', '/horario-automatico/relatorio', 'Grades por turma e professor', 7],
            ];
            foreach ($extras as [$processo, $titulo, $link, $descricao, $ordem]) {
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

            $processos = [
                Processos::RAIZ, Processos::PAINEL, Processos::GRADE, Processos::CHOQUES,
                Processos::CARGOS, Processos::DIRETIVAS, Processos::GERAR, Processos::RELATORIO,
            ];
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
        }

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        // Não recria o menu legado automaticamente.
        foreach ([Processos::DIRETIVAS, Processos::GERAR, Processos::RELATORIO] as $processo) {
            $menu = Menu::query()->where('process', $processo)->first();
            if ($menu === null) {
                continue;
            }
            DB::table('pmieducar.menu_tipo_usuario')->where('menu_id', $menu->getKey())->delete();
            $menu->delete();
        }
        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }
};
