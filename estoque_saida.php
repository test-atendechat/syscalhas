<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Inicializar variáveis
$mensagem = '';
$produto_pre_selecionado = null;

// Verificar se foi especificado um produto na URL
if (isset($_GET['produto_id']) && intval($_GET['produto_id']) > 0) {
    $produto_id = intval($_GET['produto_id']);
    $produto_pre_selecionado = buscarProduto($produto_id);
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Iniciar transação
        $db->beginTransaction();
        
        // Verificar se há produtos sendo enviados
        if (!isset($_POST['produto_id']) || !is_array($_POST['produto_id']) || count($_POST['produto_id']) == 0) {
            throw new Exception('Nenhum produto foi informado.');
        }
        
        $produtos_atualizados = 0;
        
        // Processar cada produto
        foreach ($_POST['produto_id'] as $index => $produto_id) {
            // Pular linhas vazias
            if (empty($produto_id)) continue;
            
            $produto_id = intval($produto_id);
            $quantidade = floatval(str_replace(',', '.', $_POST['quantidade'][$index]));
            $motivo = limpaString($_POST['motivo'][$index] ?? '');
            $observacao = limpaString($_POST['observacao'][$index] ?? '');
            
            // Validações básicas
            if ($produto_id <= 0) {
                continue; // Pular produto inválido
            }
            
            if ($quantidade <= 0) {
                continue; // Pular quantidade inválida
            }
            
            if (empty($motivo)) {
                continue; // Pular sem motivo
            }
            
            // Buscar produto
            $produto = buscarProduto($produto_id);
            if (!$produto) {
                continue; // Pular produto não encontrado
            }
            
            // Verificar se há estoque suficiente
            if ($produto['estoque_atual'] < $quantidade) {
                throw new Exception('Estoque insuficiente para o produto ' . $produto['descricao'] . '. Disponível: ' . 
                               number_format($produto['estoque_atual'], 2, ',', '.') . ' ' . $produto['unidade']);
            }
            
            // Calcular valor total com base no valor unitário do produto
            $valor_unitario = $produto['valor_unitario'];
            $valor_total = $quantidade * $valor_unitario;
            
            // Adicionar motivo à observação
            if (!empty($observacao)) {
                $observacao = "Motivo: {$motivo}. {$observacao}";
            } else {
                $observacao = "Motivo: {$motivo}";
            }
            
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
            
            $produtos_atualizados++;
        }
        
        // Verificar se algum produto foi atualizado
        if ($produtos_atualizados == 0) {
            throw new Exception('Nenhum produto válido foi informado para saída do estoque.');
        }
        
        // Confirmar transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        echo "<script>window.location.href = 'estoque.php?mensagem=estoque_atualizado';</script>";
        exit;
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $db->rollback();
        $mensagem = alerta('Erro ao registrar saída no estoque: ' . $e->getMessage(), 'danger');
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
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">Produto</th>
                                <th style="width: 15%;">Quantidade</th>
                                <th style="width: 15%;">Estoque Atual</th>
                                <th style="width: 15%;">Motivo</th>
                                <th style="width: 15%;">Observação</th>
                                <th style="width: 10%;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="itens-container">
                            <tr class="linha-item">
                                <td>
                                    <select class="form-select produto-select" name="produto_id[]" required>
                                        <option value="">Selecione um produto</option>
                                        <?php foreach ($produtos as $p): ?>
                                            <option value="<?php echo $p['id']; ?>" 
                                                    data-estoque="<?php echo $p['estoque_atual']; ?>"
                                                    data-unidade="<?php echo $p['unidade']; ?>">
                                                <?php echo "{$p['codigo']} - {$p['descricao']} ({$p['estoque_atual']} {$p['unidade']})"; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="text" class="form-control quantidade-input" name="quantidade[]" required>
                                        <span class="input-group-text unidade-addon">un</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="estoque-info">Disponível: -</div>
                                </td>
                                <td>
                                    <select class="form-select" name="motivo[]" required>
                                        <option value="">Selecione...</option>
                                        <option value="Venda">Venda</option>
                                        <option value="Uso interno">Uso interno</option>
                                        <option value="Perda">Perda/Quebra</option>
                                        <option value="Devolução">Devolução ao fornecedor</option>
                                        <option value="Ajuste">Ajuste de inventário</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="observacao[]" placeholder="Observação">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger btn-remover-item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between mb-4">
                    <button type="button" class="btn btn-success" id="btn-adicionar-item">
                        <i class="fas fa-plus me-2"></i>Adicionar Produto
                    </button>
                    
                    <div>
                        <button type="reset" class="btn btn-outline-secondary me-2">Limpar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-save me-2"></i>Registrar Saídas
                        </button>
                    </div>
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

<!-- Template para nova linha de produto -->
<template id="template-item">
    <tr class="linha-item">
        <td>
            <select class="form-select produto-select" name="produto_id[]" required>
                <option value="">Selecione um produto</option>
                <?php foreach ($produtos as $p): ?>
                    <option value="<?php echo $p['id']; ?>" 
                            data-estoque="<?php echo $p['estoque_atual']; ?>"
                            data-unidade="<?php echo $p['unidade']; ?>">
                        <?php echo "{$p['codigo']} - {$p['descricao']} ({$p['estoque_atual']} {$p['unidade']})"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <div class="input-group">
                <input type="text" class="form-control quantidade-input" name="quantidade[]" required>
                <span class="input-group-text unidade-addon">un</span>
            </div>
        </td>
        <td>
            <div class="estoque-info">Disponível: -</div>
        </td>
        <td>
            <select class="form-select" name="motivo[]" required>
                <option value="">Selecione...</option>
                <option value="Venda">Venda</option>
                <option value="Uso interno">Uso interno</option>
                <option value="Perda">Perda/Quebra</option>
                <option value="Devolução">Devolução ao fornecedor</option>
                <option value="Ajuste">Ajuste de inventário</option>
                <option value="Outro">Outro</option>
            </select>
        </td>
        <td>
            <input type="text" class="form-control" name="observacao[]" placeholder="Observação">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger btn-remover-item">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há um produto pré-selecionado
    const produtoPreSelecionado = <?php echo $produto_pre_selecionado ? json_encode($produto_pre_selecionado) : 'null'; ?>;
    
    // Função para configurar eventos de uma linha de item
    function setupItemRow(row) {
        const produtoSelect = row.querySelector('.produto-select');
        const unidadeAddon = row.querySelector('.unidade-addon');
        const estoqueInfo = row.querySelector('.estoque-info');
        const quantidadeInput = row.querySelector('.quantidade-input');
        const btnRemover = row.querySelector('.btn-remover-item');
        
        // Evento ao selecionar um produto
        produtoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const unidade = selectedOption.dataset.unidade;
                const estoque = selectedOption.dataset.estoque;
                
                unidadeAddon.textContent = unidade;
                estoqueInfo.textContent = 'Disponível: ' + parseFloat(estoque).toFixed(2).replace('.', ',') + ' ' + unidade;
                
                // Limitar a quantidade máxima à quantidade disponível
                quantidadeInput.setAttribute('max', estoque);
                quantidadeInput.value = '';
            } else {
                unidadeAddon.textContent = 'un';
                estoqueInfo.textContent = 'Disponível: -';
                quantidadeInput.removeAttribute('max');
                quantidadeInput.value = '';
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
                const quantidade = parseFloat(valor.replace(',', '.') || 0);
                
                if (quantidade > estoque) {
                    quantidadeInput.classList.add('is-invalid');
                    quantidadeInput.setCustomValidity('Quantidade excede o estoque disponível');
                } else {
                    quantidadeInput.classList.remove('is-invalid');
                    quantidadeInput.setCustomValidity('');
                }
            }
        });
        
        // Evento para remover linha
        btnRemover.addEventListener('click', function() {
            // Verificar se é a única linha
            const itensContainer = document.getElementById('itens-container');
            if (itensContainer.querySelectorAll('.linha-item').length > 1) {
                row.remove();
            } else {
                alert('Você precisa ter pelo menos uma linha de produto.');
            }
        });
    }
    
    // Configurar eventos para a primeira linha
    const primeiraLinha = document.querySelector('.linha-item');
    setupItemRow(primeiraLinha);
    
    // Se houver um produto pré-selecionado, configurá-lo na primeira linha
    if (produtoPreSelecionado) {
        const produtoSelect = primeiraLinha.querySelector('.produto-select');
        
        // Selecionar o produto no dropdown
        for (let i = 0; i < produtoSelect.options.length; i++) {
            if (produtoSelect.options[i].value == produtoPreSelecionado.id) {
                produtoSelect.selectedIndex = i;
                
                // Simular o evento change para atualizar os campos relacionados
                const changeEvent = new Event('change');
                produtoSelect.dispatchEvent(changeEvent);
                
                // Foco no campo de quantidade
                const quantidadeInput = primeiraLinha.querySelector('.quantidade-input');
                quantidadeInput.focus();
                
                break;
            }
        }
    }
    
    // Botão para adicionar nova linha
    document.getElementById('btn-adicionar-item').addEventListener('click', function() {
        const template = document.getElementById('template-item');
        const clone = template.content.cloneNode(true);
        const novaLinha = clone.querySelector('.linha-item');
        
        // Adicionar ao container
        document.getElementById('itens-container').appendChild(novaLinha);
        
        // Configurar eventos para a nova linha
        setupItemRow(novaLinha);
    });
    
    // Validação do formulário
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        // Verificar se pelo menos uma linha tem produto válido
        let temProdutoValido = false;
        let temErroEstoque = false;
        const linhas = form.querySelectorAll('.linha-item');
        
        linhas.forEach(function(linha) {
            const produtoSelect = linha.querySelector('.produto-select');
            const quantidadeInput = linha.querySelector('.quantidade-input');
            
            if (produtoSelect.value !== '') {
                temProdutoValido = true;
                
                // Verificar se há estoque suficiente
                const estoque = parseFloat(produtoSelect.options[produtoSelect.selectedIndex].dataset.estoque);
                const quantidade = parseFloat(quantidadeInput.value.replace(',', '.') || 0);
                
                if (quantidade > estoque) {
                    temErroEstoque = true;
                    quantidadeInput.classList.add('is-invalid');
                }
            }
        });
        
        if (!temProdutoValido) {
            event.preventDefault();
            event.stopPropagation();
            alert('Selecione pelo menos um produto para saída de estoque.');
            return;
        }
        
        if (temErroEstoque) {
            event.preventDefault();
            event.stopPropagation();
            alert('Uma ou mais quantidades excedem o estoque disponível.');
            return;
        }
        
        // Validar formulário
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    }, false);
});
</script>

<?php require_once('includes/footer.php'); ?>