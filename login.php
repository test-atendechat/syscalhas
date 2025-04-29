<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário já está autenticado
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
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-5 col-md-7">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary"><?php echo APP_NAME; ?></h2>
                            <p class="text-muted">Entre com suas credenciais para acessar o sistema</p>
                        </div>
                        
                        <?php if (!empty($erro)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $erro; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="login.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="senha" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Entrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle com Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>