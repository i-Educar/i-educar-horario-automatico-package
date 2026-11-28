@extends('horario::layout')
@section('titulo', 'Ajustar quadro semanal')
@section('migalha', 'Quadro')
@section('icone-tela', 'fa-table')
@section('titulo-tela', 'Ajustar o quadro semanal')
@section('lead')
    Monte a semana com entrada, tempo de cada aula e saída. Turmas integrais e EF + atividade complementar aparecem juntas quando houver vínculo.
@endsection
@section('acoes-topo')
    <a class="botao icone" href="{{ url('/horario-automatico/gerar') }}" title="Gerar" aria-label="Gerar"><i aria-hidden="true" class="fa fa-magic"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico/matriz') }}" title="Matriz" aria-label="Matriz"><i aria-hidden="true" class="fa fa-th-list"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico/choques') }}" title="Conflitos" aria-label="Conflitos"><i aria-hidden="true" class="fa fa-warning"></i></a>
    @if(!empty($gradeBloqueada) && !empty($podeNovaVersao))
        <form method="post" action="{{ url('/horario-automatico/nova-versao') }}" style="display:inline">@csrf
            <button class="botao claro icone" type="submit" title="Nova versão" aria-label="Nova versão"><i aria-hidden="true" class="fa fa-code-fork"></i></button>
        </form>
    @endif
    @if(empty($gradeBloqueada) && !empty($podeFazerValer))
        <form method="post" action="{{ url('/horario-automatico/fazer-valer') }}" style="display:inline" onsubmit="return confirm('Fazer valer congela a grade e propaga alocações ao diário. Continuar?')">@csrf
            <button class="botao icone" type="submit" title="Fazer valer" aria-label="Fazer valer"><i aria-hidden="true" class="fa fa-check-circle"></i></button>
        </form>
    @endif
@endsection

@section('conteudo')
@php
    $turmaAtual = $turmaAtual ?? null;
    $turmaPar = $turmaPar ?? null;
    $servidorAtual = $servidorAtual ?? null;
    $matriz = $matriz ?? [];
    $paleta = $paleta ?? [];
    $eixo = $eixo ?? [];
    $dias = $dias ?? [];
    $aulas = $aulas ?? [];
    $choqueIds = $choqueIds ?? [];
    $visao = $visao ?? 'turma';
    $regencia = $regencia ?? 'regente_turma';
    $faixa = $faixa ?? 'auto';
    $diagnostico = $diagnostico ?? ['resumo' => ['ok'=>0,'aviso'=>0,'erro'=>0], 'itens' => []];
    $q = fn (array $extra = []) => url('/horario-automatico/grade?'.http_build_query(array_filter(array_merge([
        'ano' => $ano,
        'visao' => $visao,
        'regencia' => $regencia,
        'faixa' => $faixa,
        'turma_id' => $turmaAtual['id'] ?? null,
        'servidor_id' => $servidorAtual['id'] ?? null,
        'turma_par_id' => $turmaParId ?? null,
        'unificado' => !empty($unificado) ? '1' : '0',
    ], $extra), fn ($v) => $v !== null && $v !== '')));
@endphp

<div class="bloco" style="margin:0 0 14px;border-top-color: {{ !empty($gradeBloqueada) ? 'var(--ok)' : 'var(--ambar)' }}">
    <h2 style="margin:0 0 6px"><i class="fa {{ !empty($gradeBloqueada) ? 'fa-lock' : 'fa-pencil' }}"></i>
        @if(!empty($gradeBloqueada)) Grade vigente · somente leitura @else Grade editável (rascunho) @endif
    </h2>
    <p style="margin:0;color:var(--suave)">
        @if(!empty($gradeBloqueada))
            Alterações bloqueadas. Use <strong>Nova versão</strong> para clonar sem apagar o histórico.
        @else
            Ajuste livremente. <strong>Fazer valer</strong> publica no quadro oficial e no diário.
        @endif
    </p>
</div>

