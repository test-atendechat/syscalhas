<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

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

// Filtro de período
$data_inicio = isset($_GET['data_inicio']) ? limpaString($_GET['data_inicio']) : '';
$data_fim = isset($_GET['data_fim']) ? limpaString($_GET['data_fim']) : '';

if (!empty($data_inicio)) {
    $data_inicio_mysql = dataParaMysql($data_inicio);
    $where .= " AND o.data_criacao >= :data_inicio";
    $params[':data_inicio'] = $data_inicio_mysql;
}

if (!empty($data_fim)) {
    $data_fim_mysql = dataParaMysql($data_fim);
    $where .= " AND o.data_criacao <= :data_fim";
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
                      LIMIT :offset, :limit");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se existe uma mensagem na URL
$mensagem = '';
if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'cadastrado':
            $mensagem = alerta('Orçamento cadastrado com sucesso!', 'success');
            break;
        case 'atualizado':
            $mensagem = alerta('Orçamento atualizado com sucesso!', 'success');
            break;
        case 'excluido':
            $mensagem = alerta('Orçamento excluído com sucesso!', 'success');
            break;
        case 'status_atualizado':
            $mensagem = alerta('Status do orçamento atualizado com sucesso!', 'success');
            break;
        case 'erro':
            $mensagem = alerta('Ocorreu um erro ao processar a solicitação.', 'danger');
            break;
    }
}

// Atualizar status do orçamento
if (isset($_POST['atualizar_status']) && isset($_POST['id']) && isset($_POST['status'])) {
    $id = intval($_POST['id']);
    $novo_status = limpaString($_POST['status']);
    
    try {
        $stmt = $db->prepare("UPDATE orcamentos SET status = :status WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $novo_status);
        $stmt->execute();
        
        // Redirecionar para atualizar a lista
        header('Location: orcamentos.php?mensagem=status_atualizado');
        exit;
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao atualizar status: ' . $e->getMessage(), 'danger');
    }
}

// Excluir orçamento
if (isset($_POST['excluir']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    try {
        // Iniciar transação
        $db->beginTransaction();
        
        // Excluir itens do orçamento
        $stmt = $db->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Excluir orçamento
        $stmt = $db->prepare("DELETE FROM orcamentos WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Confirmar transação
        $db->commit();
        
        // Redirecionar para atualizar a lista
        header('Location: orcamentos.php?mensagem=excluido');
        exit;
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $db->rollback();
        $mensagem = alerta('Erro ao excluir orçamento: ' . $e->getMessage(), 'danger');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-invoice-dollar me-2"></i>Orçamentos</h1>
    <a href="orcamento_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Orçamento
    </a>
</div>

<?php echo $mensagem; ?>

<!-- Filtro de busca -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="orcamentos.php" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="busca" name="busca" placeholder="Buscar por número ou cliente" value="<?php echo $busca; ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="status" name="status">
                    <option value="">Todos os status</option>
                    <option value="pendente" <?php echo $status == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="aprovado" <?php echo $status == 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="rejeitado" <?php echo $status == 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control datepicker" id="data_inicio" name="data_inicio" placeholder="Data inicial" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control datepicker" id="data_fim" name="data_fim" placeholder="Data final" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de orçamentos -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i>Lista de Orçamentos
    </div>
    <div class="card-body">
        <?php if (count($orcamentos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orcamentos as $orcamento): ?>
                            <tr>
                                <td><?php echo $orcamento['numero']; ?></td>
                                <td><?php echo $orcamento['cliente_nome']; ?></td>
                                <td><?php echo dataParaBr($orcamento['data_criacao']); ?></td>
                                <td><?php echo formataValor($orcamento['valor_total']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $orcamento['status'] == 'aprovado' ? 'bg-success' : 
                                            ($orcamento['status'] == 'rejeitado' ? 'bg-danger' : 'bg-warning'); 
                                    ?>">
                                        <?php echo ucfirst($orcamento['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="orcamento_form.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-info" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="orcamento_pdf.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" title="Gerar PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="mudarStatus(<?php echo $orcamento['id']; ?>, '<?php echo $orcamento['status']; ?>')" title="Mudar Status">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmarExclusao(<?php echo $orcamento['id']; ?>, '<?php echo $orcamento['numero']; ?>')" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
            
        <?php else: ?>
            <p class="text-center">Nenhum orçamento encontrado.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalExcluirLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Deseja realmente excluir o orçamento <strong id="numeroOrcamento"></strong>?</p>
                <p class="text-danger">Esta ação não poderá ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" action="orcamentos.php">
                    <input type="hidden" name="id" id="idExcluir">
                    <button type="submit" name="excluir" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de alteração de status -->
<div class="modal fade" id="modalStatus" tabindex="-1" aria-labelledby="modalStatusLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalStatusLabel">Alterar Status do Orçamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="orcamentos.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="idStatus">
                    
                    <div class="mb-3">
                        <label for="status_atual" class="form-label">Status Atual</label>
                        <input type="text" class="form-control" id="status_atual" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="novo_status" class="form-label">Novo Status</label>
                        <select class="form-select" id="novo_status" name="status" required>
                            <option value="pendente">Pendente</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="rejeitado">Rejeitado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="atualizar_status" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(id, numero) {
    document.getElementById('idExcluir').value = id;
    document.getElementById('numeroOrcamento').innerText = numero;
    
    var modal = new bootstrap.Modal(document.getElementById('modalExcluir'));
    modal.show();
}

function mudarStatus(id, status) {
    document.getElementById('idStatus').value = id;
    document.getElementById('status_atual').value = status.charAt(0).toUpperCase() + status.slice(1);
    document.getElementById('novo_status').value = status;
    
    var modal = new bootstrap.Modal(document.getElementById('modalStatus'));
    modal.show();
}

// Formato de datas
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.datepicker');
    
    inputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            
            if (v.length > 8) {
                v = v.substring(0, 8);
            }
            
            if (v.length > 4) {
                v = v.replace(/(\d{2})(\d{2})(\d{0,4})/, '$1/$2/$3');
            } else if (v.length > 2) {
                v = v.replace(/(\d{2})(\d{0,2})/, '$1/$2');
            }
            
            e.target.value = v;
        });
    });
});
</script>

<?php
require_once('includes/footer.php');
?>
