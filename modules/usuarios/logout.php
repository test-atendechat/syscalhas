<?php
require_once('includes/config.php');
require_once('includes/auth.php');

// Fazer logout
fazerLogout();

// Redirecionar para a página de login
header('Location: login.php');
exit;
?>