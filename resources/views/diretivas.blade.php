@extends('horario::layout')
@section('titulo', 'Regras da rede — Quadro de horários')
@section('migalha', 'Regras')
@section('icone-tela', 'fa-sliders')
@section('titulo-tela', 'Regras da rede para o quadro')
@section('lead')
    Defina períodos, limites e preferências que a montagem automática deve respeitar em todas as escolas.
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/horario-automatico/validar') }}"><i class="fa fa-check-square-o"></i> Verificar demanda</a>
    <a class="botao claro" href="{{ url('/horario-automatico/gerar') }}"><i class="fa fa-magic"></i> Gerar quadro</a>
@endsection

@section('conteudo')
@php
    $d = $diretivas;
    $periodosTxt = collect($d['periodos'] ?? [])->map(fn ($p) => ($p['inicio'] ?? '').'-'.($p['fim'] ?? '').'|'.($p['rotulo'] ?? '').'|'.($p['turno'] ?? ''))->implode("\n");
@endphp

<form method="post" action="{{ url('/horario-automatico/diretivas') }}" class="bloco">
    @csrf
            <label style="display:grid;gap:4px;margin:0 0 10px">Tempo padrão de aula (minutos)
                <input type="number" name="tempo_aula_minutos" min="20" max="120" value="{{ (int)($diretivas['tempo_aula_minutos'] ?? 50) }}">
            </label>
    <h2><i class="fa fa-cog"></i> Escopo e limites</h2>
    <div class="form-grid">
        <label>Ano letivo
            <input type="number" name="ano" value="{{ $d['ano'] }}" min="2020" max="2100">
        </label>
        <label>Aulas/semana quando a disciplina não tiver carga
            <input type="number" name="carga_padrao_aulas" value="{{ $d['carga_padrao_aulas'] }}" min="1" max="10">
        </label>
        <label>Semanas letivas (para converter carga anual)
            <input type="number" name="semanas_letivas" value="{{ $d['semanas_letivas'] ?? 40 }}" min="20" max="50">
        </label>
        <label>Máximo de aulas da mesma disciplina no mesmo dia
            <input type="number" name="max_aulas_mesmo_dia" value="{{ $d['max_aulas_mesmo_dia'] }}" min="1" max="5">
        </label>
        @include('buriti-ui::select-multi', [
            'campo' => 'escola_ids[]',
            'rotulo' => 'Escolas participantes (vazio = rede inteira)',
            'icone' => 'fa-building',
            'opcoes' => collect($escolas)->map(fn ($e) => ['id' => $e['id'], 'nome' => $e['nome'], 'meta' => $e['turmas']])->all(),
            'selecionados' => $d['escola_ids'] ?? [],
            'placeholder' => 'Buscar escola…',
        ])
        <label><input type="checkbox" name="separar_dias" value="1" @checked($d['separar_dias'] ?? true)> Espalhar as aulas pelos dias da semana</label>
        <label><input type="checkbox" name="bloquear_choque_professor" value="1" @checked($d['bloquear_choque_professor'] ?? true)> Não permitir o mesmo professor em dois horários ao mesmo tempo</label>
        <label><input type="checkbox" name="bloquear_choque_turma" value="1" @checked($d['bloquear_choque_turma'] ?? true)> Não permitir duas aulas na mesma turma no mesmo horário</label>
        <label><input type="checkbox" name="bloquear_multi_escola_turno" value="1" @checked($d['bloquear_multi_escola_turno'] ?? true)> Não permitir o professor em escolas diferentes no mesmo turno</label>
    </div>

    <h2 style="margin-top:18px"><i class="fa fa-clock-o"></i> Períodos de aula</h2>
    <p style="color:var(--suave);font-size:.85rem;margin:0 0 8px">Uma por linha: <code>07:30-08:20|1ª|matutino</code></p>
    <div class="form-grid">
        <label class="col-wide">
            <textarea name="periodos_texto" rows="10" style="font-family:ui-monospace,monospace">{{ $periodosTxt }}</textarea>
        </label>
    </div>

    <h2 style="margin-top:18px"><i class="fa fa-link"></i> Disciplinas em aula geminada</h2>
    <p style="color:var(--suave);font-size:.85rem;margin:0 0 8px">Ficam em dois períodos seguidos no mesmo turno (ex.: Educação Física).</p>
    <div class="form-grid">
        @include('buriti-ui::select-multi', [
            'campo' => 'geminadas[]',
            'rotulo' => 'Disciplinas geminadas',
            'icone' => 'fa-link',
            'opcoes' => collect($componentes)->map(fn ($c) => ['id' => $c['id'], 'nome' => $c['nome']])->all(),
            'selecionados' => $d['geminadas'] ?? [],
            'placeholder' => 'Buscar disciplina…',
            'tamanho' => 8,
        ])
    </div>

    <h2 style="margin-top:18px"><i class="fa fa-star"></i> Professor preferencial</h2>
    <p style="color:var(--suave);font-size:.85rem">Até 5 preferências turma + componente → professor.</p>
    @for($i = 0; $i < 5; $i++)
        @php $pref = $d['preferenciais'][$i] ?? null; @endphp
        <div class="form-grid" style="margin-bottom:6px">
            <label>Turma
                <select name="pref_turma[]">
                    <option value="">—</option>
                    @foreach($turmas as $t)
                        <option value="{{ $t['id'] }}" @selected(($pref['turma_id'] ?? null) == $t['id'])>{{ $t['nome'] }}@if($t['escola']) — {{ $t['escola'] }}@endif</option>
                    @endforeach
                </select>
            </label>
            <label>Componente
                <select name="pref_comp[]">
                    <option value="">—</option>
                    @foreach($componentes as $c)
                        <option value="{{ $c['id'] }}" @selected(($pref['componente_id'] ?? null) == $c['id'])>{{ $c['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Professor
                <select name="pref_prof[]">
                    <option value="">—</option>
                    @foreach($servidores as $s)
                        <option value="{{ $s['id'] }}" @selected(($pref['professor_id'] ?? null) == $s['id'])>{{ $s['nome'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    @endfor

    <h2 style="margin-top:18px"><i class="fa fa-ban"></i> Indisponibilidades (planejamento / HTPC)</h2>
    @for($i = 0; $i < 5; $i++)
        @php $ind = $d['indisponibilidades'][$i] ?? null; @endphp
        <div class="form-grid" style="margin-bottom:6px">
            <label>Professor
                <select name="ind_prof[]">
                    <option value="">—</option>
                    @foreach($servidores as $s)
                        <option value="{{ $s['id'] }}" @selected(($ind['professor_id'] ?? null) == $s['id'])>{{ $s['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Dia
                <select name="ind_dia[]">
                    <option value="">—</option>
                    @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex'] as $nd => $nr)
                        <option value="{{ $nd }}" @selected(($ind['dia'] ?? null) == $nd)>{{ $nr }}</option>
                    @endforeach
                </select>
            </label>
            <label>Início <input type="time" name="ind_inicio[]" value="{{ $ind['inicio'] ?? '' }}"></label>
            <label>Fim <input type="time" name="ind_fim[]" value="{{ $ind['fim'] ?? '' }}"></label>
            <label>Motivo <input type="text" name="ind_motivo[]" value="{{ $ind['motivo'] ?? '' }}" placeholder="HTPC / planejamento"></label>
        </div>
    @endfor

    <div class="form-grid" style="margin-top:12px">
        <label class="col-wide">Observações
            <textarea name="notas" rows="3">{{ $d['notas'] ?? '' }}</textarea>
        </label>
        <div class="acoes">
            <button type="submit" class="botao"><i class="fa fa-save"></i> Salvar diretivas</button>
        </div>
    </div>
</form>
@endsection
