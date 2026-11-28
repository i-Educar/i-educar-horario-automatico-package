# EPIC — Geração Automática de Horário

| Campo | Valor |
| --- | --- |
| Pacote | `buriti/i-educar-horario-automatico-package` |
| TR Laguna | **5.11** |
| Prioridade | **P0** |
| Nota atual (executivo) | **D** |
| Fonte | `packages/buriti/docs/laguna/DOC-EXECUTIVO-PE-77-2026-LAGUNA-IEDUCAR.md` |
| Regra de ouro | Pregão Eletrônico 77/2026 · Laguna/SC · PoC ≥ 85% por módulo · gaps ≤ 30 dias |
| Base atual | Quadro de horário apenas manual no core |

## Objetivo

Solver/otimizador de horário docente com restrições (geminadas, choque, distribuição semanal).

## Padrão técnico (i-Educar 2.12)

| Item | Valor |
| --- | --- |
| Alvo | i-Educar **2.12** · PHP **≥ 8.3** · Illuminate **^11|^12|^13** (Laravel 13 no app) |
| Composer | `buriti/i-educar-horario-automatico-package` |
| Namespace | `iEducar\Packages\BuritiHorarioAutomatico\` → `src/` |
| Compat | `extra.ieducar.compatible: ["2.12"]` · GPL-2.0-or-later · `type: library` |
| Schema / dados | horario_automatico |
| Rotas | prefixo `horario-automatico`; SME com middleware `ieducar.*` + `auth`; API apps via Sanctum quando exposto |
| Tenancy | `PackageTenant::allows('buriti/i-educar-horario-automatico-package')` antes de migrations/menu |
| Plug-and-play | path em `packages/buriti/…`; manter em `ignore` até instalável |
| Referência | `packages/buriti/PADRAO-PACOTE-2.12.md` |

**Nota de arquitetura:** Lê turmas/servidores do núcleo; grava proposta de quadro no schema do pacote.

## Cobertura 100% do TR

Este EPIC cobre **todos** os requisitos do módulo(s) 5.11 listados em [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) (13 itens OCR).

- PoC: ≥ **85%** demonstráveis e operacionais.
- Até **15%** restante: produção em ≤ **30 dias**, sem ônus.
- Nenhum item do `REQUISITOS-TR.md` pode ficar fora do `ROADMAP.md` / `TODO.md` (direto ou por grupo rastreável).

## Épicos filhos

1. Modelagem de restrições (disponibilidade, geminadas, salas, turmas).
2. Motor de otimização (heurística/ILP) com relatório de inviabilidade.
3. UX de geração, ajuste manual e publicação do quadro.
4. Integração com lotação/servidor e diário.

## Critérios de aceite (PoC)

- [ ] Checklist [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) com **100%** dos itens classificados (atende / gap / N/A)
- [ ] Itens **atende** demonstráveis na PoC (≥ 85% do módulo 5.11)
- [ ] Itens **gap** ≤ 15% com plano ≤ 30 dias em produção, sem ônus
- [ ] Nenhum requisito do TR omitido da matriz


## Fora de escopo deste EPIC

- Orçamento comercial da proposta Laguna.
- Substituição do core i-Educar fora do domínio do módulo.
