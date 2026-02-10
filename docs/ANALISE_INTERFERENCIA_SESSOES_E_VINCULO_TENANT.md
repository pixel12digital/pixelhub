# Análise: interferência entre sessões e conversa já vinculada

**Objetivo:** Investigar duas situações **sem implementar** até estar seguro da causa. Documentar causas raiz e comportamento esperado.

---

## Situação 1: Interferência entre sessões (mesmo contato, canais diferentes)

### Descrição do comportamento relatado

- Mensagem de **Charles Dietrich** para **Pixel 12 Digital** (ex.: texto "Wow Joe" ou "Da...") aparece corretamente.
- Quando chega **nova mensagem de Charles para outra sessão** (ex.: **ImobSites** – áudio), o conteúdo da conversa **Charles → Pixel 12 Digital** “desaparece” ou é afetado (ex.: canal da thread muda, mensagens somem da vista).

Ou seja: **mesmo tenant**, **mesmo contato**, **sessões diferentes** (Pixel 12 Digital vs ImobSites) devem gerar **duas conversas distintas**. Hoje, em certos fluxos, uma mensagem da sessão B está sendo aplicada na conversa da sessão A.

### Causa raiz identificada

O `ConversationService::resolveConversation()` resolve “qual conversa usar” nesta ordem:

1. **findByKey(conversation_key)**  
   - Chave = `whatsapp_{channel_account_id|shared}_{contact_external_id}`.  
   - **Diferencia canal:** Pixel12 → `whatsapp_4_5511...`, ImobSites → `whatsapp_7_5511...`.  
   - Se a chave bater, usa essa conversa e atualiza metadados (incluindo `channel_id`). **Correto.**

2. **findEquivalentConversation(contact)**  
   - Usado para **variantes do 9º dígito** (ex.: 5547999... vs 55479999...).  
   - Faz: `WHERE channel_type = 'whatsapp' AND contact_external_id = ?` (e variantes do número).  
   - **Não filtra por canal/sessão.** Retorna **qualquer** conversa com aquele contato (ex.: a mais recente).  
   - Se encontrar, chama **updateConversationMetadata** nessa conversa com o evento **da sessão atual** (ex.: ImobSites).  
   - **Problema:** Para Charles → ImobSites, pode retornar a conversa **Charles → Pixel 12 Digital**. Aí o código **atualiza essa conversa** com o evento de ImobSites e, em `updateConversationMetadata`, **sobrescreve `channel_id`** com "ImobSites".  
   - Efeito: a thread que era “Charles → Pixel 12 Digital” vira “Charles → ImobSites” e o histórico da sessão Pixel 12 “desaparece” daquela thread.

3. **findDuplicateByRemoteKey(channelInfo)**  
   - Usado para evitar duplicata quando o mesmo contato aparece com IDs diferentes (ex.: `@lid` vs E.164).  
   - Faz: `WHERE channel_type = ? AND remote_key = ?`.  
   - **Não filtra por sessão.** `remote_key` é algo como `tel:5511999...` (sem canal).  
   - **Problema:** Para Charles → ImobSites, pode retornar a conversa **Charles → Pixel 12 Digital** (mesmo `remote_key`). De novo, **updateConversationMetadata** é chamado na conversa errada e **atualiza `channel_id`** para ImobSites.  
   - Mesmo efeito: interferência entre sessões.

4. **findConversationByContactOnly(channelInfo)**  
   - Usado para conversas “shared” vs tenant específico.  
   - Já trata **channel_account_id diferente**: se a conversa encontrada tem canal A e o evento tem canal B, **não** reutiliza; cai no “cria nova”.  
   - Esse trecho está **correto** para não misturar sessões.

**Conclusão situação 1:**  
A interferência vem de **findEquivalentConversation** e **findDuplicateByRemoteKey**, que identificam “a mesma conversa” só por **contato** (ou `remote_key`), **sem exigir a mesma sessão/canal**. Ao reutilizar a conversa errada e rodar **updateConversationMetadata**, o sistema **sobrescreve `channel_id`** da thread, fazendo a conversa da sessão A “virar” da sessão B.

**Direção de correção (para implementar depois):**

- **findEquivalentConversation:**  
  Só considerar “equivalente” se for **mesmo canal** (mesmo `channel_account_id` ou mesma sessão).  
  Se `$equivalent['channel_account_id'] !== $channelInfo['channel_account_id']` (e ambos não forem “shared”), **não** usar essa conversa; retornar `null` e deixar o fluxo criar nova conversa para a sessão atual.

- **findDuplicateByRemoteKey:**  
  Quando houver **thread_key** (ou **contact_key**) no `channelInfo` (ex.: `wpp_gateway:ImobSites:tel:5511...`), buscar duplicata **por sessão + contato**:  
  ex. `WHERE channel_type = ? AND thread_key = ?` (ou equivalente que inclua sessão).  
  Assim, duplicata só dentro da **mesma** sessão; conversas em outras sessões não são reutilizadas e não sofrem `updateConversationMetadata` com `channel_id` errado.

