<?php
// Redirecionar para a página apropriada dependendo da autenticação
require_once('includes/config.php');

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    // Se estiver autenticado, redirecionar para o dashboard
    header('Location: dashboard.php');
} else {
    // Se não estiver autenticado, redirecionar para o site público
    header('Location: site/index.php');
}
exit;
?>