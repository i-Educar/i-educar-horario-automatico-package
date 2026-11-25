<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PocRegistro
{
    public const string TABELA = 'horario_automatico.poc_registro';

    public static function disponivel(): bool
    {
        return Schema::hasTable(self::TABELA);
    }

    /**
     * @return list<array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}>
     */
    public static function listar(string $tipo, int $limite = 2000): array
    {
        if (! self::disponivel()) {
            return [];
        }

        return DB::table(self::TABELA)
            ->where('tipo', $tipo)
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->map(static fn ($r): array => self::mapear($r))
            ->all();
    }

    /** @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}|null */
    public static function buscar(int $id): ?array
    {
        if (! self::disponivel() || $id < 1) {
            return null;
        }
        $r = DB::table(self::TABELA)->where('id', $id)->first();

        return $r ? self::mapear($r) : null;
    }

    public static function contar(string $tipo): int
    {
        if (! self::disponivel()) {
            return 0;
        }

        return (int) DB::table(self::TABELA)->where('tipo', $tipo)->count();
    }

    /** Último registro do tipo (diretiva / geração). */
    public static function ultimo(string $tipo): ?array
    {
        if (! self::disponivel()) {
            return null;
        }
        $r = DB::table(self::TABELA)->where('tipo', $tipo)->orderByDesc('id')->first();

        return $r ? self::mapear($r) : null;
    }

    /** @param array<string,mixed> $payload */
    public static function gravar(string $tipo, string $titulo, ?string $meta, array $payload): int
    {
        if (! self::disponivel()) {
            return 0;
        }

        return (int) DB::table(self::TABELA)->insertGetId([
            'tipo' => $tipo,
            'titulo' => mb_substr($titulo, 0, 180),
            'meta' => $meta !== null ? mb_substr($meta, 0, 180) : null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $payload */
    public static function atualizar(int $id, array $payload, ?string $titulo = null, ?string $meta = null): bool
    {
        if (! self::disponivel() || $id < 1) {
            return false;
        }
        $dados = ['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)];
        if ($titulo !== null) {
            $dados['titulo'] = mb_substr($titulo, 0, 180);
        }
        if ($meta !== null) {
            $dados['meta'] = mb_substr($meta, 0, 180);
        }

        return DB::table(self::TABELA)->where('id', $id)->update($dados) >= 0;
    }

    public static function apagar(int $id): bool
    {
        if (! self::disponivel() || $id < 1) {
            return false;
        }

        return DB::table(self::TABELA)->where('id', $id)->delete() > 0;
    }

    public static function apagarPorTipo(string $tipo): int
    {
        if (! self::disponivel() || $tipo === '') {
            return 0;
        }

        return (int) DB::table(self::TABELA)->where('tipo', $tipo)->delete();
    }

    /** @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string} */
    private static function mapear(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'tipo' => (string) $r->tipo,
            'titulo' => (string) $r->titulo,
            'meta' => $r->meta !== null ? (string) $r->meta : null,
            'payload' => is_string($r->payload) ? (json_decode($r->payload, true) ?: []) : (array) ($r->payload ?? []),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ];
    }
}
