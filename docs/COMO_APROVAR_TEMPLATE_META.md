# Como Aprovar Templates no Meta Business Suite

**Data:** 04/03/2026

---

## 📋 Pré-requisitos

Antes de submeter um template para aprovação, você precisa:

1. ✅ **WhatsApp Business Account verificado**
2. ✅ **Acesso ao Meta Business Suite**
3. ✅ **Template criado no PixelHub** (status: Rascunho)
4. ✅ **Phone Number ID configurado** em `/settings/whatsapp-providers`

---

## 🚀 Passo a Passo para Aprovação

### 1. Criar Template no PixelHub

1. Acesse: `https://hub.pixel12digital.com.br/settings/whatsapp-providers`
2. Clique na aba **"Templates Meta"**
3. Clique em **"+ Novo Template"**
4. Preencha:
   - **Nome:** `prospeccao_sistema_corretores` (apenas letras minúsculas, números e underscore)
   - **Categoria:** Marketing
   - **Idioma:** Português (Brasil)
   - **Conteúdo:** Sua mensagem
   - **Botões:** Adicione botões interativos (opcional)
5. Clique em **"Salvar"**

### 2. Criar Template no Meta Business Suite

**IMPORTANTE:** O template deve ser criado **manualmente no Meta Business Suite**, não pelo PixelHub.

#### Acesse o Meta Business Suite

1. Vá para: https://business.facebook.com/
2. Selecione sua **Business Account**
3. Menu lateral → **WhatsApp Manager**
4. Clique em **"Message Templates"**

#### Criar Novo Template

1. Clique em **"Create Template"**
2. Preencha:
   - **Template Name:** `prospeccao_sistema_corretores` (EXATAMENTE igual ao PixelHub)
   - **Category:** Marketing
   - **Languages:** Portuguese (Brazil)

#### Configurar Conteúdo

**Header (Cabeçalho) - Opcional:**
- Tipo: Text / Image / Video / Document
- Conteúdo: Digite o texto ou faça upload da mídia

**Body (Corpo) - Obrigatório:**
```
Olá! Estamos entrando em contato porque identificamos que você atua como corretor de imóveis.

Desenvolvemos uma estrutura que ajuda corretores a captar e organizar interessados em imóveis através de um site próprio integrado com WhatsApp.

Gostaria de ver rapidamente como funciona?
```

**Footer (Rodapé) - Opcional:**
```
Pixel12 Digital - Soluções para Corretores
```

**Buttons (Botões) - Opcional:**

Clique em **"Add Button"** e escolha:

**Opção 1: Quick Reply Buttons**
- Botão 1: 
  - Tipo: Quick Reply
  - Texto: `Quero conhecer`
  - ID: `btn_quero_conhecer`
- Botão 2:
  - Tipo: Quick Reply
  - Texto: `Não tenho interesse`
  - ID: `btn_nao_tenho_interesse`

**Opção 2: Call to Action Buttons**
- URL Button: Link para landing page
- Phone Button: Número de telefone

#### Submeter para Aprovação

1. Revise o preview do template
2. Clique em **"Submit"**
3. Aguarde aprovação (24-48 horas)

### 3. Aguardar Aprovação do Meta

**Status possíveis:**
- 🟡 **Pending:** Aguardando revisão (24-48h)
- 🟢 **Approved:** Aprovado e pronto para uso
- 🔴 **Rejected:** Rejeitado (veja o motivo e corrija)

**Notificações:**
- Você receberá email do Meta com o resultado
- Verifique em: Meta Business Suite → WhatsApp → Message Templates

### 4. Atualizar Status no PixelHub

Após aprovação no Meta:

1. Acesse: `https://hub.pixel12digital.com.br/settings/whatsapp-providers`
2. Aba **"Templates Meta"**
3. Localize o template
4. **Copie o Template ID** do Meta Business Suite
5. No PixelHub, execute via PHP ou banco de dados:

