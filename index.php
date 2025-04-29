<?php
// Redirecionar para a página de login ou dashboard dependendo da autenticação
require_once('includes/config.php');

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>