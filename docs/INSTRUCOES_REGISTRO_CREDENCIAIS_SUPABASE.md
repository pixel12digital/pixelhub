# Como Registrar Credenciais do Supabase no Pixel Hub

## 📍 Onde Registrar

### 1. No Projeto (Descrição)
- **Local:** Campo "Descrição / Notas Técnicas" ao criar/editar projeto
- **O que colocar:** Apenas informações gerais (sem senhas)
- **Exemplo:** Project Name, tipo de banco, problemas conhecidos

### 2. Em "Acessos Rápidos" (Credenciais Completas)
- **Local:** Menu lateral → "Minha Infraestrutura" → "Novo Acesso"
- **O que colocar:** TODAS as credenciais (serão criptografadas)
- **Categoria:** `banco`

---

## 🔐 Passo a Passo - Registrar Credenciais do Supabase

### Passo 1: Acesse "Minha Infraestrutura"
1. No menu lateral, clique em **"Minha Infraestrutura"**
2. Clique no botão **"Novo Acesso"**

### Passo 2: Preencha os Dados

**Categoria:** `banco`

**Label:** `Supabase - servico-pro (Produção)`
*(Use um nome descritivo que identifique facilmente)*

**URL:** `https://supabase.com/dashboard/project/servico-pro`
*(URL do painel do Supabase para acesso rápido)*

**Usuário:** `postgres.servico-pro`
*(Ou o usuário que aparecer no painel Supabase)*

**Senha:** `[SUA_SENHA_DO_SUPABASE]`
*(A senha será criptografada automaticamente - NÃO use senhas reais na documentação)*

**Notas:**
```
Projeto: servico-pro
Host: db.servico-pro.supabase.co
Porta: 5432
Database: postgres
Tipo: PostgreSQL
Connection String: postgresql://postgres.servico-pro:[senha]@db.servico-pro.supabase.co:5432/postgres?sslmode=require
```

### Passo 3: Salve
Clique em **"Salvar"** - as credenciais serão criptografadas automaticamente.

---

## 📋 Informações do Seu Projeto Supabase

**Project Name:** `servico-pro`

**Credenciais:**
- **Senha:** `[CONSULTE_O_PAINEL_SUPABASE]` *(NÃO coloque senhas reais na documentação)*
- **Host:** `db.servico-pro.supabase.co` (verificar no painel)
- **Porta:** `5432`
- **Database:** `postgres` (padrão)
- **Usuário:** `postgres.servico-pro` (verificar no painel)

**Onde encontrar no Supabase:**
1. Acesse https://supabase.com/dashboard
2. Selecione o projeto `servico-pro`
3. Vá em **Settings** → **Database**
4. Copie a **Connection String** ou os dados individuais

---

## 🔍 Como Consultar Depois

### Opção 1: Pela Lista de Projetos
1. Acesse **"Projetos & Tarefas"** → **"Lista de Projetos"**
2. Clique em **"📋 Detalhes"** no projeto
3. Veja todas as informações organizadas
4. Clique em **"🔐 Ver Credenciais (Acessos Rápidos)"** para acessar

### Opção 2: Direto em "Acessos Rápidos"
1. Menu lateral → **"Minha Infraestrutura"**
2. Filtre por categoria **"banco"**
3. Procure por **"Supabase - servico-pro"**
4. Clique para ver/editar credenciais

---

## ✅ Checklist de Registro

- [ ] Projeto criado no Pixel Hub com nome e slug
- [ ] Descrição preenchida (sem senhas)
- [ ] Credenciais registradas em "Acessos Rápidos" (categoria: banco)
- [ ] Label descritivo usado (ex: "Supabase - servico-pro")
- [ ] URL do painel Supabase adicionada
- [ ] Notas com informações técnicas adicionadas

---

## 🎯 Benefícios

✅ **Segurança:** Senhas criptografadas  
✅ **Acesso Rápido:** Todas as informações em um lugar  
✅ **Organização:** Fácil de encontrar quando necessário  
✅ **Histórico:** Informações preservadas e atualizáveis  

---

**Dica:** Use labels descritivos como "Supabase - servico-pro (Produção)" para facilitar a busca depois!

