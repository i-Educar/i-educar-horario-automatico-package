# Requisitos TR 5.11 — Geração automática de horário

**Edital:** PE 77/2026 Laguna/SC · Anexo I
**Cobertura documental:** **100%** dos 13 itens OCR abaixo.
**PoC:** ≥85% demonstráveis; ≤15% em ≤30 dias.
**Matriz mestre:** `tmp/edital-ocr/MATRIZ-REQUISITOS-TR.md`

## 5.11 — Geração automática de horário (13 itens)

### Checklist 100%

- [ ] **5.11.1** — Permitir a definição e parametrização de disponibilidade e indisponibilidade de horários, incluindo hora de planejamento, área de atuação e componente curricular, com controle e consistência das regras aplicadas;
- [ ] **5.11.2** — Permitir o cadastro dos períodos de aula do ano letivo, por modalidade de ensino, etapa e unidade escolar, conforme parametrização;
- [ ] **5.11.3** — Restringir a alocação de um mesmo professor em mais de uma aula no mesmo período, garantindo consistência do quadro de horários;
- [ ] **5.11.4** — Permitir a atribuição de mais de um professor para uma mesma componente curricular e turma, desde que em turnos distintos;
- [ ] **5.11.5** — Realizar o cálculo automático das componentes curriculares que devem ser ministradas de forma geminada ou separada, conforme regras configuradas;
- [ ] **5.11.6** — Evitar que aulas geminadas sejam distribuídas em períodos separados dentro do mesmo turno, ainda que intercaladas por intervalos;
- [ ] **5.11.7** — Distribuir as aulas repetidas ao longo da semana, evitando sua concentração em dias consecutivos ou concomitantes;
- [ ] **5.11.8** — Limitar a quantidade de aulas semanais, tanto totais quanto por componente curricular, conforme a grade curricular definida;
- [ ] **5.11.9** — Permitir a visualização da evolução do processo de geração dos horários, com atualização contínua;
- [ ] **5.11.10** — Permitir a pausa do processo de geração de horários, com visualização do Secretaria da E er Administração e ra Av. Colombo Machado Salles, 145 Centro Histórico / Laguna-SC progresso alcançado, edição manual do quadro, incluindo fixação ou sugestão de professor, dia e período por componente e turma, e retomada da geração com base nas alterações realizadas;
- [ ] **5.11.13** — lmpedir a alocação de um mesmo professor, em um determinado dia e turno, em mais de uma unidade escolar, garantindo consistência e integridade do quadro de horários; 51.14 Permitir a definição de professor preferencial para determinada componente curricular em uma turma;
- [ ] **5.11.15** — Permitir a execução do processo de geração de horários com persistência de estado, possibilitando sua continuidade após saída e novo acesso ao sistema;
- [ ] **5.11.16** — Permitir a emissão de relatórios das grades de horários geradas, com agrupamento por turma, professor e aluno.

## Regra de rastreio

- Todo item coberto por fase no `ROADMAP.md` e tarefa/grupo no `TODO.md`.
- `EPIC.md` declara cobertura 100% via este arquivo.
- Reconciliar IDs com ruído OCR no PDF antes da PoC.
