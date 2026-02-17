<?php

/**
 * Script de Diagnóstico: Conexão com Banco de Dados
 * 
 * Este script ajuda a diagnosticar problemas de conexão com o RDS
 */

echo "=== DIAGNÓSTICO DE CONEXÃO COM BANCO DE DADOS ===\n\n";

// 1. Testar resolução DNS
$host = 'pixelhub-db.c58i8jm2i1sa.us-east-1.rds.amazonaws.com';
echo "1. Testando resolução DNS para: $host\n";

$ip = gethostbyname($host);
if ($ip === $host) {
    echo "❌ FALHA: Não foi possível resolver o hostname\n";
    
    // Tentar ping
    echo "\n2. Tentando ping para o hostname...\n";
    $pingResult = shell_exec("ping -c 3 $host 2>&1");
    echo $pingResult;
    
    // Tentar nslookup
    echo "\n3. Tentando nslookup...\n";
    $nslookupResult = shell_exec("nslookup $host 2>&1");
    echo $nslookupResult;
    
    // Verificar /etc/hosts
    echo "\n4. Verificando /etc/hosts...\n";
    if (file_exists('/etc/hosts')) {
        $hosts = file_get_contents('/etc/hosts');
        echo $hosts;
    }
    
    // Tentar conectar com IP direto (se conhecido)
    echo "\n5. Tentando conectar com IPs conhecidos...\n";
    $possibleIPs = [
        '52.72.167.114',
        '54.235.193.81',
        '172.31.0.1',
        'localhost'
    ];
    
    foreach ($possibleIPs as $ip) {
        echo "Tentando: $ip\n";
        try {
            $pdo = new PDO("mysql:host=$ip;dbname=information_schema", 'pixelhub', 'P@ssw0rd!@#2024');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✅ SUCESSO: Conectado com $ip\n";
            
            // Listar databases
            $stmt = $pdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "Bases disponíveis: " . implode(', ', $databases) . "\n";
            
            if (in_array('pixelhub', $databases)) {
                echo "✅ Base 'pixelhub' encontrada!\n";
                return $ip; // Retornar IP funcional
            }
        } catch (Exception $e) {
            echo "❌ Falha: " . $e->getMessage() . "\n";
        }
    }
    
} else {
    echo "✅ SUCESSO: DNS resolvido para $ip\n";
    
    // Testar conexão
    echo "\n2. Testando conexão com banco...\n";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=pixelhub;charset=utf8mb4", 'pixelhub', 'P@ssw0rd!@#2024');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ SUCESSO: Conectado ao banco pixelhub\n";
        
        // Listar tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tabelas: " . implode(', ', array_slice($tables, 0, 10)) . "...\n";
        
        return $host;
    } catch (Exception $e) {
        echo "❌ FALHA na conexão: " . $e->getMessage() . "\n";
    }
}

echo "\n=== RECOMENDAÇÕES ===\n";
echo "1. Verifique se o security group permite acesso da VPS ao RDS\n";
echo "2. Verifique se a VPS tem saída para internet (porta 3306)\n";
echo "3. Tente usar o IP direto do RDS se o DNS não funcionar\n";
echo "4. Verifique as credenciais do banco\n";

return null;
