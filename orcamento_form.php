<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Inicializar variáveis
$id = 0;
$numero = '';
$cliente_id = 1; // Cliente 'Sem Identificação' como padrão
$data_criacao = date('Y-m-d');
$data_validade = date('Y-m-d', strtotime('+30 days'));
$status = 'pendente';
$taxa_mao_obra = 100; // Valor padrão
$valor_produtos = 0;
$valor_mao_obra = 0;
$valor_total = 0;
$observacoes = '';
$codigo_acesso = md5(uniqid(rand(), true));
$itens = [];
$acao = 'cadastrar';
$titulo = 'Novo Orçamento';
$mensagem = '';
$forma_pagamento = 'prazo'; // Default payment method
$desconto_vista = 10; // Default discount for cash payment
$tempo_previsto_horas = 2; // Tempo padrão para execução do serviço em horas

// Buscar configurações do banco de dados
$stmt = $db->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('desconto_pagamento_vista', 'taxa_padrao_mao_obra')");
$configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Aplicar configurações, se existirem
if (isset($configs['desconto_pagamento_vista'])) {
    $desconto_vista = (float)$configs['desconto_pagamento_vista'];
}
if (isset($configs['taxa_padrao_mao_obra'])) {
    $taxa_mao_obra = (float)$configs['taxa_padrao_mao_obra'];
}


