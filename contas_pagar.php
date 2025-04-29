<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Incluir o script para contas recorrentes
require_once('includes/gerar_contas_recorrentes.php');

// Verificar e gerar contas recorrentes
gerarContasRecorrentes();

// Processar exclusão de conta
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    
    try {
        // Verificar se existe pagamento relacionado a essa conta
        $stmt = $db->prepare("SELECT COUNT(*) FROM pagamentos_contas WHERE conta_id = ?");
        $stmt->execute([$id]);
        $tem_pagamentos = $stmt->fetchColumn() > 0;
        
        if ($tem_pagamentos) {
            $mensagem = alerta('Não é possível excluir esta conta pois ela possui pagamentos registrados.', 'danger');
        } else {
            // Verificar se existe lançamento no caixa relacionado a essa conta
            $stmt = $db->prepare("SELECT COUNT(*) FROM caixa WHERE conta_pagar_id = ?");
            $stmt->execute([$id]);
            $tem_caixa = $stmt->fetchColumn() > 0;
            
            if ($tem_caixa) {
                $mensagem = alerta('Não é possível excluir esta conta pois ela possui lançamentos no caixa.', 'danger');
            } else {
                // Excluir a conta
                $stmt = $db->prepare("DELETE FROM contas_pagar WHERE id = ?");
                $stmt->execute([$id]);
                
                $mensagem = alerta('Conta excluída com sucesso!', 'success');
            }
        }
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao excluir conta: ' . $e->getMessage(), 'danger');
    }
}

// Inicializar filtros
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'todos';
$filtro_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_fornecedor = isset($_GET['fornecedor']) ? $_GET['fornecedor'] : '';

// Preparar condições do filtro
$condicoes = [];
$params = [];

if ($filtro_status != 'todos') {
    $condicoes[] = "status = ?";
    $params[] = $filtro_status;
}

if ($filtro_periodo != 'todos') {
    // Período em dias
    $dias = intval($filtro_periodo);
    $condicoes[] = "data_vencimento BETWEEN CURRENT_DATE - INTERVAL '{$dias} days' AND CURRENT_DATE + INTERVAL '{$dias} days'";
}

if (!empty($filtro_categoria)) {
    $condicoes[] = "categoria ILIKE ?";
    $params[] = "%{$filtro_categoria}%";
}

if (!empty($filtro_fornecedor)) {
    $condicoes[] = "fornecedor ILIKE ?";
    $params[] = "%{$filtro_fornecedor}%";
}

// Montar a query com filtros
$where = !empty($condicoes) ? 'WHERE ' . implode(' AND ', $condicoes) : '';
$sql = "SELECT * FROM contas_pagar {$where} ORDER BY data_vencimento ASC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar categorias distintas para o filtro
    $stmt = $db->query("SELECT DISTINCT categoria FROM contas_pagar WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar fornecedores distintos para o filtro
    $stmt = $db->query("SELECT DISTINCT fornecedor FROM contas_pagar WHERE fornecedor IS NOT NULL AND fornecedor != '' ORDER BY fornecedor");
    $fornecedores = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $mensagem = alerta('Erro ao buscar contas: ' . $e->getMessage(), 'danger');
    $contas = [];
    $categorias = [];
    $fornecedores = [];
}

// Calcular totais
$total_pendente = 0;
$total_pago = 0;
$total_vencido = 0;
$hoje = new DateTime('today');

foreach ($contas as $conta) {
    if ($conta['status'] == 'pendente') {
        $total_pendente += $conta['valor'];
        
        // Verificar se está vencida
        $data_vencimento = new DateTime($conta['data_vencimento']);
        if ($data_vencimento < $hoje) {
            $total_vencido += $conta['valor'];
        }
    } elseif ($conta['status'] == 'pago') {
        $total_pago += $conta['valor_pago'] ?? $conta['valor'];
    }
}

// Incluir o cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-invoice-dollar me-2"></i>Contas a Pagar</h1>
    <a href="conta_pagar_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Nova Conta
    </a>
</div>

<?php if (isset($mensagem)) echo $mensagem; ?>

