<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use RuntimeException;

/**
 * Persistência e montagem do quadro (aulas) com validação dura de choques.
 */
final class QuadroEditor
{
    public const string TIPO = 'aula';

    public const string REGENTE_TURMA = 'regente_turma';

    public const string REGENTE_AULAS = 'regente_aulas';

    /** @return list<array{inicio:string,fim:string,rotulo:string,turno?:string}> */
    public static function periodosPadrao(): array
    {
        $d = DiretivasHorario::atuais();
        $periodos = (array) ($d['periodos'] ?? []);
        if ($periodos !== []) {
            return array_values($periodos);
        }

        return DiretivasHorario::periodosPadrao();
    }

    /**
     * @return list<array{
     *   id:int,professor_id:int,professor:string,cargo:string,turma_id:?int,turma:string,
     *   escola_id:?int,componente_id:?int,componente:string,dia:int,inicio:string,fim:string,
     *   regencia:?string,cor:?string,geminada:bool
     * }>
     */
    /** Todas as aulas brutas (ignora filtro de versão). */
    public static function aulasBrutas(): array
    {
        return self::mapearLista(PocRegistro::listar(self::TIPO, 5000));
    }

    public static function aulas(): array
    {
        $todas = self::aulasBrutas();
        $filtro = GradeVigencia::aulaIdsAtuais();
        if ($filtro === null) {
            return $todas;
        }
        if ($filtro === []) {
            return [];
        }
        $set = array_fill_keys($filtro, true);

        return array_values(array_filter($todas, static fn (array $a): bool => isset($set[(int) $a['id']])));
    }

