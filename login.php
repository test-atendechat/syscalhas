<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Forçar desconexão para evitar loops de redirecionamento
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
    // Obter dados do formulário
    $email = limpaString($_POST['email']);
    $senha = $_POST['senha']; // A senha será processada pelo password_verify
    
    // Validar dados
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        // Tentar autenticar o usuário
        $usuario = autenticarUsuario($email, $senha);
        
        if ($usuario) {
            // Registrar o login na sessão
            registrarLogin($usuario);
            
            // Redirecionar para o dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $erro = 'Email ou senha inválidos. Tente novamente.';
        }
    }
}

// Definir título da página
$titulo = 'Login | ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Estilo personalizado -->
    <link href="css/styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --accent-color: #ffc107;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background-color: var(--primary-color);
            padding: 30px 20px;
            text-align: center;
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            width: 100%;
            height: 16px;
            background-color: var(--primary-color);
            clip-path: polygon(0 0, 100% 0, 50% 100%);
        }
        .logo-text {
            color: white;
            font-weight: bold;
            margin: 0;
            font-size: 1.8rem;
            letter-spacing: 0.5px;
        }
        .login-body {
            padding: 40px 30px 30px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ced4da;
            font-size: 1rem;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .input-group-text {
            border-radius: 8px 0 0 8px;
            background-color: #f8f9fa;
            color: var(--primary-color);
        }
        .btn-login {
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 8px;
            background-color: var(--primary-color);
            border: none;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(13, 110, 253, 0.3);
        }
        .login-footer {
            padding-top: 20px;
            text-align: center;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }
        .version-badge {
            background-color: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card card">
                <div class="login-header">
                    <h1 class="logo-text">
                        <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?>
                    </h1>
                </div>
                
                <div class="login-body">
                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $erro; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4 text-center">
                        <p class="text-muted">Sistema de Gestão Empresarial</p>
                    </div>
                    
                    <form method="post" action="login.php">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" placeholder="Digite seu email" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite sua senha" required>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>Acessar Sistema
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="login-footer">
                <p>
                    &copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?> 
                    <span class="version-badge">v<?php echo APP_VERSION; ?></span>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle com Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>