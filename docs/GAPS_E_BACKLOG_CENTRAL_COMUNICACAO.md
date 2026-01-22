# Gaps e Backlog: Central de Comunicação

**Data:** 2026-01-09  
**Versão:** 1.0  
**Objetivo:** Priorizar e estimar esforço para implementar a Central de Comunicação completa.

---

## Priorização

- **P0 (Bloqueadores):** Impedem funcionamento básico
- **P1 (Importantes):** Essenciais para operação profissional
- **P2 (Nice-to-have):** Melhorias e otimizações

---

## P0 - Bloqueadores

### P0.1: Processamento Automático de Eventos

**Problema:** Eventos ficam em `status = 'queued'` indefinidamente após ingestão.

**Impacto:** Sistema recebe mensagens mas não as processa (roteamento, atribuição, etc.).

**Solução:**
1. Criar worker assíncrono (PHP CLI ou queue system)
2. Worker processa eventos `queued` periodicamente
3. Executa roteamento e atribuição automaticamente

**Esforço:** **M** (Médio - 2-3 semanas)
- Implementar worker: 1 semana
- Integrar com EventRouterService: 3 dias
- Testes e ajustes: 1 semana

**Risco:** **Médio**
- Dependências: Sistema de fila (Redis recomendado) ou cron job
- Complexidade: Média (já existe EventRouterService)

**Dependências:**
- Redis (recomendado) ou cron job
- EventRouterService (já existe)

---

### P0.2: Tabela `conversations`

**Problema:** Não há tabela para agrupar mensagens em threads persistentes.

**Impacto:** Threads são gerados dinamicamente no PHP, causando:
- Performance ruim (agrupa eventos toda vez)
- Duplicação de conversas
- Impossibilidade de atribuir/encerrar conversas

**Solução:**
```sql
CREATE TABLE conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_key VARCHAR(255) NOT NULL UNIQUE,  -- "whatsapp_{tenant_id}_{contact_id}"
    channel_type VARCHAR(50) NOT NULL,
    channel_account_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,  -- FK para contacts
    tenant_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    status VARCHAR(20) DEFAULT 'new',  -- new, open, pending, closed, archived
    assigned_to INT UNSIGNED NULL,  -- user_id
    assigned_at DATETIME NULL,
    first_response_at DATETIME NULL,
    first_response_by INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    closed_by INT UNSIGNED NULL,
    sla_minutes INT UNSIGNED DEFAULT 60,
    sla_status VARCHAR(20) DEFAULT 'ok',  -- ok, warning, breach
    last_message_at DATETIME NULL,
    message_count INT UNSIGNED DEFAULT 0,
    unread_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_channel_account (channel_account_id),
    INDEX idx_contact (contact_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_last_message_at (last_message_at)
);
```

**Esforço:** **S** (Pequeno - 1 semana)
- Migration: 1 dia
- Service: 2 dias
- Integração com ingestão: 2 dias
- Testes: 2 dias

**Risco:** **Baixo**
- Dependências: Nenhuma
- Complexidade: Baixa

**Dependências:**
- Nenhuma

---

### P0.3: Tabela `contacts`

**Problema:** Não há identificação persistente de contatos (pessoas que enviam mensagens).

**Impacto:** 
- Impossível identificar mesmo contato em múltiplas conversas
- Impossível histórico unificado por contato
- Duplicação de contatos

**Solução:**
```sql
CREATE TABLE contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(255) NOT NULL,  -- Telefone, e-mail, etc.
    channel_type VARCHAR(50) NOT NULL,  -- whatsapp, email, etc.
    name VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    avatar_url VARCHAR(500) NULL,
    metadata JSON NULL,  -- Dados do provedor (nome do WhatsApp, etc.)
    tenant_id INT UNSIGNED NULL,  -- Se identificado como cliente
    is_identified BOOLEAN DEFAULT FALSE,  -- Se foi identificado como tenant
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_contact (external_id, channel_type),
    INDEX idx_tenant (tenant_id),
    INDEX idx_phone (phone),
    INDEX idx_email (email)
);
```

