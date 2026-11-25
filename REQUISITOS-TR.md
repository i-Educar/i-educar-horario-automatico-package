# Requisitos TR 5.11 — Geração automática de horário

**Edital:** PE 77/2026 Laguna/SC · Anexo I
**Cobertura documental:** **100%** dos 13 itens OCR abaixo.
**PoC:** ≥85% demonstráveis; ≤15% em ≤30 dias.
**Matriz mestre:** `tmp/edital-ocr/MATRIZ-REQUISITOS-TR.md`

## 5.11 — Geração automática de horário (13 itens)

### Checklist 100%

- [x] **5.11.1** — Disponibilidade/indisponibilidade (HTPC/planejamento) + consistência nas diretivas;
- [x] **5.11.2** — Períodos de aula configuráveis (texto por turno) nas diretivas;
- [x] **5.11.3** — Bloqueio duro de professor em horários sobrepostos (alocar/mover + gerador);
- [ ] **5.11.4** — Mais de um professor na mesma componente/turma em turnos distintos (parcial: não força exclusividade de professor único);
- [x] **5.11.5** — Componentes geminados configuráveis nas diretivas;
- [x] **5.11.6** — Geminadas só em períodos consecutivos do mesmo turno;
- [x] **5.11.7** — Distribuição na semana (evitar concentração / máx. por dia);
- [x] **5.11.8** — Limite de aulas semanais a partir da matriz (carga anual→aulas/semana);
- [x] **5.11.9** — Progresso contínuo da geração (barra + log);
- [x] **5.11.10** — Pausa/retomada + edição manual na grade + professor preferencial;
- [x] **5.11.13** — Bloqueio multi-escola no mesmo turno; professor preferencial (5.11.14);
- [x] **5.11.15** — Estado persistido (tipo=geracao) para continuar depois;
- [x] **5.11.16** — Relatórios por turma e por professor (impressão).

## Regra de rastreio

- Todo item coberto por fase no `ROADMAP.md` e tarefa/grupo no `TODO.md`.
- `EPIC.md` declara cobertura 100% via este arquivo.
- Reconciliar IDs com ruído OCR no PDF antes da PoC.
