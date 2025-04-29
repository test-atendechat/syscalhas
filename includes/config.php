<?php
// Configurações da aplicação
define('APP_NAME', 'Sistema de Orçamento de Calhas');
define('APP_VERSION', '1.0.0');

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_calhas');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações de diretório e URL
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');

// Configurações de sessão
session_start();

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros (Desative em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
