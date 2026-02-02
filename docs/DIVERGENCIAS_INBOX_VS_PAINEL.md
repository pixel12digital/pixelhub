# Divergências Inbox vs Painel de Comunicação

**Objetivo:** Documentar o que está divergente no Inbox em relação ao Painel de Comunicação, para garantir o mesmo comportamento sem alterar o Painel.

**Referência:** `docs/LEVANTAMENTO_LISTA_CONVERSAS_PAINEL_PARA_INBOX.md`

---

## 1. Lista de conversas

### 1.1 Formatação de data
| Aspecto | Painel | Inbox |
|---------|--------|-------|
| Função | `formatDateBrasilia(dateStr)` | `formatInboxTime(dateStr)` |
| Formato | "Agora" ou "dd/mm HH:mm" (fuso Brasília) | "agora", "Xmin", "HH:mm", ou "dd/mm" |
| **Divergência** | Padrão consistente com backend (UTC→Brasília) | Formato relativo diferente |

### 1.2 Seção "Conversas não vinculadas"
| Aspecto | Painel | Inbox |
|---------|--------|-------|
| Exibição | ✅ Sim | ✅ Sim (já implementado) |
| Cabeçalho | Ícone + título + badge + descrição | ✅ Igual |
| **Itens - estrutura** | Nome, ícone telefone + número, channel_id, badge, menu ⋮, botão Vincular, data | Nome, preview (contact ou last_message_preview), data |
| **Divergência** | Painel NÃO exibe preview da última mensagem | Inbox exibe preview |
| **Divergência** | Painel exibe ícone de telefone + número formatado | Inbox exibe contact ou preview na segunda linha |
| **Divergência** | Painel tem menu ⋮ (Criar Cliente, Ignorar, Excluir) e botão Vincular | Inbox não tem |

### 1.3 Conversas vinculadas (threads)
| Aspecto | Painel | Inbox |
|---------|--------|-------|
| **Estrutura** | Nome, telefone + channel_id, link tenant, badge, menu ⋮, data | Nome, preview, data |
| **Divergência** | Painel exibe link "• {tenant_name}" para /tenants/view | Inbox não exibe |
| **Divergência** | Painel exibe ícone telefone + número + channel_id | Inbox exibe preview |
| **Divergência** | Painel tem menu ⋮ (Arquivar, Editar nome, etc.) | Inbox não tem |

### 1.4 API conversations-list
| Aspecto | Painel | Inbox |
|---------|--------|-------|
| Parâmetros | channel, tenant_id, status, session_id | Apenas status |
| **Divergência** | Painel envia todos os filtros do formulário | Inbox envia só status (equivalente a "Todos" em canal/cliente) |

---

## 2. Mensagens (conteúdo do chat)

### 2.1 Fonte de dados
- **Ambos** usam o mesmo endpoint: `GET /communication-hub/thread-data`
- **Ambos** recebem o mesmo formato de mensagens

### 2.2 Renderização
| Aspecto | Painel | Inbox |
|---------|--------|-------|
| Mídia | `renderMediaPlayer()` - player rico (áudio com transcrição, imagem com viewer) | Tags simples (img, audio, video, link) |
| Texto | `escapeHtml(content)` | `escapeInboxHtml(content)` |
| Mensagem sem conteúdo nem mídia | **Pula** (não exibe) | Exibe "[Mídia não disponível]" |
| Timestamp | `formatMessageTimestamp()` (Brasília) | `formatInboxTime()` (relativo) |

### 2.3 Comprovante de transferência
- O comprovante (PIX) chega como **imagem** no WhatsApp
- Backend retorna `media.url` + `media.media_type: 'image'`
- **Painel:** exibe via `renderMediaPlayer` → botão com imagem, viewer ao clicar
- **Inbox:** exibe via `<img src="...">` com onclick para abrir em nova aba
- **Comportamento funcional:** ambos exibem a imagem; o Painel tem viewer modal, o Inbox abre em nova aba

---

## 3. Correções aplicadas (Inbox) - 29/01/2026

1. **Formatação de data:** Criada `formatInboxDateBrasilia()` equivalente a `formatDateBrasilia` do Painel (dd/mm HH:mm, "Agora"). Usada na lista e nas mensagens.
2. **Estrutura dos itens (leads):** Exibição de ícone de telefone + número + channel_id; removido preview da última mensagem.
3. **Estrutura dos itens (threads):** Exibição de ícone de telefone + número + channel_id + link do tenant (quando houver); removido preview.
4. **Mensagens:** 
   - Timestamp das mensagens usa `formatInboxDateBrasilia`.
   - Suporte a `sticker` e `voice` como tipos de mídia.
   - Mensagens sem conteúdo e sem mídia são puladas (mesmo comportamento do Painel).
   - Placeholder de áudio `[Áudio]` não é exibido quando há mídia de áudio.
5. **Filtros (29/01/2026):** Canal, Sessão (WhatsApp), Cliente, Status; botão Nova Conversa (abre Painel em nova aba).

---

*Documento criado em 29/01/2026. Atualizado com correções aplicadas.*
