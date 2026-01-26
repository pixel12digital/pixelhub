# Correção: Conversas Incorretamente Vinculadas a SO OBRAS

## Problema Identificado

Números novos de WhatsApp que chegavam sem tenant vinculado estavam sendo automaticamente vinculados ao tenant "SO OBRAS EPC DISTRIBUICAO E INSTALACOES LTDA" devido a uma lógica de resolução automática pelo `channel_id`.

## Correção Implementada

### 1. Correção no Código

A lógica de resolução automática de `tenant_id` pelo `channel_id` foi removida em:
- `src/Services/ConversationService.php::createConversation()` 
- `src/Services/ConversationService.php::updateConversationMetadata()`

Agora, números novos sem tenant vinculado ficam corretamente como:
- `tenant_id = NULL`
- `is_incoming_lead = 1`
- Aparecem em "Não vinculados" na interface

### 2. Script de Correção de Dados Existentes

Foi criado um script para corrigir conversas que já foram incorretamente vinculadas:

#### Opção 1: Script PHP (Recomendado)

```bash
# Modo dry-run (apenas mostra o que seria feito)
php database/fix-conversations-so-obras.php --dry-run

# Executa a correção
php database/fix-conversations-so-obras.php

# Especifica tenant_id manualmente
php database/fix-conversations-so-obras.php --tenant-id=123
```

**O que o script faz:**
1. Identifica o tenant "SO OBRAS" automaticamente
2. Busca conversas vinculadas a esse tenant
3. Verifica se há relações legítimas (ex: telefone do tenant corresponde ao contato)
4. Move conversas sem relação legítima para `tenant_id = NULL` e `is_incoming_lead = 1`
5. Mantém conversas que têm relação legítima

#### Opção 2: Script SQL

Execute as queries em `database/fix-conversations-so-obras.sql` na ordem:

1. Primeiro, identifica o tenant "SO OBRAS"
2. Verifica quais conversas serão corrigidas
3. Executa o UPDATE (apenas após verificar)
4. Confirma as alterações

## Resultado Esperado

Após executar o script:
- Conversas incorretamente vinculadas aparecem em "Não vinculados"
- Marcadas como `is_incoming_lead = 1`
- Usuário pode decidir:
  - Vincular a um lead existente
  - Criar um novo tenant
  - Descartar a conversa

## Próximos Passos

1. Execute o script de correção (modo dry-run primeiro)
2. Revise as conversas movidas para "Não vinculados"
3. Para cada conversa, decida:
   - Vincular a um tenant existente
   - Criar novo tenant
   - Descartar (se não for relevante)

## Observações

- O script preserva conversas que têm relação legítima com SO OBRAS (ex: telefone do tenant corresponde ao contato)
- Conversas com `is_incoming_lead = 1` já existente não são alteradas
- O script registra um log da execução para auditoria