**Esforço:** **S** (Pequeno - 1 semana)
- Migration: 1 dia
- Service: 2 dias
- Integração: 2 dias
- Testes: 2 dias

**Risco:** **Baixo**
- Dependências: Nenhuma
- Complexidade: Baixa

**Dependências:**
- Nenhuma

---

### P0.4: Tabela `messages` Normalizada

**Problema:** Mensagens ficam apenas em `communication_events` como JSON, dificultando consultas.

**Impacto:**
- Performance ruim para buscar mensagens
- Impossível fazer queries complexas (busca por texto, filtros, etc.)
- Dificulta suporte a anexos

**Solução:**
```sql
CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(36) NOT NULL UNIQUE,  -- UUID
    external_message_id VARCHAR(255) NOT NULL,  -- ID do provedor
    conversation_id INT UNSIGNED NOT NULL,
    message_type VARCHAR(20) NOT NULL,  -- text, image, audio, etc.
    direction VARCHAR(10) NOT NULL,  -- inbound, outbound
    channel_type VARCHAR(50) NOT NULL,
    channel_account_id INT UNSIGNED NOT NULL,
    content_text TEXT NULL,
    content_subject VARCHAR(500) NULL,  -- Para e-mail
    content_html TEXT NULL,  -- Para e-mail
    media_url VARCHAR(500) NULL,
    media_type VARCHAR(100) NULL,
    file_name VARCHAR(255) NULL,
    file_size INT UNSIGNED NULL,
    metadata JSON NULL,  -- Metadados adicionais
    sent_by INT UNSIGNED NULL,  -- user_id (se outbound)
    read_at DATETIME NULL,
    delivered_at DATETIME NULL,
    failed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_external_id (external_message_id),
    INDEX idx_created_at (created_at),
    INDEX idx_content_text (content_text(255)),  -- Prefix index para busca
    FULLTEXT idx_content_fulltext (content_text)  -- Para busca full-text
);
```

**Esforço:** **M** (Médio - 2 semanas)
- Migration: 2 dias
- Service: 3 dias
- Migração de dados (communication_events → messages): 3 dias
- Integração: 2 dias
- Testes: 2 dias

**Risco:** **Médio**
- Dependências: Migração de dados existentes
- Complexidade: Média

**Dependências:**
- P0.2 (conversations) - precisa existir primeiro

---

## P1 - Importantes

### P1.1: Worker Assíncrono com Fila

**Problema:** Processamento síncrono pode travar em alta carga.

**Solução:** Implementar sistema de fila (Redis + workers).

**Esforço:** **L** (Grande - 3-4 semanas)
- Setup Redis: 2 dias
- Queue system: 1 semana
- Workers: 1 semana
- Monitoramento: 3 dias
- Testes: 1 semana

**Risco:** **Médio**
- Dependências: Redis (infraestrutura)
- Complexidade: Alta

**Dependências:**
- Redis instalado e configurado
- P0.1 (processamento automático)

---

### P1.2: Sistema de Atribuição (Assignment)

**Problema:** Não há atribuição automática de conversas para atendentes.

**Solução:**
1. Tabela `conversation_assignments` (histórico)
2. Service de atribuição (round-robin, disponibilidade, prioridade)
3. Integração com roteamento

**Esforço:** **M** (Médio - 2 semanas)
- Tabela e Service: 1 semana
- Integração: 3 dias
- UI (botão "Assumir"): 2 dias
- Testes: 3 dias

**Risco:** **Baixo**
- Dependências: P0.2 (conversations)
- Complexidade: Média

**Dependências:**
- P0.2 (conversations)

---

### P1.3: Funcionalidades Básicas de Inbox

**Problema:** Faltam ações essenciais: transferir, encerrar, tags, notas.

**Solução:**
1. Transferir conversa (para atendente ou time)
2. Encerrar/pausar conversa
3. Tags (criar, adicionar, filtrar)
4. Notas internas

