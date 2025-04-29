<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Inicializar variáveis
$mensagem = '';

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
            $valor_unitario = floatval(str_replace(',', '.', $_POST['valor_unitario'][$index]));
            $observacao = limpaString($_POST['observacao'][$index] ?? '');
            $atualizar_valor = isset($_POST['atualizar_valor'][$index]) ? 1 : 0;
            
            // Validações básicas
            if ($produto_id <= 0) {
                continue; // Pular produto inválido
            }
            
            if ($quantidade <= 0) {
                continue; // Pular quantidade inválida
            }
            
            if ($valor_unitario <= 0) {
                continue; // Pular valor inválido
            }
            
            // Buscar produto
            $produto = buscarProduto($produto_id);
            if (!$produto) {
                continue; // Pular produto não encontrado
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
            
            // Atualizar o valor unitário do produto (se solicitado)
            if ($atualizar_valor) {
                $stmt = $db->prepare("UPDATE produtos SET valor_unitario = :valor_unitario WHERE id = :id");
                $stmt->bindParam(':valor_unitario', $valor_unitario);
                $stmt->bindParam(':id', $produto_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            $produtos_atualizados++;
        }
        
        // Verificar se algum produto foi atualizado
        if ($produtos_atualizados == 0) {
            throw new Exception('Nenhum produto válido foi informado para entrada no estoque.');
        }
        
        // Confirmar transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        echo "<script>window.location.href = 'estoque.php?mensagem=estoque_atualizado';</script>";
        exit;
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $db->rollback();
        $mensagem = alerta('Erro ao registrar entrada no estoque: ' . $e->getMessage(), 'danger');
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
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Produto</th>
                            <th style="width: 15%;">Quantidade</th>
                            <th style="width: 15%;">Valor Unitário</th>
                            <th style="width: 20%;">Observação</th>
                            <th style="width: 5%;">Atualizar Preço</th>
                            <th style="width: 5%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="itens-container">
                        <tr class="linha-item">
                            <td>
                                <select class="form-select produto-select" name="produto_id[]" required>
                                    <option value="">Selecione um produto</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                data-valor="<?php echo $p['valor_unitario']; ?>"
                                                data-unidade="<?php echo $p['unidade']; ?>">
                                            <?php echo "{$p['codigo']} - {$p['descricao']} ({$p['unidade']})"; ?>
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
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control monetary-input valor-unitario" name="valor_unitario[]" required>
                                </div>
                            </td>
                            <td>
                                <input type="text" class="form-control" name="observacao[]" placeholder="Observação">
                            </td>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="atualizar_valor[]" checked>
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
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Registrar Entradas
                    </button>
                </div>
            </div>
        </form>
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
                            data-valor="<?php echo $p['valor_unitario']; ?>"
                            data-unidade="<?php echo $p['unidade']; ?>">
                        <?php echo "{$p['codigo']} - {$p['descricao']} ({$p['unidade']})"; ?>
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
            <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" class="form-control monetary-input valor-unitario" name="valor_unitario[]" required>
            </div>
        </td>
        <td>
            <input type="text" class="form-control" name="observacao[]" placeholder="Observação">
        </td>
        <td class="text-center">
            <input class="form-check-input" type="checkbox" name="atualizar_valor[]" checked>
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
    // Função para configurar eventos de uma linha de item
    function setupItemRow(row) {
        const produtoSelect = row.querySelector('.produto-select');
        const unidadeAddon = row.querySelector('.unidade-addon');
        const valorUnitarioInput = row.querySelector('.valor-unitario');
        const quantidadeInput = row.querySelector('.quantidade-input');
        const btnRemover = row.querySelector('.btn-remover-item');
        
        // Evento ao selecionar um produto
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
        const selects = form.querySelectorAll('.produto-select');
        selects.forEach(function(select) {
            if (select.value !== '') {
                temProdutoValido = true;
            }
        });
        
        if (!temProdutoValido) {
            event.preventDefault();
            event.stopPropagation();
            alert('Selecione pelo menos um produto para entrada de estoque.');
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