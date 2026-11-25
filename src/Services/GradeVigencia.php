<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use RuntimeException;

/**
 * Ciclo da grade: rascunho editável → vigente (imutável) → arquivada.
 * Mudanças nunca apagam a vigente; abrem nova versão clonando aulas.
 */
final class GradeVigencia
{
    public const string TIPO = 'grade_versao';

    public const string RASCUNHO = 'rascunho';

    public const string VIGENTE = 'vigente';

    public const string ARQUIVADA = 'arquivada';

    /** @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}|null */
    public static function atual(): ?array
    {
        $vigente = null;
        $rascunho = null;
        foreach (PocRegistro::listar(self::TIPO, 200) as $r) {
            $status = (string) ($r['payload']['status'] ?? '');
            if ($status === self::VIGENTE) {
                $vigente = $r;
            }
            if ($status === self::RASCUNHO) {
                $rascunho = $r;
            }
        }

        return $rascunho ?? $vigente;
    }

    /** @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}|null */
    public static function vigente(): ?array
    {
        foreach (array_reverse(PocRegistro::listar(self::TIPO, 200)) as $r) {
            if (($r['payload']['status'] ?? '') === self::VIGENTE) {
                return $r;
            }
        }

        return null;
    }

    /** @return list<int>|null null = legado sem versão (todas as aulas) */
    public static function aulaIdsAtuais(): ?array
    {
        $atual = self::atual();
        if ($atual === null) {
            return null;
        }

        return array_values(array_filter(array_map('intval', (array) ($atual['payload']['aula_ids'] ?? []))));
    }

    public static function bloqueadaParaEdicao(): bool
    {
        $atual = self::atual();
        if ($atual === null) {
            return false;
        }

        return ($atual['payload']['status'] ?? '') === self::VIGENTE;
    }

    public static function assertEditavel(): void
    {
        if (self::bloqueadaParaEdicao()) {
            throw new RuntimeException(
                'A grade vigente está congelada. Abra uma nova versão para alterar — o histórico não é apagado.'
            );
        }
    }

    /**
     * @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}
     */
    public static function garantirRascunho(): array
    {
        $atual = self::atual();
        if ($atual !== null && ($atual['payload']['status'] ?? '') === self::RASCUNHO) {
            return $atual;
        }

        $fonte = QuadroEditor::aulasBrutas();
        $ids = array_map(static fn (array $a): int => (int) $a['id'], $fonte);
        $id = PocRegistro::gravar(self::TIPO, 'Grade rascunho', 'editável', [
            'status' => self::RASCUNHO,
            'aula_ids' => $ids,
            'criada_em' => now()->toIso8601String(),
            'origem_vigente_id' => self::vigente()['id'] ?? null,
        ]);

        return PocRegistro::buscar($id) ?? [
            'id' => $id,
            'tipo' => self::TIPO,
            'titulo' => 'Grade rascunho',
            'meta' => 'editável',
            'payload' => ['status' => self::RASCUNHO, 'aula_ids' => $ids],
            'created_at' => null,
        ];
    }

    /**
     * Clona aulas da vigente para um novo conjunto editável (sem apagar a anterior).
     *
     * @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}
     */
    public static function abrirNovaVersao(): array
    {
        $atual = self::atual();
        if ($atual !== null && ($atual['payload']['status'] ?? '') === self::RASCUNHO) {
            return $atual;
        }

        $vigente = self::vigente();
        $fonteIds = $vigente !== null
            ? array_values(array_filter(array_map('intval', (array) ($vigente['payload']['aula_ids'] ?? []))))
            : array_map(static fn (array $a): int => (int) $a['id'], QuadroEditor::aulasBrutas());

        $novosIds = [];
        foreach ($fonteIds as $aid) {
            $reg = PocRegistro::buscar($aid);
            if ($reg === null || $reg['tipo'] !== QuadroEditor::TIPO) {
                continue;
            }
            $novosIds[] = PocRegistro::gravar(
                QuadroEditor::TIPO,
                $reg['titulo'],
                $reg['meta'],
                array_merge($reg['payload'], [
                    'clonada_de' => $aid,
                    'versao_em' => now()->toIso8601String(),
                ])
            );
        }

        $id = PocRegistro::gravar(self::TIPO, 'Grade rascunho', 'nova versão · editável', [
            'status' => self::RASCUNHO,
            'aula_ids' => $novosIds,
            'criada_em' => now()->toIso8601String(),
            'origem_vigente_id' => $vigente['id'] ?? null,
            'clonadas' => count($novosIds),
        ]);

        return PocRegistro::buscar($id) ?? [
            'id' => $id,
            'tipo' => self::TIPO,
            'titulo' => 'Grade rascunho',
            'meta' => 'nova versão',
            'payload' => ['status' => self::RASCUNHO, 'aula_ids' => $novosIds],
            'created_at' => null,
        ];
    }

