#!/bin/bash
# Script para configurar o cron do worker de eventos

echo "=== Configuração do Cron - Event Queue Worker ==="
echo ""

# Verifica se já existe o cron configurado
if crontab -l 2>/dev/null | grep -q "event_queue_worker.php"; then
    echo "⚠️  Cron já está configurado!"
    echo ""
    echo "Cron atual:"
    crontab -l | grep "event_queue_worker.php"
    echo ""
    read -p "Deseja reconfigurar? (s/n): " resposta
    if [ "$resposta" != "s" ]; then
        echo "Operação cancelada."
        exit 0
    fi
    # Remove o cron antigo
    crontab -l | grep -v "event_queue_worker.php" | crontab -
fi

# Adiciona o novo cron
(crontab -l 2>/dev/null; echo "* * * * * cd ~/hub.pixel12digital.com.br && php scripts/event_queue_worker.php >> logs/event_worker.log 2>&1") | crontab -

echo "✅ Cron configurado com sucesso!"
echo ""
echo "Configuração:"
echo "  Frequência: A cada minuto"
echo "  Comando: cd ~/hub.pixel12digital.com.br && php scripts/event_queue_worker.php"
echo "  Log: ~/hub.pixel12digital.com.br/logs/event_worker.log"
echo ""
echo "Para verificar os logs:"
echo "  tail -f ~/hub.pixel12digital.com.br/logs/event_worker.log"
echo ""
echo "Para verificar o cron:"
echo "  crontab -l"
echo ""
echo "Para remover o cron:"
echo "  crontab -l | grep -v 'event_queue_worker.php' | crontab -"
