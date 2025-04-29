<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/auth.php');
require_once('includes/functions.php');

// Se usuário já estiver logado, redireciona para o dashboard
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';
$mensagem = '';

// Verifica se foi enviado um formulário de login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = limpaString($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    // Validações básicas
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        // Tenta autenticar o usuário
        $usuario = autenticarUsuario($email, $senha);
        
        if ($usuario) {
            // Registra o login na sessão
            registrarLogin($usuario);
            
            // Redireciona para o dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $erro = 'Email ou senha incorretos.';
        }
    }
}

// Verifica mensagens na URL
if (isset($_GET['erro'])) {
    switch ($_GET['erro']) {
        case 'sessao_expirada':
            $erro = 'Sua sessão expirou. Por favor, faça login novamente.';
            break;
    }
}

if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'logout':
            $mensagem = 'Você foi desconectado com sucesso.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- CSS personalizado -->
    <link href="css/styles.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        .form-signin {
            width: 100%;
            max-width: 360px;
            padding: 15px;
            margin: auto;
        }
    </style>
</head>
<body>
    <div class="form-signin text-center">
        <form method="post" action="login.php">
            <i class="fas fa-tools fa-3x mb-3 text-primary"></i>
            <h1 class="h3 mb-3 fw-normal"><?php echo APP_NAME; ?></h1>
            
            <?php if ($erro): ?>
                <div class="alert alert-danger"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-success"><?php echo $mensagem; ?></div>
            <?php endif; ?>
            
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="nome@exemplo.com" required>
                <label for="email">Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" required>
                <label for="senha">Senha</label>
            </div>
            
            <button class="w-100 btn btn-lg btn-primary" type="submit">
                <i class="fas fa-sign-in-alt me-2"></i>Entrar
            </button>
            <p class="mt-5 mb-3 text-muted">&copy; <?php echo date('Y'); ?></p>
        </form>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS com Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
