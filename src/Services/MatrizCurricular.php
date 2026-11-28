<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lê a matriz curricular do i-Educar na ordem estrutural da rede:
 * turma → escola×série (com aulas_por_semana) → ano escolar → ajuste local do módulo.
 */
final class MatrizCurricular
{
    /**
     * Componentes da matriz da turma (modules.componente_curricular_turma).
     *
     * @return list<array{id:int,nome:string,abreviatura:string,carga_horaria:?float,aulas_por_semana:?int,origem:string,cor:string}>
     */
    public static function componentesDaTurma(int $turmaId): array
    {
        if ($turmaId < 1 || ! Schema::hasTable('modules.componente_curricular_turma') || ! Schema::hasTable('modules.componente_curricular')) {
            return [];
        }

        try {
            return DB::table('modules.componente_curricular_turma as cct')
                ->join('modules.componente_curricular as cc', 'cc.id', '=', 'cct.componente_curricular_id')
                ->where('cct.turma_id', $turmaId)
                ->orderByRaw('COALESCE(cc.ordenamento, 99999)')
                ->orderBy('cc.nome')
                ->get([
                    'cc.id',
                    'cc.nome',
                    'cc.abreviatura',
                    'cct.carga_horaria',
                    'cc.color',
                ])
                ->map(static fn ($r): array => [
                    'id' => (int) $r->id,
                    'nome' => (string) $r->nome,
                    'abreviatura' => (string) ($r->abreviatura ?: mb_substr((string) $r->nome, 0, 8)),
                    'carga_horaria' => $r->carga_horaria !== null ? (float) $r->carga_horaria : null,
                    'aulas_por_semana' => null,
                    'origem' => 'turma',
                    'cor' => (string) ($r->color ?: '#47728f'),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fallback: componentes da série/escola (escola_serie_disciplina) — fonte oficial de aulas_por_semana.
     *
     * @return list<array{id:int,nome:string,abreviatura:string,carga_horaria:?float,aulas_por_semana:?int,origem:string,cor:string}>
     */
    public static function componentesDaSerieEscola(int $turmaId): array
    {
        if ($turmaId < 1 || ! Schema::hasTable('pmieducar.escola_serie_disciplina')) {
            return [];
        }

        try {
            $turma = DB::table('pmieducar.turma')->where('cod_turma', $turmaId)->first([
                'ref_ref_cod_serie', 'ref_ref_cod_escola',
            ]);
            if ($turma === null) {
                return [];
            }

            $temAulasSemana = Schema::hasColumn('pmieducar.escola_serie_disciplina', 'aulas_por_semana');
            $cols = ['cc.id', 'cc.nome', 'cc.abreviatura', 'esd.carga_horaria', 'cc.color'];
            if ($temAulasSemana) {
                $cols[] = 'esd.aulas_por_semana';
            }

            return DB::table('pmieducar.escola_serie_disciplina as esd')
                ->join('modules.componente_curricular as cc', 'cc.id', '=', 'esd.ref_cod_disciplina')
                ->where('esd.ref_ref_cod_serie', $turma->ref_ref_cod_serie)
                ->where('esd.ref_ref_cod_escola', $turma->ref_ref_cod_escola)
                ->where('esd.ativo', 1)
                ->orderBy('cc.nome')
                ->get($cols)
                ->map(static function ($r) use ($temAulasSemana): array {
                    $aulas = null;
                    if ($temAulasSemana && isset($r->aulas_por_semana) && $r->aulas_por_semana !== null) {
                        $aulas = max(1, min(12, (int) $r->aulas_por_semana));
                    }

                    return [
                        'id' => (int) $r->id,
                        'nome' => (string) $r->nome,
                        'abreviatura' => (string) ($r->abreviatura ?: mb_substr((string) $r->nome, 0, 8)),
                        'carga_horaria' => $r->carga_horaria !== null ? (float) $r->carga_horaria : null,
                        'aulas_por_semana' => $aulas,
                        'origem' => 'escola_serie',
                        'cor' => (string) ($r->color ?: '#47728f'),
                    ];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fallback: carga horária do ano escolar do componente (modules.componente_curricular_ano_escolar).
     *
     * @return array<int, float> componente_id => carga_horaria
     */
    public static function cargasAnoEscolar(int $serieId, int $ano): array
    {
        if ($serieId < 1 || ! Schema::hasTable('modules.componente_curricular_ano_escolar')) {
            return [];
        }
        try {
            $q = DB::table('modules.componente_curricular_ano_escolar')
                ->where('ano_escolar_id', $serieId);
            if (Schema::hasColumn('modules.componente_curricular_ano_escolar', 'anos_letivos')) {
                // anos_letivos é array; filtragem ampla se existir
            }

            return $q->get(['componente_curricular_id', 'carga_horaria'])
                ->mapWithKeys(static fn ($r): array => [
                    (int) $r->componente_curricular_id => $r->carga_horaria !== null ? (float) $r->carga_horaria : 0.0,
                ])
                ->filter(static fn (float $v): bool => $v > 0)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Matriz efetiva da turma: prioriza turma, completa com escola×série e ano escolar,
     * aplica ajuste local (aulas/semana) do módulo de horários.
     *
     * @return list<array{id:int,nome:string,abreviatura:string,carga_horaria:?float,aulas_por_semana:?int,origem:string,cor:string,ajuste_local:bool}>
     */
    public static function componentesParaTurma(int $turmaId): array
    {
        $daTurma = self::componentesDaTurma($turmaId);
        $daSerie = self::componentesDaSerieEscola($turmaId);

        // Indexa escola×série para enriquecer (aulas_por_semana / carga) os da turma
        $seriePorId = [];
        foreach ($daSerie as $c) {
            $seriePorId[(int) $c['id']] = $c;
        }

        $anoEscolar = [];
        $serieId = 0;
        $ano = (int) date('Y');
        try {
            $t = DB::table('pmieducar.turma')->where('cod_turma', $turmaId)->first(['ref_ref_cod_serie', 'ano']);
            if ($t) {
                $serieId = (int) ($t->ref_ref_cod_serie ?? 0);
                $ano = (int) ($t->ano ?: date('Y'));
                $anoEscolar = self::cargaAnoEscolar($serieId, $ano);
            }
        } catch (\Throwable) {
            //
        }

        $base = $daTurma !== [] ? $daTurma : $daSerie;
        if ($base === [] && $anoEscolar !== [] && Schema::hasTable('modules.componente_curricular')) {
            try {
                $ids = array_keys($anoEscolar);
                $base = DB::table('modules.componente_curricular')
                    ->whereIn('id', $ids)
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'abreviatura', 'color'])
                    ->map(static fn ($r): array => [
                        'id' => (int) $r->id,
                        'nome' => (string) $r->nome,
                        'abreviatura' => (string) ($r->abreviatura ?: mb_substr((string) $r->nome, 0, 8)),
                        'carga_horaria' => $anoEscolar[(int) $r->id] ?? null,
                        'aulas_por_semana' => null,
                        'origem' => 'ano_escolar',
                        'cor' => (string) ($r->color ?: '#47728f'),
                    ])
                    ->all();
            } catch (\Throwable) {
                $base = [];
            }
        }

        $ajustes = MatrizAjuste::obter($turmaId);
        $tempos = MatrizAjuste::obterTempos($turmaId);
        $tempoPadrao = EixoHorario::tempoAulaPadrao();
        $out = [];
        foreach ($base as $c) {
            $id = (int) $c['id'];
            $serie = $seriePorId[$id] ?? null;

            // Preferência: aulas_por_semana da escola×série quando a turma não traz
            if (empty($c['aulas_por_semana']) && $serie && ! empty($serie['aulas_por_semana'])) {
                $c['aulas_por_semana'] = $serie['aulas_por_semana'];
            }
            if (($c['carga_horaria'] === null || (float) $c['carga_horaria'] <= 0) && $serie && ($serie['carga_horaria'] ?? null)) {
                $c['carga_horaria'] = $serie['carga_horaria'];
            }
            if (($c['carga_horaria'] === null || (float) $c['carga_horaria'] <= 0) && isset($anoEscolar[$id])) {
                $c['carga_horaria'] = $anoEscolar[$id];
                if (($c['origem'] ?? '') === 'turma') {
                    // mantém origem turma; só completa carga
                } elseif (($c['origem'] ?? '') === '') {
                    $c['origem'] = 'ano_escolar';
                }
            }

            $c['ajuste_local'] = false;
            if (isset($ajustes[$id])) {
                $c['aulas_por_semana'] = $ajustes[$id];
                $c['ajuste_local'] = true;
                $c['origem'] = 'ajuste_local';
            }
            $c['tempo_aula'] = isset($tempos[$id]) ? (int) $tempos[$id] : $tempoPadrao;

            $out[] = $c;
        }

        // Disciplinas só no ajuste local (ainda sem cadastro na matriz) — não inventamos nomes sem id conhecido
        return $out;
    }

    /**
     * Turmas ativas do ano (com contagem de componentes da matriz).
     *
     * @return list<array{id:int,nome:string,escola:?string,ano:int,regente_id:?int,regente:?string,componentes:int}>
     */
    public static function turmas(int $ano, int $limite = 200): array
    {
        if (! Schema::hasTable('pmieducar.turma')) {
            return [];
        }

        try {
            $cols = [
                't.cod_turma as id',
                't.nm_turma as nome',
                't.ref_ref_cod_escola as escola_id',
                'j.fantasia as escola',
                't.ano',
                't.ref_cod_regente as regente_id',
                'pr.nome as regente',
                't.turma_turno_id as turno_id',
                'tt.nome as turno',
                'c.nm_curso as curso',
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
                ->leftJoin('cadastro.pessoa as pr', 'pr.idpes', '=', 't.ref_cod_regente')
                ->leftJoin('pmieducar.turma_turno as tt', 'tt.id', '=', 't.turma_turno_id')
                ->leftJoin('pmieducar.escola as e', 'e.cod_escola', '=', 't.ref_ref_cod_escola')
                ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
                ->leftJoin('pmieducar.curso as c', 'c.cod_curso', '=', 't.ref_cod_curso')
                ->where('t.ativo', 1)
                ->where('t.ano', $ano)
                ->orderBy('j.fantasia')
                ->orderBy('t.nm_turma')
                ->limit($limite)
                ->get($cols);

            $out = [];
            foreach ($q as $r) {
                $id = (int) $r->id;
                $ehAee = TurmaPerfilHorario::ehAeeOuEducacaoEspecial(
                    (string) $r->nome,
                    isset($r->curso) && $r->curso !== null ? (string) $r->curso : null,
                    $r->tipo_atendimento ?? null,
                    $r->modalidade_curso ?? null,
                    $r->classe_especial ?? null,
                    $r->atividades_aee ?? null,
                );
                $out[] = [
                    'id' => $id,
                    'nome' => (string) $r->nome,
                    'escola_id' => $r->escola_id !== null ? (int) $r->escola_id : null,
                    'escola' => $r->escola !== null ? (string) $r->escola : null,
                    'ano' => (int) $r->ano,
                    'regente_id' => $r->regente_id !== null ? (int) $r->regente_id : null,
                    'regente' => $r->regente !== null ? (string) $r->regente : null,
                    'turno_id' => isset($r->turno_id) && $r->turno_id !== null ? (int) $r->turno_id : null,
                    'turno' => isset($r->turno) && $r->turno !== null ? (string) $r->turno : null,
                    'curso' => isset($r->curso) && $r->curso !== null ? (string) $r->curso : null,
                    'eh_aee' => $ehAee,
                    'fora_quadro_automatico' => $ehAee,
                    'componentes' => $ehAee ? 0 : count(self::componentesParaTurma($id)),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Turmas elegíveis ao gerar/recriar horário (exclui AEE / Educação especial).
     *
     * @return list<array<string,mixed>>
     */
    public static function turmasParaQuadroAutomatico(int $ano, int $limite = 400): array
    {
        return array_values(array_filter(
            self::turmas($ano, $limite),
            static fn (array $t): bool => empty($t['fora_quadro_automatico']) && empty($t['eh_aee'])
        ));
    }

    public static function escolaDaTurma(int $turmaId): ?int
    {
        if ($turmaId < 1 || ! Schema::hasTable('pmieducar.turma')) {
            return null;
        }
        try {
            $v = DB::table('pmieducar.turma')->where('cod_turma', $turmaId)->value('ref_ref_cod_escola');

            return $v !== null ? (int) $v : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function nomeServidor(int $id): ?string
    {
        if ($id < 1 || ! Schema::hasTable('cadastro.pessoa')) {
            return null;
        }
        try {
            $n = DB::table('cadastro.pessoa')->where('idpes', $id)->value('nome');

            return $n !== null ? (string) $n : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Escolas com turmas ativas no ano.
     *
     * @return list<array{id:int,nome:string,turmas:int}>
     */
    public static function escolas(int $ano, int $limite = 200): array
    {
        if (! Schema::hasTable('pmieducar.escola') || ! Schema::hasTable('pmieducar.turma')) {
            return [];
        }
        try {
            // Contagem para o quadro automático: exclui AEE / Educação especial.
            $porEscola = [];
            foreach (self::turmasParaQuadroAutomatico($ano, 2000) as $t) {
                $eid = (int) ($t['escola_id'] ?? 0);
                if ($eid < 1) {
                    continue;
                }
                $porEscola[$eid] = ($porEscola[$eid] ?? 0) + 1;
            }
            if ($porEscola === []) {
                return [];
            }

            return DB::table('pmieducar.escola as e')
                ->leftJoin('cadastro.juridica as j', 'j.idpes', '=', 'e.ref_idpes')
                ->where('e.ativo', 1)
                ->whereIn('e.cod_escola', array_keys($porEscola))
                ->orderBy('j.fantasia')
                ->limit($limite)
                ->get([
                    'e.cod_escola as id',
                    DB::raw('COALESCE(j.fantasia, e.cod_escola::text) as nome'),
                ])
                ->map(static fn ($r): array => [
                    'id' => (int) $r->id,
                    'nome' => (string) $r->nome,
                    'turmas' => (int) ($porEscola[(int) $r->id] ?? 0),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Servidores alocados na escola (fallback amplo para geração).
     *
     * @return list<array{id:int,nome:string}>
     */
    public static function professoresDaEscola(int $escolaId, int $limite = 30): array
    {
        if ($escolaId < 1 || ! Schema::hasTable('pmieducar.servidor')) {
            return self::servidores($limite);
        }
        try {
            $ano = (int) date('Y');
            if (Schema::hasTable('pmieducar.servidor_alocacao')) {
                $rows = DB::table('pmieducar.servidor_alocacao as sa')
                    ->join('cadastro.pessoa as p', 'p.idpes', '=', 'sa.ref_cod_servidor')
                    ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
                    ->where('sa.ref_cod_escola', $escolaId)
                    ->where('sa.ativo', 1)
                    ->where('s.ativo', 1)
                    ->where(function ($q) use ($ano) {
                        $q->where('sa.ano', $ano)->orWhereNull('sa.ano');
                    })
                    ->orderBy('p.nome')
                    ->limit($limite)
                    ->get(['sa.ref_cod_servidor as id', 'p.nome']);
                if ($rows->isNotEmpty()) {
                    return $rows->map(static fn ($r): array => [
                        'id' => (int) $r->id,
                        'nome' => (string) $r->nome,
                    ])->all();
                }
            }

            if (Schema::hasTable('modules.professor_turma')) {
                $rows = DB::table('modules.professor_turma as pt')
                    ->join('pmieducar.turma as t', 't.cod_turma', '=', 'pt.turma_id')
                    ->join('cadastro.pessoa as p', 'p.idpes', '=', 'pt.servidor_id')
                    ->where('t.ref_ref_cod_escola', $escolaId)
                    ->where('pt.ano', $ano)
                    ->orderBy('p.nome')
                    ->limit($limite)
                    ->distinct()
                    ->get(['pt.servidor_id as id', 'p.nome']);
                if ($rows->isNotEmpty()) {
                    return $rows->map(static fn ($r): array => [
                        'id' => (int) $r->id,
                        'nome' => (string) $r->nome,
                    ])->all();
                }
            }
        } catch (\Throwable) {
            //
        }

        return array_map(static fn (array $s): array => [
            'id' => $s['id'],
            'nome' => $s['nome'],
        ], self::servidores($limite));
    }

    /**
     * Professores candidatos: professor_turma (+ disciplina) e servidor_disciplina.
     *
     * @return list<array{id:int,nome:string,preferencial?:bool}>
     */
    public static function professoresParaComponente(int $turmaId, int $componenteId): array
    {
        $out = [];
        $seen = [];

        $add = static function (int $id, string $nome) use (&$out, &$seen): void {
            if ($id < 1 || isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $out[] = ['id' => $id, 'nome' => $nome];
        };

        try {
            if (Schema::hasTable('modules.professor_turma')) {
                $ano = (int) (DB::table('pmieducar.turma')->where('cod_turma', $turmaId)->value('ano') ?: date('Y'));
                $q = DB::table('modules.professor_turma as pt')
                    ->join('cadastro.pessoa as p', 'p.idpes', '=', 'pt.servidor_id')
                    ->where('pt.turma_id', $turmaId)
                    ->where('pt.ano', $ano);

                if (Schema::hasTable('modules.professor_turma_disciplina') && $componenteId > 0) {
                    $q->where(function ($w) use ($componenteId) {
                        $w->whereExists(function ($e) use ($componenteId) {
                            $e->select(DB::raw(1))
                                ->from('modules.professor_turma_disciplina as ptd')
                                ->whereColumn('ptd.professor_turma_id', 'pt.id')
                                ->where('ptd.componente_curricular_id', $componenteId);
                        })->orWhereNotExists(function ($e) {
                            $e->select(DB::raw(1))
                                ->from('modules.professor_turma_disciplina as ptd')
                                ->whereColumn('ptd.professor_turma_id', 'pt.id');
                        });
                    });
                }

                foreach ($q->limit(40)->get(['pt.servidor_id as id', 'p.nome']) as $r) {
                    $add((int) $r->id, (string) $r->nome);
                }
            }

            if ($componenteId > 0 && Schema::hasTable('pmieducar.servidor_disciplina')) {
                $rows = DB::table('pmieducar.servidor_disciplina as sd')
                    ->join('cadastro.pessoa as p', 'p.idpes', '=', 'sd.ref_cod_servidor')
                    ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sd.ref_cod_servidor')
                    ->where('sd.ref_cod_disciplina', $componenteId)
                    ->where('s.ativo', 1)
                    ->limit(40)
                    ->get(['sd.ref_cod_servidor as id', 'p.nome']);
                foreach ($rows as $r) {
                    $add((int) $r->id, (string) $r->nome);
                }
            }


            // Servidores alocados na escola da turma (admitidos via ACT etc.) ficam disponíveis no quadro.
            if (Schema::hasTable('pmieducar.servidor_alocacao') && Schema::hasTable('pmieducar.turma')) {
                $turmaMeta = DB::table('pmieducar.turma')->where('cod_turma', $turmaId)->first(['ref_ref_cod_escola', 'ano']);
                if ($turmaMeta && $turmaMeta->ref_ref_cod_escola) {
                    $anoAloc = (int) ($turmaMeta->ano ?: date('Y'));
                    $rows = DB::table('pmieducar.servidor_alocacao as sa')
                        ->join('cadastro.pessoa as p', 'p.idpes', '=', 'sa.ref_cod_servidor')
                        ->join('pmieducar.servidor as s', 's.cod_servidor', '=', 'sa.ref_cod_servidor')
                        ->where('sa.ref_cod_escola', (int) $turmaMeta->ref_ref_cod_escola)
                        ->where('sa.ano', $anoAloc)
                        ->where('sa.ativo', 1)
                        ->where('s.ativo', 1)
                        ->orderBy('p.nome')
                        ->limit(60)
                        ->get(['sa.ref_cod_servidor as id', 'p.nome']);
                    foreach ($rows as $r) {
                        $add((int) $r->id, (string) $r->nome);
                    }
                }
            }

            if ($out === [] && Schema::hasTable('pmieducar.turma')) {
                $reg = DB::table('pmieducar.turma as t')
                    ->leftJoin('cadastro.pessoa as p', 'p.idpes', '=', 't.ref_cod_regente')
                    ->where('t.cod_turma', $turmaId)
                    ->first(['t.ref_cod_regente as id', 'p.nome']);
                if ($reg && $reg->id) {
                    $add((int) $reg->id, (string) ($reg->nome ?: 'Regente'));
                }
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }

    /**
     * Servidores ativos + disciplinas habilitadas (servidor_disciplina) quando houver.
     *
     * @return list<array{id:int,nome:string,disciplinas:list<array{id:int,nome:string}>}>
     */
    public static function servidores(int $limite = 100): array
    {
        if (! Schema::hasTable('pmieducar.servidor') || ! Schema::hasTable('cadastro.pessoa')) {
            return [];
        }

        try {
            $servs = DB::table('pmieducar.servidor as s')
                ->join('cadastro.pessoa as p', 'p.idpes', '=', 's.cod_servidor')
                ->where('s.ativo', 1)
                ->orderBy('p.nome')
                ->limit($limite)
                ->get(['s.cod_servidor as id', 'p.nome as nome']);

            $disciplinasPorServidor = [];
            if (Schema::hasTable('pmieducar.servidor_disciplina')) {
                $rows = DB::table('pmieducar.servidor_disciplina as sd')
                    ->join('modules.componente_curricular as cc', 'cc.id', '=', 'sd.ref_cod_disciplina')
                    ->whereIn('sd.ref_cod_servidor', $servs->pluck('id'))
                    ->get(['sd.ref_cod_servidor as sid', 'cc.id', 'cc.nome']);
                foreach ($rows as $r) {
                    $disciplinasPorServidor[(int) $r->sid][] = [
                        'id' => (int) $r->id,
                        'nome' => (string) $r->nome,
                    ];
                }
            }

            return $servs->map(static function ($r) use ($disciplinasPorServidor): array {
                $id = (int) $r->id;

                return [
                    'id' => $id,
                    'nome' => (string) $r->nome,
                    'disciplinas' => $disciplinasPorServidor[$id] ?? [],
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
