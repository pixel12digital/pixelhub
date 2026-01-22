# 🔍 Problema Identificado: QR Code imobsites

## 📋 O Que Queremos (Resultado Esperado)

**Objetivo:** Conectar a sessão "imobsites" ao WhatsApp, gerando um QR code que possa ser escaneado pelo celular para conectar a conta.

**Resultado esperado:**
- ✅ Sessão "imobsites" criada no gateway-wrapper
- ✅ Sessão "imobsites" criada no WPPConnect
- ✅ QR code gerado e exibido na UI
- ✅ Usuário escaneia QR code e conecta WhatsApp

---

## ❌ Problema Real Identificado

**Erro nos logs do WPPConnect:**
```
error:    [ImobSites:browser] Error no open browser
error:    [ImobSites:browser] Failed to launch the browser process:  Code: 21
```

### O Que Está Acontecendo:

1. ✅ **Gateway-wrapper cria a sessão localmente** - Funciona
2. ✅ **Gateway-wrapper chama o WPPConnect** - Funciona (agora vemos nos logs)
3. ❌ **WPPConnect tenta abrir o navegador (browser) para gerar QR code** - **FALHA**
4. ❌ **QR code não é gerado** porque o browser não abre

### Causa Raiz:

O WPPConnect precisa abrir um navegador headless (Chromium/Chrome) para gerar o QR code do WhatsApp. O erro "Failed to launch the browser process: Code: 21" indica que:

- **Falta dependências do Chromium/Chrome** no container
- **Ou problema de permissões** no container
- **Ou falta de recursos** (memória, espaço em disco)
- **Ou problema de configuração** do ambiente do container

---

## ✅ O Que Já Foi Feito

### 1. Diagnóstico Completo
- ✅ Verificamos que containers estão na mesma rede Docker
- ✅ Confirmamos comunicação entre containers (ping OK)
- ✅ Verificamos que gateway-wrapper consegue chamar WPPConnect
- ✅ Identificamos que "pixel12digital" funciona (já estava conectada)

### 2. Tentativas de Solução
- ✅ Conectamos WPPConnect à mesma rede do gateway-wrapper
- ✅ Reiniciamos o gateway-wrapper
- ✅ Deletamos e recriamos a sessão "imobsites" várias vezes
- ✅ Tentamos diferentes endpoints e métodos
- ✅ Verificamos logs detalhados de ambos os containers

### 3. Descoberta do Problema Real
- ✅ Identificamos que o WPPConnect **está recebendo** a requisição
- ✅ Confirmamos que o WPPConnect **está tentando** criar a sessão
- ❌ Descobrimos que o WPPConnect **falha ao abrir o browser** (Code: 21)

---

## 🛠️ Soluções Possíveis

### Solução 1: Verificar Dependências do Container WPPConnect

O container pode estar faltando dependências do Chromium:

```bash
# Verificar se Chromium está instalado
docker exec wppconnect-server which chromium || docker exec wppconnect-server which chromium-browser || echo "Chromium não encontrado"

# Verificar se há pacotes relacionados
docker exec wppconnect-server dpkg -l | grep -i chromium || echo "Nenhum pacote Chromium encontrado"

# Verificar variáveis de ambiente relacionadas
docker exec wppconnect-server env | grep -i "chrome\|chromium\|browser\|display"
```

### Solução 2: Verificar Permissões e Recursos

```bash
# Verificar espaço em disco
docker exec wppconnect-server df -h

# Verificar memória disponível
docker stats wppconnect-server --no-stream

# Verificar permissões do diretório de sessões
docker exec wppconnect-server ls -la /sessions/ 2>/dev/null || docker exec wppconnect-server ls -la ./sessions/ 2>/dev/null
```

### Solução 3: Verificar Configuração do WPPConnect

```bash
# Verificar variáveis de ambiente do WPPConnect
docker exec wppconnect-server env | grep -i "wpp\|browser\|headless\|display" | sort

# Verificar logs completos do WPPConnect
docker logs wppconnect-server --tail 100 | grep -i "browser\|chromium\|error\|fail" | tail -30
```

### Solução 4: Reiniciar Container WPPConnect

Pode ser um problema temporário de estado:

```bash
# Reiniciar WPPConnect
docker restart wppconnect-server

# Aguardar inicialização
sleep 15

# Tentar criar sessão novamente
SECRET="d2c9f9c01915b35baf795808b59c94e92338410639e43329a80a2ce860f3cf54"
SESSION="imobsites"
CONTAINER_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' gateway-wrapper)

curl -s -X POST -H "X-Gateway-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{"channel": "imobsites"}' \
  "http://$CONTAINER_IP:3000/api/channels" | jq '.'

sleep 5

# Verificar logs
docker logs wppconnect-server --tail 30 | grep -i "ImobSites\|browser\|error"
```

### Solução 5: Verificar Imagem Docker do WPPConnect

A imagem pode estar incompleta ou corrompida:

```bash
# Verificar imagem atual
docker images | grep wppconnect

# Verificar se há atualizações disponíveis
# (depende de onde a imagem está hospedada)
```

---

## 🎯 Próximos Passos Recomendados

1. **Imediato:** Executar Solução 1 (verificar dependências)
2. **Se faltar Chromium:** Instalar ou usar imagem Docker que inclua Chromium
3. **Se for problema de permissões:** Ajustar permissões do container
4. **Se for problema de recursos:** Aumentar memória/espaço disponível

---

## 📊 Comparação: pixel12digital vs imobsites

| Aspecto | pixel12digital | imobsites |
|---------|---------------|-----------|
| Status no gateway-wrapper | ✅ Criada | ✅ Criada |
| Chamada ao WPPConnect | ✅ Funciona | ✅ Funciona |
| WPPConnect recebe requisição | ✅ Sim | ✅ Sim |
| Browser abre no WPPConnect | ✅ Sim (já estava conectada) | ❌ **FALHA (Code: 21)** |
| QR code gerado | ✅ Sim | ❌ Não (porque browser não abre) |
| Sessão conectada | ✅ Sim | ❌ Não |

---

## 🔧 Comando de Diagnóstico Completo

Execute este comando para diagnóstico completo:

```bash
echo "=== 1. Verificar Chromium no WPPConnect ==="
docker exec wppconnect-server which chromium 2>/dev/null || \
docker exec wppconnect-server which chromium-browser 2>/dev/null || \
docker exec wppconnect-server which google-chrome 2>/dev/null || \
echo "❌ Nenhum browser encontrado"

echo -e "\n=== 2. Verificar pacotes instalados ==="
docker exec wppconnect-server dpkg -l 2>/dev/null | grep -i "chrom\|browser" | head -10 || \
docker exec wppconnect-server rpm -qa 2>/dev/null | grep -i "chrom\|browser" | head -10 || \
echo "Não foi possível verificar pacotes"

echo -e "\n=== 3. Verificar variáveis de ambiente ==="
docker exec wppconnect-server env | grep -i "chrome\|chromium\|browser\|display\|headless" | sort

echo -e "\n=== 4. Verificar espaço e recursos ==="
echo "Espaço em disco:"
docker exec wppconnect-server df -h | head -5
echo -e "\nMemória:"
docker stats wppconnect-server --no-stream

echo -e "\n=== 5. Verificar logs de erro completos ==="
docker logs wppconnect-server --tail 100 | grep -i "browser\|chromium\|error.*21\|failed.*launch" | tail -20
```

---

**Execute o comando de diagnóstico completo acima e me envie a saída para identificarmos a causa exata do erro Code: 21.**

