# Diagnóstico: QR Code não gerado

## Objetivo

Identificar por que o QR code não aparece no Pixel Hub ao criar sessão ou reconectar.

## Passo 1: Rodar script de diagnóstico na HostMedia

```bash
cd /caminho/para/hub.pixel12digital.com.br
php scripts/diagnostico_qr_code_gateway.php pixel12digital
```

Substitua `pixel12digital` pelo nome da sessão que está testando.

## O que o script testa

1. **GET /api/channels** — lista sessões; verifica se a sessão existe
2. **GET /api/channels/{id}/qr** — 1ª tentativa de obter QR
3. **DELETE /api/channels/{id}** — remove sessão
4. **POST /api/channels** — cria sessão novamente
5. **GET /api/channels/{id}/qr** — 2ª tentativa após create

## Interpretar resultados

| Sintoma | Causa provável | Ação |
|--------|-----------------|------|
| `error="WPPConnect getQRCode failed: Invalid QR code response"` | Gateway retorna 500; sessão zombie | Aplicar patch `docs/PACOTE_VPS_PATCH_GETQRCODE_JSON_CONNECTED.md` na VPS |
| `raw.status=CONNECTED` sem QR | WPPConnect diz conectado mas dispositivo desconectado | Mesmo patch acima |
| `success=false` em DELETE | Gateway não expõe DELETE | Verificar se gateway-wrapper tem rota DELETE |
| `raw.status=INITIALIZING` | WPPConnect demorando para gerar QR | Aumentar tentativas ou intervalo no Pixel Hub |
| `error="Gateway retornou 401"` | Secret incorreto ou não configurado | Verificar WPP_GATEWAY_SECRET no .env |
| `error="Timeout"` | HostMedia não alcança o gateway | Verificar rede, firewall, URL do gateway |

## Passo 2: Retornar saída

Copie a saída completa do script e envie para análise. Exemplo:

```
=== Diagnóstico QR Code - Gateway ===
Sessão: pixel12digital
...
=== Conclusão ===
...
```
