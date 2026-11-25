<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propaga a grade PoC para o núcleo 2.12:
 * - pmieducar.quadro_horario + quadro_horario_horarios (desativa horários anteriores da turma; não apaga)
 * - pmieducar.servidor_alocacao
 * - modules.professor_turma (+ disciplinas) → diário
 */
final class PropagadorGradeNucleo
{
    /**
     * @param  list<array<string,mixed>>  $aulas
     * @return array{quadro:int,horarios:int,alocacoes:int,professor_turma:int}
     */
    public static function aplicar(array $aulas, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?: (int) (auth()->id() ?: 1);
        $inst = (int) (DB::table('pmieducar.instituicao')->orderBy('cod_instituicao')->value('cod_instituicao') ?: 1);
        $ano = (int) (DB::table('pmieducar.escola_ano_letivo')->where('andamento', 1)->orderByDesc('ano')->value('ano') ?: date('Y'));

        $porTurma = [];
        foreach ($aulas as $a) {
            $turmaId = (int) ($a['turma_id'] ?? 0);
            $professorId = (int) ($a['professor_id'] ?? 0);
            if ($turmaId < 1 || $professorId < 1) {
                continue;
            }
            $porTurma[$turmaId][] = $a;
        }

        $quadro = 0;
        $horarios = 0;
        $alocacoes = 0;
        $pt = 0;
        $paresPt = [];

        foreach ($porTurma as $turmaId => $lista) {
            $meta = self::metaTurma((int) $turmaId);
            if ($meta === null) {
                continue;
            }

            $quadroId = self::garantirQuadro((int) $turmaId, $usuarioId, (int) ($meta['ano'] ?: $ano));
            $quadro++;

            // Desativa horários anteriores (higiene: não apaga linhas).
            if (Schema::hasTable('pmieducar.quadro_horario_horarios')) {
                DB::table('pmieducar.quadro_horario_horarios')
                    ->where('ref_cod_quadro_horario', $quadroId)
                    ->where('ativo', 1)
                    ->update([
                        'ativo' => 0,
                        'data_exclusao' => now(),
                    ]);
            }

            $seq = (int) (DB::table('pmieducar.quadro_horario_horarios')
                ->where('ref_cod_quadro_horario', $quadroId)
                ->max('sequencial') ?: 0);

            foreach ($lista as $a) {
                $professorId = (int) $a['professor_id'];
                $escolaId = (int) ($a['escola_id'] ?: $meta['escola']);
                $componenteId = (int) ($a['componente_id'] ?? 0);
                if ($componenteId < 1) {
                    continue;
                }

                self::garantirServidor($professorId, $inst);
                if (self::garantirAlocacao($professorId, $inst, $escolaId, $ano, $usuarioId)) {
                    $alocacoes++;
                }

                $chavePt = $professorId.'|'.$turmaId;
                if (! isset($paresPt[$chavePt])) {
                    if (self::garantirProfessorTurma($professorId, $inst, (int) $turmaId, $ano, $componenteId)) {
                        $pt++;
                    }
                    $paresPt[$chavePt] = true;
                } else {
                    self::garantirProfessorTurma($professorId, $inst, (int) $turmaId, $ano, $componenteId);
                }

                $seq++;
                // PoC dia 1=segunda … 5=sexta → núcleo 2=segunda … 6=sexta (1=domingo).
                $diaNucleo = max(1, min(7, (int) $a['dia'] + 1));
                try {
                    DB::table('pmieducar.quadro_horario_horarios')->insert([
                        'ref_cod_quadro_horario' => $quadroId,
                        'sequencial' => $seq,
                        'ref_cod_disciplina' => $componenteId,
                        'ref_cod_escola' => $escolaId,
                        'ref_cod_serie' => (int) $meta['serie'],
                        'ref_cod_instituicao_servidor' => $inst,
                        'ref_servidor' => $professorId,
                        'dia_semana' => $diaNucleo,
                        'hora_inicial' => $a['inicio'].':00',
                        'hora_final' => $a['fim'].':00',
                        'data_cadastro' => now(),
                        'ativo' => 1,
                    ]);
                    $horarios++;
                } catch (\Throwable) {
                    // schema/constraint — segue demais aulas
                }
            }
        }

        return compact('quadro', 'horarios', 'alocacoes') + ['professor_turma' => $pt];
    }

