<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if (!verificarPermissao('gerenciar_colaboradores')) {
    header('Location: dashboard.php?erro=sempermissao');
    exit;
}

// Inicialização de variáveis
$id = 0;
$nome = '';
$tipo = 'auxiliar';
$cpf = '';
$telefone = '';
$endereco = '';
$data_nascimento = '';
$data_admissao = date('Y-m-d');
$observacoes = '';
$status = 'ativo';
$modo = 'cadastrar';
$titulo = 'Novo Colaborador';
$mensagem = '';

// Verificar se é modo de edição
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $modo = 'editar';
    $titulo = 'Editar Colaborador';
    
    // Buscar dados do colaborador
    try {
        $stmt = $db->prepare("SELECT * FROM colaboradores WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($colaborador) {
            $nome = $colaborador['nome'];
            $tipo = $colaborador['tipo'];
            $cpf = $colaborador['cpf'];
            $telefone = $colaborador['telefone'];
            $endereco = $colaborador['endereco'];
            $data_nascimento = $colaborador['data_nascimento'];
            $data_admissao = $colaborador['data_admissao'];
            $observacoes = $colaborador['observacoes'];
            $status = $colaborador['status'];
        } else {
            $mensagem = alerta('Colaborador não encontrado!', 'danger');
        }
    } catch (PDOException $e) {
        $mensagem = alerta('Erro ao buscar dados do colaborador: ' . $e->getMessage(), 'danger');
    }
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter os dados do formulário
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = limpaString($_POST['nome']);
    $tipo = limpaString($_POST['tipo']);
    $cpf = limpaString($_POST['cpf']);
    $telefone = limpaString($_POST['telefone']);
    $endereco = limpaString($_POST['endereco']);
    $data_nascimento = !empty($_POST['data_nascimento']) ? limpaString($_POST['data_nascimento']) : null;
    $data_admissao = limpaString($_POST['data_admissao']);
    $observacoes = limpaString($_POST['observacoes']);
    $status = limpaString($_POST['status']);
    
    // Validar dados
    if (empty($nome)) {
        $mensagem = alerta('Por favor, informe o nome do colaborador!', 'danger');
    } else {
        try {
            // Preparar os dados para inserção/atualização
            $params = [
                ':nome' => $nome,
                ':tipo' => $tipo,
                ':cpf' => $cpf,
                ':telefone' => $telefone,
                ':endereco' => $endereco,
                ':data_nascimento' => $data_nascimento,
                ':data_admissao' => $data_admissao,
                ':observacoes' => $observacoes,
                ':status' => $status,
                ':usuario_id' => $_SESSION['usuario_id']
            ];
            
            if ($id > 0) { // Atualizar
                $sql = "UPDATE colaboradores SET 
                          nome = :nome, 
                          tipo = :tipo, 
                          cpf = :cpf, 
                          telefone = :telefone, 
                          endereco = :endereco, 
                          data_nascimento = :data_nascimento, 
                          data_admissao = :data_admissao, 
                          observacoes = :observacoes, 
                          status = :status, 
                          usuario_id = :usuario_id, 
                          ultima_atualizacao = NOW() 
                        WHERE id = :id";
                $params[':id'] = $id;
                $mensagem_sucesso = 'Colaborador atualizado com sucesso!';
            } else { // Inserir
                $sql = "INSERT INTO colaboradores (nome, tipo, cpf, telefone, endereco, data_nascimento, data_admissao, observacoes, status, usuario_id) 
                        VALUES (:nome, :tipo, :cpf, :telefone, :endereco, :data_nascimento, :data_admissao, :observacoes, :status, :usuario_id)";
                $mensagem_sucesso = 'Colaborador cadastrado com sucesso!';
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if ($id == 0) {
                $id = $db->lastInsertId();
            }
            
            $mensagem = alerta($mensagem_sucesso, 'success');
            
            // Se foi um cadastro novo, limpar os campos para um novo cadastro
            if ($_POST['acao'] === 'cadastrar') {
                $nome = '';
                $cpf = '';
                $telefone = '';
                $endereco = '';
                $data_nascimento = '';
                $observacoes = '';
            } else {
                // Redirecionar de volta para a listagem
                header('Location: colaboradores.php?mensagem=sucesso');
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = alerta('Erro ao processar colaborador: ' . $e->getMessage(), 'danger');
        }
    }
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user-hard-hat me-2"></i><?php echo $titulo; ?></h1>
    <a href="colaboradores.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar para Colaboradores
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0"><i class="fas fa-edit me-2"></i>Formulário de Colaborador</h5>
    </div>
    <div class="card-body">
        <form method="post" class="needs-validation row g-3" novalidate>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="acao" value="<?php echo $modo; ?>">
            
            <div class="col-md-6">
                <label for="nome" class="form-label">Nome *</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $nome; ?>" required>
                <div class="invalid-feedback">Por favor, informe o nome completo do colaborador.</div>
            </div>
            
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo *</label>
                <select class="form-select" id="tipo" name="tipo" required>
                    <option value="auxiliar" <?php echo $tipo === 'auxiliar' ? 'selected' : ''; ?>>Auxiliar</option>
                    <option value="instalador" <?php echo $tipo === 'instalador' ? 'selected' : ''; ?>>Instalador</option>
                </select>
                <div class="invalid-feedback">Por favor, selecione o tipo de colaborador.</div>
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status *</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo $status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                </select>
                <div class="invalid-feedback">Por favor, selecione o status do colaborador.</div>
            </div>
            
            <div class="col-md-4">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo $cpf; ?>" placeholder="000.000.000-00">
            </div>
            
            <div class="col-md-4">
                <label for="telefone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo $telefone; ?>" placeholder="(00) 00000-0000">
            </div>
            
            <div class="col-md-4">
                <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="<?php echo $data_nascimento; ?>">
            </div>
            
            <div class="col-md-6">
                <label for="endereco" class="form-label">Endereço</label>
                <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo $endereco; ?>" placeholder="Rua, número, bairro, cidade">
            </div>
            
            <div class="col-md-6">
                <label for="data_admissao" class="form-label">Data de Admissão *</label>
                <input type="date" class="form-control" id="data_admissao" name="data_admissao" value="<?php echo $data_admissao; ?>" required>
                <div class="invalid-feedback">Por favor, informe a data de admissão.</div>
            </div>
            
            <div class="col-12">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $observacoes; ?></textarea>
            </div>
            
            <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-eraser me-2"></i>Limpar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $id > 0 ? 'Atualizar' : 'Cadastrar'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Script para validar o formulário
(function() {
    'use strict';
    
    // Fetch all forms to which we want to apply validation
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Formatar CPF e telefone
document.getElementById('cpf').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.substr(0, 11);
    
    if (value.length > 9) {
        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
    } else if (value.length > 6) {
        value = value.replace(/^(\d{3})(\d{3})(\d{0,3})$/, "$1.$2.$3");
    } else if (value.length > 3) {
        value = value.replace(/^(\d{3})(\d{0,3})$/, "$1.$2");
    }
    
    e.target.value = value;
});

document.getElementById('telefone').addEventListener('input', function (e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.substr(0, 11);
    
    if (value.length > 10) {
        value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, "($1) $2-$3");
    } else if (value.length > 6) {
        value = value.replace(/^(\d{2})(\d{4})(\d{0,4})$/, "($1) $2-$3");
    } else if (value.length > 2) {
        value = value.replace(/^(\d{2})(\d{0,5})$/, "($1) $2");
    }
    
    e.target.value = value;
});
</script>

<?php
require_once('includes/footer.php');
?>