# Guia de Investigação do Gateway

## 📋 Instruções Gerais

Execute os scripts **um por um** no VPS do gateway e me envie a saída completa de cada um.

## 📁 Scripts Disponíveis

### Script 01: Verificar Configuração do Webhook
**Arquivo:** `gateway-investigation-01-check-webhook-config.sh`

**O que faz:**
- Verifica arquivos de configuração do gateway
- Procura configurações de webhook
- Verifica processos em execução
- Verifica logs básicos

**Como executar:**
```bash
cd /caminho/do/gateway  # Ajuste conforme necessário
bash database/gateway-investigation-01-check-webhook-config.sh
```

**Ou se estiver no diretório do projeto:**
```bash
bash database/gateway-investigation-01-check-webhook-config.sh
```

---

### Script 02: Verificar Webhook via API
**Arquivo:** `gateway-investigation-02-check-webhook-api.sh`

**O que faz:**
- Testa conectividade com o gateway
- Verifica endpoints de webhook via API
- Verifica canais configurados

**Como executar:**
```bash
# Ajuste as variáveis se necessário
export GATEWAY_BASE_URL="http://localhost:8080"  # Ajuste conforme necessário
export API_TOKEN="seu-token-aqui"  # Se necessário

bash database/gateway-investigation-02-check-webhook-api.sh
```

---

### Script 03: Verificar Logs do Gateway
**Arquivo:** `gateway-investigation-03-check-webhook-logs.sh`

**O que faz:**
- Procura arquivos de log
- Verifica logs recentes relacionados a webhook
- Verifica erros recentes
- Verifica tentativas de envio de webhook

**Como executar:**
```bash
# Ajuste o diretório se necessário
export GATEWAY_DIR="/opt/wpp-gateway"  # Ajuste conforme necessário

bash database/gateway-investigation-03-check-webhook-logs.sh
```

---

### Script 04: Testar Webhook Manualmente
**Arquivo:** `gateway-investigation-04-test-webhook-manually.sh`

**O que faz:**
- Testa conectividade com o webhook
- Envia payload de teste para o webhook
- Verifica resposta do webhook
- Verifica DNS e resolução de domínio

**Como executar:**
```bash
# Ajuste a URL do webhook se necessário
export WEBHOOK_URL="https://painel.pixel12digital.com.br/api/whatsapp/webhook"

bash database/gateway-investigation-04-test-webhook-manually.sh
```

---

### Script 05: Verificar Status do Gateway
**Arquivo:** `gateway-investigation-05-check-gateway-status.sh`

**O que faz:**
- Verifica processos do gateway
- Verifica status do serviço (systemd)
- Verifica portas em uso
- Verifica uso de recursos (CPU, memória)
- Verifica espaço em disco

**Como executar:**
```bash
bash database/gateway-investigation-05-check-gateway-status.sh
```

---

## 🔄 Ordem de Execução

Execute na seguinte ordem:

1. **Script 01** → Verificar configuração
2. **Script 02** → Verificar API
3. **Script 03** → Verificar logs
4. **Script 04** → Testar webhook
5. **Script 05** → Verificar status

## 📤 Enviando Resultados

Após executar cada script:
1. Copie **TODA** a saída (incluindo mensagens de erro)
2. Envie para mim
3. Aguarde minha análise antes de executar o próximo

## ⚙️ Ajustes Necessários

Antes de executar, você pode precisar ajustar:

- **GATEWAY_DIR**: Diretório onde o gateway está instalado
- **GATEWAY_BASE_URL**: URL base da API do gateway
- **API_TOKEN**: Token de autenticação (se necessário)
- **WEBHOOK_URL**: URL completa do webhook

## 🔍 O que Estamos Procurando

1. **Configuração do webhook**: Se está configurado corretamente
2. **Conectividade**: Se o gateway consegue acessar o webhook
3. **Logs**: Se há erros ou tentativas de envio
4. **Status**: Se o gateway está funcionando corretamente

## ❓ Problemas Comuns

- **"Permission denied"**: Execute com `bash` ou dê permissão: `chmod +x script.sh`
- **"Command not found"**: Instale as dependências (curl, jq, etc.)
- **"No such file"**: Ajuste os caminhos nas variáveis de ambiente

---

**Comece pelo Script 01 e me envie a saída!** 🚀

