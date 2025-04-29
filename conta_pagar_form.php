<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Inicializar variáveis
$id = 0;
$descricao = '';
$fornecedor = '';
$data_emissao = date('Y-m-d');
$data_vencimento = date('Y-m-d');
$valor = 0;
$status = 'pendente';
$observacoes = '';
$documento = '';
$categoria = '';
$mensagem = '';
$titulo = 'Nova Conta a Pagar';
$acao = 'cadastrar';

// Verificar se é edição
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $titulo = 'Editar Conta a Pagar';
    $acao = 'atualizar';
    
    // Buscar dados da conta
    $stmt = $db->prepare("SELECT * FROM contas_pagar WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($conta = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $descricao = $conta['descricao'];
        $fornecedor = $conta['fornecedor'];
        $data_emissao = $conta['data_emissao'];
        $data_vencimento = $conta['data_vencimento'];
        $valor = $conta['valor'];
        $status = $conta['status'];
        $observacoes = $conta['observacoes'];
        $documento = $conta['documento'];
        $categoria = $conta['categoria'];
    } else {
        header('Location: contas_pagar.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = trim($_POST['descricao']);
    $fornecedor = trim($_POST['fornecedor']);
    $data_emissao = $_POST['data_emissao'];
    $data_vencimento = $_POST['data_vencimento'];
    $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor']));
    $status = $_POST['status'];
    $observacoes = trim($_POST['observacoes']);
    $documento = trim($_POST['documento']);
    $categoria = trim($_POST['categoria']);
    
    // Validar campos obrigatórios
    if (empty($descricao) || empty($data_vencimento) || $valor <= 0) {
        $mensagem = alerta('Por favor, preencha todos os campos obrigatórios.', 'danger');
    } else {
        try {
            $db->beginTransaction();
            
            if ($acao == 'cadastrar') {
                // Inserir nova conta
                $stmt = $db->prepare("INSERT INTO contas_pagar 
                    (descricao, fornecedor, data_emissao, data_vencimento, valor, status, 
                    observacoes, documento, categoria, usuario_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $descricao, 
                    $fornecedor, 
                    $data_emissao, 
                    $data_vencimento, 
                    $valor, 
                    $status, 
                    $observacoes, 
                    $documento, 
                    $categoria,
                    $_SESSION['usuario_id']
                ]);
                
                $id = $db->lastInsertId();
                $mensagem = alerta('Conta cadastrada com sucesso!', 'success');
            } else {
                // Atualizar conta existente
                $stmt = $db->prepare("UPDATE contas_pagar SET 
                    descricao = ?, 
                    fornecedor = ?, 
                    data_emissao = ?, 
                    data_vencimento = ?, 
                    valor = ?, 
                    status = ?, 
                    observacoes = ?, 
                    documento = ?, 
                    categoria = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?");
                
                $stmt->execute([
                    $descricao, 
                    $fornecedor, 
                    $data_emissao, 
                    $data_vencimento, 
                    $valor, 
                    $status, 
                    $observacoes, 
                    $documento, 
                    $categoria,
                    $id
                ]);
                
                $mensagem = alerta('Conta atualizada com sucesso!', 'success');
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            $mensagem = alerta('Erro ao salvar conta: ' . $e->getMessage(), 'danger');
        }
    }
}

// Buscar categorias para sugestões
$stmt = $db->query("SELECT DISTINCT categoria FROM contas_pagar WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar fornecedores para sugestões
$stmt = $db->query("SELECT DISTINCT fornecedor FROM contas_pagar WHERE fornecedor IS NOT NULL AND fornecedor != '' ORDER BY fornecedor");
$fornecedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Incluir o cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $titulo; ?></h1>
    <a href="contas_pagar.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card">
    <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="descricao" class="form-label required-field">Descrição</label>
                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?php echo htmlspecialchars($descricao); ?>" required>
                    <div class="invalid-feedback">Por favor, informe a descrição.</div>
                </div>
                <div class="col-md-6">
                    <label for="fornecedor" class="form-label">Fornecedor</label>
                    <input type="text" class="form-control" id="fornecedor" name="fornecedor" value="<?php echo htmlspecialchars($fornecedor); ?>" list="lista-fornecedores">
                    <datalist id="lista-fornecedores">
                        <?php foreach ($fornecedores as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="data_emissao" class="form-label">Data de Emissão</label>
                    <input type="date" class="form-control" id="data_emissao" name="data_emissao" value="<?php echo $data_emissao; ?>">
                </div>
                <div class="col-md-4">
                    <label for="data_vencimento" class="form-label required-field">Data de Vencimento</label>
                    <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?php echo $data_vencimento; ?>" required>
                    <div class="invalid-feedback">Por favor, informe a data de vencimento.</div>
                </div>
                <div class="col-md-4">
                    <label for="valor" class="form-label required-field">Valor</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control monetary-input" id="valor" name="valor" value="<?php echo number_format($valor, 2, ',', '.'); ?>" required>
                    </div>
                    <div class="invalid-feedback">Por favor, informe o valor.</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="categoria" class="form-label">Categoria</label>
                    <input type="text" class="form-control" id="categoria" name="categoria" value="<?php echo htmlspecialchars($categoria); ?>" list="lista-categorias">
                    <datalist id="lista-categorias">
                        <?php foreach ($categorias as $item): ?>
                            <option value="<?php echo htmlspecialchars($item); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label for="documento" class="form-label">Número do Documento</label>
                    <input type="text" class="form-control" id="documento" name="documento" value="<?php echo htmlspecialchars($documento); ?>">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="pendente" <?php echo $status == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo $status == 'pago' ? 'selected' : ''; ?>>Pago</option>
                        <option value="cancelado" <?php echo $status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($observacoes); ?></textarea>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="contas_pagar.php" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>