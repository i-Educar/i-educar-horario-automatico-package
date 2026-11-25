<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

/**
 * Detecta conflitos de professor, turma e multi-escola no mesmo turno.
 */
final class DetectorChoque
{
    /**
     * @param  list<array{id?:int,professor_id:int,dia:int,inicio:string,fim:string,turma_id?:?int,escola_id?:?int}>  $aulas
     * @return list<array{i:int,j:int,tipo:string,motivo:string}>
     */
    public static function detalhar(array $aulas, ?array $diretivas = null): array
    {
        $d = $diretivas ?? DiretivasHorario::atuais();
        $checkProf = (bool) ($d['bloquear_choque_professor'] ?? true);
        $checkTurma = (bool) ($d['bloquear_choque_turma'] ?? true);
        $checkEscola = (bool) ($d['bloquear_multi_escola_turno'] ?? true);
        $out = [];
        $n = count($aulas);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $aulas[$i];
                $b = $aulas[$j];
                if ((int) $a['dia'] !== (int) $b['dia']) {
                    continue;
                }
                if (! self::sobrepoe($a['inicio'], $a['fim'], $b['inicio'], $b['fim'])) {
                    continue;
                }

                if ($checkProf && (int) $a['professor_id'] > 0 && (int) $a['professor_id'] === (int) $b['professor_id']) {
                    $out[] = [
                        'i' => $i,
                        'j' => $j,
                        'tipo' => 'professor',
                        'motivo' => 'Mesmo professor em dois horários sobrepostos',
                    ];
                }

                $ta = (int) ($a['turma_id'] ?? 0);
                $tb = (int) ($b['turma_id'] ?? 0);
                if ($checkTurma && $ta > 0 && $ta === $tb) {
                    $out[] = [
                        'i' => $i,
                        'j' => $j,
                        'tipo' => 'turma',
                        'motivo' => 'Mesma turma com duas aulas no mesmo período',
                    ];
                }
            }

            // Multi-escola no mesmo turno (mesmo dia), mesmo sem sobreposição de horário
            if (! $checkEscola) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $aulas[$i];
                $b = $aulas[$j];
                if ((int) $a['professor_id'] < 1 || (int) $a['professor_id'] !== (int) $b['professor_id']) {
                    continue;
                }
                if ((int) $a['dia'] !== (int) $b['dia']) {
                    continue;
                }
                $ea = (int) ($a['escola_id'] ?? 0);
                $eb = (int) ($b['escola_id'] ?? 0);
                if ($ea < 1 || $eb < 1 || $ea === $eb) {
                    continue;
                }
                $ta = DiretivasHorario::turnoDe((string) $a['inicio']);
                $tb = DiretivasHorario::turnoDe((string) $b['inicio']);
                if ($ta === $tb) {
                    $out[] = [
                        'i' => $i,
                        'j' => $j,
                        'tipo' => 'multi_escola',
                        'motivo' => 'Professor em unidades diferentes no mesmo turno',
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Compat: pares de índices com qualquer choque.
     *
     * @param  list<array{professor_id:int,dia:int,inicio:string,fim:string}>  $aulas
     * @return list<array{0:int,1:int}>
     */
    public static function choques(array $aulas): array
    {
        $seen = [];
        $out = [];
        foreach (self::detalhar($aulas) as $c) {
            $key = $c['i'].'-'.$c['j'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [$c['i'], $c['j']];
        }

        return $out;
    }

    /**
     * Valida se uma aula candidata colide com a grade atual.
     *
     * @param  array{professor_id:int,dia:int,inicio:string,fim:string,turma_id?:?int,escola_id?:?int,id?:int}  $candidata
     * @param  list<array{id?:int,professor_id:int,dia:int,inicio:string,fim:string,turma_id?:?int,escola_id?:?int}>  $aulas
     */
    public static function conflitoCom(array $candidata, array $aulas, ?array $diretivas = null): ?string
    {
        $d = $diretivas ?? DiretivasHorario::atuais();
        $ignorarId = (int) ($candidata['id'] ?? 0);

        // Indisponibilidade do professor
        foreach ((array) ($d['indisponibilidades'] ?? []) as $ind) {
            if ((int) ($ind['professor_id'] ?? 0) !== (int) $candidata['professor_id']) {
                continue;
            }
            if ((int) ($ind['dia'] ?? 0) !== (int) $candidata['dia']) {
                continue;
            }
            if (self::sobrepoe($candidata['inicio'], $candidata['fim'], (string) $ind['inicio'], (string) $ind['fim'])) {
                $motivo = $ind['motivo'] ?? 'indisponibilidade';

                return "Professor indisponível ({$motivo}).";
            }
        }

        $teste = $aulas;
        $teste[] = $candidata;
        foreach (self::detalhar($teste, $d) as $c) {
            $ai = $teste[$c['i']];
            $aj = $teste[$c['j']];
            $idI = (int) ($ai['id'] ?? 0);
            $idJ = (int) ($aj['id'] ?? 0);
            // só importa se envolve a candidata (sem id ou id=ignorar)
            $envolve = ($idI === 0 || $idI === $ignorarId || $idJ === 0 || $idJ === $ignorarId);
            if (! $envolve && $ignorarId > 0) {
                continue;
            }
            // se ambos têm id e nenhum é o ignorado e nenhum é 0, é choque pré-existente
            if ($idI > 0 && $idJ > 0 && $idI !== $ignorarId && $idJ !== $ignorarId) {
                continue;
            }

            return $c['motivo'];
        }

        return null;
    }

    public static function sobrepoe(string $aIni, string $aFim, string $bIni, string $bFim): bool
    {
        return $aIni < $bFim && $bIni < $aFim;
    }
}
