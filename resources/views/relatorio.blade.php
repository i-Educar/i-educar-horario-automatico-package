@extends('horario::layout')
@section('titulo', 'Relatórios — Quadro de horários')
@section('migalha', 'Relatórios')
@section('icone-tela', 'fa-file-text-o')
@section('titulo-tela', 'Grades geradas')
@section('lead')
    Agrupamento por turma ou professor para impressão / conferência
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/horario-automatico/relatorio?por=turma') }}"><i class="fa fa-graduation-cap"></i> Por turma</a>
    <a class="botao claro" href="{{ url('/horario-automatico/relatorio?por=professor') }}"><i class="fa fa-user"></i> Por professor</a>
    <button type="button" class="botao" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
@endsection

@section('conteudo')
<style>
@media print {
    .lateral, .topo, .acoes-tela, footer.rodape { display: none !important; }
    .miolo, main { padding: 0 !important; }
    .bloco { break-inside: avoid; box-shadow: none; }
}
.rel-grade { width:100%; border-collapse:collapse; font-size:.82rem; margin-top:8px; }
.rel-grade th, .rel-grade td { border:1px solid var(--linha); padding:6px; vertical-align:top; }
.rel-grade th { background:var(--clara); }
</style>

<p style="margin:0 0 12px;color:var(--suave)">Visão: <strong>{{ $agrupar === 'professor' ? 'por professor' : 'por turma' }}</strong> · {{ count($grupos) }} grupo(s)</p>

@forelse($grupos as $g)
    <div class="bloco">
        <h2>{{ $g['titulo'] }} <span class="badge ok">{{ count($g['aulas']) }} aulas</span></h2>
        <table class="rel-grade">
            <thead>
                <tr>
                    <th>Dia</th><th>Horário</th><th>Componente</th>
                    @if($agrupar === 'turma')<th>Professor</th>@else<th>Turma</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach(collect($g['aulas'])->sortBy(['dia','inicio']) as $a)
                    <tr>
                        <td>{{ $dias[$a['dia']] ?? $a['dia'] }}</td>
                        <td>{{ $a['inicio'] }}–{{ $a['fim'] }}</td>
                        <td>{{ $a['componente'] }}</td>
                        <td>{{ $agrupar === 'turma' ? $a['professor'] : $a['turma'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="bloco"><p>Nenhuma aula na grade. Rode a geração ou edite o quadro.</p></div>
@endforelse
@endsection
