<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if (!verificarPermissao('gerenciar_colaboradores') && $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=sempermissao');
    exit;
}

// Inicialização de variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$colaborador = [];
$mensagem = '';
$modo = 'novo';

// Formatar a data atual como padrão para a data de admissão
$data_atual = date('Y-m-d');

// Buscar dados do colaborador se for edição
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM colaboradores WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
        $modo = 'editar';
    } else {
        $mensagem = alerta('Colaborador não encontrado!', 'danger');
    }
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter e validar os dados do formulário
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $data_admissao = $_POST['data_admissao'] ?? $data_atual;
    $status = $_POST['status'] ?? 'ativo';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $id = intval($_POST['id'] ?? 0);
    
    // Validar campos obrigatórios
    if (empty($nome) || empty($tipo) || empty($data_admissao)) {
        $mensagem = alerta('Preencha todos os campos obrigatórios.', 'danger');
    } else {
        if ($id > 0) {
            // Atualizar um colaborador existente
            $stmt = $db->prepare("UPDATE colaboradores SET 
                                 nome = :nome, 
                                 tipo = :tipo, 
                                 telefone = :telefone, 
                                 email = :email, 
                                 data_admissao = :data_admissao, 
                                 status = :status, 
                                 observacoes = :observacoes 
                                 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        } else {
            // Inserir um novo colaborador
            $stmt = $db->prepare("INSERT INTO colaboradores 
                                 (nome, tipo, telefone, email, data_admissao, status, observacoes) 
                                 VALUES 
                                 (:nome, :tipo, :telefone, :email, :data_admissao, :status, :observacoes)");
        }
        
        // Vincular parâmetros
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':data_admissao', $data_admissao);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':observacoes', $observacoes);
        
        try {
            $stmt->execute();
            
            if ($id == 0) {
                $id = $db->lastInsertId();
            }
            
            $mensagem = alerta('Colaborador salvo com sucesso!', 'success');
            $modo = 'editar';
            
            // Redirecionar de volta para a lista
            header("Location: colaboradores.php?mensagem=salvo");
            exit;
        } catch (PDOException $e) {
            $mensagem = alerta('Erro ao salvar colaborador: ' . $e->getMessage(), 'danger');
        }
    }
}

// Definir título da página
$titulo = ($modo == 'editar' ? 'Editar' : 'Novo') . ' Colaborador';

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <?php if ($modo == 'editar'): ?>
            <i class="fas fa-edit me-2"></i>Editar Colaborador
        <?php else: ?>
            <i class="fas fa-plus-circle me-2"></i>Novo Colaborador
        <?php endif; ?>
    </h1>
    <a href="colaboradores.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Dados do Colaborador</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="nome" class="form-label">Nome Completo *</label>
                    <input type="text" class="form-control" id="nome" name="nome" 
                           value="<?php echo isset($colaborador['nome']) ? $colaborador['nome'] : ''; ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="tipo" class="form-label">Tipo de Colaborador *</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Selecione...</option>
                        <option value="instalador" <?php echo (isset($colaborador['tipo']) && $colaborador['tipo'] == 'instalador') ? 'selected' : ''; ?>>
                            Instalador
                        </option>
                        <option value="auxiliar" <?php echo (isset($colaborador['tipo']) && $colaborador['tipo'] == 'auxiliar') ? 'selected' : ''; ?>>
                            Auxiliar
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" 
                           value="<?php echo isset($colaborador['telefone']) ? $colaborador['telefone'] : ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($colaborador['email']) ? $colaborador['email'] : ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="data_admissao" class="form-label">Data de Admissão *</label>
                    <input type="date" class="form-control" id="data_admissao" name="data_admissao" 
                           value="<?php echo isset($colaborador['data_admissao']) ? $colaborador['data_admissao'] : $data_atual; ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="ativo" <?php echo (!isset($colaborador['status']) || $colaborador['status'] == 'ativo') ? 'selected' : ''; ?>>
                            Ativo
                        </option>
                        <option value="inativo" <?php echo (isset($colaborador['status']) && $colaborador['status'] == 'inativo') ? 'selected' : ''; ?>>
                            Inativo
                        </option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo isset($colaborador['observacoes']) ? $colaborador['observacoes'] : ''; ?></textarea>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="colaboradores.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Salvar Colaborador
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Aplicar máscara ao telefone
document.addEventListener('DOMContentLoaded', function() {
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        IMask(telefoneInput, {
            mask: '(00) 00000-0000'
        });
    }
});
</script>

<?php
require_once('includes/footer.php');
?>