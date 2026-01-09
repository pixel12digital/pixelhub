<?php

namespace PixelHub\Core;

/**
 * Classe para carregar variáveis de ambiente do arquivo .env
 */
class Env
{
    private static array $loaded = [];

    /**
     * Carrega o arquivo .env se ainda não foi carregado
     * 
     * @param string $path Caminho do arquivo .env
     * @param bool $force Se true, força recarregar mesmo se já foi carregado
     */
    public static function load(string $path = __DIR__ . '/../../.env', bool $force = false): void
    {
        if (!empty(self::$loaded) && !$force) {
            return;
        }
        
        // Se forçar recarregamento, limpa o cache
        if ($force) {
            self::$loaded = [];
            $_ENV = array_filter($_ENV, function($key) {
                return !in_array($key, ['WPP_GATEWAY_BASE_URL', 'WPP_GATEWAY_SECRET', 'PIXELHUB_WHATSAPP_WEBHOOK_URL', 'PIXELHUB_WHATSAPP_WEBHOOK_SECRET']);
            }, ARRAY_FILTER_USE_KEY);
        }

        if (!file_exists($path)) {
            throw new \RuntimeException("Arquivo .env não encontrado em: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignora comentários
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Separa chave e valor
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove aspas externas se existirem (mas preserva o $ no início se houver)
                // Ex: "$aact_prod_..." -> mantém o $, mas remove aspas externas se houver
                // IMPORTANTE: Valores que começam com $ (como API keys do Asaas) são preservados
                // O $ não é tratado como variável aqui, é parte do valor literal
                if (strlen($value) >= 2) {
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }
                
                // Define como variável de ambiente
                // Se force=true, sempre atualiza. Caso contrário, só atualiza se não existir
                if ($force || !isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                    // putenv() - armazena diretamente (o $ no valor é preservado como literal)
                    // No Windows, putenv pode ter problemas com $, mas $_ENV é confiável
                    @putenv("{$key}={$value}");
                }
            }
        }

        self::$loaded = $_ENV;
    }

    /**
     * Obtém uma variável de ambiente
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Verifica se está em modo debug
     */
    public static function isDebug(): bool
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }
}

