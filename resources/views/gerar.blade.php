@extends('horario::layout')
@section('titulo', 'Gerar quadro')
@section('migalha', 'Gerar')
@section('icone-tela', 'fa-magic')
@section('titulo-tela', 'Gerar o quadro automaticamente')
@section('lead')
    O sistema distribui as aulas da matriz pelos dias da semana, respeitando as regras da rede e os professores vinculados.
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/horario-automatico/validar') }}" title="Verificar antes" aria-label="Verificar antes"><i aria-hidden="true" class="fa fa-check-square-o"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico/matriz') }}" title="Matriz" aria-label="Matriz"><i aria-hidden="true" class="fa fa-th-list"></i></a>
    <a class="botao claro icone" href="{{ url('/horario-automatico/grade') }}" title="Quadro" aria-label="Quadro"><i aria-hidden="true" class="fa fa-table"></i></a>
@endsection

@section('conteudo')
@if(!empty($gradeBloqueada))
    <div class="bloco" style="margin:0 0 14px;border-top-color:var(--ok)">
        <h2 style="margin:0 0 6px"><i class="fa fa-lock"></i> Grade vigente congelada</h2>
        <p style="margin:0;color:var(--suave)">Geração bloqueada. Abra uma <strong>nova versão</strong> para montar outro quadro sem apagar o histórico.
            @if(!empty($podeNovaVersao))
            <form method="post" action="{{ url('/horario-automatico/nova-versao') }}" style="display:inline;margin-left:8px">@csrf
                <button class="botao claro" type="submit"><i class="fa fa-code-fork"></i> Nova versão</button>
            </form>
            @endif
        </p>
    </div>
@endif
@php $e = $estado; $d = $diretivas; @endphp

<div class="buriti-alerta aviso">
    <i class="fa fa-lightbulb-o"></i>
    <div>
        <strong>Dica</strong>
        <p>Confira a <a href="{{ url('/horario-automatico/matriz') }}">matriz curricular</a> e a <a href="{{ url('/horario-automatico/validar') }}">verificação de demanda</a> antes de gerar. Assim evitamos turma com um único dia de aula ou disciplinas faltando.</p>
    </div>
</div>

<div class="bloco">
    <h2><i class="fa fa-play"></i> Onde gerar</h2>
    <form id="form-gerar" class="form-grid">
        @csrf
        <label>Ano letivo
            <input type="number" name="ano" id="ano" value="{{ $d['ano'] ?? date('Y') }}">
        </label>
        @include('buriti-ui::select-multi', [
            'campo' => 'escola_ids[]',
            'rotulo' => 'Escolas (vazio = regras da rede / toda a rede)',
            'icone' => 'fa-building',
            'opcoes' => collect($escolas)->map(fn ($escola) => [
                'id' => $escola['id'],
                'nome' => $escola['nome'],
                'meta' => $escola['turmas'],
            ])->all(),
            'selecionados' => $d['escola_ids'] ?? [],
            'placeholder' => 'Buscar escola…',
        ])
        <label><input type="checkbox" name="substituir" id="substituir" value="1" checked> Apagar o quadro atual e montar de novo</label>
        <p style="margin:6px 0 0;color:var(--suave);font-size:.82rem;line-height:1.4">
            Turmas de <strong>AEE / Educação especial</strong> não entram na geração nem são apagadas na recriação
            (não usam matriz curricular regular).
        </p>
        <div class="acoes">
            <button type="button" class="botao" id="btn-iniciar"><i class="fa fa-magic"></i> Iniciar geração</button>
            <button type="button" class="botao claro" id="btn-continuar" @if(!($e && ($e['status'] ?? '') === 'pausado')) style="display:none" @endif><i class="fa fa-forward"></i> Continuar</button>
            <button type="button" class="botao claro" id="btn-pausar" @if(!($e && ($e['status'] ?? '') === 'rodando')) style="display:none" @endif><i class="fa fa-pause"></i> Pausar</button>
        </div>
    </form>
</div>

