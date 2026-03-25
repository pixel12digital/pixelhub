<?php

namespace PixelHub\Core;

/**
 * Normalizador de números de telefone para formato E.164
 */
class PhoneNormalizer
{
    /**
     * Converte um número de telefone brasileiro para o formato E.164 (+55XXXXXXXXXXX)
     * Retorna null se o número for inválido
     */
    public static function toE164OrNull(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        
        // Remover tudo que não é dígito
        $digits = preg_replace('/\D/', '', $phone);
        
        // Verificar se tem dígitos suficientes
        if (strlen($digits) < 10 || strlen($digits) > 13) {
            return null;
        }
        
        // Remover 0 inicial do DDD (se existir)
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $digits = substr($digits, 1);
        }
        
        // Adicionar código do Brasil +55 se não tiver
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            // 10 dígitos: DDD + telefone (fixo)
            // 11 dígitos: DDD + 9 + telefone (móvel)
            $digits = '55' . $digits;
        }
        
        // Verificar se tem o formato correto: 55 + DDD + telefone
        if (strlen($digits) === 12) {
            // 55 + DDD (2) + telefone (8) - fixo sem 9
            // Adicionar 9 após o DDD para tornar móvel
            $digits = '55' . substr($digits, 2, 2) . '9' . substr($digits, 4);
        } elseif (strlen($digits) === 13) {
            // 55 + DDD (2) + 9 + telefone (8) - móvel
            // Já está no formato correto
        } else {
            return null;
        }
        
        // Validar DDD brasileiro
        $ddd = substr($digits, 2, 2);
        if (!self::isValidBrazilianDDD($ddd)) {
            return null;
        }
        
        return '+' . $digits;
    }
    
    /**
     * Verifica se o DDD é válido no Brasil
     */
    private static function isValidBrazilianDDD(string $ddd): bool
    {
        // DDDs válidos no Brasil
        $validDDDs = [
            '11', '12', '13', '14', '15', '16', '17', '18', '19', // São Paulo
            '21', '22', '24', // Rio de Janeiro
            '27', '28', // Espírito Santo
            '31', '32', '33', '34', '35', '37', '38', // Minas Gerais
            '41', '42', '43', '44', '45', '46', // Paraná
            '47', '48', '49', // Santa Catarina
            '51', '53', '54', '55', // Rio Grande do Sul
            '61', // Distrito Federal
            '62', '64', // Goiás
            '63', // Tocantins
            '65', '66', // Mato Grosso
            '67', '68', // Mato Grosso do Sul
            '69', // Rondônia
            '71', '73', '74', '75', // Bahia
            '77', // Bahia (Oeste)
            '79', // Sergipe
            '81', '82', '83', '85', '87', '88', // Ceará
            '84', // Piauí
            '86', '89', // Maranhão
            '91', '92', '93', '94', '95', '96', '97', '98', '99', // Pará, Amazonas, Acre, Amapá, Roraima
        ];
        
        return in_array($ddd, $validDDDs);
    }
    
    /**
     * Formata número para exibição (XX) XXXXX-XXXX
     */
    public static function formatDisplay(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Converter para E.164 primeiro
        $e164 = self::toE164OrNull($phone);
        if (!$e164) {
            return $phone; // Retorna original se não conseguir normalizar
        }
        
        // Remover +55
        $digits = substr($e164, 3);
        
        // Formatar (XX) XXXXX-XXXX
        if (strlen($digits) === 11) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
        }
        
        return $e164;
    }
}
