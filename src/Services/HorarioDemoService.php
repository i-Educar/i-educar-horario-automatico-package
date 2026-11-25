<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HorarioDemoService
{
    public const DEMO_TAG = 'laguna-carga-v1';

    /**
     * @return array{aulas:int,pulado:bool,mensagem:string}
     */
    public function executar(bool $force = false): array
    {
        $vazio = ['aulas' => 0, 'pulado' => false, 'mensagem' => ''];
        if (! PocRegistro::disponivel()) {
            $vazio['mensagem'] = 'schema horario_automatico ausente';

            return $vazio;
        }

        if (! $force && $this->jaPovoado()) {
            $vazio['pulado'] = true;
            $vazio['mensagem'] = 'já povoado (use --force)';

            return $vazio;
        }

        if ($force) {
            $this->limparDemo();
        } elseif (PocRegistro::contar('aula') > 0) {
            $vazio['pulado'] = true;
            $vazio['aulas'] = PocRegistro::contar('aula');
            $vazio['mensagem'] = 'quadro já possui aulas';

            return $vazio;
        }

        $periodos = [
            ['inicio' => '07:30', 'fim' => '08:20'],
            ['inicio' => '08:20', 'fim' => '09:10'],
            ['inicio' => '09:30', 'fim' => '10:20'],
            ['inicio' => '10:20', 'fim' => '11:10'],
        ];
        $comps = ['Português', 'Matemática', 'História', 'Ciências'];

        $servs = collect();
        $turmas = collect();
        try {
            if (Schema::hasTable('pmieducar.servidor') && Schema::hasTable('cadastro.pessoa')) {
                $servs = DB::table('pmieducar.servidor')
                    ->join('cadastro.pessoa', 'cadastro.pessoa.idpes', '=', 'pmieducar.servidor.cod_servidor')
                    ->where('pmieducar.servidor.ativo', 1)
                    ->limit(4)
                    ->get(['pmieducar.servidor.cod_servidor as id', 'cadastro.pessoa.nome as nome']);
            }
            if (Schema::hasTable('pmieducar.turma')) {
                $turmas = DB::table('pmieducar.turma')
                    ->where('ativo', 1)
                    ->where('ano', (int) date('Y'))
                    ->orderBy('cod_turma')
                    ->limit(4)
                    ->get(['cod_turma as id', 'nm_turma as nome', 'ref_ref_cod_escola as escola_id']);
            }
        } catch (\Throwable) {
            //
        }

        if ($servs->isEmpty()) {
            $servs = collect([
                (object) ['id' => 9001, 'nome' => 'Prof. Demo 1'],
                (object) ['id' => 9002, 'nome' => 'Prof. Demo 2'],
                (object) ['id' => 9003, 'nome' => 'Prof. Demo 3'],
            ]);
        }
        if ($turmas->isEmpty()) {
            $turmas = collect([
                (object) ['id' => 8001, 'nome' => '5º A', 'escola_id' => null],
                (object) ['id' => 8002, 'nome' => '6º A', 'escola_id' => null],
                (object) ['id' => 8003, 'nome' => '7º A', 'escola_id' => null],
            ]);
        }

        $n = 0;
        foreach ($servs as $i => $s) {
            $turma = $turmas[$i % $turmas->count()];
            $periodo = $periodos[$i % count($periodos)];
            $comp = $comps[$i % count($comps)];
            PocRegistro::gravar('aula', $s->nome.' · '.$turma->nome, $comp, [
                'professor_id' => (int) $s->id,
                'professor' => (string) $s->nome,
                'cargo' => 'Professor',
                'turma_id' => (int) $turma->id,
                'turma' => (string) $turma->nome,
                'escola_id' => isset($turma->escola_id) ? (int) $turma->escola_id : null,
                'componente' => $comp,
                'dia' => ($i % 5) + 1,
                'inicio' => $periodo['inicio'],
                'fim' => $periodo['fim'],
                'demo' => self::DEMO_TAG,
            ]);
            $n++;
        }

        return ['aulas' => $n, 'pulado' => false, 'mensagem' => "aulas={$n}"];
    }

    private function jaPovoado(): bool
    {
        foreach (PocRegistro::listar('aula', 50) as $r) {
            if (($r['payload']['demo'] ?? null) === self::DEMO_TAG) {
                return true;
            }
        }

        return false;
    }

    private function limparDemo(): void
    {
        foreach (PocRegistro::listar('aula', 500) as $r) {
            if (($r['payload']['demo'] ?? null) === self::DEMO_TAG) {
                DB::table(PocRegistro::TABELA)->where('id', $r['id'])->delete();
            }
        }
    }
}
