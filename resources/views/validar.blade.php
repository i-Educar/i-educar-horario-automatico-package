@extends('horario::layout')
@section('titulo', 'Verificar demanda — Quadro de horários')
@section('migalha', 'Verificar')
@section('icone-tela', 'fa-check-square-o')
@section('titulo-tela', 'Verificar demanda curricular')
@section('lead')
    Antes de gerar o quadro, confira se faltam disciplinas, professores ou se há aulas a mais do que a semana comporta.
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/horario-automatico/matriz') }}" title="Matriz" aria-label="Matriz"><i aria-hidden="true" class="fa fa-th-list"></i></a>
    <a class="botao icone" href="{{ url('/horario-automatico/gerar') }}" title="Gerar quadro" aria-label="Gerar quadro"><i aria-hidden="true" class="fa fa-magic"></i></a>
@endsection

@section('conteudo')
<div class="bloco">
    <h2><i class="fa fa-filter"></i> Escopo da verificação</h2>
    <form method="get" action="{{ url('/horario-automatico/validar') }}" class="form-grid">
        <label>Ano letivo
            <input type="number" name="ano" value="{{ $ano }}" min="2020" max="2100">
        </label>
        @include('buriti-ui::select-multi', [
            'campo' => 'escola_ids[]',
            'rotulo' => 'Escolas (vazio = todas da rede)',
            'icone' => 'fa-building',
            'opcoes' => collect($escolas)->map(fn ($e) => [
                'id' => $e['id'],
                'nome' => $e['nome'],
                'meta' => $e['turmas'],
            ])->all(),
            'selecionados' => $escolaIds,
            'placeholder' => 'Buscar escola…',
            'ajuda' => 'Deixe vazio para olhar a rede inteira. Marque uma ou mais escolas para restringir.',
        ])
        <div class="acoes">
            <button type="submit" class="botao"><i class="fa fa-search"></i> Verificar agora</button>
        </div>
    </form>
</div>

<div class="numeros">
    <article style="--tom: var(--cor)"><span class="kpi-ico"><i class="fa fa-graduation-cap"></i></span><span>Turmas</span><strong>{{ $resultado['totais']['turmas'] }}</strong></article>
    <article style="--tom: var(--destaque)"><span class="kpi-ico"><i class="fa fa-book"></i></span><span>Disciplinas</span><strong>{{ $resultado['totais']['disciplinas'] }}</strong></article>
    <article style="--tom: var(--ok)"><span class="kpi-ico"><i class="fa fa-calendar"></i></span><span>Aulas / semana</span><strong>{{ $resultado['totais']['aulas'] }}</strong></article>
    <article style="--tom: {{ $resultado['totais']['erros'] ? 'var(--erro)' : 'var(--ok)' }}"><span class="kpi-ico"><i class="fa fa-exclamation-triangle"></i></span><span>Problemas</span><strong>{{ $resultado['totais']['erros'] }}</strong></article>
    <article style="--tom: var(--ambar)"><span class="kpi-ico"><i class="fa fa-info-circle"></i></span><span>Avisos</span><strong>{{ $resultado['totais']['avisos'] }}</strong></article>
</div>

@if($resultado['ok'])
    <div class="buriti-alerta ok">
        <i class="fa fa-check-circle"></i>
        <div>
            <strong>Tudo certo para gerar</strong>
            <p>Não encontramos problemas graves. Ainda assim, revise os avisos abaixo se houver.</p>
        </div>
    </div>
@else
    <div class="buriti-alerta erro">
        <i class="fa fa-times-circle"></i>
        <div>
            <strong>Há pendências antes de montar o quadro</strong>
            <p>Corrija a matriz, vincule professores ou ajuste períodos nas regras da rede. Depois volte a verificar.</p>
        </div>
    </div>
@endif

@if($resultado['erros'] !== [])
<div class="bloco">
    <h2><i class="fa fa-exclamation-triangle"></i> Problemas a corrigir</h2>
    <ul style="margin:0;padding:0;list-style:none;display:grid;gap:8px">
        @foreach($resultado['erros'] as $e)
            <li class="buriti-alerta erro" style="margin:0">
                <i class="fa fa-times"></i>
                <div>
                    <strong>{{ $e['turma'] ?? 'Rede' }}@if($e['disciplina']) · {{ $e['disciplina'] }}@endif</strong>
                    <p>{{ $e['mensagem'] }}</p>
                </div>
            </li>
        @endforeach
    </ul>
</div>
@endif

@if($resultado['avisos'] !== [])
<div class="bloco">
    <h2><i class="fa fa-info-circle"></i> Avisos</h2>
    <ul style="margin:0;padding:0;list-style:none;display:grid;gap:8px">
        @foreach($resultado['avisos'] as $a)
            <li class="buriti-alerta aviso" style="margin:0">
                <i class="fa fa-info"></i>
                <div>
                    <strong>{{ $a['turma'] ?? 'Rede' }}@if($a['disciplina']) · {{ $a['disciplina'] }}@endif</strong>
                    <p>{{ $a['mensagem'] }}</p>
                </div>
            </li>
        @endforeach
    </ul>
</div>
@endif

<div class="bloco">
    <h2><i class="fa fa-table"></i> Resumo por turma</h2>
    <div style="overflow:auto">
        <table>
            <thead>
                <tr>
                    <th>Turma</th>
                    <th>Disciplinas</th>
                    <th>Aulas / semana</th>
                    <th>Capacidade</th>
                    <th>Sem professor</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resultado['turmas'] as $t)
                    @php
                        $excesso = $t['aulas_semana'] > $t['capacidade'];
                        $okTurma = ! $excesso && $t['disciplinas'] > 0 && $t['sem_professor'] === 0;
                    @endphp
                    <tr>
                        <td>{{ $t['turma'] }}</td>
                        <td>{{ $t['disciplinas'] }}</td>
                        <td>{{ $t['aulas_semana'] }}</td>
                        <td>{{ $t['capacidade'] }}</td>
                        <td>{{ $t['sem_professor'] }}</td>
                        <td>
                            @if($okTurma)
                                <span class="badge ok">Pronta</span>
                            @elseif($t['disciplinas'] === 0)
                                <span class="badge fail">Sem matriz</span>
                            @elseif($excesso)
                                <span class="badge fail">Excesso</span>
                            @else
                                <span class="badge warn">Revisar</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhuma turma no escopo escolhido.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
