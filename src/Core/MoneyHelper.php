<?php

namespace PixelHub\Core;

/**
 * Helper para normalização de valores monetários
 */
class MoneyHelper
{
    /**
     * Normaliza um valor monetário de entrada (BR ou decimal) para float
     * 
     * Aceita:
     * - "39,90" → 39.90
     * - "49.9" → 49.90
     * - "100" → 100.00
     * - "1.234,56" → 1234.56
     * 
     * @param string $input Valor de entrada
     * @return float Valor normalizado
     */
    public static function normalizeAmount(string $input): float
    {
        $value = trim($input);

        if ($value === '') {
            return 0.0;
        }

        // Remove espaços
        $value = str_replace(' ', '', $value);

        // Caso BR: 1.234,56 → 1234.56
        if (strpos($value, ',') !== false) {
            // Remove separador de milhar (.)
            $value = str_replace('.', '', $value);
            // Troca vírgula decimal por ponto
            $value = str_replace(',', '.', $value);
        }

        // Agora deve estar em formato 1234.56
        return (float) $value;
    }
}

