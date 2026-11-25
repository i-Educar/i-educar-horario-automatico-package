<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use RuntimeException;

/**
 * Geração automática com diretivas — heurística gulosa com priorização
 * (geminadas e componentes com menos professores primeiro).
 * Estado persistido (tipo=geracao) para pausa/retomada.
 */
final class GeradorHorario
{
    public const string TIPO = 'geracao';

    public const string STATUS_RODANDO = 'rodando';

    public const string STATUS_PAUSADO = 'pausado';

    public const string STATUS_CONCLUIDO = 'concluido';

    public const string STATUS_ERRO = 'erro';

    /**
     * @param  array{ano?:int,escola_ids?:list<int>,substituir?:bool,limite_passos?:int}  $opcoes
     * @return array<string,mixed>
     */
    public static function iniciar(array $opcoes = []): array
    {
        GradeVigencia::assertEditavel();
        GradeVigencia::garantirRascunho();

        $diretivas = DiretivasHorario::atuais();
        $ano = (int) ($opcoes['ano'] ?? $diretivas['ano'] ?? date('Y'));
        $escolaIds = array_values(array_filter(array_map('intval', (array) ($opcoes['escola_ids'] ?? $diretivas['escola_ids'] ?? []))));
        $substituir = (bool) ($opcoes['substituir'] ?? true);
        $limite = max(10, min(500, (int) ($opcoes['limite_passos'] ?? 80)));

        $validacao = ValidadorDemandaHorario::analisar($ano, $escolaIds, $diretivas);
        $demanda = self::montarDemanda($ano, $escolaIds, $diretivas);
        if ($demanda === []) {
            $msg = 'Nenhuma disciplina encontrada para montar o quadro.';
            if (($validacao['erros'][0]['mensagem'] ?? '') !== '') {
                $msg = $validacao['erros'][0]['mensagem'];
            }
            throw new RuntimeException($msg.' Revise a Matriz curricular neste módulo.');
        }

        if ($substituir) {
            // Só remove aulas do rascunho atual — nunca apaga histórico vigente/arquivado.
            $ids = GradeVigencia::aulaIdsAtuais();
            if ($ids === null) {
                PocRegistro::apagarPorTipo(QuadroEditor::TIPO);
            } else {
                foreach ($ids as $aid) {
                    PocRegistro::apagar((int) $aid);
                }
                GradeVigencia::sincronizarIdsRascunho([]);
            }
        }

        $estado = [
            'status' => self::STATUS_RODANDO,
            'ano' => $ano,
            'escola_ids' => $escolaIds,
            'total' => count($demanda),
            'feitos' => 0,
            'alocadas' => 0,
            'falhas' => 0,
            'cursor' => 0,
            'demanda' => $demanda,
            'log' => [],
            'validacao' => [
                'ok' => $validacao['ok'],
                'erros' => count($validacao['erros']),
                'avisos' => count($validacao['avisos']),
                'detalhes' => array_slice($validacao['erros'], 0, 20),
            ],
            'iniciado_em' => now()->toDateTimeString(),
            'atualizado_em' => now()->toDateTimeString(),
        ];
        foreach (array_slice($validacao['erros'], 0, 8) as $erro) {
            $estado['log'][] = 'AVISO · '.$erro['mensagem'];
        }

        $id = self::idAtual();
        if ($id !== null) {
            PocRegistro::atualizar($id, $estado, 'Geração '.$ano, '0/'.count($demanda));
        } else {
            $id = PocRegistro::gravar(self::TIPO, 'Geração '.$ano, '0/'.count($demanda), $estado);
        }

        return self::continuar($limite);
    }

