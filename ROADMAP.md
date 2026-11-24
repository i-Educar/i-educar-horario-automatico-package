# ROADMAP — Geração Automática de Horário

> Pacote: `buriti/i-educar-horario-automatico-package` · TR **5.11** · Prioridade **P0** · Nota **D**  
> Alinhado a `tmp/DOC-EXECUTIVO-PE-77-2026-LAGUNA-IEDUCAR.md`

## Visão

Solver/otimizador de horário docente com restrições (geminadas, choque, distribuição semanal).

## Fases

### F0 · Parceria/build

Decidir construir vs white-label.

### F1 · Dados

Importar carga, turmas, professores, restrições.

### F2 · Solver MVP

Gerar quadro sem choques básicos.

### F3 · Restrições TR

Geminadas, distribuição semanal, salas.

### F4 · PoC

Demo com escola real/fictícia Laguna.

## Cobertura 100% dos requisitos (TR)

Fonte: [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) — **13 itens**.

Cada lote deve sair da PoC como **atende** ou entrar no plano de 30 dias.

### Lote R1 — itens 5.11.1 … 5.11.16 (13)

- `5.11.1`, `5.11.2`, `5.11.3`, `5.11.4`, `5.11.5`, `5.11.6`, `5.11.7`, `5.11.8`, `5.11.9`, `5.11.10`, `5.11.13`, `5.11.15`, `5.11.16`

- [ ] Implementar / validar / evidenciar na matriz PoC

### Gate 100%

- [ ] Todos os lotes R* com status atende ou gap≤30dias
- [ ] Soma atende ≥ 85% na demonstração

## Conformidade Laravel / pacote i-Educar 2.12

Antes da fase de código:

1. Criar `composer.json` com `php >=8.3`, `illuminate/support`, provider em `extra.laravel`, `compatible: ["2.12"]`.
2. Scaffold: `src/Providers`, `Http/Controllers`, `Services`, `Support`, `database/migrations`, `routes/web.php`, views Blade.
3. Schema `horario_automatico` (ou estratégia documentada); FKs lógicas para núcleo (`ref_cod_aluno`, `ref_cod_escola`, etc.).
4. Menu/processos em migration própria; respeitar multi-tenant.
5. Não acoplar UI mobile; dados para lojas só via ``buriti/i-educar-apps-api-package``.
6. Seguir `packages/buriti/PADRAO-PACOTE-2.12.md`.

## Dependências

- Cadastro mestre de aluno/escola/turma no i-Educar (SGE).
- Matriz item a item OCR do Anexo I antes de cravar %.
- Alinhamento com `i-educar-apps-api-package` (APIs 5.13/5.14; UI/lojas em equipe externa) e exports (4.x) quando o módulo expõe dados ao cidadão/SME.

## Riscos

- Valor global do edital incompatível com greenfield total — reuso e MVP demonstrável são obrigatórios.
- PDF escaneado: numerações OCR podem exigir reconciliação manual.
- PoC só conta o que estiver operacional no dia.

## Definição de pronto da fase PoC

Checklist do `TODO.md` marcado para a fatia MVP + roteiro de demonstração ensaiado.
