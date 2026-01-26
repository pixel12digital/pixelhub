# Lista de Substituições: Emojis → Ícones Monocromáticos

Este documento lista todas as substituições de emojis coloridos por ícones SVG monocromáticos realizadas no projeto PixelHub.

---

## 📋 Resumo das Substituições

### Botões e Ações Principais

| **Onde estava** | **Emoji Original** | **Ícone Monocromático** | **Arquivo** |
|-----------------|-------------------|------------------------|-------------|
| Botão "Ver Semana" | 📅 | Ícone de calendário SVG | `views/agenda/index.php` |
| Botão "Detalhes" | 📋 | Removido (já tem ícone via CSS) | `views/projects/_project_actions.php` |
| Botão "Abrir ticket" | 🎫 | Removido (já tem ícone via CSS) | `views/projects/_project_actions.php` |
| Botão "Compartilhar" (WhatsApp) | 📱 | Ícone de celular SVG | `views/tasks/board.php` |
| Botão "Copiar link" | 🔗 | Ícone de link SVG | `views/tasks/board.php` |
| Botão "Gerar link" | 🔗 | Ícone de link SVG | `views/tasks/board.php` |
| Botão "Copiar" (DNS) | 📋 | Ícone de documento/clipboard SVG | `views/hosting/form.php` |
| Botão "Abrir no Asaas" | 🔗 | Ícone de link SVG | `views/tenants/form.php` |

---

### Mensagens e Alertas

| **Onde estava** | **Emoji Original** | **Ícone Monocromático** | **Arquivo** |
|-----------------|-------------------|------------------------|-------------|
| Mensagem de sucesso | ✅ | Ícone de check SVG | `views/tenants/view.php` (várias ocorrências) |
| Mensagem de erro | ❌ | Ícone de X/erro SVG | `views/tenants/view.php` (várias ocorrências) |
| Mensagem informativa | ℹ️ | Ícone de informação SVG | `views/tenants/view.php` |
| Aviso de duplicatas | ⚠️ | Ícone de aviso SVG | `views/tasks/board.php` |
| Aviso de provedor não configurado | ⚠️ | Ícone de aviso SVG | `views/hosting/form.php` |
| Informação sobre hospedagem | ℹ️ | Ícone de informação SVG | `views/hosting/form.php` |
| Aviso de cliente já cadastrado | ⚠️ | Ícone de aviso SVG | `views/tenants/form.php` |
| Aviso de cliente encontrado no Asaas | ⚠️ | Ícone de aviso SVG | `views/tenants/form.php` |
| Sucesso - cliente não encontrado | ✅ | Ícone de check SVG | `views/tenants/form.php` |
| Sucesso - dados importados | ✅ | Ícone de check SVG | `views/tenants/form.php` |
| Dados consolidados do Asaas | ℹ️ | Ícone de informação SVG | `views/tenants/view.php` |

---

### Formulários e Wizards

| **Onde estava** | **Emoji Original** | **Ícone Monocromático** | **Arquivo** |
|-----------------|-------------------|------------------------|-------------|
| Placeholder de busca | 🔍 | Removido (placeholder limpo) | `views/wizard/new_project.php` |
| Ícone de busca no botão | 🔍 | Ícone de busca SVG | `views/wizard/new_project.php` |
| Aviso CPF incompleto | ⚠️ | Ícone de aviso SVG | `views/wizard/new_project.php` |
| Aviso CNPJ incompleto | ⚠️ | Ícone de aviso SVG | `views/wizard/new_project.php` |
| Sucesso - cliente encontrado | ✅ | Ícone de check SVG | `views/wizard/new_project.php` |
| Sucesso - dados sincronizados | ✅ | Ícone de check SVG | `views/wizard/new_project.php` |
| Sucesso - cliente não encontrado | ✅ | Ícone de check SVG | `views/wizard/new_project.php` |
| Erro ao conectar com Asaas | ❌ | Ícone de X/erro SVG | `views/wizard/new_project.php` |
| Sugestões de nomes | 💡 | Ícone de lâmpada SVG | `views/wizard/new_project.php` |
| Mensagem de projetos criados | ✅ | Removido (texto limpo) | `views/wizard/new_project.php` |

---

## 🎨 Padrão de Ícones Implementado

Todos os ícones seguem o padrão:
- **SVG inline** com `fill="currentColor"` ou `stroke="currentColor"`
- **Tamanho padrão**: 16x16px (ou 14x14px para menores)
- **Cor**: Herda a cor do texto (`currentColor`)
- **Opacidade**: 0.85 (padrão) para discreção
- **Alinhamento**: `vertical-align: middle` com `margin-right: 4px`

---

## 📝 Classes CSS Criadas

As seguintes classes CSS foram adicionadas em `public/assets/css/app-overrides.css`:

- `.icon-calendar` - Calendário
- `.icon-document` - Documento/clipboard
- `.icon-ticket` - Ticket
- `.icon-check` - Check/sucesso
- `.icon-error` - Erro/X
- `.icon-warning` - Aviso
- `.icon-info` - Informação
- `.icon-search` - Busca
- `.icon-phone` - Telefone
- `.icon-whatsapp` - WhatsApp
- `.icon-email` - Email
- `.icon-globe` - Globo/site
- `.icon-mobile` - Celular
- `.icon-link` - Link
- `.icon-edit` - Lápis/editar
- `.icon-save` - Salvar
- `.icon-chart` - Gráfico/estatísticas
- `.icon-lightbulb` - Lâmpada/dica
- `.icon-lock` - Cadeado
- `.icon-image` - Imagem
- `.icon-settings` - Engrenagem/configurações

---

## ✅ Status da Implementação

- [x] Sistema de ícones monocromáticos criado no CSS
- [x] Emojis substituídos em botões e ações principais
- [x] Emojis substituídos em mensagens e alertas
- [x] Emojis substituídos em formulários e wizards
- [x] Documentação criada

---

## 🔍 Arquivos Modificados

1. `public/assets/css/app-overrides.css` - Sistema de ícones adicionado
2. `views/agenda/index.php` - Botão "Ver Semana"
3. `views/projects/_project_actions.php` - Botões de ação
4. `views/tasks/board.php` - Botões de compartilhar e links
5. `views/tenants/view.php` - Mensagens de sucesso/erro/info
6. `views/hosting/form.php` - Avisos e botão copiar
7. `views/tenants/form.php` - Mensagens e botão Asaas
8. `views/wizard/new_project.php` - Busca, avisos e mensagens

---

## 📌 Notas Importantes

1. **Ícones via CSS**: Alguns botões de ação já têm ícones injetados via CSS (`::before`), então os emojis foram apenas removidos do texto.

2. **Compatibilidade**: Todos os ícones SVG são compatíveis com navegadores modernos e herdam a cor do texto automaticamente.

3. **Acessibilidade**: Os ícones mantêm `aria-label` e `data-tooltip` onde aplicável para leitores de tela.

4. **Performance**: Ícones SVG inline são leves e não requerem requisições HTTP adicionais.

---

**Última atualização**: 2025-01-09
**Status**: ✅ Concluído