// Verificar se é uma edição
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $orcamento = buscarOrcamento($id);

    if ($orcamento) {
        $numero = $orcamento['numero'];
        $cliente_id = $orcamento['cliente_id'];
        $data_criacao = $orcamento['data_criacao'];
        $data_validade = $orcamento['data_validade'];
        $status = $orcamento['status'];
        $taxa_mao_obra = $orcamento['taxa_mao_obra'];
        $valor_produtos = $orcamento['valor_produtos'];
        $valor_mao_obra = $orcamento['valor_mao_obra'];
        $valor_total = $orcamento['valor_total'];
        $observacoes = $orcamento['observacoes'];
        $codigo_acesso = $orcamento['codigo_acesso'];
        $forma_pagamento = $orcamento['forma_pagamento'];
        $itens = buscarItensOrcamento($id);
        $acao = 'atualizar';
        $titulo = 'Editar Orçamento';
    } else {
        $mensagem = alerta('Orçamento não encontrado!', 'danger');
    }
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao_form = $_POST['acao'];

    // Obter os dados do formulário
    $cliente_id = intval($_POST['cliente_id']);
    $data_validade = limpaString($_POST['data_validade']);
    $taxa_mao_obra = floatval(str_replace(',', '.', $_POST['taxa_mao_obra']));
    $observacoes = limpaString($_POST['observacoes']);
    $forma_pagamento = $_POST['forma_pagamento'];
    $tempo_previsto_horas = intval($_POST['tempo_previsto_horas']);

    // Validar os dados
    if ($cliente_id <= 0) {
        $mensagem = alerta('Por favor, selecione um cliente válido!', 'danger');
    } else {
        try {
            // Iniciar transação
            $db->beginTransaction();

            // Array para os dados do orçamento
            $orcamento_data = [
                'cliente_id' => $cliente_id,
                'data_validade' => dataParaMysql($data_validade),
                'taxa_mao_obra' => $taxa_mao_obra,
                'observacoes' => $observacoes,
                'usuario_id' => $_SESSION['usuario_id'],
                'forma_pagamento' => $forma_pagamento,
                'tempo_previsto_horas' => $tempo_previsto_horas
            ];

            if ($acao_form == 'cadastrar') {
                // Gerar número de orçamento
                $orcamento_data['numero'] = gerarNumeroOrcamento();
                $orcamento_data['data_criacao'] = date('Y-m-d H:i:s');
                $orcamento_data['status'] = 'pendente';
                $orcamento_data['codigo_acesso'] = $codigo_acesso;

                // Inserir o orçamento
                $colunas = implode(', ', array_keys($orcamento_data));
                $placeholders = ':' . implode(', :', array_keys($orcamento_data));

                $stmt = $db->prepare("INSERT INTO orcamentos ({$colunas}) VALUES ({$placeholders})");
                foreach ($orcamento_data as $campo => $valor) {
                    $stmt->bindValue(":{$campo}", $valor);
                }
                $stmt->execute();

                $id = $db->lastInsertId();
                
                // Processar itens (se houver)
                if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
                    // Inserir cada item do formulário
                    foreach ($_POST['item_id'] as $index => $item_id) {
                        $produto_id = intval($_POST['produto_id'][$index]);
                        $descricao = limpaString($_POST['descricao'][$index]);
                        $unidade = limpaString($_POST['unidade'][$index]);
                        $quantidade = floatval(str_replace(',', '.', $_POST['quantidade'][$index]));
                        $valor_unitario = floatval(str_replace(',', '.', $_POST['valor_unitario'][$index]));
                        $valor_total_item = $quantidade * $valor_unitario;
                        
                        if ($produto_id > 0) { // Verificar se há um produto selecionado
                            // Inserir item
                            $stmt = $db->prepare("INSERT INTO orcamento_itens 
                                                (orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) 
                                                VALUES 
                                                (:orcamento_id, :produto_id, :descricao, :unidade, :quantidade, :valor_unitario, :valor_total)");
                                                
                            $stmt->bindValue(':orcamento_id', $id, PDO::PARAM_INT);
                            $stmt->bindValue(':produto_id', $produto_id, PDO::PARAM_INT);
                            $stmt->bindValue(':descricao', $descricao);
                            $stmt->bindValue(':unidade', $unidade);
                            $stmt->bindValue(':quantidade', $quantidade);
                            $stmt->bindValue(':valor_unitario', $valor_unitario);
                            $stmt->bindValue(':valor_total', $valor_total_item);
                            $stmt->execute();
                        }
                    }
                    
                    // Calcular os totais
                    $total_produtos = calcularTotalOrcamento($id);
                    $valor_mao_obra = $total_produtos * ($taxa_mao_obra / 100);
                    $valor_total = $total_produtos + $valor_mao_obra;
                    
                    // Apply discount if payment method is cash
                    if ($forma_pagamento == 'vista') {
                        $valor_total -= ($valor_total * ($desconto_vista / 100));
                    }
                    
                    // Atualizar totais no orçamento
                    $stmt = $db->prepare("UPDATE orcamentos SET 
                                        valor_produtos = :valor_produtos, 
                                        valor_mao_obra = :valor_mao_obra, 
                                        valor_total = :valor_total 
                                        WHERE id = :id");
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':valor_produtos', $total_produtos);
                    $stmt->bindValue(':valor_mao_obra', $valor_mao_obra);
                    $stmt->bindValue(':valor_total', $valor_total);
                    $stmt->execute();
                }

                // Redirecionar para a visualização com o ID gerado
                $db->commit();
                header("Location: orcamento_visualizar.php?id={$id}&mensagem=cadastrado");
                exit;
            } else if ($acao_form == 'atualizar' && $id > 0) {
                // Atualizar o orçamento
                $sql_partes = [];
                foreach ($orcamento_data as $campo => $valor) {
                    $sql_partes[] = "{$campo} = :{$campo}";
                }
                $sql_update = implode(', ', $sql_partes);

                $stmt = $db->prepare("UPDATE orcamentos SET {$sql_update} WHERE id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                foreach ($orcamento_data as $campo => $valor) {
                    $stmt->bindValue(":{$campo}", $valor);
                }
                $stmt->execute();

                // Processar itens (se houver)
                if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
                    // Excluir os itens que não estão no formulário
                    $itens_atuais = [];
                    foreach ($_POST['item_id'] as $index => $item_id) {
                        if (!empty($item_id)) {
                            $itens_atuais[] = intval($item_id);
                        }
                    }

                    // Se houver itens, prepara a cláusula para excluir os que não estão presentes
                    if (!empty($itens_atuais)) {
                        $itens_a_manter = implode(',', $itens_atuais);
                        $stmt = $db->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :orcamento_id AND id NOT IN ({$itens_a_manter})");
                    } else {
                        // Se não houver itens mantidos, exclui todos
                        $stmt = $db->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :orcamento_id");
                    }
                    $stmt->bindValue(':orcamento_id', $id, PDO::PARAM_INT);
                    $stmt->execute();

                    // Atualizar ou inserir cada item do formulário
                    foreach ($_POST['item_id'] as $index => $item_id) {
                        $produto_id = intval($_POST['produto_id'][$index]);
                        $descricao = limpaString($_POST['descricao'][$index]);
                        $unidade = limpaString($_POST['unidade'][$index]);
                        $quantidade = floatval(str_replace(',', '.', $_POST['quantidade'][$index]));
                        $valor_unitario = floatval(str_replace(',', '.', $_POST['valor_unitario'][$index]));
                        $valor_total_item = $quantidade * $valor_unitario;

                        if (!empty($item_id)) {
                            // Atualizar item existente
                            $stmt = $db->prepare("UPDATE orcamento_itens SET 
                                                produto_id = :produto_id, 
                                                descricao = :descricao, 
                                                unidade = :unidade, 
                                                quantidade = :quantidade, 
                                                valor_unitario = :valor_unitario, 
                                                valor_total = :valor_total 
                                                WHERE id = :id AND orcamento_id = :orcamento_id");
                            $stmt->bindValue(':id', $item_id, PDO::PARAM_INT);
                        } else {
                            // Inserir novo item
                            $stmt = $db->prepare("INSERT INTO orcamento_itens 
                                                (orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) 
                                                VALUES 
                                                (:orcamento_id, :produto_id, :descricao, :unidade, :quantidade, :valor_unitario, :valor_total)");
                        }

                        $stmt->bindValue(':orcamento_id', $id, PDO::PARAM_INT);
                        $stmt->bindValue(':produto_id', $produto_id, PDO::PARAM_INT);
                        $stmt->bindValue(':descricao', $descricao);
                        $stmt->bindValue(':unidade', $unidade);
                        $stmt->bindValue(':quantidade', $quantidade);
                        $stmt->bindValue(':valor_unitario', $valor_unitario);
                        $stmt->bindValue(':valor_total', $valor_total_item);
                        $stmt->execute();
                    }

                    // Calcular os totais
                    $total_produtos = calcularTotalOrcamento($id);
                    $valor_mao_obra = $total_produtos * ($taxa_mao_obra / 100);
                    $valor_total = $total_produtos + $valor_mao_obra;

                    // Apply discount if payment method is cash
                    if ($forma_pagamento == 'vista') {
                        $valor_total -= ($valor_total * ($desconto_vista / 100));
                    }

                    // Atualizar totais no orçamento
                    $stmt = $db->prepare("UPDATE orcamentos SET 
                                        valor_produtos = :valor_produtos, 
                                        valor_mao_obra = :valor_mao_obra, 
                                        valor_total = :valor_total 
                                        WHERE id = :id");
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':valor_produtos', $total_produtos);
                    $stmt->bindValue(':valor_mao_obra', $valor_mao_obra);
                    $stmt->bindValue(':valor_total', $valor_total);
                    $stmt->execute();
                }

                $db->commit();
                $mensagem = alerta('Orçamento atualizado com sucesso!', 'success');

                // Recarregar dados do orçamento e itens
                $orcamento = buscarOrcamento($id);
                $itens = buscarItensOrcamento($id);
            }
        } catch (Exception $e) {
            $db->rollback();
            $mensagem = alerta('Erro ao processar orçamento: ' . $e->getMessage(), 'danger');
        }
    }
}

