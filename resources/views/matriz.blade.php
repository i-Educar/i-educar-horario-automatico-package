@extends('horario::layout')
@section('titulo', 'Matriz curricular — Quadro de horários')
@section('migalha', 'Matriz')
@section('icone-tela', 'fa-th-list')
@section('titulo-tela', 'Matriz curricular das turmas')
@section('lead')
    Confira quantas aulas por semana cada disciplina precisa. Os números vêm do cadastro da escola no i-Educar; aqui você pode ajustar quando faltar informação.
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/horario-automatico/validar') }}"><i class="fa fa-check-square-o"></i> Verificar demanda</a>
    <a class="botao claro" href="{{ url('/horario-automatico/gerar') }}"><i class="fa fa-magic"></i> Gerar quadro</a>
@endsection

@section('conteudo')
@php
    $origemRotulo = [
        'turma' => 'Cadastro da turma',
        'escola_serie' => 'Escola e série',
        'ano_escolar' => 'Ano escolar',
        'ajuste_local' => 'Ajuste neste módulo',
    ];
@endphp

<div class="bloco">
    <h2><i class="fa fa-filter"></i> Escolher turma</h2>
    <form method="get" action="{{ url('/horario-automatico/matriz') }}" class="form-grid">
        <label>Ano letivo
            <input type="number" name="ano" value="{{ $ano }}" min="2020" max="2100">
        </label>
        @include('buriti-ui::select-busca', [
            'campo' => 'turma_id',
            'rotulo' => 'Turma',
            'icone' => 'fa-graduation-cap',
            'opcoes' => collect($turmas)->map(fn ($t) => [
                'id' => $t['id'],
                'nome' => $t['nome'],
                'meta' => trim(($t['escola'] ?? '').' · '.$t['componentes'].' disciplina(s)'),
            ])->all(),
            'selecionado' => $turmaAtual['id'] ?? null,
            'placeholder' => 'Buscar turma ou escola…',
            'ajuda' => 'Escolha a turma para ver e ajustar as disciplinas da semana.',
            'obrigatorio' => true,
        ])
        <div class="acoes">
            <button type="submit" class="botao"><i class="fa fa-search"></i> Abrir matriz</button>
        </div>
    </form>
</div>

@if($turmaAtual)
    @if($componentes === [])
        <div class="buriti-alerta aviso">
            <i class="fa fa-info-circle"></i>
            <div>
                <strong>Nenhuma disciplina encontrada</strong>
                <p>Cadastre a matriz em Escola → Série → Disciplinas no i-Educar, ou informe as aulas/semana abaixo depois de vincular os componentes na escola.</p>
            </div>
        </div>
    @else
        <div class="numeros">
            <article style="--tom: var(--cor)"><span class="kpi-ico"><i class="fa fa-book"></i></span><span>Disciplinas</span><strong>{{ count($componentes) }}</strong></article>
            <article style="--tom: var(--destaque)"><span class="kpi-ico"><i class="fa fa-calendar"></i></span><span>Aulas na semana</span><strong>{{ $totalAulas }}</strong></article>
            <article style="--tom: {{ $totalAulas > $capacidade ? 'var(--erro)' : 'var(--ok)' }}"><span class="kpi-ico"><i class="fa fa-clock-o"></i></span><span>Capacidade da grade</span><strong>{{ $capacidade }}</strong></article>
        </div>

        @if($totalAulas > $capacidade)
            <div class="buriti-alerta erro">
                <i class="fa fa-exclamation-triangle"></i>
                <div>
                    <strong>Excesso de aulas</strong>
                    <p>Esta turma precisa de {{ $totalAulas }} aulas por semana, mas a grade só comporta {{ $capacidade }} (períodos × dias). Reduza aulas/semana ou acrescente períodos nas regras da rede.</p>
                </div>
            </div>
        @endif

        <form method="post" action="{{ url('/horario-automatico/matriz') }}" class="bloco">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaAtual['id'] }}">
            <input type="hidden" name="ano" value="{{ $ano }}">
            <h2><i class="fa fa-pencil"></i> {{ $turmaAtual['nome'] }}@if($turmaAtual['escola']) <small style="font-weight:500;color:var(--suave)">· {{ $turmaAtual['escola'] }}</small>@endif</h2>
            <p style="color:var(--suave);margin:0 0 12px;font-size:.88rem">
                Informe <strong>aulas por semana</strong> e o <strong>tempo de cada aula</strong> (minutos). O quadro usa esse tempo para calcular o fim do horário.
            </p>
            <div style="overflow:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>De onde veio</th>
                            <th>Carga (h)</th>
                            <th>Aulas / semana</th>
                            <th>Tempo aula (min)</th>
                            <th>Professor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($componentes as $c)
                            @php
                                $profs = $professores[$c['id']] ?? [];
                                $sugerido = $sugeridas[$c['id']] ?? 1;
                            @endphp
                            <tr>
                                <td>
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $c['cor'] }};margin-right:6px"></span>
                                    <strong>{{ $c['nome'] }}</strong>
                                    <small style="color:var(--suave)"> · {{ $c['abreviatura'] }}</small>
                                </td>
                                <td><span class="badge {{ !empty($c['ajuste_local']) ? 'warn' : 'ok' }}">{{ $origemRotulo[$c['origem'] ?? ''] ?? ($c['origem'] ?? '—') }}</span></td>
                                <td>{{ $c['carga_horaria'] !== null ? $c['carga_horaria'] : '—' }}</td>
                                <td>
                                    <input type="number"
                                           name="aulas[{{ $c['id'] }}]"
                                           min="1" max="12"
                                           value="{{ $c['aulas_por_semana'] ?: $sugerido }}"
                                           style="width:72px;min-height:34px;padding:4px 8px;border:1px solid var(--linha);border-radius:8px">
                                </td>
                                <td>
                                    <input type="number"
                                           name="tempos[{{ $c['id'] }}]"
                                           min="20" max="120" step="5"
                                           value="{{ $c['tempo_aula'] ?? 50 }}"
                                           style="width:80px;min-height:34px;padding:4px 8px;border:1px solid var(--linha);border-radius:8px">
                                </td>
                                <td>
                                    @if($profs === [])
                                        <span class="badge fail">Sem professor</span>
                                    @else
                                        {{ $profs[0]['nome'] }}@if(count($profs) > 1) <small style="color:var(--suave)">+{{ count($profs)-1 }}</small>@endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-grid" style="margin-top:14px">
                <div class="acoes">
                    <button type="submit" class="botao"><i class="fa fa-save"></i> Salvar matriz e tempos</button>
                </div>
            </div>
        </form>
    @endif
@endif
@endsection
