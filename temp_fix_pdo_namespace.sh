#!/bin/bash
# Script para corrigir namespace PDO no ConversationService.php

cd ~/hub.pixel12digital.com.br

echo "Aplicando correção de namespace PDO..."

sed -i 's/fetchAll(PDO::FETCH_ASSOC)/fetchAll(\\PDO::FETCH_ASSOC)/g' src/Services/ConversationService.php

echo "✓ Correção aplicada"
echo ""
echo "Executando worker novamente..."

php scripts/event_queue_worker.php
