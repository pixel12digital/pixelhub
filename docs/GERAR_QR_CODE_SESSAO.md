# 🔄 Gerar QR Code para Sessão do WhatsApp Gateway

## Problema
A UI não está gerando QR code para a sessão "imobsites" e você quer conectar a sessão.

---

## Soluções

### Solução 1: Via API do Gateway (Recomendado)

Execute na VPS via SSH ou curl:

```bash
# 1. Obter Gateway Secret (se necessário)
# O secret está no arquivo .htpasswd ou configurado no sistema

# 2. Forçar geração de QR code via API
curl -k -X GET \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/api/channels/imobsites/qr
```

**Substitua `SEU_SECRET_AQUI`** pelo Gateway Secret que você viu na UI (ex: `d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54`)

---

### Solução 2: Via Docker (WPPConnect)

Se o gateway está rodando em Docker:

```bash
# 1. Verificar containers rodando
docker ps | grep wpp

# 2. Ver logs da sessão
docker logs wppconnect-server --tail 50 | grep -i "imobsites"

# 3. Reiniciar a sessão (deletar e recriar)
# Acesse o container ou use a API do gateway
```

---

### Solução 3: Deletar e Recriar Sessão

**⚠️ ATENÇÃO:** Isso vai desconectar a sessão atual. Use apenas se necessário.

```bash
# 1. Deletar sessão existente (se a API suportar)
curl -k -X DELETE \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/api/channels/imobsites

# 2. Recriar a sessão
curl -k -X POST \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  https://wpp.pixel12digital.com.br:8443/api/channels

# 3. Obter QR code da nova sessão
curl -k -X GET \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/api/channels/imobsites/qr
```

---

### Solução 4: Via Interface Web (UI)

1. Acesse: `https://wpp.pixel12digital.com.br:8443/ui/sessoes/imobsites`
2. Clique no botão **"Atualizar QR"** (se disponível)
3. Ou clique em **"Reconectar"** / **"Desconectar e Reconectar"** (se disponível)

---

## Verificar Status da Sessão

Antes de tentar gerar QR, verifique o status:

```bash
curl -k -X GET \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/api/channels/imobsites
```

**Resposta esperada:**
```json
{
  "channel": "imobsites",
  "connection": "disconnected",
  "status": "waiting_qr"
}
```

---

## Obter Gateway Secret

Se você não souber o Gateway Secret:

### Opção 1: Via Interface Web
1. Acesse: `https://wpp.pixel12digital.com.br:8443/ui/sessoes/imobsites`
2. O secret está na seção **"Gateway Secret"**

### Opção 2: Via Arquivo de Configuração (VPS)
```bash
# Verificar variável de ambiente no container
docker exec wppconnect-server env | grep GATEWAY_SECRET

# Ou verificar arquivo de configuração
cat /path/to/gateway/.env | grep GATEWAY_SECRET
```

---

## Comandos Rápidos

### Testar Conexão com Gateway
```bash
curl -k -I \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/api/channels
```

### Listar Todas as Sessões
```bash
curl -k -X GET \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/api/channels
```

### Verificar Health do Gateway
```bash
curl -k -X GET \
  -H "X-Gateway-Secret: SEU_SECRET_AQUI" \
  https://wpp.pixel12digital.com.br:8443/health
```

---

## Troubleshooting

### Erro: "Sessão não encontrada"
- Verifique se o nome da sessão está correto (case-sensitive)
- Liste todas as sessões para verificar nomes disponíveis

### Erro: "QR code não gerado"
- Verifique logs do gateway: `docker logs wppconnect-server --tail 100`
- Tente desconectar e reconectar a sessão
- Verifique se há espaço em disco e recursos disponíveis

### Erro: "Unauthorized" ou "401"
- Verifique se o Gateway Secret está correto
- Confirme que o header `X-Gateway-Secret` está sendo enviado

### QR Code expira muito rápido
- Normal: QR codes expiram em ~20 segundos
- Escaneie rapidamente ou gere novo QR code

---

## Exemplo Completo

```bash
# Definir variáveis
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
BASE_URL="https://wpp.pixel12digital.com.br:8443"

# 1. Verificar status atual
echo "Verificando status da sessão..."
curl -k -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "$BASE_URL/api/channels/$SESSION"

# 2. Gerar QR code
echo -e "\n\nGerando QR code..."
curl -k -X GET \
  -H "X-Gateway-Secret: $SECRET" \
  "$BASE_URL/api/channels/$SESSION/qr"

# 3. A resposta pode conter o QR code em base64 ou URL
# Se vier em base64, você pode salvar e visualizar:
# curl -k -X GET -H "X-Gateway-Secret: $SECRET" "$BASE_URL/api/channels/$SESSION/qr" | jq -r '.qr' | base64 -d > qrcode.png
```

---

## Próximos Passos

Após gerar o QR code:

1. **Escaneie o QR code** com o WhatsApp do celular
   - Abra WhatsApp → Menu (3 pontos) → Aparelhos conectados → Conectar um aparelho
   
2. **Aguarde confirmação** na UI
   - Status deve mudar de "Aguardando QR" para "Conectado"
   
3. **Teste o envio** de uma mensagem via API ou interface

---

**Última atualização:** Janeiro 2026

