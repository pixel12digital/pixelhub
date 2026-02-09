# Plano de Diagnóstico: QR Code não carrega

## Objetivo

Identificar por que o QR code não aparece ao criar sessão ou reconectar, e garantir que funcione corretamente.

---

## Etapa 1: Diagnóstico no HostMedia (executar primeiro)

O Pixel Hub tem um diagnóstico que testa cada etapa do fluxo. **Rodar na HostMedia** (onde o gateway responde 200):

### Via interface (recomendado)

1. Acesse **Configurações > WhatsApp Gateway > Diagnóstico (Debug)**
2. Na seção **"Diagnóstico QR Code"**, use a sessão `imobsites` (ou `pixel12digital`)
3. Clique em **Executar Diagnóstico**
4. Aguarde ~15–20 segundos
5. **Copie o resultado completo** e envie

### Via terminal (alternativa)

```bash
cd hub.pixel12digital.com.br
php scripts/diagnostico_qr_code_gateway.php imobsites
```

**O que o diagnóstico mostra:**
- `listChannels`: Gateway responde? Quantos canais?
- `getQr (1ª)`: Retorna QR? Erro? Status CONNECTED/INITIALIZING?
- `delete`: O gateway suporta DELETE?
- `create`: Create funciona?
- `getQr (após create)`: Retorna QR após criar?
- **Conclusão:** Indica a causa provável

---

## Etapa 2: Verificar Network (navegador)

1. Abra **DevTools** (F12) **antes** de clicar em "Criar sessão"
2. Vá na aba **Network**
3. Clique em **Criar sessão** (ou Reconectar)
4. Aguarde o modal aparecer
5. Localize a requisição **`create`** ou **`reconnect`** (POST)
6. Clique nela e verifique:
   - **Status:** 200? 500? 504?
   - **Tempo:** Quantos segundos?
   - **Response:** Copie o JSON retornado

**O que procurar:**
- **200 com `qr` no body** → O backend retornou QR; o problema está no frontend (exibição).
- **200 sem `qr`** → O gateway não retornou QR; ver Etapa 1.
- **504/Timeout** → Requisição demorou demais; aumentar `set_time_limit` ou otimizar.
- **500** → Erro no backend; verificar logs PHP.

---

## Etapa 3: Logs PHP (HostMedia)

Se houver erro 500 ou comportamento estranho:

```bash
# Últimas linhas do error_log do PHP
tail -100 /caminho/para/hub.pixel12digital.com.br/logs/error.log

# Ou onde o PHP escreve (ex.: /var/log/php-fpm/error.log)
tail -100 /var/log/php*.log
```

Procurar por mensagens como: `[WhatsAppGateway::request]`, `sessionsCreate`, `sessionsReconnect`, exceções.

---

## Etapa 4: Resultados esperados e próximos passos

| Diagnóstico (Etapa 1) | Ação |
|----------------------|------|
| `getQr` retorna **error="Invalid QR code response"** | Aplicar patch VPS: `docs/PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md` |
| `getQr` retorna **raw_status=CONNECTED** sem QR | Mesmo patch acima |
| `delete` retorna **success=false** | Verificar se gateway-wrapper expõe `DELETE /api/channels/{id}` |
| `getQr` retorna **has_qr=true** | Gateway OK; revisar extração no Pixel Hub (`extractQrFromResponse`) |
| `getQr` retorna **raw_status=INITIALIZING** | WPPConnect demorando; aumentar tentativas ou intervalo |

| Network (Etapa 2) | Ação |
|-------------------|------|
| Response tem `qr` mas não exibe | Verificar `showQrModal(data.qr)` e formato do base64 |
| Response sem `qr` e `message` com erro | Corrigir backend ou gateway conforme diagnóstico |
| 504 após ~60s | Aumentar timeout Nginx ou reduzir tempo do backend |

---

## Checklist de verificação

- [ ] Diagnóstico executado na HostMedia (Etapa 1)
- [ ] Resultado do diagnóstico copiado e analisado
- [ ] Network checado (status, tempo, body da resposta)
- [ ] Logs PHP verificados (se houver erro)
- [ ] Patch VPS aplicado (se diagnóstico indicar)
- [ ] Teste novamente criar sessão e reconectar

---

## Referências

- `docs/PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md` — Patch para sessão CONNECTED sem QR
- `docs/GATEWAY_DELETE_CHANNEL_PARA_RESTART_QR.md` — Verificação do endpoint DELETE
- `docs/DIAGNOSTICO_QR_CODE_NAO_GERADO.md` — Interpretação do script de diagnóstico
