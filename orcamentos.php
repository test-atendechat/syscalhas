<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Definir quantidade de registros por página
$por_pagina = 10;

// Verificar página atual
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$pagina = max(1, $pagina); // Garantir que a página seja pelo menos 1

// Calcular o offset para a consulta SQL
$offset = ($pagina - 1) * $por_pagina;

// Inicializar a cláusula WHERE
$where = "1=1";
$params = [];

// Filtro de busca
$busca = isset($_GET['busca']) ? limpaString($_GET['busca']) : '';
if (!empty($busca)) {
    $where .= " AND (o.numero LIKE :busca OR c.nome LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

// Filtro de status
$status = isset($_GET['status']) ? limpaString($_GET['status']) : '';
if (!empty($status)) {
    $where .= " AND o.status = :status";
    $params[':status'] = $status;
}

// Filtro de status de execução
$status_execucao = isset($_GET['status_execucao']) ? limpaString($_GET['status_execucao']) : '';
if (!empty($status_execucao)) {
    $where .= " AND o.status_execucao = :status_execucao";
    $params[':status_execucao'] = $status_execucao;
}

// Filtro de período
$data_inicio = isset($_GET['data_inicio']) ? limpaString($_GET['data_inicio']) : '';
$data_fim = isset($_GET['data_fim']) ? limpaString($_GET['data_fim']) : '';

if (!empty($data_inicio)) {
    $data_inicio_mysql = dataParaMysql($data_inicio);
    $where .= " AND o.data_criacao::date >= :data_inicio";
    $params[':data_inicio'] = $data_inicio_mysql;
}

if (!empty($data_fim)) {
    $data_fim_mysql = dataParaMysql($data_fim);
    $where .= " AND o.data_criacao::date <= :data_fim";
    $params[':data_fim'] = $data_fim_mysql;
}

// Obter total de registros
$stmt = $db->prepare("SELECT COUNT(*) as total 
                    FROM orcamentos o
                    LEFT JOIN clientes c ON o.cliente_id = c.id
                    WHERE {$where}");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_registros / $por_pagina);

// Obter orçamentos
$stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome 
                     FROM orcamentos o
                     LEFT JOIN clientes c ON o.cliente_id = c.id
                     WHERE {$where}
                     ORDER BY o.data_criacao DESC
                     LIMIT :limit OFFSET :offset");
                     
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mensagem de alerta
$mensagem = '';
if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'excluido':
            $mensagem = alerta('Orçamento excluído com sucesso!', 'success');
            break;
        case 'aprovado':
            $mensagem = alerta('Orçamento aprovado com sucesso!', 'success');
            break;
        case 'rejeitado':
            $mensagem = alerta('Orçamento rejeitado com sucesso!', 'warning');
            break;
    }
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir'])) {
    $id = intval($_POST['id']);
    
    try {
        $db->beginTransaction();
        
        // Primeiro exclui os itens relacionados (se houver)
        $stmt = $db->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Depois exclui o orçamento
        $stmt = $db->prepare("DELETE FROM orcamentos WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $db->commit();
        
        header('Location: orcamentos.php?mensagem=excluido');
        exit;
    } catch (Exception $e) {
        $db->rollback();
        $mensagem = alerta('Erro ao excluir orçamento: ' . $e->getMessage(), 'danger');
    }
}

// Processar mudança de status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['status'])) {
    $id = intval($_POST['id']);
    $novo_status = $_POST['status'];
    
    if ($novo_status == 'aprovado' || $novo_status == 'rejeitado') {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE orcamentos SET status = :status WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $novo_status);
            $stmt->execute();
            
            // Se for aprovado, baixar do estoque
            if ($novo_status == 'aprovado') {
                $itens = buscarItensOrcamento($id);
                
                foreach ($itens as $item) {
                    if ($item['produto_id'] > 0) {
                        // Registrar movimentação no estoque
                        $stmt = $db->prepare("INSERT INTO estoque_movimentacoes 
                                            (produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, usuario_id)
                                            VALUES 
                                            (:produto_id, 'saida', :quantidade, :valor_unitario, :valor_total, :observacao, :orcamento_id, :usuario_id)");
                        $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':quantidade', $item['quantidade']);
                        $stmt->bindParam(':valor_unitario', $item['valor_unitario']);
                        $stmt->bindParam(':valor_total', $item['valor_total']);
                        $observacao = "Saída automática do orçamento #{$item['orcamento_id']}";
                        $stmt->bindParam(':observacao', $observacao);
                        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
                        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                        $stmt->execute();
                        
                        // Atualizar estoque do produto
                        $stmt = $db->prepare("UPDATE produtos 
                                            SET estoque_atual = estoque_atual - :quantidade 
                                            WHERE id = :produto_id");
                        $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':quantidade', $item['quantidade']);
                        $stmt->execute();
                    }
                }
            }
            
            $db->commit();
            
            header('Location: orcamentos.php?mensagem=' . $novo_status);
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $mensagem = alerta('Erro ao atualizar status do orçamento: ' . $e->getMessage(), 'danger');
        }
    }
}

