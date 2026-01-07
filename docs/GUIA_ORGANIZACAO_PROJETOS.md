# Guia de Organização de Projetos - Pixel Hub

## 📋 Como Organizar um Projeto Multi-Tenant Existente

Este guia ajuda você a registrar e organizar todas as informações de um projeto existente no Pixel Hub.

---

## 🎯 Passo a Passo

### 1. Preencha os Campos Básicos

**Nome do Projeto:**
- Use um nome claro e descritivo
- Exemplo: `Sistema Prestadores de Serviços`

**Tipo de Projeto:**
- Selecione **"Interno"** para projetos da Pixel12 Digital
- Selecione **"Cliente"** apenas se for um projeto específico de um cliente

**Slug (identificador único):**
- Use apenas letras minúsculas, números e hífens
- Exemplo: `prestadores-servicos`
- Será usado para identificar o projeto em URLs/APIs

**URL Base do Projeto:**
- URL principal de acesso ao sistema
- Exemplo: `https://prestadores.pixel12digital.com.br`

---

### 2. Organize as Informações na Descrição

Use o campo **"Descrição / Notas Técnicas"** para estruturar todas as informações importantes. Use o seguinte formato:

```markdown
## 📊 ESTÁGIO DO PROJETO
- Status: [Desenvolvimento | Produção | Manutenção | Em Testes]
- Versão Atual: [ex: 1.2.3]
- Última Atualização: [data]

## 🗄️ BANCO DE DADOS
- Host: [ex: localhost ou IP do servidor]
- Porta: [ex: 3306]
- Nome do Banco: [ex: prestadores_db]
- Usuário: [ex: prestadores_user]
- Senha: [⚠️ NÃO coloque senhas aqui - use "Acessos Rápidos"]
- Tipo: [MySQL | PostgreSQL | SQLite | MongoDB]

## 🖥️ SERVIDOR/INFRAESTRUTURA
- Servidor: [ex: VPS Hostinger, AWS, etc.]
- Ambiente: [Desenvolvimento | Staging | Produção]
- IP/URL do Servidor: [se aplicável]
- Acesso SSH: [⚠️ Registre em "Acessos Rápidos"]

## 🔐 CREDENCIAIS IMPORTANTES
⚠️ NÃO coloque senhas aqui!
- Painel Admin: [URL apenas]
- API Keys: [referência apenas]
- Registre credenciais completas em "Acessos Rápidos" (Minha Infraestrutura)

## 📝 OBSERVAÇÕES TÉCNICAS
- Stack: [PHP 8.1, Laravel 10, MySQL 8.0, etc.]
- Dependências: [composer, npm, etc.]
- Configurações especiais: [o que for relevante]

## 🔗 LINKS ÚTEIS
- Repositório: [GitHub/GitLab URL]
- Documentação: [URL se houver]
- Painel Admin: [URL]
- API Docs: [URL se houver]

## 📅 HISTÓRICO
- Data de Criação: [data]
- Última Manutenção: [data]
- Próximas Tarefas: [breve descrição]
```

---

### 3. Exemplo Prático Completo

**Nome do Projeto:** `Sistema Prestadores de Serviços`

**Slug:** `prestadores-servicos`

**URL Base:** `https://prestadores.pixel12digital.com.br`

**Descrição / Notas Técnicas:**
```
## 📊 ESTÁGIO DO PROJETO
- Status: Produção
- Versão Atual: 2.1.0
- Última Atualização: 05/01/2026

## 🗄️ BANCO DE DADOS
- Host: db.pixel12digital.com.br
- Porta: 3306
- Nome do Banco: prestadores_prod
- Usuário: prestadores_user
- Tipo: MySQL 8.0
- ⚠️ Credenciais completas em "Acessos Rápidos" (categoria: banco)

## 🖥️ SERVIDOR/INFRAESTRUTURA
- Servidor: VPS Hostinger
- Ambiente: Produção
- IP: 185.xxx.xxx.xxx
- ⚠️ Acesso SSH em "Acessos Rápidos" (categoria: vps)

## 🔐 CREDENCIAIS IMPORTANTES
- Painel Admin: https://prestadores.pixel12digital.com.br/admin
- ⚠️ Login completo em "Acessos Rápidos" (categoria: ferramenta)

## 📝 OBSERVAÇÕES TÉCNICAS
- Stack: PHP 8.1, Laravel 10, MySQL 8.0
- Multi-tenant: Sim (isolation por schema)
- Cache: Redis
- Queue: Laravel Queue (Redis driver)

## 🔗 LINKS ÚTEIS
- Repositório: https://github.com/pixel12digital/prestadores-servicos
- Documentação API: https://prestadores.pixel12digital.com.br/api/docs

## 📅 HISTÓRICO
- Data de Criação: 15/11/2024
- Última Manutenção: 05/01/2026
- Próximas Tarefas: Implementar relatórios avançados
```

---

## 🔒 Registrando Credenciais com Segurança

**NÃO coloque senhas no campo Descrição!**

Use o módulo **"Minha Infraestrutura"** (`/owner-shortcuts`) para registrar credenciais com criptografia:

1. Acesse **"Minha Infraestrutura"** no menu lateral
2. Clique em **"Novo Acesso"**
3. Preencha:
   - **Categoria:** `banco` (para banco de dados), `vps` (para servidor), `ferramenta` (para painéis)
   - **Label:** Nome descritivo (ex: "Banco Prestadores - Produção")
   - **URL:** URL de acesso (se houver)
   - **Usuário:** Usuário de acesso
   - **Senha:** A senha será criptografada automaticamente
   - **Notas:** Informações adicionais

**Dica:** Na descrição do projeto, apenas referencie que as credenciais estão em "Acessos Rápidos" com o label usado.

---

## ✅ Checklist de Organização

Antes de salvar, verifique:

- [ ] Nome do projeto está claro e descritivo
- [ ] Slug foi preenchido (identificador único)
- [ ] URL base está correta (se aplicável)
- [ ] Descrição contém todas as informações importantes
- [ ] Credenciais foram registradas em "Acessos Rápidos" (não na descrição)
- [ ] Links importantes estão documentados
- [ ] Estágio/status do projeto está atualizado
- [ ] Prioridade está correta
- [ ] Tipo (Interno/Cliente) está correto

---

## 🎯 Benefícios desta Organização

✅ **Centralização:** Todas as informações em um único lugar  
✅ **Segurança:** Credenciais criptografadas em "Acessos Rápidos"  
✅ **Rastreabilidade:** Histórico e estágio do projeto documentados  
✅ **Acesso Rápido:** Links e referências organizados  
✅ **Manutenção:** Fácil atualização quando necessário  

---

## 📌 Próximos Passos Após Registrar

1. **Registre os Acessos:** Vá em "Minha Infraestrutura" e registre todas as credenciais
2. **Crie Tarefas:** Use o "Quadro Kanban" para organizar tarefas do projeto
3. **Atualize Regularmente:** Mantenha a descrição atualizada conforme o projeto evolui

---

**Dúvidas?** Consulte a documentação completa em `/docs` ou entre em contato com a equipe de desenvolvimento.

