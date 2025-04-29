<?php
require_once('config.php');
require_once('db.php');

/**
 * Verifica se o usuário está autenticado
 * Redireciona para a página de login caso não esteja
 */
function verificarAutenticacao() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verifica as credenciais do usuário
 * 
 * @param string $email Email do usuário
 * @param string $senha Senha do usuário
 * @return boolean|array Retorna dados do usuário se autenticado ou false se não
 */
function autenticarUsuario($email, $senha) {
    global $db;
    
    $stmt = $db->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = :email AND ativo = 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verifica a senha usando password_verify
        if (password_verify($senha, $usuario['senha'])) {
            // Remove a senha do array antes de retornar
            unset($usuario['senha']);
            return $usuario;
        }
    }
    
    return false;
}

/**
 * Registra o login do usuário na sessão
 * 
 * @param array $usuario Dados do usuário
 */
function registrarLogin($usuario) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['autenticado'] = true;
    $_SESSION['ultimo_acesso'] = time();
}

/**
 * Encerra a sessão do usuário
 */
function fazerLogout() {
    // Limpa todas as variáveis de sessão
    $_SESSION = array();
    
    // Destroi a sessão
    session_destroy();
}

/**
 * Verifica se o tempo da sessão expirou
 * @param int $tempo_max Tempo máximo em segundos (padrão: 3600 - 1 hora)
 * @return boolean
 */
function verificarSessaoExpirada($tempo_max = 3600) {
    if (isset($_SESSION['ultimo_acesso'])) {
        if (time() - $_SESSION['ultimo_acesso'] > $tempo_max) {
            return true;
        }
        // Atualiza o último acesso
        $_SESSION['ultimo_acesso'] = time();
    }
    return false;
}
?>
