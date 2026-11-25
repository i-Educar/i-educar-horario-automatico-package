@extends('horario::layout')
@section('titulo', 'Por cargo')
@section('migalha', 'Por cargo')
@section('icone-tela', 'fa-id-badge')
@section('titulo-tela', 'Por cargo')
@section('lead')
    Carga conforme cargo
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/horario-automatico') }}"><i class="fa fa-dashboard"></i> Painel</a>
@endsection

@section('conteudo')

    <div class="bloco">
        @yield('extra')
        @if(!empty($linhas))
            <div class="card-grid">
                @foreach($linhas as $i => $l)
                    <article class="card" style="animation-delay: {{ $i * 0.04 }}s">
                        <header>
                            <span class="ico"><i class="fa fa-id-badge"></i></span>
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
