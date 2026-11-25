<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Support;

final class Processos
{
    public const int RAIZ = 881800;
    public const int PAINEL = 881801;
    public const int GRADE = 881802;
    public const int CHOQUES = 881803;
    public const int CARGOS = 881804;
    public const int DIRETIVAS = 881805;
    public const int GERAR = 881806;
    public const int RELATORIO = 881807;

    public const int MATRIZ = 881808;

    public const int VALIDAR = 881809;

    public const int FAZER_VALER = 881810;

    public const int NOVA_VERSAO = 881811;

    /** @return array<int, string> */
    public static function titulos(): array
    {
        return [
            self::RAIZ => 'Quadro de horários',
            self::PAINEL => 'Início',
            self::GRADE => 'Quadro semanal',
            self::CHOQUES => 'Conflitos de horário',
            self::CARGOS => 'Carga por cargo',
            self::DIRETIVAS => 'Regras da rede',
            self::GERAR => 'Gerar quadro',
            self::RELATORIO => 'Relatórios',
            self::MATRIZ => 'Matriz curricular',
            self::VALIDAR => 'Verificar demanda',
            self::FAZER_VALER => 'Fazer valer',
            self::NOVA_VERSAO => 'Nova versão',
        ];
    }
}
