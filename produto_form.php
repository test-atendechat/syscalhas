<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Inicializar variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$produto = [
    'id' => 0,
    'codigo' => '',
    'descricao' => '',
    'categoria' => '',
    'unidade' => 'UN',
    'valor_unitario' => '',
    'observacoes' => ''
];
$erro = '';
$sucesso = '';
$titulo = 'Cadastrar Novo Produto';
$modo = 'cadastrar';

// Se for edição, buscar dados do produto
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = 'Editar Produto';
        $modo = 'editar';
    } else {
        $erro = 'Produto não encontrado.';
    }
}

// Obter categorias para sugestão
$categorias = $db->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturar dados do formulário
    $produto = [
        'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
        'codigo' => limpaString($_POST['codigo'] ?? ''),
        'descricao' => limpaString($_POST['descricao'] ?? ''),
        'categoria' => limpaString($_POST['categoria'] ?? ''),
        'unidade' => limpaString($_POST['unidade'] ?? 'UN'),
        'valor_unitario' => str_replace(',', '.', str_replace('.', '', $_POST['valor_unitario'] ?? '0')),
        'observacoes' => limpaString($_POST['observacoes'] ?? '')
    ];
    
    // Validações
    if (empty($produto['descricao'])) {
        $erro = 'A descrição do produto é obrigatória.';
    } elseif (empty($produto['categoria'])) {
        $erro = 'A categoria é obrigatória.';
    } elseif (empty($produto['valor_unitario']) || !is_numeric($produto['valor_unitario'])) {
        $erro = 'O valor unitário é obrigatório e deve ser um número válido.';
    } else {
        // Verificar se código já está cadastrado para outro produto (se informado)
        if (!empty($produto['codigo'])) {
            $stmt = $db->prepare("SELECT id FROM produtos WHERE codigo = :codigo AND id != :id");
            $stmt->bindParam(':codigo', $produto['codigo']);
            $stmt->bindParam(':id', $produto['id'], PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $erro = 'Este código já está cadastrado para outro produto.';
            }
        }
        
        if (empty($erro)) {
            try {
                // Inserir ou atualizar produto
                if ($produto['id'] > 0) {
                    // Atualizar
                    $stmt = $db->prepare("UPDATE produtos SET 
                        codigo = :codigo,
                        descricao = :descricao,
                        categoria = :categoria,
                        unidade = :unidade,
                        valor_unitario = :valor_unitario,
                        observacoes = :observacoes
                        WHERE id = :id");
                    $stmt->bindParam(':id', $produto['id'], PDO::PARAM_INT);
                    $mensagem = 'atualizado';
                } else {
                    // Inserir
                    $stmt = $db->prepare("INSERT INTO produtos (
                        codigo, descricao, categoria, unidade, valor_unitario, observacoes
                    ) VALUES (
                        :codigo, :descricao, :categoria, :unidade, :valor_unitario, :observacoes
                    )");
                    $mensagem = 'cadastrado';
                }
                
                // Bind de parâmetros
                $stmt->bindParam(':codigo', $produto['codigo']);
                $stmt->bindParam(':descricao', $produto['descricao']);
                $stmt->bindParam(':categoria', $produto['categoria']);
                $stmt->bindParam(':unidade', $produto['unidade']);
                $stmt->bindParam(':valor_unitario', $produto['valor_unitario']);
                $stmt->bindParam(':observacoes', $produto['observacoes']);
                
                $stmt->execute();
                
                // Redirecionar para a listagem de produtos
                header("Location: produtos.php?mensagem={$mensagem}");
                exit;
                
            } catch (Exception $e) {
                $erro = 'Erro ao salvar produto: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-box me-2"></i><?php echo $titulo; ?></h1>
    <a href="produtos.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-box-open me-2"></i>Formulário de Produto
    </div>
    <div class="card-body">
        <form method="post" action="produto_form.php" id="formProduto">
            <input type="hidden" name="id" value="<?php echo $produto['id']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="codigo" class="form-label">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $produto['codigo']; ?>">
                </div>
                <div class="col-md-9">
                    <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?php echo $produto['descricao']; ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="categoria" class="form-label">Categoria <span class="text-danger">*</span></label>
                    <select class="form-select" id="categoria" name="categoria" required>
                        <option value="">Selecione uma categoria</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['nome']; ?>" <?php echo ($produto['categoria'] == $cat['nome']) ? 'selected' : ''; ?>>
                                <?php echo $cat['nome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="unidade" class="form-label">Unidade <span class="text-danger">*</span></label>
                    <select class="form-select" id="unidade" name="unidade" required>
                        <option value="UN" <?php echo $produto['unidade'] == 'UN' ? 'selected' : ''; ?>>Unidade (UN)</option>
                        <option value="MT" <?php echo $produto['unidade'] == 'MT' ? 'selected' : ''; ?>>Metro (MT)</option>
                        <option value="M2" <?php echo $produto['unidade'] == 'M2' ? 'selected' : ''; ?>>Metro Quadrado (M²)</option>
                        <option value="KG" <?php echo $produto['unidade'] == 'KG' ? 'selected' : ''; ?>>Kilograma (KG)</option>
                        <option value="PC" <?php echo $produto['unidade'] == 'PC' ? 'selected' : ''; ?>>Peça (PC)</option>
                        <option value="CX" <?php echo $produto['unidade'] == 'CX' ? 'selected' : ''; ?>>Caixa (CX)</option>
                        <option value="PR" <?php echo $produto['unidade'] == 'PR' ? 'selected' : ''; ?>>Par (PR)</option>
                        <option value="HR" <?php echo $produto['unidade'] == 'HR' ? 'selected' : ''; ?>>Hora (HR)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="valor_unitario" class="form-label">Valor Unitário <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" id="valor_unitario" name="valor_unitario" value="<?php echo number_format($produto['valor_unitario'], 2, ',', '.'); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $produto['observacoes']; ?></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $modo == 'cadastrar' ? 'Cadastrar' : 'Atualizar'; ?> Produto
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formatação de valor unitário
    const valorUnitario = document.getElementById('valor_unitario');
    const form = document.getElementById('formProduto');
    
    valorUnitario.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        
        if (valor.length === 0) {
            e.target.value = '';
            return;
        }
        
        // Converter para formato de moeda
        valor = (parseInt(valor) / 100).toFixed(2);
        e.target.value = valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });

    // Validação do formulário
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<?php
require_once('includes/footer.php');
?>
