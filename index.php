<?php
// Redireciona para a página de login ou dashboard
session_start();

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>