**Esforço:** **M** (Médio - 2 semanas)
- Backend (APIs): 1 semana
- Frontend (UI): 1 semana

**Risco:** **Baixo**
- Dependências: P0.2 (conversations)
- Complexidade: Média

**Dependências:**
- P0.2 (conversations)

---

### P1.4: SLA e Priorização

**Problema:** Não há controle de SLA nem priorização de conversas.

**Solução:**
1. Campo `sla_minutes` em `conversations`
2. Cálculo de `sla_status` (ok, warning, breach)
3. Ordenação por SLA na Inbox
4. Alertas visuais (cores, badges)

**Esforço:** **S** (Pequeno - 1 semana)
- Cálculo de SLA: 2 dias
- Ordenação: 2 dias
- UI (cores, badges): 2 dias
- Testes: 1 dia

**Risco:** **Baixo**
- Dependências: P0.2 (conversations)
- Complexidade: Baixa

**Dependências:**
- P0.2 (conversations)

---

### P1.5: Busca e Filtros Avançados

**Problema:** Busca atual é limitada, não há filtros avançados.

**Solução:**
1. Busca full-text em mensagens
2. Filtros: canal, time, status, atendente, tags, data
3. Ordenação: SLA, não lidas, prioridade, data

**Esforço:** **M** (Médio - 2 semanas)
- Backend (queries): 1 semana
- Frontend (UI): 1 semana

**Risco:** **Baixo**
- Dependências: P0.4 (messages normalizada)
- Complexidade: Média

**Dependências:**
- P0.4 (messages)

---

### P1.6: Suporte a Mídia (Anexos)

**Problema:** Não há suporte a imagens, áudios, arquivos.

**Solução:**
1. Tabela `message_attachments`
2. Upload e armazenamento
3. Download e visualização
4. Integração com gateway (envio/recebimento)

**Esforço:** **M** (Médio - 2 semanas)
- Backend (upload, storage): 1 semana
- Frontend (visualização): 3 dias
- Integração gateway: 3 dias
- Testes: 2 dias

**Risco:** **Médio**
- Dependências: Gateway suporta mídia
- Complexidade: Média

**Dependências:**
- Gateway suporta mídia (WPP Gateway já suporta)

---

### P1.7: Templates de Resposta

**Problema:** Atendentes digitam mesmas respostas repetidamente.

**Solução:**
1. Tabela `message_templates`
2. CRUD de templates
3. UI de seleção e preenchimento
4. Variáveis dinâmicas ({name}, {tenant_name})

**Esforço:** **S** (Pequeno - 1 semana)
- Backend: 3 dias
- Frontend: 3 dias
- Testes: 1 dia

**Risco:** **Baixo**
- Dependências: Nenhuma
- Complexidade: Baixa

**Dependências:**
- Nenhuma

---

### P1.8: Integração com Projetos/Tarefas

**Problema:** Não é possível criar tarefa a partir de conversa.

**Solução:**
1. Botão "Criar Tarefa" na Inbox
2. Modal com formulário
3. Vincula tarefa à conversa
4. Notifica quando tarefa é concluída

**Esforço:** **S** (Pequeno - 1 semana)
- Backend (API): 2 dias
- Frontend (UI): 3 dias
- Testes: 2 dias

**Risco:** **Baixo**
- Dependências: Módulo de tarefas existe
- Complexidade: Baixa

**Dependências:**
- Módulo de tarefas (já existe)

---

### P1.9: Retry e Dead-Letter Queue

**Problema:** Falhas no envio não são retentadas, mensagens são perdidas.

**Solução:**
1. Tabela `message_send_attempts`
2. Retry com backoff exponencial
3. Dead-letter queue após N tentativas
4. Notificação de admin

**Esforço:** **M** (Médio - 2 semanas)
- Backend (retry logic): 1 semana
- DLQ: 3 dias
- Notificações: 2 dias
- Testes: 3 dias