<style>
    .filtros-grade { display:grid; gap:12px; margin:0 0 14px; }
    .filtros-grade .linha { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
    .filtros-grade .grupo { display:grid; gap:4px; }
    .filtros-grade .grupo > span, .filtros-grade label.cap { font-size:.72rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:var(--suave); }
    .filtros-grade .seg { display:inline-flex; border:1px solid var(--linha); border-radius:10px; overflow:hidden; }
    .filtros-grade .seg a { padding:8px 12px; text-decoration:none; color:var(--tinta); font-weight:700; font-size:.84rem; background:#fff; }
    .filtros-grade .seg a.ativo { background:var(--cor); color:#fff; }
    .filtros-grade select { min-height:36px; padding:6px 8px; border:1px solid var(--linha); border-radius:8px; font:inherit; background:#fff; min-width:140px; }
    .filtros-grade .ajuda { margin:0; color:var(--suave); font-size:.8rem; }
    .chip-mod { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; font-size:.75rem; font-weight:700; background:var(--clara); color:var(--cor); }
    .chip-mod.ac { background:#fff7ed; color:#c2410c; }
    .chip-mod.integral { background:#ecfeff; color:#0e7490; }
    .diag { display:grid; gap:8px; margin:0 0 14px; }
    .diag-resumo { display:flex; flex-wrap:wrap; gap:8px; }
    .diag-resumo span { padding:4px 10px; border-radius:999px; font-size:.78rem; font-weight:700; background:var(--clara); }
    .diag-resumo .erro { background:var(--erro-fundo); color:var(--erro); }
    .diag-resumo .aviso { background:#fff7ed; color:#c2410c; }
    .diag-lista { display:grid; gap:6px; max-height:160px; overflow:auto; }
    .diag-item { padding:8px 10px; border:1px solid var(--linha); border-radius:10px; background:#fff; font-size:.82rem; }
    .diag-item strong { display:block; }
    .diag-item small { color:var(--suave); }
    .quadro-board { display:grid; grid-template-columns: 220px 1fr; gap:12px; align-items:start; }
    .paleta { background:#fff; border:1px solid var(--linha); border-radius:14px; padding:12px; border-top:3px solid var(--cor); max-height:75vh; overflow:auto; }
    .paleta h2 { margin:0 0 8px; font-size:.95rem; display:flex; align-items:center; gap:8px; }
    .paleta .dica { margin:0 0 10px; color:var(--suave); font-size:.78rem; }
    .chip { display:block; width:100%; text-align:left; margin:0 0 6px; padding:8px 10px; border-radius:10px; border:1px dashed color-mix(in srgb, var(--chip, var(--cor)) 45%, var(--linha));
        background: color-mix(in srgb, var(--chip, var(--cor)) 12%, #fff); cursor:grab; font-weight:700; font-size:.82rem; color:var(--tinta); }
    .chip small { display:block; font-weight:500; color:var(--suave); margin-top:2px; }
    .grade-dnd { display:grid; grid-template-columns: 92px repeat(5, 1fr); gap:4px; background:#fff; border:1px solid var(--linha); border-radius:14px; padding:10px; border-top:3px solid var(--destaque); }
    .grade-dnd .h, .grade-dnd .c, .grade-dnd .p, .grade-dnd .marcador, .grade-dnd .sep { padding:6px; border-radius:8px; border:1px solid var(--linha); font-size:.75rem; }
    .grade-dnd .h { background:var(--clara); font-weight:700; color:var(--cor); text-align:center; min-height:auto; }
    .grade-dnd .p { background:#f7f9fa; font-weight:700; color:var(--suave); text-align:center; display:flex; flex-direction:column; justify-content:center; min-height:68px; gap:2px; }
    .grade-dnd .p .min { font-weight:600; font-size:.7rem; color:var(--cor); }
    .grade-dnd .p.gap { box-shadow: inset 0 6px 0 -4px color-mix(in srgb, var(--ambar) 55%, transparent); }
    .grade-dnd .marcador { background:#f0fdf4; color:#166534; font-weight:800; text-align:center; grid-column: 1 / -1; display:flex; justify-content:space-between; align-items:center; padding:8px 12px; }
    .grade-dnd .marcador.saida { background:#fff1f2; color:#9f1239; }
    .grade-dnd .sep { background:#f8fafc; color:var(--suave); font-weight:800; text-align:center; grid-column: 1 / -1; letter-spacing:.04em; text-transform:uppercase; font-size:.7rem; }
    .grade-dnd .cel { background:#fbfcfd; position:relative; transition: background .15s, border-color .15s; min-height:68px; }
    .grade-dnd .cel.over { background: color-mix(in srgb, var(--cor) 18%, #fff); border-color: var(--cor); border-style:dashed; }
    .aula-card { border-radius:8px; padding:6px 7px; background: color-mix(in srgb, var(--aula, var(--cor)) 18%, #fff); border:1px solid color-mix(in srgb, var(--aula, var(--cor)) 40%, var(--linha));
        font-weight:700; line-height:1.25; cursor:grab; position:relative; margin-bottom:4px; }
    .aula-card.choque { background:var(--erro-fundo); border-color: color-mix(in srgb, var(--erro) 45%, #fff); color:var(--erro); }
    .aula-card.ac { border-style:dashed; }
    .aula-card small { display:block; font-weight:500; opacity:.85; margin-top:2px; }
    .aula-card .x { position:absolute; top:2px; right:4px; border:0; background:transparent; color:inherit; cursor:pointer; font-size:12px; opacity:.6; }
    .aula-card .x:hover { opacity:1; }
    @media (max-width: 960px) { .quadro-board { grid-template-columns: 1fr; } }
</style>

<div class="bloco filtros-grade">
    <h2 style="margin:0"><i class="fa fa-sliders"></i> Como ver o quadro</h2>
    <p class="ajuda">Escolha primeiro <strong>o que está olhando</strong> (turma ou professor). Depois refine o modo de alocação e a faixa do dia.</p>

    <form method="get" action="{{ url('/horario-automatico/grade') }}" id="filtros-grade">
        <input type="hidden" name="ano" value="{{ $ano }}">
        <input type="hidden" name="visao" value="{{ $visao }}">
        <input type="hidden" name="regencia" value="{{ $regencia }}">

        <div class="linha">
            <div class="grupo">
                <span>1. Olhar</span>
                <div class="seg">
                    <a class="{{ $visao === 'turma' ? 'ativo' : '' }}" href="{{ $q(['visao'=>'turma']) }}">Turma</a>
                    <a class="{{ $visao === 'servidor' ? 'ativo' : '' }}" href="{{ $q(['visao'=>'servidor']) }}">Professor</a>
                </div>
            </div>
            <div class="grupo">
                <span>2. Como alocar</span>
                <div class="seg">
                    <a class="{{ $regencia === 'regente_turma' ? 'ativo' : '' }}" href="{{ $q(['regencia'=>'regente_turma']) }}" title="Várias disciplinas na mesma turma">Disciplinas → turma</a>
                    <a class="{{ $regencia === 'regente_aulas' ? 'ativo' : '' }}" href="{{ $q(['regencia'=>'regente_aulas']) }}" title="Mesma disciplina em várias turmas">Turmas → disciplina</a>
                </div>
            </div>
            <div class="grupo">
                <span>3. Faixa do dia</span>
                <div class="seg">
                    <a class="{{ $faixa === 'auto' ? 'ativo' : '' }}" href="{{ $q(['faixa'=>'auto']) }}">Auto</a>
                    <a class="{{ $faixa === 'matutino' ? 'ativo' : '' }}" href="{{ $q(['faixa'=>'matutino']) }}">Manhã</a>
                    <a class="{{ $faixa === 'vespertino' ? 'ativo' : '' }}" href="{{ $q(['faixa'=>'vespertino']) }}">Tarde</a>
                    <a class="{{ $faixa === 'todos' ? 'ativo' : '' }}" href="{{ $q(['faixa'=>'todos']) }}">Dia todo</a>
                </div>
            </div>
        </div>

        <div class="linha" style="margin-top:8px">
            @if($visao === 'turma')
                @include('buriti-ui::select-busca', [
                    'campo' => 'turma_id',
                    'rotulo' => 'Turma principal',
                    'icone' => 'fa-graduation-cap',
                    'opcoes' => collect($turmas)->map(fn ($t) => [
                        'id' => $t['id'],
                        'nome' => $t['nome'],
                        'meta' => trim(($t['escola'] ?? '').' · '.($t['turno'] ?? '').' · '.$t['componentes'].' disc.'),
                    ])->all(),
                    'selecionado' => $turmaAtual['id'] ?? null,
                    'placeholder' => 'Buscar turma…',
                    'onchange' => 'this.form.submit()',
                    'tamanho' => 5,
                    'colWide' => false,
                ])
                <div class="grupo">
                    <label class="cap" for="turma_par_id">Turma vinculada (AC / contraturno)</label>
                    <select name="turma_par_id" id="turma_par_id" onchange="this.form.submit()">
                        <option value="0">Sem vínculo</option>
                        @foreach(($turmaAtual['pares'] ?? []) as $par)
                            <option value="{{ $par['id'] }}" @selected((int)($turmaParId ?? 0) === (int)$par['id'])>
                                {{ $par['nome'] }} · {{ strtoupper($par['modalidade'] ?? '') }} {{ $par['turno'] ?? '' }}
                            </option>
                        @endforeach
                        @foreach($turmas as $t)
                            @if((int)$t['id'] !== (int)($turmaAtual['id'] ?? 0) && !collect($turmaAtual['pares'] ?? [])->contains(fn($p)=>(int)$p['id']===(int)$t['id']))
                                <option value="{{ $t['id'] }}" @selected((int)($turmaParId ?? 0) === (int)$t['id'])>{{ $t['nome'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="grupo">
                    <span>Visão unificada</span>
                    <div class="seg">
                        <a class="{{ !empty($unificado) ? 'ativo' : '' }}" href="{{ $q(['unificado'=>'1']) }}">Sim</a>
                        <a class="{{ empty($unificado) ? 'ativo' : '' }}" href="{{ $q(['unificado'=>'0','turma_par_id'=>0]) }}">Só principal</a>
                    </div>
                </div>
            @else
                @include('buriti-ui::select-busca', [
                    'campo' => 'servidor_id',
                    'rotulo' => 'Professor',
                    'icone' => 'fa-user',
                    'opcoes' => collect($servidores)->map(fn ($s) => ['id' => $s['id'], 'nome' => $s['nome']])->all(),
                    'selecionado' => $servidorAtual['id'] ?? null,
                    'placeholder' => 'Buscar professor…',
                    'onchange' => 'this.form.submit()',
                    'tamanho' => 6,
                    'colWide' => false,
                ])
                <input type="hidden" name="turma_id" value="{{ $turmaAtual['id'] ?? '' }}">
            @endif
            <input type="hidden" name="faixa" value="{{ $faixa }}">
        </div>
    </form>

    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        @if(!empty($turmaAtual['modalidade']))
            <span class="chip-mod {{ $turmaAtual['modalidade'] }}">{{ strtoupper($turmaAtual['modalidade']) }} · {{ $turmaAtual['turno'] ?? '—' }}</span>
        @endif
        @if($turmaPar)
            <span class="chip-mod ac">+ {{ strtoupper($turmaPar['modalidade'] ?? 'AC') }} · {{ $turmaPar['nome'] }}</span>
        @endif
        @if(!empty($eixoPerfil['entrada']) || !empty($eixoPerfil['saida']))
            <span class="chip-mod">Entrada {{ $eixoPerfil['entrada'] ?? '—' }} → Saída {{ $eixoPerfil['saida'] ?? '—' }}</span>
        @endif
    </div>

    @if($regencia === 'regente_aulas')
        <div class="linha">
            <label class="grupo">Disciplina fixa (modo turmas → disciplina)
                <select id="disciplina-fixixa">
                    <option value="">— escolher —</option>
                    @foreach($matriz as $c)
                        <option value="{{ $c['id'] }}" data-nome="{{ $c['nome'] }}" data-cor="{{ $c['cor'] }}" data-tempo="{{ $c['tempo_aula'] ?? $tempoPadrao }}">{{ $c['nome'] }} · {{ $c['tempo_aula'] ?? $tempoPadrao }}min</option>
                    @endforeach
                </select>
            </label>
        </div>
    @endif
</div>

@if(!empty($diagnostico['itens']))
    <div class="bloco diag">
        <h2 style="margin:0"><i class="fa fa-stethoscope"></i> Verificação do quadro</h2>
        <div class="diag-resumo">
            <span class="erro">{{ $diagnostico['resumo']['erro'] ?? 0 }} erro(s)</span>
            <span class="aviso">{{ $diagnostico['resumo']['aviso'] ?? 0 }} aviso(s)</span>
            <span>{{ $diagnostico['resumo']['ok'] ?? 0 }} disponibilidade(s)</span>
        </div>
        <div class="diag-lista">
            @foreach($diagnostico['itens'] as $item)
                <div class="diag-item">
                    <strong>{{ $item['titulo'] }}</strong>
                    <small>{{ $item['detalhe'] }}</small>
                    @if(!empty($item['acao']))
                        <div><a href="{{ url($item['acao']) }}">Abrir</a></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="quadro-board" @if(!empty($gradeBloqueada)) style="pointer-events:none;opacity:.72" title="Grade congelada" @endif>
    <aside class="paleta">
        <h2><i class="fa fa-th"></i> {{ $regencia === 'regente_turma' ? 'Disciplinas' : 'Turmas' }}</h2>
        <p class="dica">Solte na célula. O fim da aula usa o <strong>tempo</strong> da disciplina ({{ $tempoPadrao ?? 50 }}min padrão).</p>
        @forelse($paleta as $item)
            <div class="chip" draggable="true"
                 style="--chip: {{ $item['cor'] ?? 'var(--cor)' }}"
                 data-paleta='@json($item)'>
                {{ $item['rotulo'] }}
                <small>{{ $item['meta'] ?? '' }}</small>
            </div>
        @empty
            <p class="dica">Nada na paleta — selecione uma turma com matriz curricular.</p>
        @endforelse
    </aside>

    <div class="grade-dnd" id="grade-dnd"
         data-visao="{{ $visao }}"
         data-regencia="{{ $regencia }}"
         data-turma-id="{{ $turmaAtual['id'] ?? '' }}"
         data-turma="{{ $turmaAtual['nome'] ?? '' }}"
         data-servidor-id="{{ $servidorAtual['id'] ?? '' }}"
         data-servidor="{{ $servidorAtual['nome'] ?? '' }}"
         data-tempo-padrao="{{ $tempoPadrao ?? 50 }}"
         data-alocar="{{ url('/horario-automatico/grade/alocar') }}"
         data-csrf="{{ $csrf }}">
        <div class="h">Horário</div>
        @foreach($dias as $dia => $rotulo)
            <div class="h">{{ $rotulo }}</div>
        @endforeach

        @foreach($eixo as $linha)
            @if(($linha['tipo'] ?? '') === 'entrada')
                <div class="marcador">
                    <span><i class="fa fa-sign-in"></i> {{ $linha['rotulo'] }}</span>
                    <strong>{{ $linha['inicio'] }}</strong>
                </div>
            @elseif(($linha['tipo'] ?? '') === 'saida')
                <div class="marcador saida">
                    <span><i class="fa fa-sign-out"></i> {{ $linha['rotulo'] }}</span>
                    <strong>{{ $linha['fim'] }}</strong>
                </div>
            @elseif(($linha['tipo'] ?? '') === 'separador')
                <div class="sep">{{ $linha['rotulo'] }}</div>
            @else
                <div class="p {{ !empty($linha['gap_antes']) ? 'gap' : '' }}">
                    <span>{{ $linha['rotulo'] }}</span>
                    <span class="min">{{ $linha['minutos'] ?? '—' }} min</span>
                    <small style="font-weight:500">{{ $linha['inicio'] }}–{{ $linha['fim'] }}</small>
                </div>
                @foreach($dias as $dia => $rotulo)
                    @php
                        $naCelula = array_values(array_filter($aulas, fn ($a) => (int)$a['dia'] === (int)$dia && $a['inicio'] === $linha['inicio']));
                    @endphp
                    <div class="c cel"
                         data-dia="{{ $dia }}"
                         data-inicio="{{ $linha['inicio'] }}"
                         data-fim="{{ $linha['fim'] }}">
                        @foreach($naCelula as $aula)
                            @php
                                $ehPar = $turmaPar && (int)($aula['turma_id'] ?? 0) === (int)$turmaPar['id'];
                            @endphp
                            <div class="aula-card {{ isset($choqueIds[$aula['id']]) ? 'choque' : '' }} {{ $ehPar ? 'ac' : '' }}"
                                 draggable="true"
                                 style="--aula: {{ $aula['cor'] ?? 'var(--cor)' }}"
                                 data-aula-id="{{ $aula['id'] }}"
                                 title="{{ $aula['componente'] }} · {{ $aula['professor'] }} · {{ $aula['inicio'] }}–{{ $aula['fim'] }}">
                                <button type="button" class="x" data-apagar="{{ $aula['id'] }}" title="Remover">&times;</button>
                                {{ $ehPar ? '[AC] ' : '' }}{{ $aula['componente'] }}
                                <small>
                                    @if($visao === 'turma')
                                        {{ $aula['professor'] }}
                                    @else
                                        {{ $aula['turma'] }}
                                    @endif
                                    · {{ $aula['inicio'] }}–{{ $aula['fim'] }}
                                </small>
                            </div>
                        @endforeach
                    </div>
                @endforeach
        @endif
        @endforeach
    </div>
</div>

<script>
(function () {
    var board = document.getElementById('grade-dnd');
    if (!board) return;
    var csrf = board.dataset.csrf;
    var urlAlocar = board.dataset.alocar;
    var tempoPadrao = parseInt(board.dataset.tempoPadrao || '50', 10);
    var dragPayload = null;

    function toast(msg, ok) {
        var el = document.createElement('p');
        el.className = 'flash ' + (ok ? 'ok' : 'erro');
        el.textContent = msg;
        var main = document.querySelector('main');
        if (main) main.insertBefore(el, main.firstChild);
        setTimeout(function () { el.remove(); }, 4000);
    }

    function somarMinutos(inicio, min) {
        var p = (inicio || '').split(':');
        if (p.length < 2) return inicio;
        var total = (parseInt(p[0],10)*60 + parseInt(p[1],10) + (min||50)) % (24*60);
        var h = Math.floor(total/60), m = total%60;
        return (h<10?'0':'')+h+':'+(m<10?'0':'')+m;
    }

    document.querySelectorAll('.chip[draggable]').forEach(function (chip) {
        chip.addEventListener('dragstart', function (e) {
            dragPayload = { origem: 'paleta', item: JSON.parse(chip.getAttribute('data-paleta')) };
            e.dataTransfer.setData('text/plain', 'paleta');
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    document.querySelectorAll('.aula-card[draggable]').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
            dragPayload = { origem: 'aula', id: parseInt(card.dataset.aulaId, 10) };
            e.dataTransfer.setData('text/plain', 'aula');
            e.dataTransfer.effectAllowed = 'move';
            e.stopPropagation();
        });
    });

    board.querySelectorAll('.cel').forEach(function (cel) {
        cel.addEventListener('dragover', function (e) {
            e.preventDefault();
            cel.classList.add('over');
        });
        cel.addEventListener('dragleave', function () { cel.classList.remove('over'); });
        cel.addEventListener('drop', function (e) {
            e.preventDefault();
            cel.classList.remove('over');
            if (!dragPayload) return;

            var dia = cel.dataset.dia;
            var inicio = cel.dataset.inicio;
            var fim = cel.dataset.fim;

            if (dragPayload.origem === 'aula') {
                fetch(urlAlocar.replace(/alocar$/, '') + dragPayload.id + '/mover', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ dia: dia, inicio: inicio, fim: fim })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (!j.ok) return toast(j.erro || 'Falha ao mover', false);
                    location.reload();
                }).catch(function () { toast('Erro de rede ao mover', false); });
                return;
            }

            var item = dragPayload.item || {};
            var regencia = board.dataset.regencia;
            var tempo = parseInt(item.tempo_aula || tempoPadrao, 10);
            var fimCalc = fim;
            if (tempo >= 20) {
                fimCalc = somarMinutos(inicio, tempo);
            }

            var body = {
                dia: dia, inicio: inicio, fim: fimCalc,
                tempo_aula: tempo,
                regencia: regencia,
                professor_id: board.dataset.servidorId,
                professor: board.dataset.servidor,
                cargo: 'Professor',
                turma_id: item.turma_id || board.dataset.turmaId,
                turma: board.dataset.turma
            };

            if (regencia === 'regente_turma') {
                body.componente_id = item.componente_id || item.id;
                body.componente = item.componente || item.rotulo;
                body.cor = item.cor || null;
                if (item.turma_id) {
                    body.turma_id = item.turma_id;
                }
            } else {
                var sel = document.getElementById('disciplina-fixixa');
                if (!sel || !sel.value) {
                    toast('Escolha a disciplina antes de soltar a turma.', false);
                    return;
                }
                var opt = sel.options[sel.selectedIndex];
                body.componente_id = sel.value;
                body.componente = opt.getAttribute('data-nome') || opt.text;
                body.cor = opt.getAttribute('data-cor') || null;
                body.tempo_aula = parseInt(opt.getAttribute('data-tempo') || tempoPadrao, 10);
                body.fim = somarMinutos(inicio, body.tempo_aula);
                body.turma_id = item.turma_id || item.id;
                body.turma = item.turma || item.rotulo;
            }

            fetch(urlAlocar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body)
            }).then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
              .then(function (res) {
                  if (!res.j.ok) return toast(res.j.erro || 'Não foi possível alocar', false);
                  location.reload();
              }).catch(function () { toast('Erro de rede ao alocar', false); });
        });
    });

    document.querySelectorAll('[data-apagar]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = btn.getAttribute('data-apagar');
            if (!confirm('Remover esta aula do quadro?')) return;
            fetch('{{ url('/horario-automatico/grade') }}/' + id + '/apagar', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j.ok) return toast(j.erro || 'Falha ao remover', false);
                location.reload();
            });
        });
    });
})();
</script>
@endsection
