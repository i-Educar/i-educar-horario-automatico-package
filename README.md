# Geração automática de horário

Otimizador de quadro de horários docentes com restrições (choques, geminadas, distribuição semanal).

Composer: `buriti/i-educar-horario-automatico-package`

## Para que serve

Reduz o esforço manual de montar horários: propõe alocações viáveis a partir de lotação, turmas, componentes e restrições pedagógicas/operacionais.

## Desenho de funções

| Função | Entrega |
| --- | --- |
| Entrada de restrições | Geminadas, indisponibilidades, limites semanais |
| Solver / otimização | Geração de proposta de quadro |
| Validação de choques | Detecção de conflitos professor/turma/sala |
| Publicação / ajuste | Revisão humana antes de consolidar no horário oficial |

## Quem usa

- Secretaria escolar
- SME (apoio à rede)
- Coordenação pedagógica

## Integrações

- Servidor / lotação
- Turmas e componentes do núcleo
- RH educacional

## Compatibilidade

| Produto | Versão |
| --- | --- |
| **i-Educar** | **2.12.x** |
| **i-Diário** | Portabilis i-Diário (API / SSO do núcleo) |
| Pacote | `0.2.0` |

Declarado em `composer.json` → `extra.ieducar` / `extra.idiario`.

## Documentação

| Documento | Uso |
| --- | --- |
| [`EPIC.md`](./EPIC.md) | Escopo de produto, aceite e critérios |
| [`ROADMAP.md`](./ROADMAP.md) | Fases e entregas |
| [`TODO.md`](./TODO.md) | Backlog operacional |
| [`REQUISITOS-TR.md`](./REQUISITOS-TR.md) | Rastreio de requisitos (planejamento) |

---

Licença: **GPL-2.0-or-later** · Organização [i-Educar](https://github.com/i-Educar)