**Risco:** **Médio**
- Dependências: Worker assíncrono (P1.1)
- Complexidade: Média

**Dependências:**
- P1.1 (worker assíncrono)

---

### P1.10: Métricas e Observabilidade

**Problema:** Não há métricas para diagnosticar problemas.

**Solução:**
1. Tabela `communication_metrics`
2. Coleta de métricas (mensagens/min, SLA, falhas)
3. Dashboard de métricas
4. Logs correlacionados

**Esforço:** **M** (Médio - 2 semanas)
- Coleta de métricas: 1 semana
- Dashboard: 1 semana

**Risco:** **Baixo**
- Dependências: Nenhuma
- Complexidade: Média

**Dependências:**
- Nenhuma

---

## P2 - Nice-to-Have

### P2.1: Bots e Automação

**Esforço:** **L** (Grande - 4-6 semanas)  
**Risco:** **Alto**  
**Dependências:** P0.2, P1.1

---

### P2.2: Integração com CRM (Leads/Deals)

**Esforço:** **L** (Grande - 3-4 semanas)  
**Risco:** **Médio**  
**Dependências:** Módulo de CRM (não existe ainda)

---

### P2.3: Integração com Tickets

**Esforço:** **M** (Médio - 2 semanas)  
**Risco:** **Médio**  
**Dependências:** Módulo de tickets (não encontrado na auditoria)

---

### P2.4: Integração com Marketing

**Esforço:** **L** (Grande - 3-4 semanas)  
**Risco:** **Alto**  
**Dependências:** Módulo de marketing (não existe)

---

### P2.5: Chat Widget para Sites

**Esforço:** **M** (Médio - 2 semanas)  
**Risco:** **Baixo**  
**Dependências:** P0.2, P0.3

---

### P2.6: Integração com E-mail

**Esforço:** **M** (Médio - 2 semanas)  
**Risco:** **Baixo**  
**Dependências:** P0.2, P0.3, P0.4

---

## Resumo de Esforço

### P0 (Bloqueadores)
- **Total:** ~6 semanas (1.5 meses)
- **Crítico:** Sem isso, sistema não funciona

### P1 (Importantes)
- **Total:** ~18 semanas (4.5 meses)
- **Essencial:** Para operação profissional

### P2 (Nice-to-Have)
- **Total:** ~16 semanas (4 meses)
- **Opcional:** Melhorias futuras

### **Total Geral:** ~40 semanas (10 meses)

**Recomendação:** Focar em P0 + P1 críticos primeiro (~3 meses), depois iterar com P2.

---

## Ordem de Implementação Recomendada

### Fase 1: Fundação (P0) - 6 semanas
1. P0.2: Tabela `conversations` (1 semana)
2. P0.3: Tabela `contacts` (1 semana)
3. P0.4: Tabela `messages` (2 semanas)
4. P0.1: Processamento automático (2 semanas)

### Fase 2: Operação Básica (P1 críticos) - 6 semanas
5. P1.2: Sistema de atribuição (2 semanas)
6. P1.3: Funcionalidades básicas (2 semanas)
7. P1.4: SLA e priorização (1 semana)
8. P1.7: Templates (1 semana)

### Fase 3: Melhorias (P1 restantes) - 8 semanas
9. P1.1: Worker assíncrono (3 semanas)
10. P1.5: Busca avançada (2 semanas)
11. P1.6: Suporte a mídia (2 semanas)
12. P1.9: Retry e DLQ (1 semana)

### Fase 4: Integrações (P1) - 4 semanas
13. P1.8: Integração tarefas (1 semana)
14. P1.10: Métricas (2 semanas)
15. Outras integrações (1 semana)

### Fase 5: Expansão (P2) - Conforme necessidade
16. P2.5: Chat widget
17. P2.6: E-mail
18. P2.1: Bots
19. Outros P2

---

**Total Fase 1-4:** ~24 semanas (6 meses) para sistema completo e profissional.

