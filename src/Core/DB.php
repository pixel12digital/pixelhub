<?php

namespace PixelHub\Core;

use PDO;
use PDOException;

/**
 * Classe para gerenciar conexão com o banco de dados
 */
class DB
{
    private static ?PDO $connection = null;

    /**
     * Obtém a conexão PDO (singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $options
            );
            
            error_log("Conexão com banco de dados estabelecida: {$config['database']}");
            
            return self::$connection;
        } catch (PDOException $e) {
            error_log("Erro ao conectar ao banco de dados: " . $e->getMessage());
            throw new \RuntimeException("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }

    /**
     * Fecha a conexão (útil para testes)
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
    }
}

