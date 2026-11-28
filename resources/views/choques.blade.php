@extends('horario::layout')
@section('titulo', 'Conflitos de horário')
@section('migalha', 'Conflitos')
@section('icone-tela', 'fa-warning')
@section('titulo-tela', 'Conflitos de horário')
@section('lead')
    Veja quando o mesmo professor ou a mesma turma ficaram com duas aulas no mesmo horário.
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/horario-automatico/grade') }}" title="Ajustar quadro" aria-label="Ajustar quadro"><i aria-hidden="true" class="fa fa-table"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico') }}" title="Início" aria-label="Início"><i aria-hidden="true" class="fa fa-dashboard"></i></a>
@endsection

@section('conteudo')

    <div class="bloco">
        @yield('extra')
        @if(!empty($linhas))
            <div class="card-grid">
                @foreach($linhas as $i => $l)
                    <article class="card" style="animation-delay: {{ $i * 0.04 }}s">
                        <header>
                            <span class="ico"><i class="fa fa-warning"></i></span>
                            <div>
                                <h3>{{ $l['titulo'] ?? ('Item '.($i+1)) }}</h3>
                                <p class="meta">{{ $l['meta'] ?? '' }}</p>
                            </div>
                        </header>
                        @if(!empty($l['badge']))
                            <span class="badge {{ $l['badge_tom'] ?? 'ok' }}">{{ $l['badge'] }}</span>
                        @endif
                        <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <p style="color:var(--suave);margin:0">Sem registros nesta PoC — os dados de demonstração aparecem após o povoamento do módulo.</p>
        @endif
    </div>
@endsection