// Incluindo o cabeçalho após todas as operações de header
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-invoice-dollar me-2"></i>Orçamentos</h1>
    <a href="orcamento_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Orçamento
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label for="busca" class="form-label">Busca</label>
                <input type="text" class="form-control" id="busca" name="busca" value="<?php echo $busca; ?>" placeholder="Número ou nome do cliente">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="pendente" <?php echo ($status == 'pendente') ? 'selected' : ''; ?>>Pendentes</option>
                    <option value="aprovado" <?php echo ($status == 'aprovado') ? 'selected' : ''; ?>>Aprovados</option>
                    <option value="rejeitado" <?php echo ($status == 'rejeitado') ? 'selected' : ''; ?>>Rejeitados</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="text" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>" placeholder="dd/mm/aaaa">
            </div>
            
            <div class="col-md-2">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="text" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>" placeholder="dd/mm/aaaa">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Validade</th>
                        <th class="text-end">Valor</th>
                        <th>Status</th>
                        <th>Execução</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orcamentos) > 0): ?>
                        <?php foreach ($orcamentos as $orcamento): ?>
                            <tr>
                                <td><a href="orcamento_visualizar.php?id=<?php echo $orcamento['id']; ?>"><?php echo $orcamento['numero']; ?></a></td>
                                <td><?php echo $orcamento['cliente_nome']; ?></td>
                                <td><?php echo dataParaBr($orcamento['data_criacao']); ?></td>
                                <td><?php echo dataParaBr($orcamento['data_validade']); ?></td>
                                <td class="text-end"><?php echo formataValor($orcamento['valor_total']); ?></td>
                                <td>
                                    <span class="status-box status-<?php echo $orcamento['status']; ?>">
                                        <?php echo ucfirst($orcamento['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($orcamento['status_execucao'] != 'pendente'): ?>
                                    <span class="status-box status-<?php echo $orcamento['status_execucao']; ?>">
                                        <?php echo ucfirst($orcamento['status_execucao']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted small">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="orcamento_visualizar.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-info text-white" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="orcamento_form.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (podeDeletar()): ?>
                                        <button type="button" class="btn btn-sm btn-danger" title="Excluir" onclick="confirmarExclusao(<?php echo $orcamento['id']; ?>, '<?php echo $orcamento['numero']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($orcamento['status'] == 'pendente'): ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <form method="post">
                                                            <input type="hidden" name="id" value="<?php echo $orcamento['id']; ?>">
                                                            <input type="hidden" name="status" value="aprovado">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="fas fa-check-circle me-1"></i>Aprovar
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="post">
                                                            <input type="hidden" name="id" value="<?php echo $orcamento['id']; ?>">
                                                            <input type="hidden" name="status" value="rejeitado">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="fas fa-times-circle me-1"></i>Rejeitar
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum orçamento encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Navegação de página" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($pagina <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina-1; ?>&busca=<?php echo $busca; ?>&status=<?php echo $status; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>">Anterior</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($pagina == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo $busca; ?>&status=<?php echo $status; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina+1; ?>&busca=<?php echo $busca; ?>&status=<?php echo $status; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>">Próxima</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        
        <!-- Resumo -->
        <div class="alert alert-info mt-3">
            <div class="row">
                <div class="col-md-3">
                    <strong>Total de Orçamentos:</strong> <?php echo $total_registros; ?>
                </div>
                <?php
                // Contar orçamentos por status
                $stmt = $db->query("SELECT status, COUNT(*) as total FROM orcamentos GROUP BY status");
                $totais_status = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $totais_status[$row['status']] = $row['total'];
                }
                ?>
                <div class="col-md-3">
                    <strong>Pendentes:</strong> <?php echo isset($totais_status['pendente']) ? $totais_status['pendente'] : 0; ?>
                </div>
                <div class="col-md-3">
                    <strong>Aprovados:</strong> <?php echo isset($totais_status['aprovado']) ? $totais_status['aprovado'] : 0; ?>
                </div>
                <div class="col-md-3">
                    <strong>Rejeitados:</strong> <?php echo isset($totais_status['rejeitado']) ? $totais_status['rejeitado'] : 0; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar máscaras para campos de data
    if (typeof IMask !== 'undefined') {
        const dataInicioMask = IMask(document.getElementById('data_inicio'), {
            mask: '00/00/0000'
        });
        
        const dataFimMask = IMask(document.getElementById('data_fim'), {
            mask: '00/00/0000'
        });
    }
});

// Função para confirmação de exclusão
function confirmarExclusao(id, numero) {
    if (confirm('Tem certeza que deseja excluir o orçamento #' + numero + '?')) {
        let form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        
        let idInput = document.createElement('input');
        idInput.name = 'id';
        idInput.value = id;
        
        let excluirInput = document.createElement('input');
        excluirInput.name = 'excluir';
        excluirInput.value = '1';
        
        form.appendChild(idInput);
        form.appendChild(excluirInput);
        document.body.appendChild(form);
        
        form.submit();
    }
}
</script>

<?php require_once('includes/footer.php'); ?>