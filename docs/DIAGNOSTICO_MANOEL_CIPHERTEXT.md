# Diagnóstico: Mensagens do Manoel (conversa 139)

## Resumo

A mensagem de hoje (05/02) **existe** no banco e **é exibida**, mas o conteúdo aparece como `[Mensagem criptografada]` porque o evento é do tipo **ciphertext**.

## O que é ciphertext?

O WhatsApp Business API (via WPPConnect/Baileys) envia mensagens E2E (end-to-end) criptografadas como `type: "ciphertext"`. O gateway **não recebe o texto descriptografado** — apenas a indicação de que uma mensagem foi enviada/recebida. É uma limitação técnica da plataforma.

## Evidência

Payload do evento `f1bf99b7` (05/02 12:06):

```json
{
  "raw": {
    "payload": {
      "type": "ciphertext",
      "from": "193952250601573@lid",
      "to": "554797146908@c.us",
      ...
    }
  },
  "message": {
    "text": "",
    "from": "193952250601573@lid",
    "to": "554797146908@c.us"
  }
}
```

- `message.text` está vazio
- `raw.payload.type` = `ciphertext`

## Correção aplicada

O backend agora:

1. Considera `payload.raw.payload.type` ao definir o placeholder quando `content` está vazio
2. Exibe `[Mensagem criptografada]` quando o tipo é `ciphertext` (em vez de `[Mídia]` ou `[ciphertext]`)

Assim o usuário entende que a mensagem existe, mas o conteúdo não está disponível por criptografia.

## Script de diagnóstico

```bash
php database/diagnostico-manoel-139.php
```
