
<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Iniciar nova sessão limpa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();
session_start();

// Verificar se o usuário já está autenticado após a nova sessão
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Inicializar variáveis
$email = '';
$erro = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpaString($_POST['email']);
    $senha = $_POST['senha'];
    
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        $usuario = autenticarUsuario($email, $senha);
        
        if ($usuario) {
            registrarLogin($usuario);
            header('Location: dashboard.php');
            exit;
        } else {
            $erro = 'Email ou senha inválidos. Tente novamente.';
        }
    }
}

$titulo = 'Login | ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <style>
        .login-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #0143a3, #0097fb);
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .login-logo {
            font-size: 2.5rem;
            color: #0143a3;
            margin-bottom: 1rem;
        }
        .login-form input {
            border-radius: 50px;
            padding: 12px 20px;
        }
        .login-btn {
            border-radius: 50px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .login-divider {
            position: relative;
            text-align: center;
            margin: 2rem 0;
        }
        .login-divider::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 45%;
            height: 1px;
            background: #dee2e6;
        }
        .login-divider::after {
            content: "";
            position: absolute;
            right: 0;
            top: 50%;
            width: 45%;
            height: 1px;
            background: #dee2e6;
        }
    </style>
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-lg-5 col-md-7">
                <div class="login-card p-4 p-sm-5">
                    <div class="text-center">
                        <div class="login-logo">
                            <i class="fas fa-water"></i>
                        </div>
                        <h2 class="fw-bold text-primary mb-4"><?php echo APP_NAME; ?></h2>
                    </div>
                    
                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $erro; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="login.php" class="login-form">
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-envelope text-primary"></i>
                                </span>
                                <input type="email" class="form-control border-start-0 ps-0" 
                                       id="email" name="email" value="<?php echo $email; ?>" 
                                       placeholder="Seu email" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-lock text-primary"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 ps-0" 
                                       id="senha" name="senha" 
                                       placeholder="Sua senha" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary login-btn w-100">
                            Entrar no Sistema
                        </button>
                    </form>

                    <div class="login-divider">
                        <span class="px-3 bg-white text-muted">ou</span>
                    </div>

                    <div class="text-center">
                        <p class="text-muted mb-0">
                            Precisa de ajuda? Entre em contato com o administrador
                        </p>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white">
                    <small>
                        &copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?> 
                        <span class="mx-1">|</span> 
                        v<?php echo APP_VERSION; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
