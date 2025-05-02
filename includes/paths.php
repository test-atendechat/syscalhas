<?php
/**
 * Configuração de caminhos para o sistema
 * Este arquivo define constantes para os caminhos dos diretórios do sistema
 */

// Caminho absoluto para a raiz do projeto
define('ROOT_PATH', dirname(__DIR__));

// Caminhos para os diretórios principais
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('CSS_PATH', ROOT_PATH . '/css');
define('JS_PATH', ROOT_PATH . '/js');
define('IMG_PATH', ROOT_PATH . '/img');
define('AJAX_PATH', ROOT_PATH . '/ajax');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('UTILS_PATH', ROOT_PATH . '/utils');
define('NOTIFICACOES_PATH', ROOT_PATH . '/notificacoes');

// Caminhos para os módulos específicos
define('AGENDAMENTOS_PATH', MODULES_PATH . '/agendamentos');
define('CAIXA_PATH', MODULES_PATH . '/caixa');
define('CLIENTES_PATH', MODULES_PATH . '/clientes');
define('COLABORADORES_PATH', MODULES_PATH . '/colaboradores');
define('ESTOQUE_PATH', MODULES_PATH . '/estoque');
define('FINANCEIRO_PATH', MODULES_PATH . '/financeiro');
define('ORCAMENTOS_PATH', MODULES_PATH . '/orcamentos');
define('PRODUTOS_PATH', MODULES_PATH . '/estoque');
define('RELATORIOS_PATH', MODULES_PATH . '/relatorios');
define('SISTEMA_PATH', MODULES_PATH . '/sistema');
define('USUARIOS_PATH', MODULES_PATH . '/usuarios');
define('VENDAS_PATH', MODULES_PATH . '/vendas');

// URLs para os diretórios (para uso em links e redirecionamentos)
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/');
define('CSS_URL', BASE_URL . 'css/');
define('JS_URL', BASE_URL . 'js/');
define('IMG_URL', BASE_URL . 'img/');

// URLs amigáveis para os módulos
define('CLIENTES_URL', BASE_URL . 'clientes');
define('ORCAMENTOS_URL', BASE_URL . 'orcamentos');
define('VENDAS_URL', BASE_URL . 'vendas');
define('PRODUTOS_URL', BASE_URL . 'produtos');
define('ESTOQUE_URL', BASE_URL . 'estoque');
define('AGENDAMENTOS_URL', BASE_URL . 'agendamentos');
define('COLABORADORES_URL', BASE_URL . 'colaboradores');
define('CAIXA_URL', BASE_URL . 'caixa');
define('CONTAS_URL', BASE_URL . 'contas');
define('RELATORIOS_URL', BASE_URL . 'relatorios');
define('USUARIOS_URL', BASE_URL . 'usuarios');
define('DASHBOARD_URL', BASE_URL . 'dashboard');
define('CONFIGURACOES_URL', BASE_URL . 'configuracoes');
?>