<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

/**
 * Diretivas da geração automática — regras da rede municipal.
 * Persistidas em poc_registro (tipo=diretiva), uma configuração ativa.
 */
final class DiretivasHorario
{
    public const string TIPO = 'diretiva';

    /**
     * @return array{
     *   ano:int,
     *   escola_ids:list<int>,
     *   periodos:list<array{inicio:string,fim:string,rotulo:string,turno:string}>,
     *   geminadas:list<int>,
     *   separar_dias:bool,
     *   max_aulas_mesmo_dia:int,
     *   bloquear_multi_escola_turno:bool,
     *   bloquear_choque_turma:bool,
     *   bloquear_choque_professor:bool,
     *   preferenciais:list<array{turma_id:int,componente_id:int,professor_id:int}>,
     *   indisponibilidades:list<array{professor_id:int,dia:int,inicio:string,fim:string,motivo:?string}>,
     *   carga_padrao_aulas:int,
     *   semanas_letivas:int,
     *   notas:?string
     * }
     */
    public static function padrao(): array
    {
        return [
            'ano' => (int) date('Y'),
            'escola_ids' => [],
            'periodos' => self::periodosPadrao(),
            'geminadas' => [],
            'separar_dias' => true,
            'max_aulas_mesmo_dia' => 2,
            'bloquear_multi_escola_turno' => true,
            'bloquear_choque_turma' => true,
            'bloquear_choque_professor' => true,
            'preferenciais' => [],
            'indisponibilidades' => [],
            'carga_padrao_aulas' => 1,
            'tempo_aula_minutos' => 50,
            'semanas_letivas' => 40,
            'notas' => null,
        ];
    }

    /** @return list<array{inicio:string,fim:string,rotulo:string,turno:string}> */
    public static function periodosPadrao(): array
    {
        return [
            ['inicio' => '07:30', 'fim' => '08:20', 'rotulo' => '1ª', 'turno' => 'matutino'],
            ['inicio' => '08:20', 'fim' => '09:10', 'rotulo' => '2ª', 'turno' => 'matutino'],
            ['inicio' => '09:30', 'fim' => '10:20', 'rotulo' => '3ª', 'turno' => 'matutino'],
            ['inicio' => '10:20', 'fim' => '11:10', 'rotulo' => '4ª', 'turno' => 'matutino'],
            ['inicio' => '11:10', 'fim' => '12:00', 'rotulo' => '5ª', 'turno' => 'matutino'],
            ['inicio' => '13:30', 'fim' => '14:20', 'rotulo' => '6ª', 'turno' => 'vespertino'],
            ['inicio' => '14:20', 'fim' => '15:10', 'rotulo' => '7ª', 'turno' => 'vespertino'],
            ['inicio' => '15:30', 'fim' => '16:20', 'rotulo' => '8ª', 'turno' => 'vespertino'],
            ['inicio' => '16:20', 'fim' => '17:10', 'rotulo' => '9ª', 'turno' => 'vespertino'],
        ];
    }

    /** @return array<string,mixed> */
    public static function atuais(): array
    {
        $reg = PocRegistro::ultimo(self::TIPO);
        if ($reg === null) {
            return self::padrao();
        }
        $payload = $reg['payload'] ?? [];

        return array_replace_recursive(self::padrao(), is_array($payload) ? $payload : []);
    }

    public static function idAtual(): ?int
    {
        return PocRegistro::ultimo(self::TIPO)['id'] ?? null;
    }

    /**
     * @param  array<string,mixed>  $dados
     */
    public static function salvar(array $dados): int
    {
        $base = self::padrao();
        $payload = array_replace_recursive($base, $dados);

        $payload['ano'] = (int) ($payload['ano'] ?: date('Y'));
        $payload['escola_ids'] = array_values(array_unique(array_map('intval', (array) ($payload['escola_ids'] ?? []))));
        $payload['geminadas'] = array_values(array_unique(array_map('intval', (array) ($payload['geminadas'] ?? []))));
        $payload['separar_dias'] = (bool) ($payload['separar_dias'] ?? true);
        $payload['max_aulas_mesmo_dia'] = max(1, min(5, (int) ($payload['max_aulas_mesmo_dia'] ?? 2)));
        $payload['bloquear_multi_escola_turno'] = (bool) ($payload['bloquear_multi_escola_turno'] ?? true);
        $payload['bloquear_choque_turma'] = (bool) ($payload['bloquear_choque_turma'] ?? true);
        $payload['bloquear_choque_professor'] = (bool) ($payload['bloquear_choque_professor'] ?? true);
        $payload['carga_padrao_aulas'] = max(1, min(10, (int) ($payload['carga_padrao_aulas'] ?? 1)));
        $payload['tempo_aula_minutos'] = max(20, min(120, (int) ($payload['tempo_aula_minutos'] ?? 50)));
        $payload['semanas_letivas'] = max(20, min(50, (int) ($payload['semanas_letivas'] ?? 40)));
        $payload['preferenciais'] = array_values(array_filter(array_map(static function ($p): ?array {
            $turma = (int) ($p['turma_id'] ?? 0);
            $comp = (int) ($p['componente_id'] ?? 0);
            $prof = (int) ($p['professor_id'] ?? 0);
            if ($turma < 1 || $comp < 1 || $prof < 1) {
                return null;
            }

            return ['turma_id' => $turma, 'componente_id' => $comp, 'professor_id' => $prof];
        }, (array) ($payload['preferenciais'] ?? []))));
        $payload['indisponibilidades'] = array_values(array_filter(array_map(static function ($i): ?array {
            $prof = (int) ($i['professor_id'] ?? 0);
            $dia = (int) ($i['dia'] ?? 0);
            $inicio = (string) ($i['inicio'] ?? '');
            $fim = (string) ($i['fim'] ?? '');
            if ($prof < 1 || $dia < 1 || $dia > 5 || $inicio === '' || $fim === '') {
                return null;
            }

            return [
                'professor_id' => $prof,
                'dia' => $dia,
                'inicio' => $inicio,
                'fim' => $fim,
                'motivo' => isset($i['motivo']) ? (string) $i['motivo'] : null,
            ];
        }, (array) ($payload['indisponibilidades'] ?? []))));

        if (! is_array($payload['periodos']) || $payload['periodos'] === []) {
            $payload['periodos'] = self::periodosPadrao();
        }

        $titulo = 'Diretivas '.$payload['ano'];
        $meta = count($payload['escola_ids']).' escola(s) · '.count($payload['geminadas']).' geminada(s)';

        $id = self::idAtual();
        if ($id !== null) {
            PocRegistro::atualizar($id, $payload, $titulo, $meta);

            return $id;
        }

        return PocRegistro::gravar(self::TIPO, $titulo, $meta, $payload);
    }

    public static function turnoDe(string $inicio): string
    {
        $h = (int) substr($inicio, 0, 2);

        return $h < 12 ? 'matutino' : 'vespertino';
    }
}
