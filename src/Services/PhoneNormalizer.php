<?php

namespace PixelHub\Services;

/**
 * Service para normalização de telefones
 * 
 * Regra principal: NÃO força "9", usa apenas o que o provedor/gateway fornece.
 * O identificador canônico deve ser baseado no que o gateway entrega no inbound.
 */
class PhoneNormalizer
{
    /**
     * Normaliza telefone para formato E.164 (apenas dígitos, sem +)
     * 
     * Regras:
     * - Remove tudo que não é dígito
     * - Se começar com 55, mantém
     * - Se tiver 10 ou 11 dígitos e não começar com 55, prefixa 55
     * - Aceita 12/13 dígitos também se já vier com 55
     * - Retorna null se insuficiente
     * 
     * PROIBIDO: adicionar "9" automaticamente no Brasil
     * PROIBIDO: remover dígitos com heurística
     * PROIBIDO: assumir que todo BR tem 11 dígitos
     * 
     * 🔍 PASSO 2: Normalização com logs antes/depois
     * 
     * @param string|null $raw Telefone original (pode vir do gateway com @c.us, etc)
     * @param string $country Código do país (padrão: BR)
     * @param bool $logEnabled Se true, loga antes/depois (padrão: true)
     * @return string|null Telefone normalizado (ex: 554796164699) ou null se inválido
     */
    public static function toE164OrNull(?string $raw, string $country = 'BR', bool $logEnabled = true): ?string
    {
        $rawForLog = $raw;
        $rawThreadIdCandidate = $raw;
        
        if (empty($raw)) {
            if ($logEnabled) {
                error_log('[HUB_PHONE_NORM] raw_from=NULL normalized_from=NULL');
            }
            return null;
        }

        // Remove sufixos @c.us, @s.whatsapp.net, @lid antes de processar
        $rawCleaned = preg_replace('/@.*$/', '', $raw);
        if ($logEnabled) {
            error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' raw_thread_id_candidate=' . $rawThreadIdCandidate);
        }

        // Remove tudo que não é dígito (incluindo @, espaços, etc)
        $digits = preg_replace('/[^0-9]/', '', $rawCleaned);

        if (empty($digits)) {
            if ($logEnabled) {
                error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=NULL reason=empty_digits');
            }
            return null;
        }

        $len = strlen($digits);

        // Se já começa com 55 (DDI do Brasil) e tem tamanho válido
        if (substr($digits, 0, 2) === '55') {
            // Aceita 12 ou 13 dígitos (55 + DDD + número com/sem 9º dígito)
            // Exemplos válidos:
            // - 12 dígitos: 554796164699 (55 + 47 + 96164699 - sem 9º dígito)
            // - 13 dígitos: 5511999999999 (55 + 11 + 999999999 - com 9º dígito)
            if ($len >= 12 && $len <= 13) {
                if ($logEnabled) {
                    error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=' . $digits . ' normalized_thread_id_candidate=' . $digits);
                }
                return $digits;
            }
            
            // Se tem mais de 13 dígitos, pode ser formato inválido
            // Mas se tem pelo menos 12, ainda aceita (pode ter erros de formatação)
            if ($len > 13) {
                // Log mas aceita os primeiros 13 dígitos como fallback seguro
                $normalized = substr($digits, 0, 13);
                error_log("[PhoneNormalizer] Número com mais de 13 dígitos após 55: {$digits} (len={$len})");
                if ($logEnabled) {
                    error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=' . $normalized . ' normalized_thread_id_candidate=' . $normalized . ' reason=truncated_to_13_digits');
                }
                return $normalized;
            }
            
            // Menos de 12 dígitos após remover formatação é inválido
            if ($logEnabled) {
                error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=NULL reason=less_than_12_digits_after_55');
            }
            return null;
        }

        // Se não começa com 55
        if ($country === 'BR') {
            // Para Brasil: se tem 10 ou 11 dígitos, prefixa 55
            // 10 dígitos: DDD (2) + número (8) - telefone fixo
            // 11 dígitos: DDD (2) + número (9) - celular
            if ($len === 10 || $len === 11) {
                $normalized = '55' . $digits;
                if ($logEnabled) {
                    error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=' . $normalized . ' normalized_thread_id_candidate=' . $normalized);
                }
                return $normalized;
            }
            
            // Se tem menos de 10 dígitos, é inválido (não tem DDD completo)
            if ($len < 10) {
                error_log("[PhoneNormalizer] Número BR com menos de 10 dígitos: {$digits} (len={$len})");
                if ($logEnabled) {
                    error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=NULL reason=less_than_10_digits');
                }
                return null;
            }
            
            // Se tem mais de 11 dígitos e não começa com 55, pode ser formato internacional
            // Retorna null para não inventar DDI
            if ($len > 11) {
                error_log("[PhoneNormalizer] Número BR com mais de 11 dígitos sem DDI: {$digits} (len={$len})");
                if ($logEnabled) {
                    error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=NULL reason=more_than_11_digits_without_ddi');
                }
                return null;
            }
        }

        // Para outros países, retorna null se não começar com DDI
        // (não inventamos DDI para outros países)
        if ($logEnabled) {
            error_log('[HUB_PHONE_NORM] raw_from=' . $rawForLog . ' normalized_from=NULL reason=not_brazil_and_no_ddi');
        }
        return null;
    }
}

