# Prompt: Implementação — Fix @lid

Implementação — Fix @lid

Criar migration para tabela whatsapp_business_ids conforme doc DIAGNOSTICO (business_id UNIQUE, phone_number, tenant_id opcional, índices). 

DIAGNOSTICO_SERVPRO_CAUSA_RAIZ

No ConversationService::extractChannelInfo() (ou helper equivalente), detectar @lid em message.from / raw.payload.from / sender.id. 

DIAGNOSTICO_SERVPRO_CAUSA_RAIZ

Se for @lid, consultar whatsapp_business_ids pelo business_id completo. Se existir, usar phone_number como contact_external_id e prosseguir com resolveConversation() (sem early return).

Se não existir mapeamento, aplicar fallback apenas para atualizar conversa existente (não criar conversa nova por nome). Nome (notifyName/verifiedName) pode ser usado só como critério auxiliar e apenas se houver match único. 

DIAGNOSTICO_SERVPRO_CAUSA_RAIZ

Criar um script simples de bootstrap para inserir mapeamento conhecido do ServPro (business_id 105233...@lid → phone 554796474223) e quaisquer outros já identificados.

Testar em produção com mensagem "TESTE SERVPRO PROD <hora>"; validar last_message_at, unread_count e has_updates=true. 

RESUMO_FINAL_DIAGNOSTICO_SERVPRO

Remover logs temporários após confirmação.

