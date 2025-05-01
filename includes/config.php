<?php
// Configurações do sistema
define('APP_NAME', 'Grupo Sandro Calhas LTDA');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/');
define('LOGO_URL', 'generated-icon.png');

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
define('DB_TYPE', 'pgsql'); // mysql ou pgsql

// Verifica se o arquivo de configuração local existe
$config_local = __DIR__ . '/config.local.php';
if (file_exists($config_local)) {
    // Se existir, inclui o arquivo de configuração local
    include($config_local);
} else {
    // Se não existir, usa as variáveis de ambiente ou valores padrão
    define('DB_HOST', getenv('PGHOST') ?: 'localhost');
    define('DB_NAME', getenv('PGDATABASE') ?: 'postgres');
    define('DB_USER', getenv('PGUSER') ?: 'postgres');
    define('DB_PASS', getenv('PGPASSWORD') ?: 'postgres');
    define('DB_PORT', getenv('PGPORT') ?: '5432');
}

// Configurações de data e hora
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros (desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>