<div class="bloco">
    <h2><i class="fa fa-tasks"></i> Progresso</h2>
    <div class="numeros" id="kpis-geracao">
        <article style="--tom: var(--cor)"><span>Status</span><strong id="g-status">{{ $e['status'] ?? '—' }}</strong></article>
        <article style="--tom: var(--destaque)"><span>Fila</span><strong id="g-fila">{{ isset($e['feitos']) ? $e['feitos'].'/'.$e['total'] : '—' }}</strong></article>
        <article style="--tom: var(--ok)"><span>Alocadas</span><strong id="g-ok">{{ $e['alocadas'] ?? 0 }}</strong></article>
        <article style="--tom: var(--erro)"><span>Falhas</span><strong id="g-fail">{{ $e['falhas'] ?? 0 }}</strong></article>
    </div>
    <div style="height:12px;background:var(--clara);border-radius:999px;overflow:hidden;margin:0 0 12px">
        <div id="g-barra" style="height:100%;width:{{ isset($e['total']) && $e['total']>0 ? round(100*($e['feitos']/$e['total'])) : 0 }}%;background:linear-gradient(90deg,var(--destaque),var(--cor));transition:width .3s"></div>
    </div>
    <pre id="g-log" style="max-height:280px;overflow:auto;background:#0f1720;color:#d7e2ea;padding:12px;border-radius:12px;font-size:.78rem;margin:0">{{ isset($e['log']) ? implode("\n", array_reverse($e['log'])) : 'Aguardando…' }}</pre>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var rodando = false;

    function selectedEscolas() {
        var sel = document.querySelector('#form-gerar select[name="escola_ids[]"]');
        if (!sel) return [];
        return Array.from(sel.selectedOptions).map(function (o) { return parseInt(o.value, 10); }).filter(function (n) { return n > 0; });
    }

    function pintar(estado) {
        if (!estado) return;
        document.getElementById('g-status').textContent = estado.status || '—';
        document.getElementById('g-fila').textContent = (estado.feitos || 0) + '/' + (estado.total || 0);
        document.getElementById('g-ok').textContent = estado.alocadas || 0;
        document.getElementById('g-fail').textContent = estado.falhas || 0;
        var pct = estado.total ? Math.round(100 * estado.feitos / estado.total) : 0;
        document.getElementById('g-barra').style.width = pct + '%';
        var log = (estado.log || []).slice().reverse().join('\n');
        document.getElementById('g-log').textContent = log || '…';
        document.getElementById('btn-pausar').style.display = estado.status === 'rodando' ? '' : 'none';
        document.getElementById('btn-continuar').style.display = estado.status === 'pausado' ? '' : 'none';
    }

    function tick() {
        if (!rodando) return;
        fetch('{{ url('/horario-automatico/gerar/continuar') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ limite_passos: {{ (int) $passos }} })
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j.ok) { rodando = false; alert(j.erro || 'Falha'); return; }
            pintar(j.estado);
            if (j.estado.status === 'concluido' || j.estado.status === 'pausado' || j.estado.status === 'erro') {
                rodando = false;
                return;
            }
            setTimeout(tick, 200);
        }).catch(function () { rodando = false; });
    }

    document.getElementById('btn-iniciar').addEventListener('click', function () {
        fetch('{{ url('/horario-automatico/gerar/iniciar') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                ano: parseInt(document.getElementById('ano').value, 10),
                escola_ids: selectedEscolas(),
                substituir: document.getElementById('substituir').checked,
                limite_passos: {{ (int) $passos }}
            })
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j.ok) { alert(j.erro || 'Não iniciou'); return; }
            pintar(j.estado);
            rodando = j.estado.status === 'rodando';
            if (rodando) tick();
        });
    });

    document.getElementById('btn-continuar').addEventListener('click', function () {
        rodando = true; tick();
    });
    document.getElementById('btn-pausar').addEventListener('click', function () {
        fetch('{{ url('/horario-automatico/gerar/pausar') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (j) {
            rodando = false;
            if (j.ok) pintar(j.estado);
        });
    });
})();
</script>
@endsection
