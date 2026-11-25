@extends('horario::layout')
@section('titulo', 'Início — Quadro de horários')
@section('migalha', 'Início')
@section('icone-tela', 'fa-calendar')
@section('titulo-tela', 'Quadro de horários da rede')
@section('lead')
    Organize as aulas da semana: confira a matriz, verifique a demanda, gere o quadro e ajuste o que precisar.
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/horario-automatico/matriz') }}"><i class="fa fa-th-list"></i> Matriz</a>
    <a class="botao claro" href="{{ url('/horario-automatico/validar') }}"><i class="fa fa-check-square-o"></i> Verificar</a>
    <a class="botao claro" href="{{ url('/horario-automatico/gerar') }}"><i class="fa fa-magic"></i> Gerar quadro</a>
    @if(!empty($gradeBloqueada) && !empty($podeNovaVersao))
        <form method="post" action="{{ url('/horario-automatico/nova-versao') }}" style="display:inline">@csrf
            <button class="botao claro" type="submit"><i class="fa fa-code-fork"></i> Nova versão</button>
        </form>
    @endif
    @if(empty($gradeBloqueada) && !empty($podeFazerValer))
        <form method="post" action="{{ url('/horario-automatico/fazer-valer') }}" style="display:inline" onsubmit="return confirm('Fazer valer congela a grade e propaga alocações ao diário. Continuar?')">@csrf
            <button class="botao" type="submit"><i class="fa fa-check-circle"></i> Fazer valer</button>
        </form>
    @endif
@endsection

@section('conteudo')
    @php
        $vig = $vigencia['payload'] ?? null;
        $statusVig = $vig['status'] ?? 'sem versão';
    @endphp
    <div class="bloco" style="margin:0 0 14px;border-top-color: {{ !empty($gradeBloqueada) ? 'var(--ok)' : 'var(--ambar)' }}">
        <h2 style="margin:0 0 6px"><i class="fa {{ !empty($gradeBloqueada) ? 'fa-lock' : 'fa-pencil' }}"></i>
            Situação: {{ strtoupper($statusVig) }}
        </h2>
        <p style="margin:0;color:var(--suave)">
            @if(!empty($gradeBloqueada))
                Grade vigente congelada — alterações exigem <strong>nova versão</strong> (histórico preservado). Alocações e diário já refletem esta publicação.
            @else
                Grade editável. Quando estiver pronta, use <strong>Fazer valer</strong> para propagar ao quadro oficial, alocação do servidor e vínculos do diário.
            @endif
        </p>
    </div>
    @include('buriti-ui::painel-corpo', [
        'kpis' => $kpis ?? [],
        'atalhos' => $atalhos ?? [],
        'destaques' => $destaques ?? [],
        'analise' => $analise ?? [],
        'analiseTitulo' => $analiseTitulo ?? 'Análise',
        'resumo' => $resumo ?? null,
        'fluxoTitulo' => $fluxoTitulo ?? 'Fluxo da rede',
    ])
@endsection
