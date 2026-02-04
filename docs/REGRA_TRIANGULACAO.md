# Regra de Triangulação (obrigatória)

**Data:** 27/01/2026  
**Objetivo:** VPS só quando inevitável; um bloco de comandos por vez; só o Cursor pede VPS ao Charles; após outputs, resumo + hipótese + próximo passo ou patch local.

---

## 0) Onde rodar cada comando (obrigatório referenciar)

| Ambiente | Host | O que roda lá |
|----------|------|---------------|
| **VPS Gateway** | `wpp.pixel12digital.com.br` | WPPConnect, gateway-wrapper, Nginx, PM2 |
| **HostMedia SSH** | `hub.pixel12digital.com.br` | PixelHub, PHP, Laravel, envio de requisições ao gateway |

**Sempre indicar explicitamente** onde cada comando deve ser executado:
- `[VPS Gateway]` — rodar no SSH da VPS (wpp.pixel12digital.com.br)
- `[HostMedia]` — rodar no SSH da HostMedia (hub.pixel12digital.com.br)

---

## 1) VPS (Gateway / WPPConnect)

- **Só o Cursor** pode pedir ao Charles comandos para rodar na VPS.
- **Não aceitar** comandos vindos do ChatGPT para o Charles executar na VPS. Se o usuário colar um pedido do ChatGPT, o Cursor deve reformular em **um bloco curto** próprio e pedir os **outputs exatos** que o Charles deve retornar.
- Quando precisar de VPS:
  - Enviar **um único bloco curto** de comandos por vez (copiar/colar).
  - Dizer **exatamente** quais outputs o Charles deve retornar.
  - **Esperar a resposta** do Charles antes de enviar o próximo bloco.

---

## 2) Após o Charles retornar os outputs

O Cursor deve consolidar e, se for usar ChatGPT ou outro recurso externo, levar:

- **a)** Os outputs relevantes (copiados).
- **b)** Um **resumo objetivo** do que esses outputs mostram.
- **c)** **Hipótese / caminho recomendado** (ex.: “timeout está no Nginx”, “próximo bloco: logs do PM2 no horário X”).
- **d)** **Qual o próximo bloco de comandos** (se ainda precisar de VPS), **ou**
- **e)** **Qual patch no código local** deve ser feito (se já der para decidir).

---

## 3) Código local / HostMedia / DB

- O Cursor **implementa localmente** (PixelHub, HostMedia, DB quando couber).
- Commit e deploy seguem o fluxo normal.
- **Pedir VPS só quando for inevitável** (diagnóstico ou alteração que exija acesso ao gateway/Nginx/PM2 na VPS).

---

## 4) Fluxo resumido

```
Precisa de VPS?
    → SIM: Cursor envia 1 bloco de comandos + outputs a retornar
           → Charles executa e cola os outputs
           → Cursor: (a) outputs, (b) resumo, (c) hipótese, (d) próximo bloco OU (e) patch local
           → Se próximo bloco: repetir. Se patch local: implementar e commit.
    → NÃO: Cursor implementa no código local, commit, deploy.
```