```php
// Via PHP (criar script temporário)
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PixelHub\Services\MetaTemplateService;
use PixelHub\Core\Env;

Env::load();

$templateId = 1; // ID do template no PixelHub
$metaTemplateId = 'XXXXXXXXXXXXXX'; // ID do Meta

MetaTemplateService::markAsApproved($templateId, $metaTemplateId);

echo "Template aprovado com sucesso!\n";
```

**Ou via SQL:**
```sql
UPDATE whatsapp_message_templates 
SET status = 'approved',
    meta_template_id = 'XXXXXXXXXXXXXX',
    approved_at = NOW()
WHERE id = 1;
```

---

## ⚠️ Motivos Comuns de Rejeição

### 1. Conteúdo Promocional Excessivo
❌ **Errado:**
```
🔥 PROMOÇÃO IMPERDÍVEL! 🔥
Compre agora com 50% OFF!
Últimas unidades!
```

✅ **Correto:**
```
Olá! Temos uma oferta especial para você.
Gostaria de conhecer nossos produtos?
```

### 2. Variáveis Mal Formatadas
❌ **Errado:**
```
Olá {nome}, sua compra foi confirmada!
```

✅ **Correto:**
```
Olá {{1}}, sua compra foi confirmada!
```

### 3. Informações Enganosas
❌ **Errado:**
```
Você ganhou um prêmio! Clique aqui para resgatar.
```

✅ **Correto:**
```
Você foi selecionado para participar de nossa promoção.
```

### 4. Conteúdo Sensível
❌ Evite:
- Política
- Religião
- Conteúdo adulto
- Produtos regulamentados (álcool, tabaco, etc.)

### 5. Gramática e Ortografia
- Revise cuidadosamente
- Evite CAPS LOCK excessivo
- Use pontuação adequada

---

## 📊 Categorias de Templates

### Marketing
- Promoções
- Ofertas
- Novidades de produtos
- **Limite:** 24h após última interação do cliente

### Utility
- Confirmações de pedido
- Atualizações de status
- Lembretes de agendamento
- **Sem limite de tempo**

### Authentication
- Códigos de verificação (OTP)
- Senhas temporárias
- **Sem limite de tempo**

---

## 🔧 Troubleshooting

### Template não aparece no Meta
- Verifique se você está na Business Account correta
- Confirme que tem permissões de administrador
- Aguarde alguns minutos (sincronização)

### Aprovação demora mais de 48h
- Entre em contato com o suporte do Meta
- Verifique se há pendências na sua Business Account

### Template aprovado mas não funciona
1. Verifique se o `meta_template_id` está correto no PixelHub
2. Confirme que o status é `approved`
3. Teste enviando via API do Meta diretamente

### Erro ao enviar template
- Verifique se o Phone Number ID está correto
- Confirme que o Access Token está válido
- Verifique se o template está aprovado no Meta

---

## 📝 Checklist Final

Antes de submeter para aprovação:

- [ ] Nome do template usa apenas letras minúsculas, números e underscore
- [ ] Categoria está correta (Marketing/Utility/Authentication)
- [ ] Conteúdo está claro e sem erros de português
- [ ] Variáveis estão no formato {{1}}, {{2}}, etc.
- [ ] Botões têm textos claros (máx. 20 caracteres)
- [ ] Preview está correto
- [ ] Não contém conteúdo promocional excessivo
- [ ] Não contém informações enganosas
- [ ] Respeita as políticas do WhatsApp Business

---

## 🔗 Links Úteis

- **Meta Business Suite:** https://business.facebook.com/
- **WhatsApp Business API Docs:** https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates
- **Políticas do WhatsApp:** https://www.whatsapp.com/legal/business-policy
- **Suporte Meta:** https://business.facebook.com/business/help

---

## 💡 Dicas Importantes

1. **Crie templates genéricos** que possam ser reutilizados
2. **Use variáveis** para personalização ({{1}}, {{2}})
3. **Teste antes de enviar em massa** - envie para você mesmo primeiro
4. **Mantenha backup** dos templates aprovados
5. **Documente** quais variáveis cada template usa
6. **Monitore métricas** de entrega e leitura

---

**Última atualização:** 04/03/2026  
**Autor:** PixelHub Development Team