---

## Situação 2: Tenant já no banco → conversa deveria aparecer como vinculada

### Descrição do comportamento esperado

- Quando o **tenant está “automaticamente registrado”** (já existe no banco e o canal está configurado para ele em `tenant_message_channels`), a conversa deveria **já aparecer como vinculada** a esse tenant.
- Hoje, em parte dos casos, a conversa ainda aparece como **não vinculada** (ex.: “precisa linkar”), mesmo com o tenant já existente e o canal associado a ele.

### Fluxo atual relevante

1. **Webhook:**  
   - Resolve `tenant_id` pelo canal: `resolveTenantByChannel($channelId)` (consulta `tenant_message_channels`).  
   - Se o canal (ex.: ImobSites) estiver configurado para um tenant X, `tenant_id = X` é enviado na ingestão.

2. **ConversationService:**  
   - Em **createConversation** e **updateConversationMetadata** é chamado **validatePhoneBelongsToTenant(contactExternalId, tenantId)**.  
   - Regra: só aceita vincular aquele `tenant_id` à conversa se o **número do contato** for considerado “do” tenant (ex.: igual a `tenants.phone` ou variante 9º dígito).  
   - Se **não** pertencer, o código **zera** `tenant_id` (não vincula) e a conversa segue como “não vinculada” / lead.

3. **validatePhoneBelongsToTenant:**  
   - Compara o telefone do **contato** com o telefone do **tenant** (`tenants.phone`).  
   - Retorna `true` só se forem iguais (ou variante 9º dígito), ou se o tenant não tiver telefone cadastrado (aí permite vincular).  
   - Ou seja: a vinculação automática hoje está pensada para o caso “contato = próprio tenant (mesmo número)”, e não para “qualquer contato que falou no canal desse tenant”.

**Conclusão situação 2:**  
Quando o `tenant_id` vem **do canal** (resolvido no webhook por `resolveTenantByChannel`), o sistema ainda exige que o **contato** seja o telefone do tenant. Se o contato for outro (ex.: Charles), a vinculação é **rejeitada** e a conversa fica “não vinculada”.  
O comportamento desejado é: **se o tenant foi resolvido pelo canal** (canal configurado para esse tenant no banco), a conversa deve ser considerada **desse tenant** e exibida como **vinculada**, mesmo que o número do contato não seja o `tenants.phone`.

**Direção de correção (para implementar depois):**

- Tratar explicitamente o caso “tenant resolvido pelo canal”:
  - Ex.: no webhook, ao definir `tenant_id` por `resolveTenantByChannel`, enviar um sinal nos metadados do evento (ex.: `metadata.tenant_resolved_from_channel = true`).
  - Em **createConversation** e **updateConversationMetadata**, quando esse sinal estiver presente (e `tenant_id` veio do canal), **não** chamar `validatePhoneBelongsToTenant` para rejeitar o vínculo: confiar no mapeamento canal → tenant e manter a conversa como **vinculada** a esse tenant.
- Assim, “tenant já no banco” + “canal configurado para esse tenant” passa a resultar em “conversa já vinculada”, sem necessidade de link manual.

---

## Resumo

| Situação | Causa raiz | O que corrigir (quando for implementar) |
|----------|------------|----------------------------------------|
| **1. Interferência entre sessões** | **findEquivalentConversation** e **findDuplicateByRemoteKey** reutilizam conversa só por contato/remote_key, sem exigir mesma sessão; **updateConversationMetadata** sobrescreve `channel_id` na thread “errada”. | Exigir **mesmo canal/sessão** ao reutilizar conversa (por channel_account_id ou thread_key). Não atualizar conversa de outra sessão com evento de outra sessão. |
| **2. Conversa não vinculada mesmo com tenant no banco** | **validatePhoneBelongsToTenant** rejeita vínculo quando o contato não é o telefone do tenant; tenant resolvido pelo canal é ignorado nesses casos. | Quando **tenant_id** for resolvido pelo canal (ex.: flag em metadata), não usar a validação de “telefone do contato = telefone do tenant” para recusar o vínculo; manter conversa vinculada ao tenant do canal. |

---

## Implementação realizada

- **findEquivalentConversation:** passa a exigir mesmo `channel_account_id`; se a conversa encontrada for de outro canal, é ignorada e o fluxo cria nova conversa.
- **findDuplicateByRemoteKey:** quando existe `thread_key` no `channelInfo`, a busca é feita por `thread_key` (sessão + contato), evitando reutilizar conversa de outra sessão.
- **Webhook:** envia `metadata.tenant_resolved_from_channel = true` quando `tenant_id` é resolvido por `resolveTenantByChannel`.
- **ConversationService (create e update):** quando `tenant_resolved_from_channel` está em metadata, não aplica `validatePhoneBelongsToTenant`; a conversa permanece vinculada ao tenant do canal.
