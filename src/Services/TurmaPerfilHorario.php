<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil da turma para o quadro: turno, integral, AC/contraturno e par unificado.
 */
final class TurmaPerfilHorario
{
    /**
     * @return array{
     *   id:int,nome:string,escola_id:?int,escola:?string,ano:int,
     *   turno_id:?int,turno:?string,modalidade:string,curso:?string,
     *   entrada:?string,saida:?string,entrada_tarde:?string,saida_tarde:?string,
     *   eh_integral:bool,eh_ac:bool,eh_aee:bool,
     *   pares:list<array{id:int,nome:string,turno:?string,modalidade:string}>,
     *   componentes:int
     * }|null
     */
    public static function detalhe(int $turmaId, int $ano = 0): ?array
    {
        if ($turmaId < 1 || ! Schema::hasTable('pmieducar.turma')) {
            return null;
        }
        try {
            $cols = [
                't.cod_turma as id',
                't.nm_turma as nome',
                't.ref_ref_cod_escola as escola_id',
                'j.fantasia as escola',
                't.ano',
                't.turma_turno_id as turno_id',
                'tt.nome as turno',
                'c.nm_curso as curso',
                't.hora_inicial',
                't.hora_final',
                't.hora_inicial_matutino',
                't.hora_final_matutino',
                't.hora_inicial_vespertino',
                't.hora_final_vespertino',
            ];
            if (Schema::hasColumn('pmieducar.turma', 'tipo_atendimento')) {
                $cols[] = 't.tipo_atendimento';
            }
            if (Schema::hasColumn('pmieducar.turma', 'classe_especial')) {
                $cols[] = 't.classe_especial';
            }
            if (Schema::hasColumn('pmieducar.turma', 'atividades_aee')) {
                $cols[] = 't.atividades_aee';
            }
            if (Schema::hasColumn('pmieducar.curso', 'modalidade_curso')) {
                $cols[] = 'c.modalidade_curso';
            }
            $q = DB::table('pmieducar.turma as t')
                ->leftJoin('pmieducar.turma_turno as tt', 'tt.id', '=', 't.turma_turno_id')
                ->leftJoin('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
                ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
                ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
                ->where('t.cod_turma', $turmaId);
            $r = $q->first($cols);
            if ($r === null) {
                return null;
            }

            $nome = (string) $r->nome;
            $curso = $r->curso !== null ? (string) $r->curso : null;
            $turno = $r->turno !== null ? (string) $r->turno : null;
            $turnoId = $r->turno_id !== null ? (int) $r->turno_id : null;
            $ehIntegral = $turnoId === 4 || mb_stripos((string) $turno, 'integral') !== false;

            $ehAee = self::ehAeeOuEducacaoEspecial(
                $nome,
                $curso,
                $r->tipo_atendimento ?? null,
                $r->modalidade_curso ?? null,
                $r->classe_especial ?? null,
                $r->atividades_aee ?? null,
            );
            $ehAc = ! $ehAee && self::pareceAtividadeComplementar($nome, $curso);

            $modalidade = $ehAee ? 'aee' : ($ehAc ? 'ac' : ($ehIntegral ? 'integral' : 'regular'));

            $entrada = self::hora($r->hora_inicial_matutino ?? $r->hora_inicial);
            $saida = self::hora($r->hora_final_matutino ?? $r->hora_final);
            $entradaTarde = self::hora($r->hora_inicial_vespertino);
            $saidaTarde = self::hora($r->hora_final_vespertino);

            $anoTurma = (int) ($r->ano ?: ($ano ?: date('Y')));
            $escolaId = $r->escola_id !== null ? (int) $r->escola_id : null;

            return [
                'id' => (int) $r->id,
                'nome' => $nome,
                'escola_id' => $escolaId,
                'escola' => $r->escola !== null ? (string) $r->escola : null,
                'ano' => $anoTurma,
                'turno_id' => $turnoId,
                'turno' => $turno,
                'modalidade' => $modalidade,
                'curso' => $curso,
                'entrada' => $entrada,
                'saida' => $saida,
                'entrada_tarde' => $entradaTarde,
                'saida_tarde' => $saidaTarde,
                'eh_integral' => $ehIntegral,
                'eh_ac' => $ehAc,
                'eh_aee' => $ehAee,
                'pares' => self::sugerirPares((int) $r->id, $nome, $escolaId, $anoTurma, $modalidade, $turnoId),
                'componentes' => count(MatrizCurricular::componentesParaTurma((int) $r->id)),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{id:int,nome:string,turno:?string,modalidade:string}>
     */
    public static function sugerirPares(
        int $turmaId,
        string $nome,
        ?int $escolaId,
        int $ano,
        string $modalidade,
        ?int $turnoId
    ): array {
        if ($escolaId === null || $escolaId < 1 || ! Schema::hasTable('pmieducar.turma')) {
            return [];
        }
        try {
            $base = self::baseNome($nome);
            $rows = DB::table('pmieducar.turma as t')
                ->leftJoin('pmieducar.turma_turno as tt', 'tt.id', '=', 't.turma_turno_id')
                ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
                ->where('t.ativo', 1)
                ->where('t.ano', $ano)
                ->where('t.ref_ref_cod_escola', $escolaId)
                ->where('t.cod_turma', '!=', $turmaId)
                ->orderBy('t.nm_turma')
                ->limit(40)
                ->get(['t.cod_turma as id', 't.nm_turma as nome', 't.turma_turno_id as turno_id', 'tt.nome as turno', 'c.nm_curso as curso']);

            $out = [];
            foreach ($rows as $r) {
                $n = (string) $r->nome;
                $curso = $r->curso !== null ? (string) $r->curso : null;
                $mod = self::pareceAtividadeComplementar($n, $curso)
                    ? 'ac'
                    : ((str_contains(mb_strtolower((string) $curso), 'aee') || str_contains(mb_strtolower($n), 'aee')) ? 'aee' : 'regular');
                $mesmoNucleo = self::baseNome($n) === $base
                    || str_contains(mb_strtolower($n), mb_strtolower($base))
                    || str_contains(mb_strtolower($base), self::baseNome($n));
                $turnosOpostos = $turnoId && $r->turno_id && (int) $r->turno_id !== $turnoId;
                $parNatural = ($modalidade === 'regular' && $mod === 'ac')
                    || ($modalidade === 'ac' && $mod === 'regular')
                    || ($modalidade === 'integral' && $mod === 'ac');

                if (($mesmoNucleo && ($turnosOpostos || $parNatural)) || ($parNatural && $mesmoNucleo)) {
                    $out[] = [
                        'id' => (int) $r->id,
                        'nome' => $n,
                        'turno' => $r->turno !== null ? (string) $r->turno : null,
                        'modalidade' => $mod,
                    ];
                }
            }

            return array_slice($out, 0, 6);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Perfil de eixo (entrada/saída/faixas) para turma + eventual par AC.
     *
     * @return array{entrada:?string,saida:?string,turnos:list<string>,faixa:string,blocos:list<array{turma_id:int,rotulo:string,modalidade:string,turno:?string}>}
     */
    public static function eixoPara(array $turma, ?array $par = null, string $faixa = 'auto'): array
    {
        $blocos = [[
            'turma_id' => (int) $turma['id'],
            'rotulo' => self::rotuloModalidade($turma),
            'modalidade' => (string) ($turma['modalidade'] ?? 'regular'),
            'turno' => $turma['turno'] ?? null,
        ]];

        $turnos = [];
        if (! empty($turma['eh_integral'])) {
            $turnos = ['matutino', 'vespertino'];
            $entrada = $turma['entrada'] ?? '07:30';
            $saida = $turma['saida_tarde'] ?? $turma['saida'] ?? '17:10';
        } else {
            $t = mb_strtolower((string) ($turma['turno'] ?? 'matutino'));
            if (str_contains($t, 'vespert')) {
                $turnos = ['vespertino'];
            } elseif (str_contains($t, 'noturn')) {
                $turnos = ['noturno'];
            } else {
                $turnos = ['matutino'];
            }
            $entrada = $turma['entrada'] ?? null;
            $saida = $turma['saida'] ?? null;
        }

        if ($par !== null) {
            $blocos[] = [
                'turma_id' => (int) $par['id'],
                'rotulo' => self::rotuloModalidade($par),
                'modalidade' => (string) ($par['modalidade'] ?? 'ac'),
                'turno' => $par['turno'] ?? null,
            ];
            $tp = mb_strtolower((string) ($par['turno'] ?? ''));
            if (str_contains($tp, 'vespert') && ! in_array('vespertino', $turnos, true)) {
                $turnos[] = 'vespertino';
            }
            if (str_contains($tp, 'matut') && ! in_array('matutino', $turnos, true)) {
                $turnos[] = 'matutino';
            }
            if (! empty($par['entrada']) && ($entrada === null || $par['entrada'] < $entrada)) {
                $entrada = $par['entrada'];
            }
            if (! empty($par['saida']) && ($saida === null || $par['saida'] > $saida)) {
                $saida = $par['saida'];
            }
            if (! empty($par['saida_tarde']) && ($saida === null || $par['saida_tarde'] > $saida)) {
                $saida = $par['saida_tarde'];
            }
        }

        if ($faixa === 'auto') {
            $faixa = count($turnos) > 1 ? 'todos' : ($turnos[0] ?? 'todos');
        }
        if ($faixa === 'matutino') {
            $turnos = ['matutino'];
            $saida = $turma['saida'] ?? $saida;
        } elseif ($faixa === 'vespertino') {
            $turnos = ['vespertino'];
            $entrada = $turma['entrada_tarde'] ?? $entrada;
            $saida = $turma['saida_tarde'] ?? $saida;
        }

        return [
            'entrada' => $entrada,
            'saida' => $saida,
            'turnos' => $turnos,
            'faixa' => $faixa,
            'blocos' => $blocos,
        ];
    }

    /** @param array<string,mixed> $turma */
    private static function rotuloModalidade(array $turma): string
    {
        $mod = (string) ($turma['modalidade'] ?? 'regular');

        return match ($mod) {
            'ac' => 'Atividade complementar · '.($turma['nome'] ?? ''),
            'aee' => 'AEE · '.($turma['nome'] ?? ''),
            'integral' => 'Integral · '.($turma['nome'] ?? ''),
            default => 'Ensino regular · '.($turma['nome'] ?? ''),
        };
    }

    /**
     * Turmas de AEE / Educação especial / classe especial ficam fora do quadro automático:
     * não usam matriz curricular regular e gerariam pendências permanentes.
     */
    public static function ehAeeOuEducacaoEspecial(
        string $nome = '',
        ?string $curso = null,
        mixed $tipoAtendimento = null,
        mixed $modalidadeCurso = null,
        mixed $classeEspecial = null,
        mixed $atividadesAee = null,
    ): bool {
        // ModalidadeCurso::EDUCACAO_ESPECIAL = 2
        if ((int) $modalidadeCurso === 2) {
            return true;
        }

        if ($classeEspecial === 1 || $classeEspecial === true || $classeEspecial === '1' || $classeEspecial === 't') {
            return true;
        }

        $ativ = is_string($atividadesAee) ? trim($atividadesAee) : '';
        if ($ativ !== '' && $ativ !== '{}' && $ativ !== '[]' && strcasecmp($ativ, 'null') !== 0) {
            return true;
        }

        // TipoAtendimentoTurma::AEE = 5
        if (in_array(5, self::normalizarTiposAtendimento($tipoAtendimento), true)) {
            return true;
        }

        $blob = mb_strtolower($nome.' '.($curso ?? ''));

        return str_contains($blob, 'aee')
            || str_contains($blob, 'atendimento educacional')
            || str_contains($blob, 'educação especial')
            || str_contains($blob, 'educacao especial')
            || str_contains($blob, 'classe especial');
    }

    /** Cache por request: turma fora do gerar/recriar automático. */
    public static function ehForaDoQuadroAutomatico(int $turmaId): bool
    {
        static $cache = [];
        if ($turmaId < 1) {
            return false;
        }
        if (array_key_exists($turmaId, $cache)) {
            return $cache[$turmaId];
        }

        $detalhe = self::detalhe($turmaId);
        if ($detalhe !== null) {
            return $cache[$turmaId] = ! empty($detalhe['eh_aee']);
        }

        try {
            if (! Schema::hasTable('pmieducar.turma')) {
                return $cache[$turmaId] = false;
            }
            $cols = ['t.nm_turma as nome', 'c.nm_curso as curso'];
            $q = DB::table('pmieducar.turma as t')
                ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
                ->where('t.cod_turma', $turmaId);
            if (Schema::hasColumn('pmieducar.turma', 'tipo_atendimento')) {
                $cols[] = 't.tipo_atendimento';
            }
            if (Schema::hasColumn('pmieducar.turma', 'classe_especial')) {
                $cols[] = 't.classe_especial';
            }
            if (Schema::hasColumn('pmieducar.turma', 'atividades_aee')) {
                $cols[] = 't.atividades_aee';
            }
            if (Schema::hasColumn('pmieducar.curso', 'modalidade_curso')) {
                $cols[] = 'c.modalidade_curso';
            }
            $r = $q->first($cols);
            if ($r === null) {
                return $cache[$turmaId] = false;
            }

            return $cache[$turmaId] = self::ehAeeOuEducacaoEspecial(
                (string) $r->nome,
                $r->curso !== null ? (string) $r->curso : null,
                $r->tipo_atendimento ?? null,
                $r->modalidade_curso ?? null,
                $r->classe_especial ?? null,
                $r->atividades_aee ?? null,
            );
        } catch (\Throwable) {
            return $cache[$turmaId] = false;
        }
    }

    /** @return list<int> */
    public static function normalizarTiposAtendimento(mixed $tipoAtendimento): array
    {
        if ($tipoAtendimento === null || $tipoAtendimento === '' || $tipoAtendimento === false) {
            return [];
        }
        if (is_int($tipoAtendimento) || (is_string($tipoAtendimento) && ctype_digit($tipoAtendimento))) {
            return [(int) $tipoAtendimento];
        }
        if (is_array($tipoAtendimento)) {
            return array_values(array_map('intval', $tipoAtendimento));
        }
        $s = trim((string) $tipoAtendimento);
        if ($s === '' || $s === '{}' || $s === '[]') {
            return [];
        }
        // PostgreSQL array: {5} ou {0,5}
        if ($s[0] === '{') {
            $s = trim($s, '{}');
        }
        if ($s === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $p): int => (int) trim($p),
            preg_split('/\s*,\s*/', $s) ?: []
        ), static fn (int $n): bool => $n >= 0));
    }

    private static function pareceAtividadeComplementar(string $nome, ?string $curso): bool
    {
        $blob = mb_strtolower($nome.' '.($curso ?? ''));

        return str_contains($blob, 'atividade complementar')
            || str_contains($blob, 'ativ. complementar')
            || preg_match('/\bac\b/', $blob) === 1
            || str_contains($blob, 'contraturno')
            || str_contains($blob, 'mais educação')
            || str_contains($blob, 'mais educacao');
    }

    private static function baseNome(string $nome): string
    {
        $n = trim(preg_replace('/\s+/', ' ', $nome) ?? $nome);
        $n = preg_replace('/\s*[-–]\s*(ac|aee|atividade.complementar|contraturno|matutino|vespertino|integral).*$/iu', '', $n) ?? $n;
        $n = preg_replace('/\s+(ac|aee)$/iu', '', $n) ?? $n;

        return mb_strtolower(trim($n));
    }

    private static function hora(mixed $h): ?string
    {
        if ($h === null || $h === '') {
            return null;
        }
        $s = (string) $h;
        if (preg_match('/^\d{2}:\d{2}/', $s, $m)) {
            return substr($m[0], 0, 5);
        }

        return null;
    }
}
