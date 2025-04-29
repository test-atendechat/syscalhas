<?php
require_once('includes/config.php');
require_once('includes/auth.php');

// Faz o logout do usuário
fazerLogout();

// Redireciona para a página de login com mensagem
header('Location: login.php?mensagem=logout');
exit;
?>