// Consultar clientes para o select
$stmt = $db->query("SELECT id, nome, cpf_cnpj FROM clientes ORDER BY nome");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar produtos para o select
$stmt = $db->query("SELECT id, codigo, descricao, unidade, valor_unitario FROM produtos ORDER BY descricao");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agora podemos incluir o header, depois de qualquer possível redirecionamento
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-invoice-dollar me-2"></i><?php echo $titulo; ?></h1>
    <div>
        <a href="orcamentos.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
        <?php if ($id > 0): ?>
        <a href="orcamento_visualizar.php?id=<?php echo $id; ?>" class="btn btn-info text-white">
            <i class="fas fa-eye me-2"></i>Visualizar
        </a>
        <?php endif; ?>
    </div>
</div>

<?php echo $mensagem; ?>

<form id="formOrcamento" method="post" class="needs-validation" novalidate>
    <input type="hidden" name="acao" value="<?php echo $acao; ?>">

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações Básicas</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php if ($id > 0): ?>
                <div class="col-md-3 mb-3">
                    <label for="numero" class="form-label">Número do Orçamento</label>
                    <input type="text" class="form-control" id="numero" value="<?php echo $numero; ?>" readonly>
                </div>
                <?php endif; ?>

                <div class="<?php echo ($id > 0) ? 'col-md-9' : 'col-md-12'; ?> mb-3">
                    <label for="cliente_id" class="form-label required-field">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione um cliente</option>
                        <!-- Cliente "Sem Identificação" já vem selecionado como padrão -->
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo ($cliente_id == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo $cliente['nome']; ?> <?php echo $cliente['cpf_cnpj'] ? " - " . $cliente['cpf_cnpj'] : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione um cliente.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="data_criacao" class="form-label">Data de Criação</label>
                    <input type="text" class="form-control" id="data_criacao" value="<?php echo dataParaBr($data_criacao); ?>" readonly>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="data_validade" class="form-label required-field">Data de Validade</label>
                    <input type="text" class="form-control" id="data_validade" name="data_validade" value="<?php echo dataParaBr($data_validade); ?>" required>
                    <div class="invalid-feedback">Por favor, informe a data de validade.</div>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="taxa_mao_obra" class="form-label required-field">Taxa de Mão de Obra (%)</label>
                    <div class="input-group">
                        <input type="text" class="form-control monetary-input" id="taxa_mao_obra" name="taxa_mao_obra" value="<?php echo number_format($taxa_mao_obra, 2, ',', '.'); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="invalid-feedback">Por favor, informe a taxa de mão de obra.</div>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                    <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                        <option value="prazo" <?php echo ($forma_pagamento == 'prazo') ? 'selected' : ''; ?>>A Prazo</option>
                        <option value="vista" <?php echo ($forma_pagamento == 'vista') ? 'selected' : ''; ?>>À Vista</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="tempo_previsto_horas" class="form-label required-field">Tempo Previsto (horas)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="tempo_previsto_horas" name="tempo_previsto_horas" value="<?php echo isset($tempo_previsto_horas) ? $tempo_previsto_horas : 2; ?>" min="1" max="24" required>
                        <span class="input-group-text">h</span>
                    </div>
                    <div class="form-text">Tempo estimado para a execução do serviço.</div>
                </div>

            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $observacoes; ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Itens do Orçamento</h5>
            <button type="button" class="btn btn-light btn-sm" id="btnAdicionarItem">
                <i class="fas fa-plus-circle me-1"></i>Adicionar Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tabelaItens">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Produto</th>
                            <th>Descrição</th>
                            <th style="width: 10%;">Unidade</th>
                            <th style="width: 10%;">Quantidade</th>
                            <th style="width: 15%;">Valor Unitário</th>
                            <th style="width: 15%;">Valor Total</th>
                            <th style="width: 5%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($itens) > 0): ?>
                            <?php foreach ($itens as $index => $item): ?>
                                <tr class="linha-item">
                                    <td>
                                        <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                                        <select class="form-select produto-select" name="produto_id[]" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($produtos as $produto): ?>
                                                <option value="<?php echo $produto['id']; ?>" 
                                                        data-descricao="<?php echo $produto['descricao']; ?>"
                                                        data-unidade="<?php echo $produto['unidade']; ?>"
                                                        data-valor="<?php echo $produto['valor_unitario']; ?>"
                                                        <?php echo ($item['produto_id'] == $produto['id']) ? 'selected' : ''; ?>>
                                                    <?php echo "{$produto['codigo']} - {$produto['descricao']}"; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="descricao[]" value="<?php echo $item['descricao']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="unidade[]" value="<?php echo $item['unidade']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control monetary-input quantidade-input" name="quantidade[]" value="<?php echo number_format($item['quantidade'], 2, ',', '.'); ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control monetary-input valor-unitario" name="valor_unitario[]" value="<?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control valor-total" value="<?php echo number_format($item['valor_total'], 2, ',', '.'); ?>" readonly>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger btn-remover-item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="nenhumItem">
                                <td colspan="7" class="text-center">Nenhum item adicionado ao orçamento.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end fw-bold">Total de Produtos:</td>
                            <td>
                                <input type="text" class="form-control" id="total_produtos" value="<?php echo formataValor($valor_produtos); ?>" readonly>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-end fw-bold"><span id="texto_mao_obra">Mão de Obra (<?php echo number_format($taxa_mao_obra, 2, ',', '.'); ?>%):</span></td>
                            <td>
                                <input type="text" class="form-control" id="total_mao_obra" value="<?php echo formataValor($valor_mao_obra); ?>" readonly>
                            </td>
                            <td></td>
                        </tr>
                        <tr id="linha_desconto" style="display:none; color: green;">
                            <td colspan="5" class="text-end fw-bold">Desconto à vista (<?php echo formataValor($desconto_vista); ?>%):</td>
                            <td>
                                <input type="text" class="form-control" id="total_desconto" value="R$ 0,00" readonly style="color: green;">
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-end fw-bold">Valor Total:</td>
                            <td>
                                <input type="text" class="form-control" id="total_geral" value="<?php echo formataValor($valor_total); ?>" readonly>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <a href="orcamentos.php" class="btn btn-secondary me-2">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Salvar Orçamento
        </button>
    </div>
</form>

<!-- Template para adicionar novos itens -->
<template id="itemTemplate">
    <tr class="linha-item">
        <td>
            <input type="hidden" name="item_id[]" value="">
            <select class="form-select produto-select" name="produto_id[]" required>
                <option value="">Selecione...</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?php echo $produto['id']; ?>" 
                            data-descricao="<?php echo $produto['descricao']; ?>"
                            data-unidade="<?php echo $produto['unidade']; ?>"
                            data-valor="<?php echo $produto['valor_unitario']; ?>">
                        <?php echo "{$produto['codigo']} - {$produto['descricao']}"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" class="form-control" name="descricao[]" required>
        </td>
        <td>
            <input type="text" class="form-control" name="unidade[]" required>
        </td>
        <td>
            <input type="text" class="form-control monetary-input quantidade-input" name="quantidade[]" value="1,00" required>
        </td>
        <td>
            <input type="text" class="form-control monetary-input valor-unitario" name="valor_unitario[]" value="0,00" required>
        </td>
        <td>
            <input type="text" class="form-control valor-total" value="0,00" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger btn-remover-item">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar máscara para o campo de data
    if (typeof IMask !== 'undefined') {
        const dataMask = IMask(document.getElementById('data_validade'), {
            mask: '00/00/0000'
        });
    }
    
    // Inicializar formatação monetária para campos existentes
    document.querySelectorAll('.monetary-input').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let valor = e.target.value.replace(/\D/g, '');
            if (valor.length === 0) {
                e.target.value = '';
                return;
            }
            valor = (parseInt(valor) / 100).toFixed(2);
            e.target.value = valor.replace('.', ',');
        });
    });
    
    // Inicializar formatação decimal para campos de quantidade
    document.querySelectorAll('.quantidade-input').forEach(function(input) {
        input.addEventListener('input', function(e) {
            // Remover formatação anterior
            let valor = e.target.value.replace(/\./g, '').replace(',', '.');
            
            // Se não for um número válido, limpar o campo
            if (isNaN(parseFloat(valor)) || !isFinite(valor)) {
                e.target.value = '';
                return;
            }
            
            // Formatar com 2 casas decimais
            valor = parseFloat(valor).toFixed(2);
            e.target.value = valor.replace('.', ',');
            
            // Recalcular totais se estiver em uma linha de item
            if (e.target.closest('.linha-item')) {
                calcularValorTotalItem(e.target.closest('.linha-item'));
                recalcularTotais();
            }
        });
    });

    // Função para calcular o valor total de um item
    function calcularValorTotalItem(row) {
        const quantidade = parseFloat(row.querySelector('.quantidade-input').value.replace('.', '').replace(',', '.')) || 0;
        const valorUnitario = parseFloat(row.querySelector('.valor-unitario').value.replace('.', '').replace(',', '.')) || 0;
        const valorTotal = quantidade * valorUnitario;

        row.querySelector('.valor-total').value = valorTotal.toFixed(2).replace('.', ',');
        return valorTotal;
    }

    // Função para recalcular totais do orçamento
    function recalcularTotais() {
        let totalProdutos = 0;
        document.querySelectorAll('.linha-item').forEach(function(row) {
            totalProdutos += calcularValorTotalItem(row);
        });

        const taxaMaoObra = parseFloat(document.getElementById('taxa_mao_obra').value.replace('.', '').replace(',', '.')) || 0;
        let valorMaoObra = totalProdutos * (taxaMaoObra / 100);
        let subtotal = totalProdutos + valorMaoObra;
        let valorTotal = subtotal;
        let valorDesconto = 0;

        const formaPagamento = document.getElementById('forma_pagamento').value;
        const linhaDesconto = document.getElementById('linha_desconto');
        
        if (formaPagamento === 'vista') {
            const descontoVista = parseFloat(<?php echo json_encode($desconto_vista); ?>); // Fetch discount from PHP
            valorDesconto = subtotal * (descontoVista / 100);
            valorTotal = subtotal - valorDesconto;
            
            // Mostrar linha de desconto
            linhaDesconto.style.display = 'table-row';
            document.getElementById('total_desconto').value = '- R$ ' + valorDesconto.toFixed(2).replace('.', ',');
        } else {
            // Esconder linha de desconto
            linhaDesconto.style.display = 'none';
        }

        // Atualizar texto da mão de obra para refletir a porcentagem atual
        document.getElementById('texto_mao_obra').innerHTML = 'Mão de Obra (' + taxaMaoObra.toFixed(2).replace('.', ',') + '%):'
        
        document.getElementById('total_produtos').value = 'R$ ' + totalProdutos.toFixed(2).replace('.', ',');
        document.getElementById('total_mao_obra').value = 'R$ ' + valorMaoObra.toFixed(2).replace('.', ',');
        document.getElementById('total_geral').value = 'R$ ' + valorTotal.toFixed(2).replace('.', ',');
    }

    // Evento de mudança em quantidade ou valor unitário
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantidade-input') || 
            e.target.classList.contains('valor-unitario') ||
            e.target.id === 'taxa_mao_obra') {
            recalcularTotais();
        }
    });
    
    // Evento para forma de pagamento
    document.getElementById('forma_pagamento').addEventListener('change', function() {
        recalcularTotais();
    });

    // Evento para seleção de produto
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('produto-select')) {
            const option = e.target.options[e.target.selectedIndex];
            const row = e.target.closest('tr');

            if (option.value) {
                row.querySelector('input[name="descricao[]"]').value = option.dataset.descricao;
                row.querySelector('input[name="unidade[]"]').value = option.dataset.unidade;
                row.querySelector('input[name="valor_unitario[]"]').value = parseFloat(option.dataset.valor).toFixed(2).replace('.', ',');

                calcularValorTotalItem(row);
                recalcularTotais();
            }
        }
    });

    // Botão para adicionar novo item
    document.getElementById('btnAdicionarItem').addEventListener('click', function() {
        const nenhumItem = document.getElementById('nenhumItem');
        if (nenhumItem) {
            nenhumItem.remove();
        }

        const template = document.getElementById('itemTemplate');
        const clone = document.importNode(template.content, true);
        document.querySelector('#tabelaItens tbody').appendChild(clone);

        // Inicializar formatação monetária nos novos campos
        const novaLinha = document.querySelector('#tabelaItens tbody tr:last-child');
        const monetaryInputs = novaLinha.querySelectorAll('.monetary-input');

        monetaryInputs.forEach(function(input) {
            input.addEventListener('input', function(e) {
                let valor = e.target.value.replace(/\D/g, '');
                if (valor.length === 0) {
                    e.target.value = '';
                    return;
                }
                valor = (parseInt(valor) / 100).toFixed(2);
                e.target.value = valor.replace('.', ',');
            });
        });
        
        // Inicializar formatação decimal para campos de quantidade
        const quantidadeInputs = novaLinha.querySelectorAll('.quantidade-input');
        quantidadeInputs.forEach(function(input) {
            input.addEventListener('input', function(e) {
                // Remover formatação anterior
                let valor = e.target.value.replace(/\./g, '').replace(',', '.');
                
                // Se não for um número válido, limpar o campo
                if (isNaN(parseFloat(valor)) || !isFinite(valor)) {
                    e.target.value = '';
                    return;
                }
                
                // Formatar com 2 casas decimais
                valor = parseFloat(valor).toFixed(2);
                e.target.value = valor.replace('.', ',');
                
                // Recalcular totais se estiver em uma linha de item
                if (e.target.closest('.linha-item')) {
                    calcularValorTotalItem(e.target.closest('.linha-item'));
                    recalcularTotais();
                }
            });
        });

        recalcularTotais();
    });

    // Remover item
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remover-item')) {
            const row = e.target.closest('tr');
            row.remove();

            // Se não houver mais itens, mostrar mensagem
            const linhasItem = document.querySelectorAll('.linha-item');
            if (linhasItem.length === 0) {
                const tbody = document.querySelector('#tabelaItens tbody');
                tbody.innerHTML = '<tr id="nenhumItem"><td colspan="7" class="text-center">Nenhum item adicionado ao orçamento.</td></tr>';
            }

            recalcularTotais();
        }
    });

    // Calcular totais na inicialização
    recalcularTotais();

    // Validação do formulário
    const form = document.getElementById('formOrcamento');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');

        // Verificar se há itens no orçamento
        const linhasItem = document.querySelectorAll('.linha-item');
        if (linhasItem.length === 0) {
            event.preventDefault();
            alert('Por favor, adicione pelo menos um item ao orçamento.');
        }
    }, false);
});
</script>

<?php require_once('includes/footer.php'); ?>