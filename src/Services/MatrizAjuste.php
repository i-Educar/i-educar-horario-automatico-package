<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

/**
 * Ajustes locais de aulas/semana por turma×disciplina (quando a SME precisa
 * complementar o que está no cadastro escolar do i-Educar).
 */
final class MatrizAjuste
{
    public const string TIPO = 'matriz_ajuste';

    /**
     * @return array<int, int> componente_id => aulas_por_semana
     */
    public static function obter(int $turmaId): array
    {
        return self::payloadTurma($turmaId)['aulas'];
    }

    /**
     * @return array<int, int> componente_id => minutos por aula
     */
    public static function obterTempos(int $turmaId): array
    {
        return self::payloadTurma($turmaId)['tempos'];
    }

    /**
     * @return array{aulas:array<int,int>,tempos:array<int,int>}
     */
    private static function payloadTurma(int $turmaId): array
    {
        $vazio = ['aulas' => [], 'tempos' => []];
        if ($turmaId < 1) {
            return $vazio;
        }
        foreach (PocRegistro::listar(self::TIPO, 500) as $r) {
            $p = $r['payload'] ?? [];
            if ((int) ($p['turma_id'] ?? 0) !== $turmaId) {
                continue;
            }
            $aulas = [];
            foreach ((array) ($p['aulas'] ?? []) as $compId => $n) {
                $cid = (int) $compId;
                $qtd = (int) $n;
                if ($cid > 0 && $qtd > 0) {
                    $aulas[$cid] = min(12, $qtd);
                }
            }
            $tempos = [];
            foreach ((array) ($p['tempos'] ?? []) as $compId => $n) {
                $cid = (int) $compId;
                $min = (int) $n;
                if ($cid > 0 && $min >= 20 && $min <= 120) {
                    $tempos[$cid] = $min;
                }
            }

            return ['aulas' => $aulas, 'tempos' => $tempos];
        }

        return $vazio;
    }

    /**
     * @param  array<int|string, int|string>  $aulasPorComponente
     * @param  array<int|string, int|string>  $temposPorComponente
     */
    public static function salvar(int $turmaId, array $aulasPorComponente, ?string $turmaNome = null, array $temposPorComponente = []): int
    {
        $aulas = [];
        foreach ($aulasPorComponente as $compId => $n) {
            $cid = (int) $compId;
            $qtd = (int) $n;
            if ($cid > 0 && $qtd > 0) {
                $aulas[$cid] = min(12, max(1, $qtd));
            }
        }
        $tempos = [];
        foreach ($temposPorComponente as $compId => $n) {
            $cid = (int) $compId;
            $min = (int) $n;
            if ($cid > 0 && $min >= 20 && $min <= 120) {
                $tempos[$cid] = $min;
            }
        }

        $payload = [
            'turma_id' => $turmaId,
            'aulas' => $aulas,
            'tempos' => $tempos,
            'atualizado_em' => now()->toDateTimeString(),
        ];

        $titulo = 'Matriz · '.($turmaNome ?: ('Turma #'.$turmaId));
        $meta = count($aulas).' disciplina(s) · '.count($tempos).' tempo(s)';

        foreach (PocRegistro::listar(self::TIPO, 500) as $r) {
            if ((int) (($r['payload']['turma_id'] ?? 0)) === $turmaId) {
                PocRegistro::atualizar((int) $r['id'], $payload, $titulo, $meta);

                return (int) $r['id'];
            }
        }

        return PocRegistro::gravar(self::TIPO, $titulo, $meta, $payload);
    }
}
