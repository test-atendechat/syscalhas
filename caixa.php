<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : date('Y-m-d');
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : date('Y-m-d');
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Processar exclusão se solicitado
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $stmt = $db->prepare("DELETE FROM caixa WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    header("Location: caixa.php?mensagem=excluido");
    exit;
}

// Obter movimentações de caixa com base nos filtros
$sql = "SELECT c.*, u.nome as usuario_nome, o.numero as orcamento_numero, cl.nome as cliente_nome, cl.id as cliente_id 
        FROM caixa c 
        LEFT JOIN usuarios u ON c.usuario_id = u.id 
        LEFT JOIN orcamentos o ON c.orcamento_id = o.id 
        LEFT JOIN clientes cl ON c.cliente_id = cl.id ";

$where = [];
$params = [];

if (!empty($data_inicial)) {
    $where[] = "c.data_operacao >= :data_inicial";
    $params[':data_inicial'] = $data_inicial;
}

if (!empty($data_final)) {
    $where[] = "c.data_operacao <= :data_final";
    $params[':data_final'] = $data_final;
}

if (!empty($filtro_tipo)) {
    $where[] = "c.tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY c.data_operacao DESC, c.id DESC";

$stmt = $db->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais
$total_entradas = 0;
$total_saidas = 0;

foreach ($movimentacoes as $mov) {
    if ($mov['tipo'] == 'entrada') {
        $total_entradas += $mov['valor'];
    } else {
        $total_saidas += $mov['valor'];
    }
}

$saldo = $total_entradas - $total_saidas;

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-cash-register me-2"></i>Controle de Caixa</h1>
    <div>
        <a href="caixa_form.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Nova Movimentação
        </a>
        <a href="vendas.php" class="btn btn-primary ms-2">
            <i class="fas fa-shopping-cart me-2"></i>Registrar Venda
        </a>
    </div>
</div>

<?php if (isset($_GET['mensagem'])): ?>
    <?php if ($_GET['mensagem'] == 'cadastrado'): ?>
        <div class="alert alert-success">Movimentação registrada com sucesso!</div>
    <?php elseif ($_GET['mensagem'] == 'excluido'): ?>
        <div class="alert alert-warning">Movimentação excluída com sucesso.</div>
    <?php endif; ?>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-filter me-2"></i>Filtros
    </div>
    <div class="card-body">
        <form method="get" action="caixa.php" class="row g-3">
            <div class="col-md-3">
                <label for="data_inicial" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" id="data_inicial" name="data_inicial" value="<?php echo $data_inicial; ?>">
            </div>
            <div class="col-md-3">
                <label for="data_final" class="form-label">Data Final</label>
                <input type="date" class="form-control" id="data_final" name="data_final" value="<?php echo $data_final; ?>">
            </div>
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <option value="entrada" <?php echo $filtro_tipo == 'entrada' ? 'selected' : ''; ?>>Entradas</option>
                    <option value="saida" <?php echo $filtro_tipo == 'saida' ? 'selected' : ''; ?>>Saídas</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumo financeiro -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-arrow-circle-up me-2"></i>Entradas</h5>
                <h3 class="mb-0"><?php echo formataValor($total_entradas); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-danger h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-arrow-circle-down me-2"></i>Saídas</h5>
                <h3 class="mb-0"><?php echo formataValor($total_saidas); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white <?php echo $saldo >= 0 ? 'bg-primary' : 'bg-danger'; ?> h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-balance-scale me-2"></i>Saldo</h5>
                <h3 class="mb-0"><?php echo formataValor($saldo); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Listagem das movimentações -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i>Movimentações do Caixa
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Forma de Pagamento</th>
                        <th class="text-end">Valor</th>
                        <th>Referência</th>
                        <th>Cliente</th>
                        <th>Usuário</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($movimentacoes) > 0): ?>
                        <?php foreach ($movimentacoes as $mov): ?>
                            <tr>
                                <td><?php echo dataParaBr($mov['data_operacao']); ?></td>
                                <td><?php echo $mov['descricao']; ?></td>
                                <td>
                                    <?php if ($mov['tipo'] == 'entrada'): ?>
                                        <span class="badge bg-success">Entrada</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Saída</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ucfirst($mov['forma_pagamento']); ?></td>
                                <td class="text-end"><?php echo formataValor($mov['valor']); ?></td>
                                <td>
                                    <?php if ($mov['orcamento_id']): ?>
                                        <a href="orcamento_visualizar.php?id=<?php echo $mov['orcamento_id']; ?>" class="text-decoration-none">
                                            <i class="fas fa-file-invoice-dollar me-1"></i>Orçamento #<?php echo $mov['orcamento_numero']; ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mov['cliente_id']): ?>
                                        <a href="cliente_form.php?id=<?php echo $mov['cliente_id']; ?>" class="text-decoration-none">
                                            <i class="fas fa-user me-1"></i><?php echo $mov['cliente_nome']; ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $mov['usuario_nome']; ?></td>
                                <td class="text-center">
                                    <a href="caixa_form.php?id=<?php echo $mov['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" onclick="confirmarExclusao(<?php echo $mov['id']; ?>, '<?php echo addslashes($mov['descricao']); ?>', 'caixa.php'); return false;" class="btn btn-sm btn-danger" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">Nenhuma movimentação encontrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once('includes/footer.php');
?>