# Correção: Identificação da Origem do "channel_id": "Pixel12 Digital"

## Objetivo
Identificar exatamente de onde sai `"channel_id": "Pixel12 Digital"` no JSON de erro e corrigir a causa raiz (escopo/permite canal para tenant 25), preservando a lógica atual.

## Implementações Realizadas

### 1. Logs Inequívocos para Provar que Produção Está Rodando o Código Certo

**Localização:** `src/Controllers/CommunicationHubController.php`, método `send()`, início do método

**Implementação:**
- Adicionado log com `__FILE__` e `__LINE__` no primeiro statement do handler
- Adicionado stamp fixo: `SEND_HANDLER_STAMP=15a1023`
- Logs aparecem imediatamente no início do método, antes de qualquer processamento

**Como verificar:**
- Procurar no log do servidor por `SEND_HANDLER_STAMP=15a1023`
- Se o stamp aparecer no mesmo request que retorna 400, confirma que o código certo está rodando
- Se o stamp NÃO aparecer, pode indicar que a rota está apontando para outro controller ou a produção não recebeu o deploy correto

### 2. Trace da Origem do "channel_id" do JSON de Erro

**Localização:** `src/Controllers/CommunicationHubController.php`, método `send()`, após ler `$_POST`

**Logs adicionados:**
- `raw $_POST['channel_id']` - valor bruto do POST
- `trim($_POST['channel_id'])` - valor após trim
- `tenant_id recebido` - tenant_id do POST
- `thread_id recebido` - thread_id do POST
- `originalChannelIdFromPost` - valor preservado do POST

**Logs após resolução/fallback:**
- Valor final de `$channelId`
- Valor de `$originalChannelIdFromPost`
- Valor de `$sessionId` (se existir)
- Se carregou um registro de canal, loga:
  - `channel.id`
  - `channel.channel_id/slug`
  - `channel.name` (se disponível)
  - `channel.tenant_id`

**Tags exclusivos por ponto de retorno:**
- `RETURN_POINT=A` - Erro quando validação do sessionId da thread falha
- `RETURN_POINT=B` - Erro quando canal do banco não passa na validação
- `RETURN_POINT=C` - Erro quando canal do banco não passa na validação e não tem session_id
- `RETURN_POINT=D` - Erro quando nenhum canal é encontrado no banco

**Em cada retorno CHANNEL_NOT_FOUND, antes do `$this->json(...)`, loga:**
- Qual variável está sendo usada para preencher `channel_id` no response (e seu valor)
- Origem da variável (originalChannelIdFromPost, channelId, sessionId, etc.)
- Valores de todas as variáveis relevantes

### 3. Validação da Causa Raiz: Escopo do Canal por Tenant

**Estrutura atual da tabela `tenant_message_channels`:**
- `tenant_id INT UNSIGNED NOT NULL` - FK para tenants
- `provider VARCHAR(50) NOT NULL DEFAULT 'wpp_gateway'`
- `channel_id VARCHAR(100) NOT NULL`
- `is_enabled BOOLEAN NOT NULL DEFAULT TRUE`
- Constraint UNIQUE: `(tenant_id, provider)` - cada tenant pode ter apenas um canal por provider

**Problema identificado:**
- A constraint UNIQUE em `(tenant_id, provider)` impede que múltiplos tenants compartilhem o mesmo canal diretamente
- Cada tenant precisa ter seu próprio registro na tabela
- O canal "pixel12digital" precisa estar vinculado ao tenant 25

**Solução implementada:**
- O método `validateGatewaySessionId()` já tenta buscar canais sem filtro de tenant primeiro (linha 4977-5028)
- Se não encontrar com tenant_id específico, tenta sem filtro (canais compartilhados)
- Mas como a tabela não permite `tenant_id NULL`, a solução é garantir que o tenant 25 tenha um registro próprio

**Ações necessárias:**
1. Executar o script `database/fix-tenant-25-channel.php` para garantir o vínculo do tenant 25 com o canal pixel12digital
2. Verificar se o canal está habilitado (`is_enabled = 1`)
3. Verificar se o `channel_id` no banco corresponde ao que está sendo enviado no POST (considerando normalização de espaços e case)

### 4. Correção do Response "channel_id" (Depois de Resolver o Envio)

**Status:** Pendente - será implementado após confirmar que o envio está funcionando

**Plano:**
- Ajustar o response para sempre usar `originalChannelIdFromPost` (slug)
- Colocar o nome amigável em outro campo (ex.: `channel_label`)
- Sem misturar "id" com "name"

## Scripts Criados

### `database/fix-tenant-25-channel.php`

Script para garantir o vínculo do tenant 25 com o canal pixel12digital.

**Uso:**
```bash
php database/fix-tenant-25-channel.php
```

**Funcionalidades:**
1. Verifica se o tenant 25 existe
2. Verifica se já existe vínculo para este tenant
3. Busca canais existentes com channel_id similar a pixel12digital
4. Cria vínculo se não existir
5. Garante que o canal está habilitado (`is_enabled = 1`)

## Como Usar os Logs para Diagnóstico

### 1. Verificar se o código certo está rodando:
```bash
grep "SEND_HANDLER_STAMP=15a1023" /var/log/php/error.log
```

### 2. Verificar trace do channel_id:
```bash
grep "TRACE channel_id" /var/log/php/error.log
```

### 3. Verificar resolução do canal:
```bash
grep "RESOLUÇÃO CANAL" /var/log/php/error.log
```

### 4. Verificar pontos de retorno CHANNEL_NOT_FOUND:
```bash
grep "RETURN_POINT=" /var/log/php/error.log
```

### 5. Verificar validação do gateway:
```bash
grep "validateGatewaySessionId" /var/log/php/error.log
```

## Próximos Passos

1. **Executar o script de fix:**
   ```bash
   php database/fix-tenant-25-channel.php
   ```

2. **Testar o envio:**
   - Fazer uma requisição POST para `/communication-hub/send` com tenant_id=25
   - Verificar os logs para identificar qual RETURN_POINT está sendo acionado
   - Verificar se o canal está sendo encontrado corretamente

3. **Analisar os logs:**
   - Verificar se o `channel_id` no response está vindo de `originalChannelIdFromPost` ou de outra fonte
   - Verificar se o problema é de normalização (espaços, case, etc.)
   - Verificar se o problema é de vínculo (tenant 25 não tem acesso ao canal)

4. **Corrigir o response (após resolver o envio):**
   - Ajustar para sempre usar `originalChannelIdFromPost` no campo `channel_id`
   - Adicionar campo `channel_label` com o nome amigável

## Estrutura da Tabela tenant_message_channels

```sql
CREATE TABLE tenant_message_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'wpp_gateway',
    channel_id VARCHAR(100) NOT NULL,
    session_id VARCHAR(100) NULL,  -- Pode não existir em versões antigas
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    webhook_configured BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_tenant_provider (tenant_id, provider),
    INDEX idx_channel_id (channel_id),
    INDEX idx_provider (provider),
    INDEX idx_is_enabled (is_enabled),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Observações importantes:**
- A coluna `session_id` pode não existir em versões antigas (o código detecta automaticamente)
- A constraint UNIQUE em `(tenant_id, provider)` significa que cada tenant pode ter apenas um canal por provider
- Para canais compartilhados, cada tenant precisa ter seu próprio registro na tabela