    /**
     * Processa até $limite itens da fila (para UI com progresso).
     *
     * @return array<string,mixed>
     */
    public static function continuar(int $limite = 80): array
    {
        $estado = self::estado();
        if ($estado === null) {
            throw new RuntimeException('Nenhuma geração em andamento.');
        }
        if (($estado['status'] ?? '') === self::STATUS_CONCLUIDO) {
            return $estado;
        }
        if (($estado['status'] ?? '') === self::STATUS_PAUSADO) {
            $estado['status'] = self::STATUS_RODANDO;
        }

        $diretivas = DiretivasHorario::atuais();
        $periodos = array_values((array) ($diretivas['periodos'] ?? DiretivasHorario::periodosPadrao()));
        $demanda = (array) ($estado['demanda'] ?? []);
        $cursor = (int) ($estado['cursor'] ?? 0);
        $processados = 0;
        $aulas = QuadroEditor::aulas();

        while ($cursor < count($demanda) && $processados < $limite) {
            $item = $demanda[$cursor];
            if (! empty($item['ok'])) {
                $cursor++;
                $processados++;

                continue;
            }

            $resultado = self::alocarItem($item, $aulas, $periodos, $diretivas);
            if ($resultado['ok']) {
                $demanda[$cursor]['ok'] = true;
                $demanda[$cursor]['aula_id'] = $resultado['aula_id'];
                $estado['alocadas'] = (int) ($estado['alocadas'] ?? 0) + 1;
                if (! empty($resultado['aulas_novas'])) {
                    foreach ($resultado['aulas_novas'] as $nova) {
                        $aulas[] = $nova;
                    }
                } else {
                    $aulas = QuadroEditor::aulas();
                }
                $estado['log'][] = 'OK · '.$item['turma'].' / '.$item['componente'].' · dia '.$resultado['dia'].' '.$resultado['inicio'];
            } else {
                $demanda[$cursor]['ok'] = false;
                $demanda[$cursor]['erro'] = $resultado['erro'];
                $estado['falhas'] = (int) ($estado['falhas'] ?? 0) + 1;
                $estado['log'][] = 'FALHA · '.$item['turma'].' / '.$item['componente'].' — '.$resultado['erro'];
            }

            $estado['feitos'] = (int) ($estado['feitos'] ?? 0) + 1;
            $cursor++;
            $processados++;

            // limita log
            if (count($estado['log']) > 80) {
                $estado['log'] = array_slice($estado['log'], -80);
            }
        }

        $estado['cursor'] = $cursor;
        $estado['demanda'] = $demanda;
        $estado['atualizado_em'] = now()->toDateTimeString();
        $estado['status'] = $cursor >= count($demanda) ? self::STATUS_CONCLUIDO : self::STATUS_RODANDO;

        $id = self::idAtual();
        if ($id !== null) {
            PocRegistro::atualizar(
                $id,
                $estado,
                'Geração '.$estado['ano'],
                $estado['feitos'].'/'.$estado['total'].' · '.$estado['status']
            );
        }

        return $estado;
    }

    /** @return array<string,mixed> */
    public static function pausar(): array
    {
        $estado = self::estado();
        if ($estado === null) {
            throw new RuntimeException('Nenhuma geração em andamento.');
        }
        $estado['status'] = self::STATUS_PAUSADO;
        $estado['atualizado_em'] = now()->toDateTimeString();
        $id = self::idAtual();
        if ($id !== null) {
            PocRegistro::atualizar($id, $estado, null, $estado['feitos'].'/'.$estado['total'].' · pausado');
        }

        return $estado;
    }

    /** @return array<string,mixed>|null */
    public static function estado(): ?array
    {
        $reg = PocRegistro::ultimo(self::TIPO);
        if ($reg === null) {
            return null;
        }

        return is_array($reg['payload'] ?? null) ? $reg['payload'] : null;
    }

    public static function idAtual(): ?int
    {
        return PocRegistro::ultimo(self::TIPO)['id'] ?? null;
    }

