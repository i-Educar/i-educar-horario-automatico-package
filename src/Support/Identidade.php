<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Support;

final class Identidade
{
    public const COR = '#0f1b2d';
    public const DESTAQUE = '#1a5f9e';
    public const COR_CLARA = '#e8eef2';
    public const ICONE = 'fa-calendar';
    public const NOME = 'Quadro de horários';

    public const DESCRICAO = 'Matriz curricular e montagem da grade semanal';

    /** @return array{cor:string,destaque:string,clara:string,icone:string,nome:string,descricao:string} */
    public static function tokens(): array
    {
        return [
            'cor' => self::COR,
            'destaque' => self::DESTAQUE,
            'clara' => self::COR_CLARA,
            'icone' => self::ICONE,
            'nome' => self::NOME,
            'descricao' => self::DESCRICAO,
        ];
    }
}