    /**
     * @return array{versao_id:int,aulas:int,quadro:int,alocacoes:int,professor_turma:int,horarios:int}
     */
    public static function fazerValer(?int $usuarioId = null): array
    {
        self::garantirRascunho();
        $aulas = QuadroEditor::aulas();
        if ($aulas === []) {
            throw new RuntimeException('Não há aulas na grade para publicar.');
        }
        if (DetectorChoque::detalhar($aulas) !== []) {
            throw new RuntimeException('Existem conflitos de horário. Resolva antes de fazer valer.');
        }

        foreach (PocRegistro::listar(self::TIPO, 200) as $r) {
            if (($r['payload']['status'] ?? '') === self::VIGENTE) {
                $p = $r['payload'];
                $p['status'] = self::ARQUIVADA;
                $p['arquivada_em'] = now()->toIso8601String();
                PocRegistro::atualizar((int) $r['id'], $p, 'Grade arquivada', 'histórico · imutável');
            }
        }

        $propagado = PropagadorGradeNucleo::aplicar($aulas, $usuarioId);
        $ids = array_map(static fn (array $a): int => (int) $a['id'], $aulas);
        $payload = [
            'status' => self::VIGENTE,
            'aula_ids' => $ids,
            'publicada_em' => now()->toIso8601String(),
            'publicada_por' => $usuarioId,
            'nucleo' => $propagado,
            'snapshot' => array_map(static fn (array $a): array => [
                'id' => $a['id'],
                'professor_id' => $a['professor_id'],
                'turma_id' => $a['turma_id'],
                'escola_id' => $a['escola_id'],
                'componente_id' => $a['componente_id'],
                'dia' => $a['dia'],
                'inicio' => $a['inicio'],
                'fim' => $a['fim'],
            ], $aulas),
        ];

        $rascunho = null;
        foreach (PocRegistro::listar(self::TIPO, 200) as $r) {
            if (($r['payload']['status'] ?? '') === self::RASCUNHO) {
                $rascunho = $r;
            }
        }

        if ($rascunho !== null) {
            PocRegistro::atualizar((int) $rascunho['id'], $payload, 'Grade vigente', 'faz valer · imutável');
            $versaoId = (int) $rascunho['id'];
        } else {
            $versaoId = PocRegistro::gravar(self::TIPO, 'Grade vigente', 'faz valer · imutável', $payload);
        }

        return [
            'versao_id' => $versaoId,
            'aulas' => count($aulas),
            'quadro' => (int) ($propagado['quadro'] ?? 0),
            'horarios' => (int) ($propagado['horarios'] ?? 0),
            'alocacoes' => (int) ($propagado['alocacoes'] ?? 0),
            'professor_turma' => (int) ($propagado['professor_turma'] ?? 0),
        ];
    }

    /** @param list<int> $ids */
    public static function sincronizarIdsRascunho(array $ids): void
    {
        $atual = self::atual();
        if ($atual === null || ($atual['payload']['status'] ?? '') !== self::RASCUNHO) {
            return;
        }
        $p = $atual['payload'];
        $p['aula_ids'] = array_values(array_map('intval', $ids));
        PocRegistro::atualizar((int) $atual['id'], $p);
    }

    /** Após alocar uma aula no rascunho, inclui o id. */
    public static function registrarAulaNoRascunho(int $aulaId): void
    {
        if ($aulaId < 1) {
            return;
        }
        $atual = self::atual();
        if ($atual === null || ($atual['payload']['status'] ?? '') !== self::RASCUNHO) {
            return;
        }
        $ids = array_values(array_unique(array_merge(
            array_map('intval', (array) ($atual['payload']['aula_ids'] ?? [])),
            [$aulaId]
        )));
        self::sincronizarIdsRascunho($ids);
    }
}
