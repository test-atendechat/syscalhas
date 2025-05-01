<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');
require_once('includes/notificacoes.php');

// Verificar autenticação
verificarAutenticacao();

// Adicionar uma notificação de teste
$resultado = adicionarNotificacao(
    "Esta é uma notificação de teste",
    'info',
    'dashboard.php'
);

// Redirecionar para o dashboard
header('Location: dashboard.php?mensagem=notificacao_adicionada');
exit;
