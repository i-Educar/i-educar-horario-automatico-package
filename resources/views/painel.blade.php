@extends('horario::layout')
@section('titulo', 'Início — Quadro de horários')
@section('migalha', 'Início')
@section('icone-tela', 'fa-calendar')
@section('titulo-tela', 'Quadro de horários da rede')
@section('lead')
    Organize as aulas da semana: confira a matriz, verifique a demanda, gere o quadro e ajuste o que precisar.
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/horario-automatico/matriz') }}" title="Matriz" aria-label="Matriz"><i aria-hidden="true" class="fa fa-th-list"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico/validar') }}" title="Verificar" aria-label="Verificar"><i aria-hidden="true" class="fa fa-check-square-o"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico/gerar') }}" title="Gerar quadro" aria-label="Gerar quadro"><i aria-hidden="true" class="fa fa-magic"></i></a>
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
