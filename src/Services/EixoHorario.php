<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

/**
 * Monta o eixo vertical do quadro: entrada → aulas (com duração) → saída.
 * Lacunas entre blocos ficam implícitas (sem rotular intervalo/almoço).
 */
final class EixoHorario
{
    /**
     * @param  list<array{inicio:string,fim:string,rotulo:string,turno?:string}>  $periodos
     * @param  array{turno_ids?:list<int>,turnos?:list<string>,entrada?:?string,saida?:?string,faixa?:string}|null  $perfil
     * @return list<array{
     *   tipo:string,inicio:?string,fim:?string,rotulo:string,minutos:?int,turno:?string,
     *   faixa:?string,gap_antes:bool
     * }>
     */
    public static function montar(array $periodos, ?array $perfil = null): array
    {
        $faixa = (string) ($perfil['faixa'] ?? 'todos');
        $turnosFiltro = array_values(array_filter(array_map('strval', (array) ($perfil['turnos'] ?? []))));
        $filtrados = [];
        foreach ($periodos as $p) {
            $turno = (string) ($p['turno'] ?? DiretivasHorario::turnoDe((string) ($p['inicio'] ?? '07:30')));
            if ($faixa === 'matutino' && $turno !== 'matutino') {
                continue;
            }
            if ($faixa === 'vespertino' && $turno !== 'vespertino') {
                continue;
            }
            if ($faixa === 'noturno' && $turno !== 'noturno') {
                continue;
            }
            if ($turnosFiltro !== [] && ! in_array($turno, $turnosFiltro, true)) {
                continue;
            }
            $filtrados[] = array_merge($p, ['turno' => $turno]);
        }
        if ($filtrados === []) {
            $filtrados = $periodos;
        }

        usort($filtrados, static fn ($a, $b) => strcmp((string) $a['inicio'], (string) $b['inicio']));

        $entrada = self::hora((string) ($perfil['entrada'] ?? ($filtrados[0]['inicio'] ?? '07:30')));
        $saida = self::hora((string) ($perfil['saida'] ?? ($filtrados[array_key_last($filtrados)]['fim'] ?? '12:00')));

        $linhas = [];
        $linhas[] = [
            'tipo' => 'entrada',
            'inicio' => $entrada,
            'fim' => null,
            'rotulo' => 'Entrada',
            'minutos' => null,
            'turno' => $filtrados[0]['turno'] ?? null,
            'faixa' => $filtrados[0]['turno'] ?? null,
            'gap_antes' => false,
        ];

        $prevFim = $entrada;
        $prevTurno = $filtrados[0]['turno'] ?? null;
        foreach ($filtrados as $p) {
            $inicio = self::hora((string) $p['inicio']);
            $fim = self::hora((string) $p['fim']);
            $min = self::minutosEntre($inicio, $fim);
            $turno = (string) ($p['turno'] ?? '');
            $gapAntes = self::minutosEntre($prevFim, $inicio) >= 15;
            $mudouFaixa = $prevTurno !== null && $turno !== '' && $turno !== $prevTurno;

            if ($mudouFaixa) {
                $linhas[] = [
                    'tipo' => 'separador',
                    'inicio' => null,
                    'fim' => null,
                    'rotulo' => self::rotuloFaixa($turno),
                    'minutos' => null,
                    'turno' => $turno,
                    'faixa' => $turno,
                    'gap_antes' => true,
                ];
            }

            $linhas[] = [
                'tipo' => 'aula',
                'inicio' => $inicio,
                'fim' => $fim,
                'rotulo' => (string) ($p['rotulo'] ?? ''),
                'minutos' => $min,
                'turno' => $turno,
                'faixa' => $turno,
                'gap_antes' => $gapAntes && ! $mudouFaixa,
            ];
            $prevFim = $fim;
            $prevTurno = $turno !== '' ? $turno : $prevTurno;
        }

        $linhas[] = [
            'tipo' => 'saida',
            'inicio' => null,
            'fim' => $saida,
            'rotulo' => 'Saída',
            'minutos' => null,
            'turno' => $filtrados[array_key_last($filtrados)]['turno'] ?? null,
            'faixa' => $filtrados[array_key_last($filtrados)]['turno'] ?? null,
            'gap_antes' => self::minutosEntre($prevFim, $saida) >= 5,
        ];

        return $linhas;
    }

    /** Minutos padrão de aula a partir das diretivas ou do 1º período. */
    public static function tempoAulaPadrao(): int
    {
        $d = DiretivasHorario::atuais();
        $cfg = (int) ($d['tempo_aula_minutos'] ?? 0);
        if ($cfg >= 20 && $cfg <= 120) {
            return $cfg;
        }
        $periodos = (array) ($d['periodos'] ?? DiretivasHorario::periodosPadrao());
        if ($periodos !== []) {
            $p = $periodos[0];
            $m = self::minutosEntre((string) $p['inicio'], (string) $p['fim']);
            if ($m >= 20) {
                return $m;
            }
        }

        return 50;
    }

    public static function somarMinutos(string $inicio, int $minutos): string
    {
        $inicio = self::hora($inicio);
        if ($inicio === '' || ! preg_match('/^(\d{2}):(\d{2})$/', $inicio, $m)) {
            return $inicio;
        }
        $total = ((int) $m[1] * 60 + (int) $m[2] + max(1, $minutos)) % (24 * 60);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public static function minutosEntre(string $a, string $b): int
    {
        $a = self::hora($a);
        $b = self::hora($b);
        if ($a === '' || $b === '' || ! preg_match('/^\d{2}:\d{2}$/', $a) || ! preg_match('/^\d{2}:\d{2}$/', $b)) {
            return 0;
        }
        [$ah, $am] = array_map('intval', explode(':', $a));
        [$bh, $bm] = array_map('intval', explode(':', $b));

        return max(0, ($bh * 60 + $bm) - ($ah * 60 + $am));
    }

    private static function rotuloFaixa(string $turno): string
    {
        return match ($turno) {
            'matutino' => 'Manhã',
            'vespertino' => 'Tarde',
            'noturno' => 'Noite',
            default => ucfirst($turno),
        };
    }

    private static function hora(string $h): string
    {
        $h = trim($h);
        if (preg_match('/^\d{2}:\d{2}/', $h, $m)) {
            return substr($m[0], 0, 5);
        }

        return $h;
    }
}