    /**
     * @param  list<int>  $escolaIds
     * @param  array<string,mixed>  $diretivas
     * @return list<array<string,mixed>>
     */
    private static function montarDemanda(int $ano, array $escolaIds, array $diretivas): array
    {
        $turmas = MatrizCurricular::turmas($ano, 400);
        if ($escolaIds !== []) {
            $set = array_flip($escolaIds);
            $turmas = array_values(array_filter($turmas, static fn (array $t): bool => isset($set[(int) ($t['escola_id'] ?? 0)])));
        }

        $geminadas = array_flip(array_map('intval', (array) ($diretivas['geminadas'] ?? [])));
        $cargaPadrao = max(1, (int) ($diretivas['carga_padrao_aulas'] ?? 1));
        $semanas = max(20, min(50, (int) ($diretivas['semanas_letivas'] ?? 40)));
        $preferenciais = [];
        foreach ((array) ($diretivas['preferenciais'] ?? []) as $p) {
            $preferenciais[(int) $p['turma_id'].':'.(int) $p['componente_id']] = (int) $p['professor_id'];
        }

        $demanda = [];
        foreach ($turmas as $turma) {
            $comps = MatrizCurricular::componentesParaTurma((int) $turma['id']);
            foreach ($comps as $c) {
                $aulasSemana = self::aulasSemanaisDeComponente($c, $cargaPadrao, $semanas);
                $geminada = isset($geminadas[(int) $c['id']]);
                $pref = $preferenciais[(int) $turma['id'].':'.(int) $c['id']] ?? null;

                if ($geminada) {
                    $blocos = (int) ceil($aulasSemana / 2);
                    for ($i = 0; $i < $blocos; $i++) {
                        $demanda[] = self::itemDemanda($turma, $c, true, $pref);
                    }
                } else {
                    for ($i = 0; $i < $aulasSemana; $i++) {
                        $demanda[] = self::itemDemanda($turma, $c, false, $pref);
                    }
                }
            }
        }

        // Prioriza geminadas e depois quem tem menos professores candidatos
        usort($demanda, static function (array $a, array $b): int {
            if ($a['geminada'] !== $b['geminada']) {
                return $a['geminada'] ? -1 : 1;
            }

            return ($a['candidatos'] <=> $b['candidatos']);
        });

        return $demanda;
    }

    /**
     * Aulas/semana a partir do componente (público para o validador).
     *
     * Prioridade: aulas_por_semana (escola×série / ajuste) → carga semanal (<20) → carga anual ÷ semanas letivas.
     *
     * @param  array{aulas_por_semana?:?int,carga_horaria?:?float}  $c
     */
    public static function aulasSemanaisPublico(array $c, int $padrao = 1, int $semanasLetivas = 40): int
    {
        return self::aulasSemanaisDeComponente($c, $padrao, $semanasLetivas);
    }

    /**
     * @param  array{aulas_por_semana?:?int,carga_horaria?:?float}  $c
     */
    private static function aulasSemanaisDeComponente(array $c, int $padrao, int $semanasLetivas): int
    {
        $aulas = (int) ($c['aulas_por_semana'] ?? 0);
        if ($aulas > 0) {
            return max(1, min(12, $aulas));
        }

        return self::aulasSemanais((float) ($c['carga_horaria'] ?? 0), $padrao, $semanasLetivas);
    }

    /**
     * Converte carga da matriz em aulas/semana.
     * Valores ≥ 20 costumam ser carga anual (ex.: 80h / 40 semanas ≈ 2 aulas/semana).
     */
    private static function aulasSemanais(float $carga, int $padrao, int $semanasLetivas = 40): int
    {
        if ($carga <= 0) {
            return max(1, $padrao);
        }
        if ($carga < 20) {
            // Já parece semanal (ex.: 3 = 3 aulas/semana)
            return max(1, min(12, (int) round($carga)));
        }
        $semanas = max(20, min(50, $semanasLetivas));

        return max(1, min(12, (int) round($carga / $semanas)));
    }

