<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

$titulo = "Meu Perfil";
require_once('includes/header.php');

// Inicializar variáveis
$usuario_id = $_SESSION['usuario_id'];
$usuario = [];
$mensagem = '';

// Buscar dados do usuário
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->bindParam(':id', $usuario_id);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        // Atualizar dados do perfil
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        
        if (empty($nome)) {
            $mensagem = alerta('O nome é obrigatório!', 'danger');
        } elseif (empty($email)) {
            $mensagem = alerta('O e-mail é obrigatório!', 'danger');
        } else {
            try {
                // Verificar se o e-mail já está em uso por outro usuário
                $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':id', $usuario_id);
                $stmt->execute();
                
                if ($stmt->fetchColumn() > 0) {
                    $mensagem = alerta('Este e-mail já está em uso por outro usuário!', 'danger');
                } else {
                    // Atualizar dados
                    $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id");
                    $stmt->bindParam(':nome', $nome);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':id', $usuario_id);
                    $stmt->execute();
                    
                    // Atualizar sessão
                    $_SESSION['usuario_nome'] = $nome;
                    $_SESSION['usuario_email'] = $email;
                    
                    // Recarregar dados do usuário
                    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
                    $stmt->bindParam(':id', $usuario_id);
                    $stmt->execute();
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $mensagem = alerta('Perfil atualizado com sucesso!', 'success');
                }
            } catch (Exception $e) {
                $mensagem = alerta('Erro ao atualizar perfil: ' . $e->getMessage(), 'danger');
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        // Alterar senha
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirma_senha = $_POST['confirma_senha'];
        
        if (empty($senha_atual) || empty($nova_senha) || empty($confirma_senha)) {
            $mensagem = alerta('Todos os campos de senha são obrigatórios!', 'danger');
        } elseif ($nova_senha != $confirma_senha) {
            $mensagem = alerta('A nova senha e a confirmação não coincidem!', 'danger');
        } elseif (strlen($nova_senha) < 6) {
            $mensagem = alerta('A nova senha deve ter pelo menos 6 caracteres!', 'danger');
        } else {
            try {
                // Verificar senha atual
                if (password_verify($senha_atual, $usuario['senha'])) {
                    // Atualizar senha
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
                    $stmt->bindParam(':senha', $senha_hash);
                    $stmt->bindParam(':id', $usuario_id);
                    $stmt->execute();
                    
                    $mensagem = alerta('Senha alterada com sucesso!', 'success');
                } else {
                    $mensagem = alerta('Senha atual incorreta!', 'danger');
                }
            } catch (Exception $e) {
                $mensagem = alerta('Erro ao alterar senha: ' . $e->getMessage(), 'danger');
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-id-card me-2"></i>Meu Perfil</h1>
    <a href="dashboard.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Informações do Perfil</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $usuario['nome']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuario['email']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível de Acesso</label>
                        <input type="text" class="form-control" id="nivel" value="<?php echo ucfirst($usuario['nivel']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="data_criacao" class="form-label">Data de Cadastro</label>
                        <input type="text" class="form-control" id="data_criacao" value="<?php echo dataParaBr($usuario['data_criacao']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ultimo_acesso" class="form-label">Último Acesso</label>
                        <input type="text" class="form-control" id="ultimo_acesso" value="<?php echo !empty($usuario['ultimo_acesso']) ? dataParaBr($usuario['ultimo_acesso'], true) : 'Não disponível'; ?>" readonly>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Atualizar Perfil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Alterar Senha</h5>
            </div>
            <div class="card-body">
                <form method="post" id="formAlterarSenha">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="senha_atual" class="form-label">Senha Atual</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="senha_atual">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="nova_senha">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">A senha deve ter pelo menos 6 caracteres.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirma_senha" class="form-label">Confirmar Nova Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirma_senha">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Dicas de Segurança</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-check-circle text-success me-2"></i>Use uma senha forte com pelo menos 8 caracteres.</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i>Combine letras maiúsculas, minúsculas, números e símbolos.</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i>Evite senhas óbvias como datas de nascimento ou nomes de familiares.</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i>Nunca compartilhe sua senha com outras pessoas.</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i>Altere sua senha regularmente para maior segurança.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alternar visibilidade da senha
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordField = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Validar formulário de alteração de senha
    const formAlterarSenha = document.getElementById('formAlterarSenha');
    
    formAlterarSenha.addEventListener('submit', function(event) {
        const novaSenha = document.getElementById('nova_senha').value;
        const confirmaSenha = document.getElementById('confirma_senha').value;
        
        if (novaSenha !== confirmaSenha) {
            event.preventDefault();
            alert('A nova senha e a confirmação não coincidem!');
        } else if (novaSenha.length < 6) {
            event.preventDefault();
            alert('A nova senha deve ter pelo menos 6 caracteres!');
        }
    });
});
</script>

<?php require_once('includes/footer.php'); ?>