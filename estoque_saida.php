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
        $observacao = limpaString($_POST['observacao']);
        
        // Validações básicas
        if ($produto_id <= 0) {
            throw new Exception('Selecione um produto válido.');
        }
        
        if ($quantidade <= 0) {
            throw new Exception('Informe uma quantidade válida maior que zero.');
        }
        
        // Buscar produto
        $produto = buscarProduto($produto_id);
        if (!$produto) {
            throw new Exception('Produto não encontrado.');
        }
        
        // Verificar se há estoque suficiente
        if ($produto['estoque_atual'] < $quantidade) {
            throw new Exception('Estoque insuficiente. Disponível: ' . number_format($produto['estoque_atual'], 2, ',', '.') . ' ' . $produto['unidade']);
        }
        
        // Calcular valor total com base no valor unitário do produto
        $valor_unitario = $produto['valor_unitario'];
        $valor_total = $quantidade * $valor_unitario;
        
        // Registrar a movimentação de estoque
        $stmt = $db->prepare("INSERT INTO estoque_movimentacoes 
                             (produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, usuario_id) 
                             VALUES 
                             (:produto_id, 'saida', :quantidade, :valor_unitario, :valor_total, :observacao, :usuario_id)");
        $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':valor_unitario', $valor_unitario);
        $stmt->bindParam(':valor_total', $valor_total);
        $stmt->bindParam(':observacao', $observacao);
        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Atualizar o estoque do produto
        $stmt = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual - :quantidade WHERE id = :id");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':id', $produto_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Confirmar transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        header('Location: estoque.php?mensagem=estoque_atualizado');
        exit;
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $db->rollback();
        $mensagem = alerta('Erro ao registrar saída no estoque: ' . $e->getMessage(), 'danger');
        
        // Se houve erro e um produto foi selecionado, recarregá-lo
        if ($produto_id > 0) {
            $produto = buscarProduto($produto_id);
        }
    }
}

// Consultar produtos para o select
$stmt = $db->query("SELECT id, codigo, descricao, unidade, valor_unitario, estoque_atual FROM produtos WHERE estoque_atual > 0 ORDER BY descricao");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-arrow-alt-circle-up me-2"></i>Saída de Estoque</h1>
    <a href="estoque.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-minus-circle me-2"></i>Registrar Saída</h5>
    </div>
    <div class="card-body">
        <?php if (count($produtos) > 0): ?>
            <form method="post" class="needs-validation" novalidate>
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label for="produto_id" class="form-label required-field">Produto</label>
                        <select class="form-select" id="produto_id" name="produto_id" required>
                            <option value="">Selecione um produto</option>
                            <?php foreach ($produtos as $p): ?>
                                <option value="<?php echo $p['id']; ?>" 
                                        data-estoque="<?php echo $p['estoque_atual']; ?>"
                                        data-unidade="<?php echo $p['unidade']; ?>"
                                        <?php echo ($produto && $produto['id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo "{$p['codigo']} - {$p['descricao']} ({$p['estoque_atual']} {$p['unidade']})"; ?>
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
                        <div class="form-text" id="estoque-disponivel">Estoque disponível: -</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="motivo" class="form-label required-field">Motivo</label>
                        <select class="form-select" id="motivo" name="motivo" required>
                            <option value="">Selecione...</option>
                            <option value="Venda">Venda</option>
                            <option value="Uso interno">Uso interno</option>
                            <option value="Perda">Perda/Quebra</option>
                            <option value="Devolução">Devolução ao fornecedor</option>
                            <option value="Ajuste">Ajuste de inventário</option>
                            <option value="Outro">Outro</option>
                        </select>
                        <div class="invalid-feedback">Por favor, selecione o motivo da saída.</div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12 mb-3">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary me-2">Limpar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save me-2"></i>Registrar Saída
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Não há produtos com estoque disponível para registrar saída.
            </div>
            <div class="text-center">
                <a href="estoque_entrada.php" class="btn btn-success">
                    <i class="fas fa-arrow-alt-circle-down me-2"></i>Registrar Entrada de Estoque
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar unidade de medida e estoque disponível ao selecionar um produto
    const produtoSelect = document.getElementById('produto_id');
    const unidadeAddon = document.getElementById('unidade-addon');
    const estoqueDisponivel = document.getElementById('estoque-disponivel');
    const quantidadeInput = document.getElementById('quantidade');
    
    produtoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const unidade = selectedOption.dataset.unidade;
            const estoque = selectedOption.dataset.estoque;
            
            unidadeAddon.textContent = unidade;
            estoqueDisponivel.textContent = 'Estoque disponível: ' + parseFloat(estoque).toFixed(2).replace('.', ',') + ' ' + unidade;
            
            // Limitar a quantidade máxima à quantidade disponível
            quantidadeInput.setAttribute('max', estoque);
        } else {
            unidadeAddon.textContent = 'un';
            estoqueDisponivel.textContent = 'Estoque disponível: -';
            quantidadeInput.removeAttribute('max');
        }
    });
    
    // Formatar campo de quantidade
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
        
        // Verificar se a quantidade excede o estoque disponível
        const selectedOption = produtoSelect.options[produtoSelect.selectedIndex];
        if (selectedOption.value) {
            const estoque = parseFloat(selectedOption.dataset.estoque);
            const quantidade = parseFloat(valor.replace(',', '.'));
            
            if (quantidade > estoque) {
                quantidadeInput.classList.add('is-invalid');
                quantidadeInput.setCustomValidity('Quantidade excede o estoque disponível');
            } else {
                quantidadeInput.classList.remove('is-invalid');
                quantidadeInput.setCustomValidity('');
            }
        }
    });
    
    // Inicializar unidade e estoque se um produto já estiver selecionado
    if (produtoSelect.value) {
        const selectedOption = produtoSelect.options[produtoSelect.selectedIndex];
        const unidade = selectedOption.dataset.unidade;
        const estoque = selectedOption.dataset.estoque;
        
        unidadeAddon.textContent = unidade;
        estoqueDisponivel.textContent = 'Estoque disponível: ' + parseFloat(estoque).toFixed(2).replace('.', ',') + ' ' + unidade;
    }
    
    // Validação do formulário
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Verificar se há estoque suficiente
        const selectedOption = produtoSelect.options[produtoSelect.selectedIndex];
        if (selectedOption.value) {
            const estoque = parseFloat(selectedOption.dataset.estoque);
            const quantidade = parseFloat(quantidadeInput.value.replace(',', '.'));
            
            if (quantidade > estoque) {
                event.preventDefault();
                alert('Quantidade excede o estoque disponível de ' + parseFloat(estoque).toFixed(2).replace('.', ',') + ' ' + selectedOption.dataset.unidade);
            }
        }
        
        form.classList.add('was-validated');
    }, false);
});
</script>

<?php require_once('includes/footer.php'); ?>