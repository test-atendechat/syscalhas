<?php
// Redirecionar para a página apropriada dependendo da autenticação
require_once('includes/config.php');

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    // Se estiver autenticado, redirecionar para o ' . DASHBOARD_URL
    header('Location: ' . DASHBOARD_URL);
} else {
    // Se não estiver autenticado, redirecionar para a página de login
    header('Location: ' . BASE_URL . 'login');
}
exit;
?>