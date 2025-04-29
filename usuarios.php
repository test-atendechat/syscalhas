<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

$titulo = "Gerenciar Usuários";
require_once('includes/header.php');

// Verificar se o usuário é administrador
if (!isset($_SESSION['usuario']['nivel']) || $_SESSION['usuario']['nivel'] != 'admin') {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>Acesso negado! Você não tem permissão para acessar esta página.
          </div>';
    require_once('includes/footer.php');
    exit;
}

// Inicializar variáveis
$mensagem = '';
$usuario = [
    'id' => 0,
    'nome' => '',
    'email' => '',
    'nivel' => 'usuario',
    'ativo' => 1
];
$editando = false;

// Processar exclusão de usuário
if (isset($_POST['excluir']) && $_POST['excluir'] == 1) {
    $id = intval($_POST['id']);
    
    // Não permitir que o usuário exclua a si mesmo
    if ($id == $_SESSION['usuario_id']) {
        $mensagem = alerta('Você não pode excluir seu próprio usuário!', 'danger');
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $mensagem = alerta('Usuário excluído com sucesso!', 'success');
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao excluir usuário: ' . $e->getMessage(), 'danger');
        }
    }
}

// Processar formulário de edição/criação quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['excluir'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $nivel = $_POST['nivel'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $senha = trim($_POST['senha']);
    
    if (empty($nome)) {
        $mensagem = alerta('O nome é obrigatório!', 'danger');
    } elseif (empty($email)) {
        $mensagem = alerta('O e-mail é obrigatório!', 'danger');
    } else {
        try {
            // Verificar se o e-mail já está em uso por outro usuário
            $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $mensagem = alerta('Este e-mail já está em uso por outro usuário!', 'danger');
            } else {
                if ($id > 0) {
                    // Atualizar usuário existente
                    if (!empty($senha)) {
                        // Se forneceu senha, alterar
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email, nivel = :nivel, ativo = :ativo, senha = :senha WHERE id = :id");
                        $stmt->bindParam(':senha', $senha_hash);
                    } else {
                        // Se não forneceu senha, manter a existente
                        $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email, nivel = :nivel, ativo = :ativo WHERE id = :id");
                    }
                    
                    $stmt->bindParam(':id', $id);
                    $stmt->bindParam(':nome', $nome);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':nivel', $nivel);
                    $stmt->bindParam(':ativo', $ativo);
                    $stmt->execute();
                    
                    $mensagem = alerta('Usuário atualizado com sucesso!', 'success');
                } else {
                    // Criar novo usuário
                    if (empty($senha)) {
                        $mensagem = alerta('A senha é obrigatória para novos usuários!', 'danger');
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES (:nome, :email, :senha, :nivel, :ativo)");
                        $stmt->bindParam(':nome', $nome);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':senha', $senha_hash);
                        $stmt->bindParam(':nivel', $nivel);
                        $stmt->bindParam(':ativo', $ativo);
                        $stmt->execute();
                        
                        $mensagem = alerta('Usuário criado com sucesso!', 'success');
                    }
                }
            }
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao salvar usuário: ' . $e->getMessage(), 'danger');
        }
    }
}

// Carregar usuário para edição
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $editando = true;
    
    $stmt = $db->prepare("SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $mensagem = alerta('Usuário não encontrado!', 'danger');
    }
}

// Buscar todos os usuários
$stmt = $db->query("SELECT id, nome, email, nivel, ativo, data_criacao, ultimo_acesso FROM usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-users-cog me-2"></i>Gerenciar Usuários</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
            <i class="fas fa-user-plus me-2"></i>Novo Usuário
        </button>
        <a href="dashboard.php" class="btn btn-outline-primary ms-2">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Usuários do Sistema</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Último Acesso</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios) > 0): ?>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?php echo $u['nome']; ?></td>
                                <td><?php echo $u['email']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $u['nivel'] == 'admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo ucfirst($u['nivel'] == 'admin' ? 'Administrador' : 'Usuário'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $u['ativo'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td><?php echo dataParaBr($u['data_criacao']); ?></td>
                                <td><?php echo !empty($u['ultimo_acesso']) ? dataParaBr($u['ultimo_acesso'], true) : 'Nunca'; ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="?editar=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="usuario_permissoes.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info text-white" title="Permissões">
                                            <i class="fas fa-user-shield"></i>
                                        </a>
                                        <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmarExclusao(<?php echo $u['id']; ?>, '<?php echo $u['nome']; ?>')" 
                                                    title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum usuário cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para adicionar/editar usuário -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalUsuarioLabel">
                    <?php echo $editando ? 'Editar Usuário' : 'Novo Usuário'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $usuario['nome']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuario['email']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="senha" class="form-label">
                            <?php echo $editando ? 'Nova Senha (deixe em branco para manter a atual)' : 'Senha'; ?>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="senha" name="senha" <?php echo !$editando ? 'required' : ''; ?>>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="senha">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">A senha deve ter pelo menos 6 caracteres.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nível de Acesso</label>
                        <select class="form-select" id="nivel" name="nivel">
                            <option value="usuario" <?php echo $usuario['nivel'] == 'usuario' ? 'selected' : ''; ?>>Usuário</option>
                            <option value="administrador" <?php echo $usuario['nivel'] == 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1" <?php echo $usuario['ativo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativo">Usuário Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para confirmar exclusão -->
<div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o usuário <strong id="nomeUsuarioExcluir"></strong>?</p>
                <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Esta ação não pode ser desfeita!</p>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="id" id="idUsuarioExcluir">
                    <input type="hidden" name="excluir" value="1">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Abrir modal de edição se estiver editando
    <?php if ($editando): ?>
    var modalUsuario = new bootstrap.Modal(document.getElementById('modalUsuario'));
    modalUsuario.show();
    <?php endif; ?>
    
    // Alternar visibilidade da senha
    const toggleButton = document.querySelector('.toggle-password');
    
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
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
    }
});

// Função para confirmar exclusão
function confirmarExclusao(id, nome) {
    document.getElementById('idUsuarioExcluir').value = id;
    document.getElementById('nomeUsuarioExcluir').textContent = nome;
    
    var modalConfirmarExclusao = new bootstrap.Modal(document.getElementById('modalConfirmarExclusao'));
    modalConfirmarExclusao.show();
}
</script>

<?php require_once('includes/footer.php'); ?>