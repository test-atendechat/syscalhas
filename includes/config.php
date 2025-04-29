<?php
// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do sistema
define('APP_NAME', 'Sistema de Orçamentos para Calhas');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/');

// Configurações do banco de dados
define('DB_TYPE', 'pgsql'); // mysql ou pgsql
define('DB_HOST', getenv('PGHOST') ?: 'localhost');
define('DB_NAME', getenv('PGDATABASE') ?: 'postgres');
define('DB_USER', getenv('PGUSER') ?: 'postgres');
define('DB_PASS', getenv('PGPASSWORD') ?: '');
define('DB_PORT', getenv('PGPORT') ?: '5432');

// Configurações de email
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'seu-email@gmail.com');
define('MAIL_PASS', 'sua-senha-ou-token');
define('MAIL_FROM', 'seu-email@gmail.com');
define('MAIL_FROM_NAME', APP_NAME);

// Configurações de data e hora
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros (desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Em produção, comentar as linhas acima e descomentar estas:
// ini_set('display_errors', 0);
// ini_set('display_startup_errors', 0);
// error_reporting(0);
?>