    /**
     * @param  list<array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>}>  $regs
     * @return list<array<string,mixed>>
     */
    private static function mapearLista(array $regs): array
    {
        $out = [];
        foreach ($regs as $r) {
            $p = $r['payload'];
            $out[] = [
                'id' => (int) $r['id'],
                'professor_id' => (int) ($p['professor_id'] ?? 0),
                'professor' => (string) ($p['professor'] ?? '—'),
                'cargo' => (string) ($p['cargo'] ?? 'Professor'),
                'turma_id' => isset($p['turma_id']) ? (int) $p['turma_id'] : null,
                'turma' => (string) ($p['turma'] ?? '—'),
                'escola_id' => isset($p['escola_id']) ? (int) $p['escola_id'] : null,
                'componente_id' => isset($p['componente_id']) ? (int) $p['componente_id'] : null,
                'componente' => (string) ($p['componente'] ?? $r['titulo']),
                'dia' => (int) ($p['dia'] ?? 1),
                'inicio' => self::hora((string) ($p['inicio'] ?? '07:30')),
                'fim' => self::hora((string) ($p['fim'] ?? '08:20')),
                'regencia' => isset($p['regencia']) ? (string) $p['regencia'] : null,
                'cor' => isset($p['cor']) ? (string) $p['cor'] : null,
                'geminada' => (bool) ($p['geminada'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $dados
     */
    public static function alocar(array $dados): int
    {
        GradeVigencia::assertEditavel();
        GradeVigencia::garantirRascunho();
        $professor = trim((string) ($dados['professor'] ?? ''));
        $turma = trim((string) ($dados['turma'] ?? ''));
        $componente = trim((string) ($dados['componente'] ?? ''));
        $dia = max(1, min(5, (int) ($dados['dia'] ?? 1)));
        $inicio = self::hora((string) ($dados['inicio'] ?? ''));
        $fim = self::hora((string) ($dados['fim'] ?? ''));
        $tempoAula = (int) ($dados['tempo_aula'] ?? 0);
        if ($tempoAula < 20 || $tempoAula > 120) {
            $compIdTmp = isset($dados['componente_id']) ? (int) $dados['componente_id'] : 0;
            $turmaIdTmp = isset($dados['turma_id']) ? (int) $dados['turma_id'] : 0;
            if ($turmaIdTmp > 0 && $compIdTmp > 0) {
                $tempos = MatrizAjuste::obterTempos($turmaIdTmp);
                $tempoAula = (int) ($tempos[$compIdTmp] ?? 0);
            }
            if ($tempoAula < 20) {
                $tempoAula = EixoHorario::tempoAulaPadrao();
            }
        }
        if ($inicio !== '' && ($fim === '' || $fim <= $inicio)) {
            $fim = EixoHorario::somarMinutos($inicio, $tempoAula);
        }

        if ($professor === '' || $componente === '' || $turma === '' || $inicio === '' || $fim === '') {
            throw new RuntimeException('Professor, turma, componente e horário são obrigatórios.');
        }
        if ($inicio >= $fim) {
            throw new RuntimeException('Horário inválido: início deve ser antes do fim.');
        }

        $professorId = (int) ($dados['professor_id'] ?? 0);
        if ($professorId < 1) {
            $professorId = abs(crc32($professor)) % 100000;
        }

        $escolaId = isset($dados['escola_id']) ? (int) $dados['escola_id'] : null;
        if (($escolaId === null || $escolaId < 1) && ! empty($dados['turma_id'])) {
            $escolaId = MatrizCurricular::escolaDaTurma((int) $dados['turma_id']);
        }

        $candidata = [
            'professor_id' => $professorId,
            'turma_id' => isset($dados['turma_id']) ? (int) $dados['turma_id'] : null,
            'escola_id' => $escolaId,
            'componente_id' => isset($dados['componente_id']) ? (int) $dados['componente_id'] : null,
            'dia' => $dia,
            'inicio' => $inicio,
            'fim' => $fim,
        ];

        $validar = (bool) ($dados['validar_choque'] ?? true);
        if ($validar && (bool) config('horario.detectar_choques', true)) {
            $erro = DetectorChoque::conflitoCom($candidata, self::aulas());
            if ($erro !== null) {
                throw new RuntimeException($erro);
            }
        }

        $payload = [
            'professor_id' => $professorId,
            'professor' => $professor,
            'cargo' => (string) ($dados['cargo'] ?? 'Professor'),
            'turma_id' => isset($dados['turma_id']) ? (int) $dados['turma_id'] : null,
            'turma' => $turma,
            'escola_id' => $escolaId,
            'componente_id' => isset($dados['componente_id']) ? (int) $dados['componente_id'] : null,
            'componente' => $componente,
            'dia' => $dia,
            'inicio' => $inicio,
            'fim' => $fim,
            'regencia' => $dados['regencia'] ?? null,
            'cor' => $dados['cor'] ?? null,
            'geminada' => (bool) ($dados['geminada'] ?? false),
            'tempo_aula' => $tempoAula,
        ];

        $id = PocRegistro::gravar(
            self::TIPO,
            $componente.' · '.$turma,
            $professor.' · dia '.$dia,
            $payload
        );
        GradeVigencia::registrarAulaNoRascunho($id);

        return $id;
    }

    public static function mover(int $id, int $dia, string $inicio, string $fim): void
    {
        GradeVigencia::assertEditavel();
        $reg = PocRegistro::buscar($id);
        if ($reg === null || $reg['tipo'] !== self::TIPO) {
            throw new RuntimeException("Aula #{$id} não encontrada.");
        }
        $dia = max(1, min(5, $dia));
        $inicio = self::hora($inicio);
        $fim = self::hora($fim);
        if ($inicio === '' || $fim === '' || $inicio >= $fim) {
            throw new RuntimeException('Horário inválido.');
        }
        $p = $reg['payload'];
        $candidata = [
            'id' => $id,
            'professor_id' => (int) ($p['professor_id'] ?? 0),
            'turma_id' => isset($p['turma_id']) ? (int) $p['turma_id'] : null,
            'escola_id' => isset($p['escola_id']) ? (int) $p['escola_id'] : null,
            'dia' => $dia,
            'inicio' => $inicio,
            'fim' => $fim,
        ];
        if ((bool) config('horario.detectar_choques', true)) {
            $outras = array_values(array_filter(self::aulas(), static fn (array $a): bool => (int) $a['id'] !== $id));
            $erro = DetectorChoque::conflitoCom($candidata, $outras);
            if ($erro !== null) {
                throw new RuntimeException($erro);
            }
        }
        $p['dia'] = $dia;
        $p['inicio'] = $inicio;
        $p['fim'] = $fim;
        PocRegistro::atualizar($id, $p, null, ($p['professor'] ?? '').' · dia '.$dia);
    }

    public static function apagar(int $id): void
    {
        GradeVigencia::assertEditavel();
        if (! PocRegistro::apagar($id)) {
            throw new RuntimeException("Não foi possível remover a aula #{$id}.");
        }
    }

    /**
     * @param  list<array{id:int,professor_id:int,dia:int,inicio:string,fim:string}>  $aulas
     * @return array<int,bool>
     */
    public static function idsEmChoque(array $aulas): array
    {
        $ids = [];
        foreach (DetectorChoque::detalhar($aulas) as $c) {
            if (isset($aulas[$c['i']]['id'])) {
                $ids[(int) $aulas[$c['i']]['id']] = true;
            }
            if (isset($aulas[$c['j']]['id'])) {
                $ids[(int) $aulas[$c['j']]['id']] = true;
            }
        }

        return $ids;
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
