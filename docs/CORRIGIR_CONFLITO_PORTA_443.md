# 🔧 Corrigir Conflito de Porta 443 - Docker vs Nginx

## Problema Identificado

O script foi executado com sucesso, mas há um **conflito crítico**:

1. ✅ **Porta 443 está sendo usada pelo Docker** (docker-proxy)
2. ⚠️ **Nginx não consegue escutar na porta 443** (erro: Address already in use)
3. ⚠️ **Conflito de configuração** (server_name duplicado)

## 🎯 Solução

Precisamos identificar qual container Docker está usando a porta 443 e ajustar a configuração.

---

## 📋 Comandos para Executar (Nesta Ordem)

### 1. Identificar qual container Docker está usando a porta 443

```bash
docker ps --format "table {{.ID}}\t{{.Names}}\t{{.Ports}}" | grep 443
```

**O que esperamos:** Ver qual container está mapeando a porta 443

---

### 2. Ver todos os containers rodando

```bash
docker ps
```

**O que esperamos:** Lista completa de containers, especialmente AzuraCast

---

### 3. Verificar configurações do Nginx que usam wpp.pixel12digital.com.br

```bash
grep -r "wpp.pixel12digital.com.br" /etc/nginx/ --include="*.conf"
```

**O que esperamos:** Ver todas as configurações que mencionam o domínio

---

### 4. Ver qual processo está usando a porta 443

```bash
ss -tlnp | grep :443
lsof -i :443
```

**O que esperamos:** Confirmar que é docker-proxy

---

### 5. Verificar se AzuraCast está configurado para usar porta 443

```bash
docker inspect $(docker ps -q --filter "name=azuracast") | grep -A 10 -i "443\|port"
```

ou

```bash
docker ps | grep azura
docker inspect <CONTAINER_ID_AZURACAST> | grep -A 20 "Ports"
```

---

### 6. Ver configuração atual criada pelo script

```bash
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

**O que esperamos:** Ver a configuração que foi criada

---

### 7. Verificar se há outra configuração para o mesmo domínio

```bash
find /etc/nginx -name "*.conf" -exec grep -l "wpp.pixel12digital.com.br" {} \;
```

**O que esperamos:** Lista de arquivos que contêm o domínio

---

## 🔍 Análise dos Resultados

Com base nos resultados, temos 3 cenários possíveis:

### Cenário A: AzuraCast está usando porta 443

**Solução:** Configurar Nginx para usar proxy reverso através do AzuraCast ou usar outra porta externa.

### Cenário B: Outro serviço Docker está usando porta 443

**Solução:** Identificar o serviço e decidir se deve usar outra porta ou remover o mapeamento.

### Cenário C: Nginx já tem configuração para o domínio

**Solução:** Remover configuração duplicada ou ajustar a existente.

---

## 🛠️ Soluções Possíveis

### Solução 1: Usar Nginx como Proxy Reverso (Recomendado)

Se o Docker está usando 443, podemos configurar o Nginx para escutar em outra porta (ex: 8443) e fazer proxy reverso, OU configurar o Docker para não usar 443 diretamente.

### Solução 2: Remover Mapeamento Docker da Porta 443

Se não for necessário, remover o mapeamento do Docker e deixar o Nginx usar 443.

### Solução 3: Usar Porta Alternativa para o Gateway

Configurar o gateway para usar porta 8443 externamente e manter 443 para outros serviços.

---

## ⚠️ IMPORTANTE

**NÃO execute nenhuma ação destrutiva** até identificarmos qual serviço está usando a porta 443.

Execute os comandos acima e compartilhe os resultados para criarmos a solução específica.

