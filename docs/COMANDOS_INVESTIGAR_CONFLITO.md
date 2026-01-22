# 🔍 Comandos para Investigar Conflito de Porta 443

Execute estes comandos **nesta ordem** e compartilhe os resultados:

---

## 1. Identificar Container Docker usando porta 443

```bash
docker ps --format "table {{.ID}}\t{{.Names}}\t{{.Image}}\t{{.Ports}}" | grep -E "443|ID"
```

---

## 2. Ver todos os containers rodando

```bash
docker ps -a
```

---

## 3. Verificar mapeamentos de porta do Docker

```bash
docker ps --format "{{.Names}}: {{.Ports}}" | grep 443
```

---

## 4. Ver qual processo está usando porta 443

```bash
ss -tlnp | grep :443
```

---

## 5. Ver todas as configurações Nginx para wpp.pixel12digital.com.br

```bash
find /etc/nginx -type f -name "*.conf" -exec grep -l "wpp.pixel12digital.com.br" {} \;
```

---

## 6. Ver conteúdo de cada configuração encontrada

```bash
# Primeiro, liste os arquivos:
find /etc/nginx -type f -name "*.conf" -exec grep -l "wpp.pixel12digital.com.br" {} \;

# Depois, para cada arquivo listado, execute:
cat /caminho/do/arquivo.conf
```

---

## 7. Ver configuração criada pelo script

```bash
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

---

## 8. Verificar se AzuraCast tem configuração para o domínio

```bash
docker exec $(docker ps -q --filter "name=azuracast" | head -1) cat /etc/nginx/azuracast.conf 2>/dev/null | grep -i "wpp\|pixel12digital" || echo "Não encontrado ou container não existe"
```

---

## 9. Ver logs do Nginx sobre o conflito

```bash
grep -i "conflicting\|bind.*443" /var/log/nginx/error.log | tail -20
```

---

## 10. Verificar se há docker-compose usando porta 443

```bash
find /root /home -name "docker-compose.yml" -o -name "docker-compose.yaml" 2>/dev/null | head -5
```

Se encontrar arquivos, verifique o conteúdo:
```bash
grep -A 5 -B 5 "443" /caminho/do/docker-compose.yml
```

---

## 📊 Após Executar

Compartilhe os resultados de **todos os comandos** para criarmos a solução específica.

**Foco especial nos comandos: 1, 3, 5, 6 e 7**