<!-- Painel de resumo -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h5 class="card-title">Total Pendente</h5>
                <h3 class="card-text"><?php echo formataValor($total_pendente); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-danger h-100">
            <div class="card-body">
                <h5 class="card-title">Total Vencido</h5>
                <h3 class="card-text"><?php echo formataValor($total_vencido); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h5 class="card-title">Total Pago (período)</h5>
                <h3 class="card-text"><?php echo formataValor($total_pago); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="todos" <?php echo $filtro_status == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="pendente" <?php echo $filtro_status == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="pago" <?php echo $filtro_status == 'pago' ? 'selected' : ''; ?>>Pago</option>
                    <option value="cancelado" <?php echo $filtro_status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="periodo" class="form-label">Período</label>
                <select class="form-select" id="periodo" name="periodo">
                    <option value="15" <?php echo $filtro_periodo == '15' ? 'selected' : ''; ?>>15 dias</option>
                    <option value="30" <?php echo $filtro_periodo == '30' ? 'selected' : ''; ?>>30 dias</option>
                    <option value="60" <?php echo $filtro_periodo == '60' ? 'selected' : ''; ?>>60 dias</option>
                    <option value="90" <?php echo $filtro_periodo == '90' ? 'selected' : ''; ?>>90 dias</option>
                    <option value="todos" <?php echo $filtro_periodo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="categoria" class="form-label">Categoria</label>
                <select class="form-select" id="categoria" name="categoria">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo htmlspecialchars($categoria); ?>" <?php echo $filtro_categoria == $categoria ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categoria); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="fornecedor" class="form-label">Fornecedor</label>
                <select class="form-select" id="fornecedor" name="fornecedor">
                    <option value="">Todos</option>
                    <?php foreach ($fornecedores as $fornecedor): ?>
                        <option value="<?php echo htmlspecialchars($fornecedor); ?>" <?php echo $filtro_fornecedor == $fornecedor ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fornecedor); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Filtrar</button>
                <a href="contas_pagar.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-undo me-2"></i>Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de contas -->
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Descrição</th>
                <th>Fornecedor</th>
                <th>Categoria</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($contas) > 0): ?>
                <?php foreach ($contas as $conta): 
                    // Verificar se está vencida para destacar
                    $vencida = false;
                    if ($conta['status'] == 'pendente') {
                        $data_vencimento = new DateTime($conta['data_vencimento']);
                        $vencida = $data_vencimento < $hoje;
                    }
                    
                    // Definir classes de estilo com base no status
                    $row_class = '';
                    if ($conta['status'] == 'pago') {
                        $row_class = 'table-success';
                    } elseif ($conta['status'] == 'cancelado') {
                        $row_class = 'text-decoration-line-through';
                    } elseif ($vencida) {
                        $row_class = 'table-danger';
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo htmlspecialchars($conta['descricao']); ?></td>
                    <td><?php echo htmlspecialchars($conta['fornecedor'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($conta['categoria'] ?? '-'); ?></td>
                    <td>
                        <?php echo dataParaBr($conta['data_vencimento']); ?>
                        <?php if ($vencida): ?>
                            <span class="badge bg-danger ms-1">
                                <?php 
                                $dias_atraso = $hoje->diff($data_vencimento)->days;
                                echo $dias_atraso . ' ' . ($dias_atraso == 1 ? 'dia' : 'dias');
                                ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo formataValor($conta['valor']); ?></td>
                    <td>
                        <?php if ($conta['status'] == 'pendente'): ?>
                            <span class="badge bg-warning text-dark">Pendente</span>
                        <?php elseif ($conta['status'] == 'pago_parcial'): ?>
                            <span class="badge bg-info text-dark">Pago Parcial</span>
                            <div class="small mt-1">
                                <?php echo formataValor($conta['valor_pago']) . ' de ' . formataValor($conta['valor']); ?>
                                (<?php echo number_format(($conta['valor_pago'] / $conta['valor']) * 100, 0); ?>%)
                            </div>
                        <?php elseif ($conta['status'] == 'pago'): ?>
                            <span class="badge bg-success">Pago</span>
                        <?php elseif ($conta['status'] == 'cancelado'): ?>
                            <span class="badge bg-secondary">Cancelado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="conta_pagar_form.php?id=<?php echo $conta['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($conta['status'] == 'pendente' || $conta['status'] == 'pago_parcial'): ?>
                            <a href="pagar_conta.php?id=<?php echo $conta['id']; ?>" class="btn btn-sm btn-outline-success" title="<?php echo $conta['status'] == 'pago_parcial' ? 'Completar pagamento' : 'Pagar'; ?>">
                                <i class="fas fa-money-bill-wave"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($conta['status'] == 'pago' || $conta['status'] == 'pago_parcial'): ?>
                            <a href="estornar_pagamento.php?id=<?php echo $conta['id']; ?>" class="btn btn-sm btn-outline-warning" title="Estornar pagamento">
                                <i class="fas fa-undo-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($conta['status'] != 'pago' && $conta['status'] != 'pago_parcial'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="confirmarExclusao(<?php echo $conta['id']; ?>, '<?php echo addslashes($conta["descricao"]); ?>', 'contas_pagar.php')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhuma conta encontrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once('includes/footer.php'); ?>