    /** @return array{escola:int,serie:int,ano:int}|null */
    private static function metaTurma(int $turmaId): ?array
    {
        $t = DB::table('pmieducar.turma')->where('cod_turma', $turmaId)->where('ativo', 1)->first();
        if ($t === null) {
            return null;
        }

        return [
            'escola' => (int) ($t->ref_ref_cod_escola ?? 0),
            'serie' => (int) ($t->ref_ref_cod_serie ?? 0),
            'ano' => (int) ($t->ano ?? date('Y')),
        ];
    }

    private static function garantirQuadro(int $turmaId, int $usuarioId, int $ano): int
    {
        $existente = DB::table('pmieducar.quadro_horario')
            ->where('ref_cod_turma', $turmaId)
            ->where('ativo', 1)
            ->orderByDesc('cod_quadro_horario')
            ->value('cod_quadro_horario');
        if ($existente) {
            return (int) $existente;
        }

        return (int) DB::table('pmieducar.quadro_horario')->insertGetId([
            'ref_usuario_cad' => $usuarioId,
            'ref_cod_turma' => $turmaId,
            'data_cadastro' => now(),
            'ativo' => 1,
            'ano' => $ano,
        ], 'cod_quadro_horario');
    }

    private static function garantirServidor(int $idpes, int $inst): void
    {
        if (! Schema::hasTable('pmieducar.servidor')) {
            return;
        }
        if (! DB::table('cadastro.pessoa')->where('idpes', $idpes)->exists()) {
            return;
        }
        DB::table('pmieducar.servidor')->updateOrInsert(
            ['cod_servidor' => $idpes],
            [
                'ref_cod_instituicao' => $inst,
                'carga_horaria' => 40,
                'data_cadastro' => now(),
                'ativo' => 1,
            ]
        );
    }

    private static function garantirAlocacao(int $idpes, int $inst, int $escola, int $ano, int $usuarioId): bool
    {
        if (! Schema::hasTable('pmieducar.servidor_alocacao') || $escola < 1) {
            return false;
        }
        $exists = DB::table('pmieducar.servidor_alocacao')
            ->where('ref_cod_servidor', $idpes)
            ->where('ref_cod_escola', $escola)
            ->where('ano', $ano)
            ->where('ativo', 1)
            ->exists();
        if ($exists) {
            return false;
        }
        try {
            DB::table('pmieducar.servidor_alocacao')->insert([
                'ref_ref_cod_instituicao' => $inst,
                'ref_cod_escola' => $escola,
                'ref_cod_servidor' => $idpes,
                'ref_usuario_cad' => $usuarioId,
                'carga_horaria' => '40:00:00',
                'periodo' => 3,
                'ano' => $ano,
                'data_admissao' => now()->toDateString(),
                'data_cadastro' => now(),
                'ativo' => 1,
            ]);

            return true;
        } catch (\Throwable) {
            try {
                DB::table('pmieducar.servidor_alocacao')->insert([
                    'ref_ref_cod_instituicao' => $inst,
                    'ref_cod_escola' => $escola,
                    'ref_cod_servidor' => $idpes,
                    'ref_usuario_cad' => $usuarioId,
                    'carga_horaria' => 40,
                    'periodo' => 3,
                    'ano' => $ano,
                    'data_admissao' => now()->toDateString(),
                    'data_cadastro' => now(),
                    'ativo' => 1,
                ]);

                return true;
            } catch (\Throwable) {
                return false;
            }
        }
    }

    private static function garantirProfessorTurma(int $servidorId, int $inst, int $turmaId, int $ano, int $componenteId): bool
    {
        if (! Schema::hasTable('modules.professor_turma')) {
            return false;
        }

        $existente = DB::table('modules.professor_turma')
            ->where('servidor_id', $servidorId)
            ->where('turma_id', $turmaId)
            ->where('ano', $ano)
            ->value('id');

        $novo = false;
        if (! $existente) {
            $existente = DB::table('modules.professor_turma')->insertGetId([
                'ano' => $ano,
                'instituicao_id' => $inst,
                'turma_id' => $turmaId,
                'servidor_id' => $servidorId,
                'funcao_exercida' => 1,
                'tipo_vinculo' => 1,
                'permite_lancar_faltas_componente' => 1,
                'updated_at' => now(),
            ]);
            $novo = true;
        }

        if ($componenteId > 0 && Schema::hasTable('modules.professor_turma_disciplina')) {
            $has = DB::table('modules.professor_turma_disciplina')
                ->where('professor_turma_id', $existente)
                ->where('componente_curricular_id', $componenteId)
                ->exists();
            if (! $has) {
                DB::table('modules.professor_turma_disciplina')->insert([
                    'professor_turma_id' => $existente,
                    'componente_curricular_id' => $componenteId,
                ]);
            }
        }

        return $novo;
    }
}
