<?php
/**
 * Arquivo auxiliar para verificação de permissões
 * 
 * Este arquivo é apenas um atalho para verificar permissões nos arquivos principais
 * usando a função verificarPermissao() definida em includes/functions.php
 */

// Impedir execução direta
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: index.php');
    exit;
}

// Verificar se a função de permissão já existe
if (!function_exists('verificarPermissao')) {
    require_once('includes/functions.php');
}

// Aqui não definimos mais a função verificarPermissao, pois ela já está em functions.php