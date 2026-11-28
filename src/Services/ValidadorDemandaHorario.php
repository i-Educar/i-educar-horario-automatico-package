<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

/**
 * Confere a demanda curricular antes/depois da geração:
 * falta de disciplina, excesso de aulas, professor ausente, capacidade semanal.
 */
final class ValidadorDemandaHorario
{
    /**
     * @param  list<int>  $escolaIds
     * @return array{
     *   ok:bool,
     *   erros:list<array{tipo:string,turma:?string,disciplina:?string,mensagem:string}>,
     *   avisos:list<array{tipo:string,turma:?string,disciplina:?string,mensagem:string}>,
     *   turmas:list<array{turma:string,disciplinas:int,aulas_semana:int,capacidade:int,sem_professor:int}>,
     *   totais:array{turmas:int,disciplinas:int,aulas:int,erros:int,avisos:int}
     * }
     */
    public static function analisar(int $ano, array $escolaIds = [], ?array $diretivas = null): array
    {
        $diretivas ??= DiretivasHorario::atuais();
        $periodos = array_values((array) ($diretivas['periodos'] ?? DiretivasHorario::periodosPadrao()));
        $dias = 5;
        // Capacidade por turno: períodos matutinos × 5 dias (mais comum no fundamental)
        $matutinos = count(array_filter($periodos, static fn ($p) => ($p['turno'] ?? '') === 'matutino'));
        $capacidadeDia = max(1, $matutinos > 0 ? $matutinos : min(5, count($periodos)));
        $capacidadeSemana = $capacidadeDia * $dias;

        $semanas = max(20, min(50, (int) ($diretivas['semanas_letivas'] ?? 40)));
        $cargaPadrao = max(1, (int) ($diretivas['carga_padrao_aulas'] ?? 1));

        $todas = MatrizCurricular::turmas($ano, 400);
        if ($escolaIds !== []) {
            $set = array_flip($escolaIds);
            $todas = array_values(array_filter($todas, static fn (array $t): bool => isset($set[(int) ($t['escola_id'] ?? 0)])));
        }

        $ignoradasAee = 0;
        $turmas = [];
        foreach ($todas as $turma) {
            if (! empty($turma['fora_quadro_automatico']) || ! empty($turma['eh_aee'])
                || TurmaPerfilHorario::ehForaDoQuadroAutomatico((int) $turma['id'])) {
                $ignoradasAee++;

                continue;
            }
            $turmas[] = $turma;
        }

        $erros = [];
        $avisos = [];
        $resumoTurmas = [];
        $totalDisc = 0;
        $totalAulas = 0;

        if ($ignoradasAee > 0) {
            $avisos[] = [
                'tipo' => 'aee_ignorada',
                'turma' => null,
                'disciplina' => null,
                'mensagem' => $ignoradasAee.' turma(s) de AEE / Educação especial foram ignoradas na validação e na geração (sem matriz curricular regular).',
            ];
        }

        foreach ($turmas as $turma) {
            $comps = MatrizCurricular::componentesParaTurma((int) $turma['id']);
            if ($comps === []) {
                $erros[] = [
                    'tipo' => 'sem_matriz',
                    'turma' => (string) $turma['nome'],
                    'disciplina' => null,
                    'mensagem' => 'A turma «'.$turma['nome'].'» não tem disciplinas na matriz curricular. Cadastre na escola/série ou use a tela Matriz deste módulo.',
                ];
                $resumoTurmas[] = [
                    'turma' => (string) $turma['nome'],
                    'disciplinas' => 0,
                    'aulas_semana' => 0,
                    'capacidade' => $capacidadeSemana,
                    'sem_professor' => 0,
                ];

                continue;
            }

            $aulasTurma = 0;
            $semProf = 0;
            foreach ($comps as $c) {
                $totalDisc++;
                $n = GeradorHorario::aulasSemanaisPublico($c, $cargaPadrao, $semanas);
                $aulasTurma += $n;
                $totalAulas += $n;

                $profs = MatrizCurricular::professoresParaComponente((int) $turma['id'], (int) $c['id']);
                if ($profs === []) {
                    $profs = MatrizCurricular::professoresDaEscola((int) ($turma['escola_id'] ?? 0), 5);
                }
                if ($profs === []) {
                    $semProf++;
                    $erros[] = [
                        'tipo' => 'sem_professor',
                        'turma' => (string) $turma['nome'],
                        'disciplina' => (string) $c['nome'],
                        'mensagem' => 'Não há professor para «'.$c['nome'].'» na turma «'.$turma['nome'].'». Vincule o docente à turma ou à disciplina.',
                    ];
                }

                if (empty($c['aulas_por_semana']) && (float) ($c['carga_horaria'] ?? 0) <= 0) {
                    $avisos[] = [
                        'tipo' => 'carga_padrao',
                        'turma' => (string) $turma['nome'],
                        'disciplina' => (string) $c['nome'],
                        'mensagem' => '«'.$c['nome'].'» sem aulas/semana nem carga horária — usando padrão de '.$cargaPadrao.' aula(s).',
                    ];
                }
            }

            if ($aulasTurma > $capacidadeSemana) {
                $erros[] = [
                    'tipo' => 'excesso_aulas',
                    'turma' => (string) $turma['nome'],
                    'disciplina' => null,
                    'mensagem' => 'A turma «'.$turma['nome'].'» precisa de '.$aulasTurma.' aulas/semana, mas há só '.$capacidadeSemana.' horários livres ('.$capacidadeDia.' períodos × '.$dias.' dias). Reduza aulas/semana ou acrescente períodos.',
                ];
            } elseif ($aulasTurma > (int) floor($capacidadeSemana * 0.9)) {
                $avisos[] = [
                    'tipo' => 'capacidade_apertada',
                    'turma' => (string) $turma['nome'],
                    'disciplina' => null,
                    'mensagem' => 'A turma «'.$turma['nome'].'» está com a grade quase cheia ('.$aulasTurma.'/'.$capacidadeSemana.').',
                ];
            }

            $resumoTurmas[] = [
                'turma' => (string) $turma['nome'],
                'disciplinas' => count($comps),
                'aulas_semana' => $aulasTurma,
                'capacidade' => $capacidadeSemana,
                'sem_professor' => $semProf,
            ];
        }

        return [
            'ok' => $erros === [],
            'erros' => $erros,
            'avisos' => $avisos,
            'turmas' => $resumoTurmas,
            'totais' => [
                'turmas' => count($resumoTurmas),
                'disciplinas' => $totalDisc,
                'aulas' => $totalAulas,
                'erros' => count($erros),
                'avisos' => count($avisos),
            ],
        ];
    }
}
