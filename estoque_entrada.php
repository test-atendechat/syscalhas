<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Verificar se foi especificado um produto
$produto_id = isset($_GET['produto_id']) ? intval($_GET['produto_id']) : 0;
$produto = null;
$mensagem = '';

if ($produto_id > 0) {
    $produto = buscarProduto($produto_id);
    if (!$produto) {
        $produto_id = 0;
    }
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Iniciar transação
        $db->beginTransaction();
        
        $produto_id = intval($_POST['produto_id']);
        $quantidade = floatval(str_replace(',', '.', $_POST['quantidade']));
        $valor_unitario = floatval(str_replace(',', '.', $_POST['valor_unitario']));
        $observacao = limpaString($_POST['observacao']);
        
        // Validações básicas
        if ($produto_id <= 0) {
            throw new Exception('Selecione um produto válido.');
        }
        
        if ($quantidade <= 0) {
            throw new Exception('Informe uma quantidade válida maior que zero.');
        }
        
        if ($valor_unitario <= 0) {
            throw new Exception('Informe um valor unitário válido maior que zero.');
        }
        
        // Buscar produto
        $produto = buscarProduto($produto_id);
        if (!$produto) {
            throw new Exception('Produto não encontrado.');
        }
        
        // Calcular valor total
        $valor_total = $quantidade * $valor_unitario;
        
        // Registrar a movimentação de estoque
        $stmt = $db->prepare("INSERT INTO estoque_movimentacoes 
                             (produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, usuario_id) 
                             VALUES 
                             (:produto_id, 'entrada', :quantidade, :valor_unitario, :valor_total, :observacao, :usuario_id)");
        $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':valor_unitario', $valor_unitario);
        $stmt->bindParam(':valor_total', $valor_total);
        $stmt->bindParam(':observacao', $observacao);
        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Atualizar o estoque do produto
        $stmt = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual + :quantidade WHERE id = :id");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':id', $produto_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Atualizar o valor unitário do produto (opcional, depende da regra de negócio)
        if (isset($_POST['atualizar_valor']) && $_POST['atualizar_valor'] == '1') {
            $stmt = $db->prepare("UPDATE produtos SET valor_unitario = :valor_unitario WHERE id = :id");
            $stmt->bindParam(':valor_unitario', $valor_unitario);
            $stmt->bindParam(':id', $produto_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Confirmar transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        // Usamos JavaScript ao invés de header() para evitar "headers already sent"
        echo "<script>window.location.href = 'estoque.php?mensagem=estoque_atualizado';</script>";
        exit;
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $db->rollback();
        $mensagem = alerta('Erro ao registrar entrada no estoque: ' . $e->getMessage(), 'danger');
        
        // Se houve erro e um produto foi selecionado, recarregá-lo
        if ($produto_id > 0) {
            $produto = buscarProduto($produto_id);
        }
    }
}

// Consultar produtos para o select
$stmt = $db->query("SELECT id, codigo, descricao, unidade, valor_unitario FROM produtos ORDER BY descricao");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-arrow-alt-circle-down me-2"></i>Entrada de Estoque</h1>
    <a href="estoque.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Registrar Entrada</h5>
    </div>
    <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label for="produto_id" class="form-label required-field">Produto</label>
                    <select class="form-select" id="produto_id" name="produto_id" required>
                        <option value="">Selecione um produto</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?php echo $p['id']; ?>" 
                                    data-valor="<?php echo $p['valor_unitario']; ?>"
                                    data-unidade="<?php echo $p['unidade']; ?>"
                                    <?php echo ($produto && $produto['id'] == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo "{$p['codigo']} - {$p['descricao']} ({$p['unidade']})"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione um produto.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="quantidade" class="form-label required-field">Quantidade</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="quantidade" name="quantidade" required>
                        <span class="input-group-text" id="unidade-addon">un</span>
                    </div>
                    <div class="invalid-feedback">Por favor, informe a quantidade.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="valor_unitario" class="form-label required-field">Valor Unitário</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control monetary-input" id="valor_unitario" name="valor_unitario" required>
                    </div>
                    <div class="invalid-feedback">Por favor, informe o valor unitário.</div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12 mb-3">
                    <label for="observacao" class="form-label">Observação</label>
                    <textarea class="form-control" id="observacao" name="observacao" rows="3"></textarea>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="atualizar_valor" name="atualizar_valor" value="1" checked>
                        <label class="form-check-label" for="atualizar_valor">
                            Atualizar o valor unitário do produto no cadastro
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="reset" class="btn btn-outline-secondary me-2">Limpar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Registrar Entrada
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar unidade de medida ao selecionar um produto
    const produtoSelect = document.getElementById('produto_id');
    const unidadeAddon = document.getElementById('unidade-addon');
    const valorUnitarioInput = document.getElementById('valor_unitario');
    
    produtoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const unidade = selectedOption.dataset.unidade;
            const valor = selectedOption.dataset.valor;
            
            unidadeAddon.textContent = unidade;
            valorUnitarioInput.value = parseFloat(valor).toFixed(2).replace('.', ',');
        } else {
            unidadeAddon.textContent = 'un';
            valorUnitarioInput.value = '';
        }
    });
    
    // Formatar campo de quantidade
    const quantidadeInput = document.getElementById('quantidade');
    
    quantidadeInput.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/[^\d,]/g, '');
        valor = valor.replace(/,/g, '.');
        
        // Verificar se há mais de um ponto decimal
        const pontos = valor.match(/\./g);
        if (pontos && pontos.length > 1) {
            const partes = valor.split('.');
            valor = partes[0] + '.' + partes.slice(1).join('');
        }
        
        // Converter de volta para formato com vírgula
        valor = valor.replace('.', ',');
        e.target.value = valor;
    });
    
    // Inicializar unidade se um produto já estiver selecionado
    if (produtoSelect.value) {
        const selectedOption = produtoSelect.options[produtoSelect.selectedIndex];
        const unidade = selectedOption.dataset.unidade;
        unidadeAddon.textContent = unidade;
    }
    
    // Validação do formulário
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    }, false);
});
</script>

<?php require_once('includes/footer.php'); ?>