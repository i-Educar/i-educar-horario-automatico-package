<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Http\Controllers;

use iEducar\Packages\BuritiHorarioAutomatico\Services\DetectorChoque;
use iEducar\Packages\BuritiHorarioAutomatico\Services\VerificadorQuadro;
use iEducar\Packages\BuritiHorarioAutomatico\Services\TurmaPerfilHorario;
use iEducar\Packages\BuritiHorarioAutomatico\Services\EixoHorario;
use iEducar\Packages\BuritiHorarioAutomatico\Services\GradeVigencia;
use iEducar\Packages\BuritiHorarioAutomatico\Support\Acesso;
use iEducar\Packages\BuritiHorarioAutomatico\Support\Processos;
use iEducar\Packages\BuritiHorarioAutomatico\Services\DiretivasHorario;
use iEducar\Packages\BuritiHorarioAutomatico\Services\GeradorHorario;
use iEducar\Packages\BuritiHorarioAutomatico\Services\GradeSemanal;
use iEducar\Packages\BuritiHorarioAutomatico\Services\MatrizAjuste;
use iEducar\Packages\BuritiHorarioAutomatico\Services\MatrizCurricular;
use iEducar\Packages\BuritiHorarioAutomatico\Services\PocRegistro;
use iEducar\Packages\BuritiHorarioAutomatico\Services\QuadroEditor;
use iEducar\Packages\BuritiHorarioAutomatico\Services\ValidadorDemandaHorario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HorarioController extends Controller
{
    public function index(): View
    {
        if ((bool) config('horario.seed_demo_se_vazio', false)) {
            $this->garantirSeed();
        }
        $grade = QuadroEditor::aulas();
        $detalhes = (bool) config('horario.detectar_choques', true) ? DetectorChoque::detalhar($grade) : [];
        $profs = collect($grade)->pluck('professor_id')->unique()->filter()->count();
        $turmas = collect($grade)->pluck('turma_id')->unique()->filter()->count();
        $geracao = GeradorHorario::estado();
        $diretivas = DiretivasHorario::atuais();

        $porTipo = ['professor' => 0, 'turma' => 0, 'multi_escola' => 0];
        foreach ($detalhes as $c) {
            $t = (string) ($c['tipo'] ?? 'professor');
            $porTipo[$t] = ($porTipo[$t] ?? 0) + 1;
        }
        $analise = [
            [
                'titulo' => 'Choques de professor',
                'meta' => 'Mesmo servidor em horários sobrepostos',
                'valor' => (string) $porTipo['professor'],
                'badge' => $porTipo['professor'] ? 'Revisar' : 'OK',
                'badge_tom' => $porTipo['professor'] ? 'fail' : 'ok',
                'url' => '/horario-automatico/choques',
            ],
            [
                'titulo' => 'Choques de turma',
                'meta' => 'Duas aulas no mesmo período da turma',
                'valor' => (string) $porTipo['turma'],
                'badge' => $porTipo['turma'] ? 'Revisar' : 'OK',
                'badge_tom' => $porTipo['turma'] ? 'fail' : 'ok',
                'url' => '/horario-automatico/choques',
            ],
            [
                'titulo' => 'Multi-escola no turno',
                'meta' => 'Professor em unidades diferentes',
                'valor' => (string) $porTipo['multi_escola'],
                'badge' => $porTipo['multi_escola'] ? 'Revisar' : 'OK',
                'badge_tom' => $porTipo['multi_escola'] ? 'fail' : 'ok',
                'url' => '/horario-automatico/choques',
            ],
            [
                'titulo' => 'Geração',
                'meta' => ($geracao['status'] ?? 'não iniciada').(isset($geracao['feitos']) ? ' · '.$geracao['feitos'].'/'.$geracao['total'] : ''),
                'valor' => (string) ($geracao['alocadas'] ?? 0),
                'badge' => (string) ($geracao['status'] ?? '—'),
                'badge_tom' => ($geracao['status'] ?? '') === 'concluido' ? 'ok' : 'warn',
                'url' => '/horario-automatico/gerar',
            ],
        ];

        return view('horario::painel', [
            'kpis' => [
                ['rotulo' => 'Aulas', 'valor' => count($grade), 'icone' => 'fa-calendar', 'tom' => 'var(--cor)'],
                ['rotulo' => 'Choques', 'valor' => count($detalhes), 'icone' => 'fa-warning', 'tom' => count($detalhes) ? 'var(--erro)' : 'var(--ok)'],
                ['rotulo' => 'Professores', 'valor' => $profs, 'icone' => 'fa-users', 'tom' => 'var(--destaque)'],
                ['rotulo' => 'Turmas', 'valor' => $turmas, 'icone' => 'fa-graduation-cap', 'tom' => '#0e7490'],
                ['rotulo' => 'Alocadas (geração)', 'valor' => (int) ($geracao['alocadas'] ?? 0), 'icone' => 'fa-magic', 'tom' => 'var(--ok)'],
                ['rotulo' => 'Falhas (geração)', 'valor' => (int) ($geracao['falhas'] ?? 0), 'icone' => 'fa-times-circle', 'tom' => ((int) ($geracao['falhas'] ?? 0)) > 0 ? 'var(--ambar)' : 'var(--ok)'],
            ],
            'geracao' => $geracao,
            'diretivas' => $diretivas,
            'destaques' => [
                [
                    'titulo' => count($detalhes) ? 'Conflitos ativos' : 'Grade consistente',
                    'meta' => 'Diretivas + validação dura na alocação',
                    'icone' => count($detalhes) ? 'fa-warning' : 'fa-check',
                    'tom' => count($detalhes) ? 'var(--erro)' : 'var(--ok)',
                    'corpo' => count($detalhes)
                        ? count($detalhes).' conflito(s) — professor, turma ou multi-escola.'
                        : 'Sem sobreposição de professor/turma na grade atual.',
                ],
                [
                    'titulo' => 'Geração automática',
                    'meta' => $geracao['status'] ?? 'ainda não iniciada',
                    'icone' => 'fa-magic',
                    'tom' => 'var(--destaque)',
                    'corpo' => isset($geracao['feitos'])
                        ? ($geracao['feitos'].'/'.$geracao['total'].' · '.$geracao['alocadas'].' alocadas · '.$geracao['falhas'].' falhas')
                        : 'Configure diretivas e rode a montagem por escola/rede.',
                ],
            ],
            'analise' => $analise,
            'analiseTitulo' => 'Consistência da grade',
            'resumo' => 'Quadro municipal · '.((int) ($diretivas['ano'] ?? date('Y'))).' · '
                .(count($diretivas['escola_ids'] ?? []) ?: 'todas as').' escola(s) · '
                .count($diretivas['periodos'] ?? []).' períodos · '
                .((($diretivas['bloquear_multi_escola_turno'] ?? true) ? '1 escola/turno' : 'multi-escola ok')),
            'atalhos' => [
                ['url' => '/horario-automatico/matriz', 'icone' => 'fa-th-list', 'rotulo' => 'Matriz', 'meta' => 'Aulas por disciplina'],
                ['url' => '/horario-automatico/validar', 'icone' => 'fa-check-square-o', 'rotulo' => 'Verificar', 'meta' => 'Faltas e excessos'],
                ['url' => '/horario-automatico/diretivas', 'icone' => 'fa-sliders', 'rotulo' => 'Regras', 'meta' => 'Períodos e limites'],
                ['url' => '/horario-automatico/gerar', 'icone' => 'fa-magic', 'rotulo' => 'Gerar', 'meta' => 'Montagem automática'],
                ['url' => '/horario-automatico/grade', 'icone' => 'fa-table', 'rotulo' => 'Ajustar', 'meta' => 'Quadro semanal'],
                ['url' => '/horario-automatico/choques', 'icone' => 'fa-warning', 'rotulo' => 'Conflitos', 'meta' => 'Choques de horário'],
            ],
            'fluxoTitulo' => 'Atalhos da rede',
            'vigencia' => GradeVigencia::atual(),
            'gradeBloqueada' => GradeVigencia::bloqueadaParaEdicao(),
            'podeFazerValer' => Acesso::permite(Processos::FAZER_VALER, 'gravar'),
            'podeNovaVersao' => Acesso::permite(Processos::NOVA_VERSAO, 'gravar'),
        ]);
    }

    public function matriz(Request $request): View
    {
        $diretivas = DiretivasHorario::atuais();
        $ano = (int) ($request->query('ano') ?: ($diretivas['ano'] ?? date('Y')));
        $turmas = MatrizCurricular::turmas($ano, 400);
        $turmaId = (int) $request->query('turma_id', $turmas[0]['id'] ?? 0);
        $turmaAtual = collect($turmas)->firstWhere('id', $turmaId) ?: ($turmas[0] ?? null);

        $componentes = $turmaAtual ? MatrizCurricular::componentesParaTurma((int) $turmaAtual['id']) : [];
        $semanas = max(20, min(50, (int) ($diretivas['semanas_letivas'] ?? 40)));
        $cargaPadrao = max(1, (int) ($diretivas['carga_padrao_aulas'] ?? 1));
        $sugeridas = [];
        $professores = [];
        $totalAulas = 0;
        foreach ($componentes as $c) {
            $n = GeradorHorario::aulasSemanaisPublico($c, $cargaPadrao, $semanas);
            $sugeridas[(int) $c['id']] = $n;
            $totalAulas += $n;
            $professores[(int) $c['id']] = MatrizCurricular::professoresParaComponente((int) $turmaAtual['id'], (int) $c['id']);
        }

        $periodos = array_values((array) ($diretivas['periodos'] ?? DiretivasHorario::periodosPadrao()));
        $matutinos = count(array_filter($periodos, static fn ($p) => ($p['turno'] ?? '') === 'matutino'));
        $capacidadeDia = max(1, $matutinos > 0 ? $matutinos : min(5, count($periodos)));
        $capacidade = $capacidadeDia * 5;

        return view('horario::matriz', [
            'ano' => $ano,
            'turmas' => $turmas,
            'turmaAtual' => $turmaAtual,
            'componentes' => $componentes,
            'sugeridas' => $sugeridas,
            'professores' => $professores,
            'totalAulas' => $totalAulas,
            'capacidade' => $capacidade,
        ]);
    }

    public function salvarMatriz(Request $request): RedirectResponse
    {
        $turmaId = (int) $request->input('turma_id', 0);
        $ano = (int) $request->input('ano', date('Y'));
        if ($turmaId < 1) {
            return back()->with('erro', 'Escolha uma turma para salvar a matriz.');
        }

        $aulas = (array) $request->input('aulas', []);
        $turmaNome = null;
        foreach (MatrizCurricular::turmas($ano, 400) as $t) {
            if ((int) $t['id'] === $turmaId) {
                $turmaNome = (string) $t['nome'];
                break;
            }
        }

        $tempos = (array) $request->input('tempos', []);
        MatrizAjuste::salvar($turmaId, $aulas, $turmaNome, $tempos);

        return redirect('/horario-automatico/matriz?ano='.$ano.'&turma_id='.$turmaId)
            ->with('ok', 'Aulas por semana e tempo de aula salvos. Serão usados na geração e no quadro.');
    }

    public function validar(Request $request): View
    {
        $diretivas = DiretivasHorario::atuais();
        $ano = (int) ($request->query('ano') ?: ($diretivas['ano'] ?? date('Y')));
        $escolaIds = array_values(array_filter(array_map('intval', (array) $request->query('escola_ids', []))));
        $resultado = ValidadorDemandaHorario::analisar($ano, $escolaIds, $diretivas);

        return view('horario::validar', [
            'ano' => $ano,
            'escolas' => MatrizCurricular::escolas($ano),
            'escolaIds' => $escolaIds,
            'resultado' => $resultado,
        ]);
    }

    public function diretivas(): View
    {
        $d = DiretivasHorario::atuais();
        $ano = (int) ($d['ano'] ?: date('Y'));
        $escolas = MatrizCurricular::escolas($ano);
        $componentes = $this->componentesGlobais();

        return view('horario::diretivas', [
            'diretivas' => $d,
            'escolas' => $escolas,
            'componentes' => $componentes,
            'servidores' => MatrizCurricular::servidores(150),
            'turmas' => MatrizCurricular::turmas($ano, 300),
        ]);
    }

    public function salvarDiretivas(Request $request): RedirectResponse
    {
        $escolas = array_map('intval', (array) $request->input('escola_ids', []));
        $geminadas = array_map('intval', (array) $request->input('geminadas', []));

        $preferenciais = [];
        foreach ((array) $request->input('pref_turma', []) as $i => $turmaId) {
            $comp = (int) ($request->input('pref_comp')[$i] ?? 0);
            $prof = (int) ($request->input('pref_prof')[$i] ?? 0);
            if ((int) $turmaId > 0 && $comp > 0 && $prof > 0) {
                $preferenciais[] = [
                    'turma_id' => (int) $turmaId,
                    'componente_id' => $comp,
                    'professor_id' => $prof,
                ];
            }
        }

        $indisponibilidades = [];
        foreach ((array) $request->input('ind_prof', []) as $i => $profId) {
            $dia = (int) ($request->input('ind_dia')[$i] ?? 0);
            $inicio = (string) ($request->input('ind_inicio')[$i] ?? '');
            $fim = (string) ($request->input('ind_fim')[$i] ?? '');
            if ((int) $profId > 0 && $dia > 0 && $inicio !== '' && $fim !== '') {
                $indisponibilidades[] = [
                    'professor_id' => (int) $profId,
                    'dia' => $dia,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'motivo' => (string) ($request->input('ind_motivo')[$i] ?? 'planejamento'),
                ];
            }
        }

        // Períodos: textarea "HH:MM-HH:MM|rótulo|turno" por linha
        $periodos = [];
        $rawPeriodos = trim((string) $request->input('periodos_texto', ''));
        if ($rawPeriodos !== '') {
            foreach (preg_split('/\R/', $rawPeriodos) as $linha) {
                $linha = trim($linha);
                if ($linha === '' || str_starts_with($linha, '#')) {
                    continue;
                }
                // 07:30-08:20|1ª|matutino
                if (preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})(?:\|([^|]+))?(?:\|(\w+))?/', $linha, $m)) {
                    $periodos[] = [
                        'inicio' => $m[1],
                        'fim' => $m[2],
                        'rotulo' => trim($m[3] ?? $m[1]),
                        'turno' => trim($m[4] ?? DiretivasHorario::turnoDe($m[1])),
                    ];
                }
            }
        }

        DiretivasHorario::salvar([
            'ano' => (int) $request->input('ano', date('Y')),
            'escola_ids' => $escolas,
            'geminadas' => $geminadas,
            'separar_dias' => $request->boolean('separar_dias'),
            'max_aulas_mesmo_dia' => (int) $request->input('max_aulas_mesmo_dia', 2),
            'bloquear_multi_escola_turno' => $request->boolean('bloquear_multi_escola_turno'),
            'bloquear_choque_turma' => $request->boolean('bloquear_choque_turma'),
            'bloquear_choque_professor' => $request->boolean('bloquear_choque_professor'),
            'carga_padrao_aulas' => (int) $request->input('carga_padrao_aulas', 1),
            'tempo_aula_minutos' => (int) $request->input('tempo_aula_minutos', 50),
            'semanas_letivas' => (int) $request->input('semanas_letivas', 40),
            'preferenciais' => $preferenciais,
            'indisponibilidades' => $indisponibilidades,
            'periodos' => $periodos !== [] ? $periodos : null,
            'notas' => $request->input('notas'),
        ]);

        return redirect('/horario-automatico/diretivas')->with('ok', 'Regras da rede salvas.');
    }

    public function gerar(): View
    {
        $diretivas = DiretivasHorario::atuais();
        $ano = (int) ($diretivas['ano'] ?: date('Y'));

        return view('horario::gerar', [
            'diretivas' => $diretivas,
            'escolas' => MatrizCurricular::escolas($ano),
            'estado' => GeradorHorario::estado(),
            'passos' => (int) config('horario.geracao_passos', 60),
            'gradeBloqueada' => GradeVigencia::bloqueadaParaEdicao(),
            'podeNovaVersao' => Acesso::permite(Processos::NOVA_VERSAO, 'gravar'),
        ]);
    }

    public function iniciarGeracao(Request $request): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        try {
            $estado = GeradorHorario::iniciar([
                'ano' => (int) $request->input('ano', date('Y')),
                'escola_ids' => array_map('intval', (array) $request->input('escola_ids', [])),
                'substituir' => $request->boolean('substituir', true),
                'limite_passos' => (int) $request->input('limite_passos', config('horario.geracao_passos', 60)),
            ]);
        } catch (Throwable $e) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
            }

            return back()->with('erro', $e->getMessage());
        }

        if ($wantsJson) {
            return response()->json(['ok' => true, 'estado' => $estado]);
        }

        return redirect('/horario-automatico/gerar')->with('ok', 'Geração iniciada.');
    }

    public function continuarGeracao(Request $request): JsonResponse
    {
        try {
            $estado = GeradorHorario::continuar((int) $request->input('limite_passos', config('horario.geracao_passos', 60)));

            return response()->json(['ok' => true, 'estado' => $estado]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    public function pausarGeracao(): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'estado' => GeradorHorario::pausar()]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    public function statusGeracao(): JsonResponse
    {
        return response()->json(['ok' => true, 'estado' => GeradorHorario::estado()]);
    }

    public function grade(Request $request): View
    {
        if ((bool) config('horario.seed_demo_se_vazio', false)) {
            $this->garantirSeed();
        }
        $ano = (int) ($request->query('ano') ?: (DiretivasHorario::atuais()['ano'] ?? date('Y')));
        $visao = $request->query('visao') === 'servidor' ? 'servidor' : 'turma';
        $regencia = $request->query('regencia') === QuadroEditor::REGENTE_AULAS
            ? QuadroEditor::REGENTE_AULAS
            : QuadroEditor::REGENTE_TURMA;
        $faixa = (string) $request->query('faixa', 'auto');
        if (! in_array($faixa, ['auto', 'todos', 'matutino', 'vespertino', 'noturno'], true)) {
            $faixa = 'auto';
        }

        $turmas = MatrizCurricular::turmas($ano);
        $servidores = MatrizCurricular::servidores();
        if ($turmas === []) {
            $turmas = $this->turmasFallback();
        }
        if ($servidores === []) {
            $servidores = $this->professoresFallback();
        }

        $turmaId = (int) $request->query('turma_id', $turmas[0]['id'] ?? 0);
        $servidorId = (int) $request->query('servidor_id', $servidores[0]['id'] ?? 0);
        $turmaParInformado = $request->query->has('turma_par_id');
        $turmaParId = (int) $request->query('turma_par_id', 0);

        $turmaAtual = collect($turmas)->firstWhere('id', $turmaId) ?: ($turmas[0] ?? null);
        $servidorAtual = collect($servidores)->firstWhere('id', $servidorId) ?: ($servidores[0] ?? null);

        $perfil = $turmaAtual ? TurmaPerfilHorario::detalhe((int) $turmaAtual['id'], $ano) : null;
        if ($perfil !== null) {
            $turmaAtual = array_merge($turmaAtual, $perfil);
        }

        $unificado = $request->query('unificado', '1') !== '0';
        $turmaPar = null;
        if ($unificado && $visao === 'turma') {
            if ($turmaParId > 0) {
                $turmaPar = TurmaPerfilHorario::detalhe($turmaParId, $ano);
            } elseif (! $turmaParInformado && $perfil && ! empty($perfil['pares'])) {
                $sug = $perfil['pares'][0] ?? null;
                if ($sug) {
                    $turmaPar = TurmaPerfilHorario::detalhe((int) $sug['id'], $ano);
                    $turmaParId = (int) ($sug['id'] ?? 0);
                }
            }
        } else {
            $turmaParId = 0;
        }

        $matriz = $turmaAtual ? MatrizCurricular::componentesParaTurma((int) $turmaAtual['id']) : [];
        $matrizPar = $turmaPar ? MatrizCurricular::componentesParaTurma((int) $turmaPar['id']) : [];
        if ($matriz === [] && $regencia === QuadroEditor::REGENTE_AULAS) {
            $matriz = $this->componentesGlobais();
        }

        $aulas = QuadroEditor::aulas();
        $choqueIds = (bool) config('horario.detectar_choques', true)
            ? QuadroEditor::idsEmChoque($aulas)
            : [];

        $idsEscopo = array_values(array_filter([
            (int) ($turmaAtual['id'] ?? 0),
            (int) ($turmaPar['id'] ?? 0),
        ]));

        $filtradas = array_values(array_filter($aulas, function (array $a) use ($visao, $turmaAtual, $servidorAtual, $idsEscopo): bool {
            if ($visao === 'turma') {
                if ($idsEscopo === []) {
                    return false;
                }
                $tid = (int) ($a['turma_id'] ?? 0);
                if ($tid > 0) {
                    return in_array($tid, $idsEscopo, true);
                }

                return $a['turma'] === ($turmaAtual['nome'] ?? '');
            }
            if ($servidorAtual === null) {
                return false;
            }

            return (int) $a['professor_id'] === (int) $servidorAtual['id'];
        }));

        $paleta = [];
        if ($regencia === QuadroEditor::REGENTE_TURMA) {
            foreach ($matriz as $c) {
                $paleta[] = [
                    'tipo' => 'componente',
                    'id' => $c['id'],
                    'rotulo' => $c['nome'],
                    'meta' => $c['abreviatura']
                        .($c['carga_horaria'] ? ' · '.$c['carga_horaria'].'h' : '')
                        .' · '.((int) ($c['tempo_aula'] ?? 50)).'min',
                    'cor' => $c['cor'],
                    'componente_id' => $c['id'],
                    'componente' => $c['nome'],
                    'tempo_aula' => (int) ($c['tempo_aula'] ?? 50),
                    'turma_id' => (int) ($turmaAtual['id'] ?? 0),
                    'bloco' => 'regular',
                ];
            }
            foreach ($matrizPar as $c) {
                $paleta[] = [
                    'tipo' => 'componente',
                    'id' => 'par-'.$c['id'],
                    'rotulo' => '[AC] '.$c['nome'],
                    'meta' => $c['abreviatura'].' · '.((int) ($c['tempo_aula'] ?? 50)).'min · contraturno',
                    'cor' => $c['cor'] ?: '#b45309',
                    'componente_id' => $c['id'],
                    'componente' => $c['nome'],
                    'tempo_aula' => (int) ($c['tempo_aula'] ?? 50),
                    'turma_id' => (int) ($turmaPar['id'] ?? 0),
                    'bloco' => 'ac',
                ];
            }
        } else {
            foreach ($turmas as $t) {
                $paleta[] = [
                    'tipo' => 'turma',
                    'id' => $t['id'],
                    'rotulo' => $t['nome'],
                    'meta' => ($t['escola'] ?? '').' · '.$t['componentes'].' disc.',
                    'cor' => '#0e7490',
                    'turma_id' => $t['id'],
                    'turma' => $t['nome'],
                    'escola_id' => $t['escola_id'] ?? null,
                ];
            }
        }

        $eixoPerfil = $perfil
            ? TurmaPerfilHorario::eixoPara($perfil, $turmaPar, $faixa)
            : ['entrada' => null, 'saida' => null, 'turnos' => [], 'faixa' => $faixa, 'blocos' => []];
        $periodos = QuadroEditor::periodosPadrao();
        $eixo = EixoHorario::montar($periodos, $eixoPerfil);

        $diagnostico = VerificadorQuadro::analisar($aulas, array_values(array_filter($eixo, static fn ($l) => ($l['tipo'] ?? '') === 'aula')), [
            'turma_ids' => $idsEscopo,
            'servidor_id' => $visao === 'servidor' ? $servidorId : 0,
            'ano' => $ano,
        ]);

        return view('horario::grade', [
            'ano' => $ano,
            'visao' => $visao,
            'regencia' => $regencia,
            'faixa' => $faixa,
            'dias' => [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex'],
            'periodos' => $periodos,
            'eixo' => $eixo,
            'eixoPerfil' => $eixoPerfil,
            'turmas' => $turmas,
            'servidores' => $servidores,
            'turmaAtual' => $turmaAtual,
            'turmaPar' => $turmaPar,
            'turmaParId' => $turmaParId,
            'unificado' => $unificado,
            'servidorAtual' => $servidorAtual,
            'matriz' => $matriz,
            'matrizPar' => $matrizPar,
            'paleta' => $paleta,
            'aulas' => $filtradas,
            'aulasJson' => $filtradas,
            'choqueIds' => $choqueIds,
            'diagnostico' => $diagnostico,
            'tempoPadrao' => EixoHorario::tempoAulaPadrao(),
            'csrf' => csrf_token(),
            'vigencia' => GradeVigencia::atual(),
            'gradeBloqueada' => GradeVigencia::bloqueadaParaEdicao(),
            'podeFazerValer' => Acesso::permite(Processos::FAZER_VALER, 'gravar'),
            'podeNovaVersao' => Acesso::permite(Processos::NOVA_VERSAO, 'gravar'),
        ]);
    }

    public function alocar(Request $request): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $id = QuadroEditor::alocar([
                'professor_id' => (int) $request->input('professor_id', 0),
                'professor' => (string) $request->input('professor', ''),
                'cargo' => (string) $request->input('cargo', 'Professor'),
                'turma_id' => $request->filled('turma_id') ? (int) $request->input('turma_id') : null,
                'turma' => (string) $request->input('turma', ''),
                'escola_id' => $request->filled('escola_id') ? (int) $request->input('escola_id') : null,
                'componente_id' => $request->filled('componente_id') ? (int) $request->input('componente_id') : null,
                'componente' => (string) $request->input('componente', ''),
                'dia' => (int) $request->input('dia', 1),
                'inicio' => (string) $request->input('inicio', ''),
                'fim' => (string) $request->input('fim', ''),
                'tempo_aula' => (int) $request->input('tempo_aula', 0),
                'regencia' => (string) $request->input('regencia', QuadroEditor::REGENTE_TURMA),
                'cor' => $request->input('cor'),
            ]);
        } catch (Throwable $e) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
            }

            return back()->with('erro', $e->getMessage());
        }

        if ($wantsJson) {
            $aula = QuadroEditor::aulas();
            $nova = collect($aula)->firstWhere('id', $id);

            return response()->json(['ok' => true, 'id' => $id, 'aula' => $nova]);
        }

        return redirect($this->voltarGrade($request))->with('ok', "Aula #{$id} alocada.");
    }

    public function mover(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        try {
            QuadroEditor::mover(
                $id,
                (int) $request->input('dia', 1),
                (string) $request->input('inicio', ''),
                (string) $request->input('fim', ''),
            );
        } catch (Throwable $e) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
            }

            return back()->with('erro', $e->getMessage());
        }

        if ($wantsJson) {
            return response()->json(['ok' => true]);
        }

        return redirect($this->voltarGrade($request))->with('ok', "Aula #{$id} movida.");
    }

    public function apagar(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson() || $request->ajax();
        try {
            QuadroEditor::apagar($id);
        } catch (Throwable $e) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
            }

            return back()->with('erro', $e->getMessage());
        }

        if ($wantsJson) {
            return response()->json(['ok' => true]);
        }

        return redirect($this->voltarGrade($request))->with('ok', "Aula #{$id} removida.");
    }

    public function salvarAula(Request $request): RedirectResponse
    {
        try {
            QuadroEditor::alocar([
                'professor_id' => (int) $request->input('professor_id', 0),
                'professor' => (string) $request->input('professor', ''),
                'cargo' => (string) $request->input('cargo', 'Professor PEB I'),
                'turma' => (string) $request->input('turma', ''),
                'componente' => (string) $request->input('componente', ''),
                'dia' => (int) $request->input('dia', 1),
                'inicio' => (string) $request->input('inicio', ''),
                'fim' => (string) $request->input('fim', ''),
            ]);
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect('/horario-automatico/grade')->with('ok', 'Aula adicionada à grade.');
    }

    public function choques(): View
    {
        $grade = QuadroEditor::aulas();
        $detalhes = (bool) config('horario.detectar_choques', true) ? DetectorChoque::detalhar($grade) : [];
        $linhas = [];

        if (! (bool) config('horario.detectar_choques', true)) {
            $linhas[] = [
                'titulo' => 'Detector desligado',
                'meta' => 'config horario.detectar_choques=false',
                'badge' => 'Off',
                'badge_tom' => 'warn',
                'corpo' => 'Habilite HORARIO_DETECTAR_CHOQUES=true para ver os conflitos.',
            ];

            return view('horario::choques', compact('linhas'));
        }

        foreach ($detalhes as $n => $c) {
            $a = $grade[$c['i']];
            $b = $grade[$c['j']];
            $linhas[] = [
                'titulo' => strtoupper($c['tipo']).' — conflito #'.($n + 1),
                'meta' => 'Dia '.$a['dia'].' · '.$a['inicio'].'–'.$a['fim'].' × '.$b['inicio'].'–'.$b['fim'],
                'badge' => $c['tipo'],
                'badge_tom' => 'fail',
                'corpo' => $c['motivo'].' · '.$a['professor'].' · '.$a['turma'].'/'.$a['componente'].' ↔ '.$b['turma'].'/'.$b['componente'],
            ];
        }

        return view('horario::choques', compact('linhas'));
    }

    public function cargos(): View
    {
        $grade = QuadroEditor::aulas();
        $porCargo = [];
        foreach ($grade as $aula) {
            $cargo = $aula['cargo'];
            $porCargo[$cargo]['minutos'] = ($porCargo[$cargo]['minutos'] ?? 0)
                + GradeSemanal::cargaMinutos([['inicio' => $aula['inicio'], 'fim' => $aula['fim'], 'dia' => $aula['dia'], 'componente' => $aula['componente']]]);
            $porCargo[$cargo]['profs'][$aula['professor']] = true;
            $porCargo[$cargo]['componentes'][$aula['componente']] = true;
        }

        $linhas = [];
        foreach ($porCargo as $cargo => $dados) {
            $linhas[] = [
                'titulo' => $cargo,
                'meta' => count($dados['profs']).' servidor(es) · '.implode(', ', array_keys($dados['componentes'])),
                'badge' => $dados['minutos'].' min',
                'badge_tom' => 'ok',
                'corpo' => 'Carga agregada na grade atual.',
            ];
        }

        return view('horario::cargos', compact('linhas'));
    }

    public function relatorio(Request $request): View
    {
        $agrupar = $request->query('por') === 'professor' ? 'professor' : 'turma';
        $grade = QuadroEditor::aulas();
        $grupos = [];
        foreach ($grade as $a) {
            $chave = $agrupar === 'professor'
                ? ((string) $a['professor_id'].'|'.$a['professor'])
                : ((string) ($a['turma_id'] ?? $a['turma']).'|'.$a['turma']);
            $grupos[$chave]['titulo'] = $agrupar === 'professor' ? $a['professor'] : $a['turma'];
            $grupos[$chave]['aulas'][] = $a;
        }
        ksort($grupos);

        return view('horario::relatorio', [
            'agrupar' => $agrupar,
            'grupos' => $grupos,
            'dias' => [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex'],
        ]);
    }

    public function exportarRelatorio(Request $request): Response|RedirectResponse
    {
        $agrupar = $request->input('por', $request->query('por')) === 'professor' ? 'professor' : 'turma';
        $formato = strtolower(trim((string) $request->input('formato', 'pdf')));
        $grade = QuadroEditor::aulas();
        $dias = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex'];

        usort($grade, static function ($a, $b) use ($agrupar) {
            $ga = $agrupar === 'professor' ? (string) ($a['professor'] ?? '') : (string) ($a['turma'] ?? '');
            $gb = $agrupar === 'professor' ? (string) ($b['professor'] ?? '') : (string) ($b['turma'] ?? '');
            if ($ga !== $gb) {
                return $ga <=> $gb;
            }
            $da = (int) ($a['dia'] ?? 0);
            $db = (int) ($b['dia'] ?? 0);
            if ($da !== $db) {
                return $da <=> $db;
            }

            return ((string) ($a['inicio'] ?? '')) <=> ((string) ($b['inicio'] ?? ''));
        });

        $linhas = [];
        foreach ($grade as $a) {
            $grupo = $agrupar === 'professor' ? (string) ($a['professor'] ?? '') : (string) ($a['turma'] ?? '');
            $linhas[] = [
                $grupo,
                $dias[$a['dia'] ?? 0] ?? ($a['dia'] ?? ''),
                ((string) ($a['inicio'] ?? '')).'–'.((string) ($a['fim'] ?? '')),
                (string) ($a['componente'] ?? ''),
                $agrupar === 'turma' ? (string) ($a['professor'] ?? '') : (string) ($a['turma'] ?? ''),
            ];
        }

        return \iEducar\Packages\BuritiExportacaoUnificada\Support\ExportacaoModulo::download(
            'Grade de horários',
            ['Grupo', 'Dia', 'Horário', 'Componente', $agrupar === 'turma' ? 'Professor' : 'Turma'],
            $linhas,
            'Horário automático',
            $formato,
            $agrupar === 'professor' ? 'Agrupado por professor' : 'Agrupado por turma',
            null,
            'horario-relatorio',
        );
    }

    private function voltarGrade(Request $request): string
    {
        $q = http_build_query(array_filter([
            'visao' => $request->input('visao'),
            'regencia' => $request->input('regencia'),
            'turma_id' => $request->input('turma_id'),
            'servidor_id' => $request->input('servidor_id'),
            'ano' => $request->input('ano'),
        ], static fn ($v) => $v !== null && $v !== ''));

        return '/horario-automatico/grade'.($q !== '' ? '?'.$q : '');
    }

    private function garantirSeed(): void
    {
        if (! PocRegistro::disponivel()) {
            return;
        }
        if (! (bool) config('horario.usar_servidores_nucleo', true)) {
            return;
        }
        if (PocRegistro::contar('aula') > 0) {
            return;
        }
        if (! Schema::hasTable('pmieducar.servidor') || ! Schema::hasTable('pmieducar.turma') || ! Schema::hasTable('cadastro.pessoa')) {
            return;
        }

        try {
            $servs = DB::table('pmieducar.servidor')
                ->join('cadastro.pessoa', 'cadastro.pessoa.idpes', '=', 'pmieducar.servidor.cod_servidor')
                ->where('pmieducar.servidor.ativo', 1)
                ->limit(3)
                ->get(['pmieducar.servidor.cod_servidor as id', 'cadastro.pessoa.nome as nome']);

            $turmas = DB::table('pmieducar.turma')
                ->where('pmieducar.turma.ativo', 1)
                ->where('ano', (int) date('Y'))
                ->orderBy('cod_turma')
                ->limit(3)
                ->get(['cod_turma as id', 'nm_turma as nome', 'ref_ref_cod_escola as escola_id']);
        } catch (Throwable) {
            return;
        }

        if ($servs->isEmpty() || $turmas->isEmpty()) {
            return;
        }

        $i = 0;
        foreach ($servs as $s) {
            $turma = $turmas[$i % $turmas->count()];
            $comps = MatrizCurricular::componentesParaTurma((int) $turma->id);
            $comp = $comps[$i % max(1, count($comps))] ?? ['id' => null, 'nome' => 'Português', 'cor' => null];
            $periodo = QuadroEditor::periodosPadrao()[$i % 5];
            try {
                QuadroEditor::alocar([
                    'professor_id' => (int) $s->id,
                    'professor' => (string) $s->nome,
                    'cargo' => 'Professor',
                    'turma_id' => (int) $turma->id,
                    'turma' => (string) $turma->nome,
                    'escola_id' => isset($turma->escola_id) ? (int) $turma->escola_id : null,
                    'componente_id' => $comp['id'] ?? null,
                    'componente' => (string) ($comp['nome'] ?? 'Componente'),
                    'dia' => ($i % 5) + 1,
                    'inicio' => $periodo['inicio'],
                    'fim' => $periodo['fim'],
                    'regencia' => QuadroEditor::REGENTE_AULAS,
                    'cor' => $comp['cor'] ?? null,
                    'validar_choque' => false,
                ]);
            } catch (Throwable) {
                //
            }
            $i++;
        }
    }

    /** @return list<array{id:int,nome:string,escola:?string,escola_id:?int,ano:int,regente_id:?int,regente:?string,componentes:int}> */
    private function turmasFallback(): array
    {
        return [
            ['id' => 1, 'nome' => '5º A', 'escola' => null, 'escola_id' => null, 'ano' => (int) date('Y'), 'regente_id' => null, 'regente' => null, 'componentes' => 0],
            ['id' => 2, 'nome' => '6º A', 'escola' => null, 'escola_id' => null, 'ano' => (int) date('Y'), 'regente_id' => null, 'regente' => null, 'componentes' => 0],
        ];
    }

    /** @return list<array{id:int,nome:string,disciplinas:list}> */
    private function professoresFallback(): array
    {
        return [
            ['id' => 1, 'nome' => 'Ana Souza', 'disciplinas' => []],
            ['id' => 2, 'nome' => 'Bruno Lima', 'disciplinas' => []],
        ];
    }

    /**
     * @return list<array{id:int,nome:string,abreviatura:string,carga_horaria:?float,cor:string}>
     */
    private function componentesGlobais(): array
    {
        if (! Schema::hasTable('modules.componente_curricular')) {
            return [];
        }
        try {
            return DB::table('modules.componente_curricular')
                ->orderByRaw('COALESCE(ordenamento, 99999)')
                ->orderBy('nome')
                ->limit(80)
                ->get(['id', 'nome', 'abreviatura', 'color'])
                ->map(static fn ($r): array => [
                    'id' => (int) $r->id,
                    'nome' => (string) $r->nome,
                    'abreviatura' => (string) ($r->abreviatura ?: mb_substr((string) $r->nome, 0, 8)),
                    'carga_horaria' => null,
                    'cor' => (string) ($r->color ?: '#47728f'),
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function fazerValer(Request $request): RedirectResponse
    {
        if (! Acesso::permite(Processos::FAZER_VALER, 'gravar')) {
            return back()->with('erro', 'Sem permissão para fazer valer o quadro.');
        }
        try {
            $r = GradeVigencia::fazerValer((int) ($request->user()?->getKey() ?: 0));
            return redirect('/horario-automatico')->with(
                'ok',
                "Quadro vigente · {$r['aulas']} aulas · núcleo: {$r['horarios']} horários, {$r['alocacoes']} alocações, {$r['professor_turma']} vínculos diário."
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }
    }

    public function novaVersao(Request $request): RedirectResponse
    {
        if (! Acesso::permite(Processos::NOVA_VERSAO, 'gravar')) {
            return back()->with('erro', 'Sem permissão para abrir nova versão.');
        }
        try {
            $v = GradeVigencia::abrirNovaVersao();
            $n = count($v['payload']['aula_ids'] ?? []);
            return redirect('/horario-automatico/grade')->with(
                'ok',
                "Nova versão aberta com {$n} aula(s) clonadas. A grade anterior permanece no histórico."
            );
        } catch (Throwable $e) {
            return back()->with('erro', $e->getMessage());
        }
    }
}
