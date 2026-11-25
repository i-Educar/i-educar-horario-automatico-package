<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Support;

final class MenuFluxo
{
    /**
     * @param  array<string, bool>  $permite
     * @return list<array{nome:string,itens:list<array{rotulo:string,url:string,icone:string,chave:string}>}>
     */
    public static function grupos(array $permite): array
    {
        $item = static function (string $chave, string $rotulo, string $url, string $icone) use ($permite): ?array {
            if (! ($permite[$chave] ?? true)) {
                return null;
            }

            return compact('chave', 'rotulo', 'url', 'icone');
        };
        $filtrar = static fn (array $itens): array => array_values(array_filter($itens));

        return array_values(array_filter([
            [
                'nome' => 'Montar o quadro',
                'itens' => $filtrar([
                    $item('painel', 'Início', '/horario-automatico', 'fa-th-large'),
                    $item('matriz', 'Matriz curricular', '/horario-automatico/matriz', 'fa-th-list'),
                    $item('validar', 'Verificar demanda', '/horario-automatico/validar', 'fa-check-square-o'),
                    $item('diretivas', 'Regras da rede', '/horario-automatico/diretivas', 'fa-sliders'),
                    $item('gerar', 'Gerar quadro', '/horario-automatico/gerar', 'fa-magic'),
                    $item('grade', 'Ajustar quadro', '/horario-automatico/grade', 'fa-table'),
                    $item('fazer-valer', 'Fazer valer', '/horario-automatico', 'fa-check-circle'),
                ]),
            ],
            [
                'nome' => 'Consultas',
                'itens' => $filtrar([
                    $item('choques', 'Conflitos de horário', '/horario-automatico/choques', 'fa-exclamation-triangle'),
                    $item('cargos', 'Carga por cargo', '/horario-automatico/cargos', 'fa-id-badge'),
                    $item('relatorio', 'Relatórios', '/horario-automatico/relatorio', 'fa-file-text-o'),
                ]),
            ],
        ], static fn (array $g): bool => $g['itens'] !== []));
    }
}
