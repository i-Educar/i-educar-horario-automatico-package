<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

/**
 * Diagnósticos do quadro: professores livres, aulas vagas, demanda incompleta e choques.
 */
final class VerificadorQuadro
{
    /**
     * @param  list<array<string,mixed>>  $aulasTodas
     * @param  list<array{inicio:string,fim:string,rotulo:string,turno?:string}>  $periodos
     * @param  array{turma_id?:?int,turma_ids?:list<int>,servidor_id?:?int,ano?:int}  $escopo
     * @return array{
     *   resumo:array{ok:int,aviso:int,erro:int},
     *   itens:list<array{tipo:string,severidade:string,titulo:string,detalhe:string,acao:?string}>
     * }
     */
    public static function analisar(array $aulasTodas, array $periodos, array $escopo = []): array
    {
        $itens = [];
        $turmaIds = array_values(array_filter(array_map('intval', (array) ($escopo['turma_ids'] ?? []))));
        if (! empty($escopo['turma_id'])) {
            $turmaIds[] = (int) $escopo['turma_id'];
        }
        $turmaIds = array_values(array_unique(array_filter($turmaIds)));
        $servidorId = isset($escopo['servidor_id']) ? (int) $escopo['servidor_id'] : 0;
        $ano = (int) ($escopo['ano'] ?? DiretivasHorario::atuais()['ano'] ?? date('Y'));
        $dias = [1, 2, 3, 4, 5];

        $periodosAula = array_values(array_filter($periodos, static fn ($p) => ! empty($p['inicio'])));

        // Choques
        foreach (DetectorChoque::detalhar($aulasTodas) as $c) {
            $itens[] = [
                'tipo' => 'choque',
                'severidade' => 'erro',
                'titulo' => $c['motivo'],
                'detalhe' => 'Tipo: '.$c['tipo'],
                'acao' => '/horario-automatico/choques',
            ];
        }

        // Demanda da matriz × alocado (AEE / Educação especial fora do escopo)
        foreach ($turmaIds as $tid) {
            if (TurmaPerfilHorario::ehForaDoQuadroAutomatico($tid)) {
                continue;
            }
            $comps = MatrizCurricular::componentesParaTurma($tid);
            $semanas = (int) (DiretivasHorario::atuais()['semanas_letivas'] ?? 40);
            $cargaPadrao = (int) (DiretivasHorario::atuais()['carga_padrao_aulas'] ?? 1);
            $porComp = [];
            foreach ($aulasTodas as $a) {
                if ((int) ($a['turma_id'] ?? 0) !== $tid) {
                    continue;
                }
                $cid = (int) ($a['componente_id'] ?? 0);
                if ($cid < 1) {
                    continue;
                }
                $porComp[$cid] = ($porComp[$cid] ?? 0) + 1;
            }
            foreach ($comps as $c) {
                $cid = (int) $c['id'];
                $precisa = GeradorHorario::aulasSemanaisPublico($c, $cargaPadrao, $semanas);
                $tem = $porComp[$cid] ?? 0;
                if ($tem < $precisa) {
                    $itens[] = [
                        'tipo' => 'demanda',
                        'severidade' => 'aviso',
                        'titulo' => ($c['nome'] ?? 'Disciplina').' incompleta',
                        'detalhe' => "Turma #{$tid}: {$tem}/{$precisa} aulas na semana",
                        'acao' => '/horario-automatico/grade?visao=turma&turma_id='.$tid,
                    ];
                } elseif ($tem > $precisa) {
                    $itens[] = [
                        'tipo' => 'excesso',
                        'severidade' => 'aviso',
                        'titulo' => ($c['nome'] ?? 'Disciplina').' com excesso',
                        'detalhe' => "Turma #{$tid}: {$tem}/{$precisa} aulas na semana",
                        'acao' => '/horario-automatico/grade?visao=turma&turma_id='.$tid,
                    ];
                }
            }
        }

        // Células vagas da(s) turma(s)
        foreach ($turmaIds as $tid) {
            $ocupadas = [];
            foreach ($aulasTodas as $a) {
                if ((int) ($a['turma_id'] ?? 0) !== $tid) {
                    continue;
                }
                $ocupadas[(int) $a['dia'].'|'.$a['inicio']] = true;
            }
            $vagas = 0;
            $exemplos = [];
            foreach ($dias as $dia) {
                foreach ($periodosAula as $p) {
                    $chave = $dia.'|'.$p['inicio'];
                    if (! isset($ocupadas[$chave])) {
                        $vagas++;
                        if (count($exemplos) < 3) {
                            $exemplos[] = self::diaCurto($dia).' '.$p['inicio'];
                        }
                    }
                }
            }
            if ($vagas > 0) {
                $itens[] = [
                    'tipo' => 'vaga_turma',
                    'severidade' => $vagas >= 5 ? 'aviso' : 'ok',
                    'titulo' => "Turma com {$vagas} horário(s) vago(s)",
                    'detalhe' => $exemplos !== [] ? ('Ex.: '.implode(', ', $exemplos)) : "Turma #{$tid}",
                    'acao' => '/horario-automatico/grade?visao=turma&turma_id='.$tid,
                ];
            }
        }

        // Professor com horários livres (visão servidor ou lista curta)
        $profIds = [];
        if ($servidorId > 0) {
            $profIds[] = $servidorId;
        } else {
            foreach ($aulasTodas as $a) {
                $pid = (int) ($a['professor_id'] ?? 0);
                if ($pid > 0) {
                    $profIds[$pid] = $pid;
                }
            }
            $profIds = array_slice(array_values($profIds), 0, 12);
        }
        foreach ($profIds as $pid) {
            $ocup = [];
            foreach ($aulasTodas as $a) {
                if ((int) ($a['professor_id'] ?? 0) !== $pid) {
                    continue;
                }
                $ocup[(int) $a['dia'].'|'.$a['inicio']] = true;
            }
            $livres = 0;
            $exemplos = [];
            foreach ($dias as $dia) {
                foreach ($periodosAula as $p) {
                    $chave = $dia.'|'.$p['inicio'];
                    if (! isset($ocup[$chave])) {
                        $livres++;
                        if (count($exemplos) < 3) {
                            $exemplos[] = self::diaCurto($dia).' '.$p['inicio'];
                        }
                    }
                }
            }
            if ($livres > 0) {
                $nome = 'Professor #'.$pid;
                foreach ($aulasTodas as $a) {
                    if ((int) ($a['professor_id'] ?? 0) === $pid) {
                        $nome = (string) ($a['professor'] ?? $nome);
                        break;
                    }
                }
                $itens[] = [
                    'tipo' => 'prof_livre',
                    'severidade' => 'ok',
                    'titulo' => "{$nome} · {$livres} horário(s) disponível(is)",
                    'detalhe' => $exemplos !== [] ? ('Livres: '.implode(', ', $exemplos)) : '',
                    'acao' => '/horario-automatico/grade?visao=servidor&servidor_id='.$pid,
                ];
            }
        }

        // Aulas sem professor / sem componente
        foreach ($aulasTodas as $a) {
            if (! empty($turmaIds) && ! in_array((int) ($a['turma_id'] ?? 0), $turmaIds, true) && $servidorId < 1) {
                continue;
            }
            if ((int) ($a['professor_id'] ?? 0) < 1) {
                $itens[] = [
                    'tipo' => 'sem_professor',
                    'severidade' => 'erro',
                    'titulo' => 'Aula sem professor',
                    'detalhe' => ($a['componente'] ?? '—').' · '.($a['turma'] ?? ''),
                    'acao' => null,
                ];
            }
            if ((int) ($a['componente_id'] ?? 0) < 1 && trim((string) ($a['componente'] ?? '')) === '') {
                $itens[] = [
                    'tipo' => 'sem_disciplina',
                    'severidade' => 'erro',
                    'titulo' => 'Aula sem disciplina',
                    'detalhe' => ($a['turma'] ?? '').' · dia '.($a['dia'] ?? ''),
                    'acao' => null,
                ];
            }
        }

        // Limita volume na UI
        usort($itens, static function ($a, $b): int {
            $peso = ['erro' => 0, 'aviso' => 1, 'ok' => 2];

            return ($peso[$a['severidade']] ?? 9) <=> ($peso[$b['severidade']] ?? 9);
        });
        $itens = array_slice($itens, 0, 40);

        $resumo = ['ok' => 0, 'aviso' => 0, 'erro' => 0];
        foreach ($itens as $i) {
            $s = $i['severidade'];
            if (isset($resumo[$s])) {
                $resumo[$s]++;
            }
        }

        return compact('resumo', 'itens');
    }

    private static function diaCurto(int $dia): string
    {
        return [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex'][$dia] ?? (string) $dia;
    }
}
