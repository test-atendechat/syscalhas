<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : date('Y-m-d', strtotime('-30 days'));
$data_final = isset($_GET['data_final']) ? $_GET['data_final'] : date('Y-m-d');

// Processar exclusão se solicitado
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    
    // Verificar se a venda existe
    $stmt = $db->prepare("SELECT id FROM vendas WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        try {
            // Iniciar transação para garantir integridade
            $db->beginTransaction();
            
            // Excluir os itens da venda
            $stmt = $db->prepare("DELETE FROM vendas_itens WHERE venda_id = :venda_id");
            $stmt->bindParam(':venda_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Excluir a venda
            $stmt = $db->prepare("DELETE FROM vendas WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Finalizar transação
            $db->commit();
            
            $mensagem = "excluido";
        } catch (Exception $e) {
            // Desfazer alterações em caso de erro
            $db->rollback();
            $mensagem = "erro_excluir";
        }
    } else {
        $mensagem = "nao_encontrado";
    }
    
    header("Location: vendas.php?mensagem={$mensagem}");
    exit;
}

// Obter vendas com base nos filtros
$sql = "SELECT v.*, 
       c.nome as cliente_nome,
       u.nome as usuario_nome,
       COALESCE(v.status_pagamento, 'pendente') as status_pagamento
       FROM vendas v 
       LEFT JOIN clientes c ON v.cliente_id = c.id 
       LEFT JOIN usuarios u ON v.usuario_id = u.id ";

$where = [];
$params = [];

if (!empty($data_inicial)) {
    $where[] = "v.data_venda >= :data_inicial";
    $params[':data_inicial'] = $data_inicial;
}

if (!empty($data_final)) {
    $where[] = "v.data_venda <= :data_final";
    $params[':data_final'] = $data_final;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY v.data_venda DESC, v.id DESC";

$stmt = $db->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-shopping-cart me-2"></i>Vendas Diretas</h1>
    <div>
        <a href="venda_form.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Nova Venda
        </a>
        <a href="caixa.php" class="btn btn-primary ms-2">
            <i class="fas fa-cash-register me-2"></i>Ver Caixa
        </a>
    </div>
</div>

<?php if (isset($_GET['mensagem'])): ?>
    <?php if ($_GET['mensagem'] == 'cadastrado'): ?>
        <div class="alert alert-success">Venda registrada com sucesso!</div>
    <?php elseif ($_GET['mensagem'] == 'atualizado'): ?>
        <div class="alert alert-success">Venda atualizada com sucesso!</div>
    <?php elseif ($_GET['mensagem'] == 'excluido'): ?>
        <div class="alert alert-warning">Venda excluída com sucesso.</div>
    <?php elseif ($_GET['mensagem'] == 'erro_excluir'): ?>
        <div class="alert alert-danger">Erro ao excluir venda. Tente novamente.</div>
    <?php elseif ($_GET['mensagem'] == 'nao_encontrado'): ?>
        <div class="alert alert-danger">Venda não encontrada.</div>
    <?php endif; ?>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-filter me-2"></i>Filtros
    </div>
    <div class="card-body">
        <form method="get" action="vendas.php" class="row g-3">
            <div class="col-md-4">
                <label for="data_inicial" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" id="data_inicial" name="data_inicial" value="<?php echo $data_inicial; ?>">
            </div>
            <div class="col-md-4">
                <label for="data_final" class="form-label">Data Final</label>
                <input type="date" class="form-control" id="data_final" name="data_final" value="<?php echo $data_final; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Listagem das vendas -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i>Vendas Registradas
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Forma de Pagamento</th>
                        <th class="text-end">Valor Total</th>
                        <th>Status</th>
                        <th>Vendedor</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vendas) > 0): ?>
                        <?php foreach ($vendas as $venda): ?>
                            <tr>
                                <td>#<?php echo $venda['numero']; ?></td>
                                <td><?php echo dataParaBr($venda['data_venda']); ?></td>
                                <td>
                                    <?php if (!empty($venda['cliente_nome'])): ?>
                                        <?php echo $venda['cliente_nome']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Cliente avulso</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ucfirst($venda['forma_pagamento']); ?></td>
                                <td class="text-end"><?php echo formataValor($venda['valor_total']); ?></td>
                                <td>
                                    <?php if ($venda['status_pagamento'] == 'pago_total'): ?>
                                        <span class="badge bg-success">Pago Total</span>
                                    <?php elseif ($venda['status_pagamento'] == 'pago_parcial'): ?>
                                        <span class="badge bg-info">Pago Parcial</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $venda['usuario_nome']; ?></td>
                                <td class="text-center">
                                    <a href="venda_visualizar.php?id=<?php echo $venda['id']; ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" onclick="confirmarExclusao(<?php echo $venda['id']; ?>, '<?php echo $venda['numero']; ?>', 'vendas.php'); return false;" class="btn btn-sm btn-danger" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">Nenhuma venda encontrada.</td>
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