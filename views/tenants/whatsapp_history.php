<?php
ob_start();

$tenant = $tenant ?? null;
$whatsappTimeline = $whatsappTimeline ?? [];
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Histórico de WhatsApp – <?= htmlspecialchars($tenant['name'] ?? 'Cliente') ?></h2>
        <p>Visualização completa do histórico de mensagens enviadas</p>
    </div>
    <div>
        <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id']) ?>" 
           style="background: #023A8D; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
            ← Voltar ao Cliente
        </a>
    </div>
</div>

<div class="card">
    <?php if (empty($whatsappTimeline)): ?>
        <p style="color: #666; text-align: center; padding: 40px 20px;">
            Nenhum histórico de WhatsApp registrado para este cliente.
        </p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Data/Hora</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Origem</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Template</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Observação</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($whatsappTimeline as $index => $item): ?>
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $sentAt = $item['sent_at'] ?? null;
                        echo $sentAt ? htmlspecialchars(date('d/m/Y H:i', strtotime($sentAt))) : '-';
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $source = $item['source'] ?? 'generic';
                        $sourceLabel = $source === 'billing' ? 'Financeiro' : 'Visão Geral';
                        echo htmlspecialchars($sourceLabel);
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $templateId = $item['template_id'] ?? null;
                        $templateName = $item['template_name'] ?? null;
                        
                        if ($templateId === null && $source === 'generic') {
                            echo '<span style="color: #999;">Sem template / mensagem livre</span>';
                        } elseif ($templateName) {
                            echo htmlspecialchars($templateName);
                        } else {
                            echo '<span style="color: #999;">-</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php
                        $message = $item['message'] ?? $item['message_full'] ?? '';
                        $messageFull = $item['message_full'] ?? $message;
                        
                        // Preview de 100-120 caracteres
                        $preview = mb_substr($message, 0, 120);
                        if (mb_strlen($message) > 120) {
                            $preview .= '...';
                        }
                        
                        if ($source === 'billing') {
                            // Para billing, mostra descrição + preview da mensagem se houver
                            $description = $item['description'] ?? '';
                            echo htmlspecialchars($description);
                            if (!empty($message)) {
                                echo '<br><span style="color: #666; font-size: 12px;">' . htmlspecialchars($preview) . '</span>';
                            }
                        } else {
                            // Para generic, mostra preview da mensagem
                            echo '<span style="color: #666; font-size: 13px;">' . htmlspecialchars($preview) . '</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <?php if (!empty($messageFull)): ?>
                            <button onclick="openMessageModal(<?= $index ?>)" 
                                    style="background: #023A8D; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">
                                Ver mensagem
                            </button>
                            <!-- Dados da mensagem completa (ocultos) -->
                            <div id="message-data-<?= $index ?>" style="display: none;" 
                                 data-sent-at="<?= htmlspecialchars($item['sent_at'] ?? '', ENT_QUOTES) ?>"
                                 data-source="<?= htmlspecialchars($source, ENT_QUOTES) ?>"
                                 data-source-label="<?= htmlspecialchars($sourceLabel, ENT_QUOTES) ?>"
                                 data-template-name="<?= htmlspecialchars($templateName ?? ($templateId === null && $source === 'generic' ? 'Sem template / mensagem livre' : 'Sem template'), ENT_QUOTES) ?>"
                                 data-phone="<?= htmlspecialchars($item['phone'] ?? '', ENT_QUOTES) ?>"
                                 data-message-full="<?= htmlspecialchars($messageFull, ENT_QUOTES) ?>">
                            </div>
                        <?php else: ?>
                            <span style="color: #999; font-size: 13px;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal de Detalhes da Mensagem -->
<div id="message-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 700px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
        <button onclick="closeMessageModal()" 
                style="position: absolute; top: 15px; right: 15px; background: #c33; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; line-height: 1;">×</button>
        
        <h2 style="margin: 0 0 20px 0; color: #023A8D;">Detalhes da Mensagem</h2>
        
        <div id="message-detail-content" style="color: #666;">
            <!-- Conteúdo será preenchido via JavaScript -->
        </div>
    </div>
</div>

<script>
// Funções do modal de detalhes da mensagem
function openMessageModal(index) {
    const dataDiv = document.getElementById('message-data-' + index);
    if (!dataDiv) {
        console.error('Dados da mensagem não encontrados para índice:', index);
        return;
    }

    const sentAt = dataDiv.getAttribute('data-sent-at') || '';
    const sourceLabel = dataDiv.getAttribute('data-source-label') || '';
    const templateName = dataDiv.getAttribute('data-template-name') || 'Sem template';
    const phone = dataDiv.getAttribute('data-phone') || '';
    const messageFull = dataDiv.getAttribute('data-message-full') || '';

    // Formata data/hora
    let sentAtFormatted = '-';
    if (sentAt) {
        try {
            const date = new Date(sentAt);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            sentAtFormatted = `${day}/${month}/${year} ${hours}:${minutes}`;
        } catch (e) {
            sentAtFormatted = sentAt;
        }
    }

    // Formata telefone (se houver)
    let phoneFormatted = phone;
    if (phone && phone.length >= 10) {
        // Formata como (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
        const digits = phone.replace(/\D/g, '');
        if (digits.length === 11) {
            phoneFormatted = `(${digits.substring(0, 2)}) ${digits.substring(2, 7)}-${digits.substring(7)}`;
        } else if (digits.length === 10) {
            phoneFormatted = `(${digits.substring(0, 2)}) ${digits.substring(2, 6)}-${digits.substring(6)}`;
        }
    }

    // Monta conteúdo do modal
    let content = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
    content += '<tr><td style="padding: 8px; font-weight: 600; width: 150px; border-bottom: 1px solid #eee;">Data/Hora:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(sentAtFormatted) + '</td></tr>';
    content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Origem:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(sourceLabel) + '</td></tr>';
    content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Template:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(templateName) + '</td></tr>';
    if (phoneFormatted) {
        content += '<tr><td style="padding: 8px; font-weight: 600; border-bottom: 1px solid #eee;">Telefone:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">' + escapeHtml(phoneFormatted) + '</td></tr>';
    }
    content += '</table>';

    content += '<div style="margin-top: 20px;">';
    content += '<h3 style="margin: 0 0 10px 0; font-size: 16px; color: #023A8D;">Mensagem Completa:</h3>';
    // Preserva quebras de linha usando <pre> ou nl2br
    const messageEscaped = escapeHtml(messageFull);
    const messageWithBreaks = messageEscaped.replace(/\n/g, '<br>');
    content += '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #023A8D; white-space: pre-wrap; word-wrap: break-word; font-family: inherit; line-height: 1.6;">' + messageWithBreaks + '</div>';
    content += '</div>';

    document.getElementById('message-detail-content').innerHTML = content;
    document.getElementById('message-detail-modal').style.display = 'block';
}

function closeMessageModal() {
    document.getElementById('message-detail-modal').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Fecha modal ao clicar fora
document.getElementById('message-detail-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMessageModal();
    }
});
</script>

<?php
$content = ob_get_clean();
$title = 'Histórico WhatsApp – ' . htmlspecialchars($tenant['name'] ?? 'Cliente');

require __DIR__ . '/../layout/main.php';
?>