    /**
     * @param  array{id:int,nome:string,escola:?string,escola_id?:?int}  $turma
     * @param  array{id:int,nome:string,cor:string}  $c
     * @return array<string,mixed>
     */
    private static function itemDemanda(array $turma, array $c, bool $geminada, ?int $pref): array
    {
        $cands = MatrizCurricular::professoresParaComponente((int) $turma['id'], (int) $c['id']);
        if ($cands === []) {
            $cands = MatrizCurricular::professoresDaEscola((int) ($turma['escola_id'] ?? 0));
        }
        if ($pref !== null && $pref > 0) {
            array_unshift($cands, [
                'id' => $pref,
                'nome' => MatrizCurricular::nomeServidor($pref) ?? ('Servidor #'.$pref),
                'preferencial' => true,
            ]);
            $seen = [];
            $uniq = [];
            foreach ($cands as $p) {
                if (isset($seen[$p['id']])) {
                    continue;
                }
                $seen[$p['id']] = true;
                $uniq[] = $p;
            }
            $cands = $uniq;
        }

        return [
            'turma_id' => (int) $turma['id'],
            'turma' => (string) $turma['nome'],
            'escola_id' => isset($turma['escola_id']) ? (int) $turma['escola_id'] : null,
            'escola' => $turma['escola'] ?? null,
            'componente_id' => (int) $c['id'],
            'componente' => (string) $c['nome'],
            'cor' => (string) ($c['cor'] ?? '#47728f'),
            'geminada' => $geminada,
            'preferencial' => $pref,
            'professores' => $cands,
            'candidatos' => count($cands),
            'ok' => false,
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  list<array<string,mixed>>  $aulas
     * @param  list<array{inicio:string,fim:string,rotulo:string,turno?:string}>  $periodos
     * @param  array<string,mixed>  $diretivas
     * @return array{ok:bool,aula_id?:int,dia?:int,inicio?:string,erro?:string,aulas_novas?:list<array<string,mixed>>}
     */
    private static function alocarItem(array $item, array $aulas, array $periodos, array $diretivas): array
    {
        $profs = (array) ($item['professores'] ?? []);
        if ($profs === []) {
            return ['ok' => false, 'erro' => 'Sem professor habilitado / vinculado'];
        }

        $separar = (bool) ($diretivas['separar_dias'] ?? true);
        $maxDia = max(1, (int) ($diretivas['max_aulas_mesmo_dia'] ?? 2));
        $dias = [1, 2, 3, 4, 5];

        // Carga da turma por dia + aulas desta disciplina por dia (espalha a semana)
        $cargaTurmaDia = array_fill_keys($dias, 0);
        $cargaProfDia = array_fill_keys($dias, 0);
        $contagemDia = array_fill_keys($dias, 0);
        foreach ($aulas as $a) {
            $d = (int) ($a['dia'] ?? 0);
            if ($d < 1 || $d > 5) {
                continue;
            }
            if ((int) ($a['turma_id'] ?? 0) === (int) $item['turma_id']) {
                $cargaTurmaDia[$d]++;
                if ((int) ($a['componente_id'] ?? 0) === (int) $item['componente_id']) {
                    $contagemDia[$d]++;
                }
            }
        }
        usort($dias, static function (int $x, int $y) use ($cargaTurmaDia, $contagemDia, $separar): int {
            // 1) dias com menos aulas da turma  2) menos desta disciplina  3) ordem da semana
            $cmp = ($cargaTurmaDia[$x] <=> $cargaTurmaDia[$y]);
            if ($cmp !== 0) {
                return $cmp;
            }
            if ($separar) {
                $cmp = ($contagemDia[$x] <=> $contagemDia[$y]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return $x <=> $y;
        });

        $ultimoErro = 'Sem horário livre nesta semana (todos os períodos estão ocupados ou em conflito).';
        foreach ($profs as $prof) {
            $profId = (int) ($prof['id'] ?? 0);
            foreach ($dias as $d) {
                $cargaProfDia[$d] = 0;
            }
            foreach ($aulas as $a) {
                $d = (int) ($a['dia'] ?? 0);
                if ($d >= 1 && $d <= 5 && (int) ($a['professor_id'] ?? 0) === $profId) {
                    $cargaProfDia[$d]++;
                }
            }
            // Reordena leve: preferir dia em que o professor também está mais livre
            $diasProf = $dias;
            usort($diasProf, static function (int $x, int $y) use ($cargaTurmaDia, $cargaProfDia, $contagemDia): int {
                return ($cargaTurmaDia[$x] <=> $cargaTurmaDia[$y])
                    ?: ($cargaProfDia[$x] <=> $cargaProfDia[$y])
                    ?: ($contagemDia[$x] <=> $contagemDia[$y])
                    ?: ($x <=> $y);
            });

            foreach ($diasProf as $dia) {
                if (($contagemDia[$dia] ?? 0) >= $maxDia) {
                    continue;
                }
                for ($pi = 0; $pi < count($periodos); $pi++) {
                    $p = $periodos[$pi];
                    if (! empty($item['geminada'])) {
                        if ($pi + 1 >= count($periodos)) {
                            continue;
                        }
                        $p2 = $periodos[$pi + 1];
                        // geminadas no mesmo turno, períodos consecutivos
                        $t1 = $p['turno'] ?? DiretivasHorario::turnoDe($p['inicio']);
                        $t2 = $p2['turno'] ?? DiretivasHorario::turnoDe($p2['inicio']);
                        if ($t1 !== $t2) {
                            continue;
                        }
                        $cand1 = self::candidata($item, $prof, $dia, $p);
                        $cand2 = self::candidata($item, $prof, $dia, $p2);
                        $err1 = DetectorChoque::conflitoCom($cand1, $aulas, $diretivas);
                        if ($err1 !== null) {
                            $ultimoErro = $err1;

                            continue;
                        }
                        $tmp = $aulas;
                        $tmp[] = $cand1;
                        $err2 = DetectorChoque::conflitoCom($cand2, $tmp, $diretivas);
                        if ($err2 !== null) {
                            $ultimoErro = $err2;

                            continue;
                        }
                        try {
                            $payload1 = array_merge($cand1, [
                                'professor' => (string) $prof['nome'],
                                'componente' => (string) $item['componente'],
                                'turma' => (string) $item['turma'],
                                'cor' => $item['cor'],
                                'regencia' => QuadroEditor::REGENTE_AULAS,
                                'geminada' => true,
                                'validar_choque' => true,
                            ]);
                            $id1 = QuadroEditor::alocar($payload1);
                            $payload2 = array_merge($cand2, [
                                'professor' => (string) $prof['nome'],
                                'componente' => (string) $item['componente'],
                                'turma' => (string) $item['turma'],
                                'cor' => $item['cor'],
                                'regencia' => QuadroEditor::REGENTE_AULAS,
                                'geminada' => true,
                                'validar_choque' => true,
                            ]);
                            $id2 = QuadroEditor::alocar($payload2);

                            return [
                                'ok' => true,
                                'aula_id' => $id1,
                                'dia' => $dia,
                                'inicio' => $p['inicio'],
                                'aulas_novas' => [
                                    array_merge($payload1, ['id' => $id1]),
                                    array_merge($payload2, ['id' => $id2]),
                                ],
                            ];
                        } catch (\Throwable $e) {
                            $ultimoErro = $e->getMessage();

                            continue;
                        }
                    }

                    $cand = self::candidata($item, $prof, $dia, $p);
                    $err = DetectorChoque::conflitoCom($cand, $aulas, $diretivas);
                    if ($err !== null) {
                        $ultimoErro = $err;

                        continue;
                    }
                    try {
                        $payload = array_merge($cand, [
                            'professor' => (string) $prof['nome'],
                            'componente' => (string) $item['componente'],
                            'turma' => (string) $item['turma'],
                            'cor' => $item['cor'],
                            'regencia' => QuadroEditor::REGENTE_AULAS,
                            'validar_choque' => true,
                        ]);
                        $id = QuadroEditor::alocar($payload);

                        return [
                            'ok' => true,
                            'aula_id' => $id,
                            'dia' => $dia,
                            'inicio' => $p['inicio'],
                            'aulas_novas' => [array_merge($payload, ['id' => $id])],
                        ];
                    } catch (\Throwable $e) {
                        $ultimoErro = $e->getMessage();
                    }
                }
            }
        }

        return ['ok' => false, 'erro' => $ultimoErro];
    }

    /**
     * @param  array<string,mixed>  $item
     * @param  array{id:int,nome:string}  $prof
     * @param  array{inicio:string,fim:string}  $periodo
     * @return array<string,mixed>
     */
    private static function candidata(array $item, array $prof, int $dia, array $periodo): array
    {
        return [
            'professor_id' => (int) $prof['id'],
            'turma_id' => (int) $item['turma_id'],
            'escola_id' => isset($item['escola_id']) ? (int) $item['escola_id'] : null,
            'componente_id' => (int) $item['componente_id'],
            'dia' => $dia,
            'inicio' => (string) $periodo['inicio'],
            'fim' => (string) $periodo['fim'],
        ];
    }
}
