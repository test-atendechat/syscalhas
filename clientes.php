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
    $where .= " AND (nome LIKE :busca OR email LIKE :busca OR telefone LIKE :busca OR cpf_cnpj LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

// Obter total de registros
$stmt = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE {$where}");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_registros / $por_pagina);

// Obter clientes
$stmt = $db->prepare("SELECT * FROM clientes WHERE {$where} ORDER BY nome LIMIT :offset, :limit");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se existe uma mensagem na URL
$mensagem = '';
if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'cadastrado':
            $mensagem = alerta('Cliente cadastrado com sucesso!', 'success');
            break;
        case 'atualizado':
            $mensagem = alerta('Cliente atualizado com sucesso!', 'success');
            break;
        case 'excluido':
            $mensagem = alerta('Cliente excluído com sucesso!', 'success');
            break;
        case 'erro':
            $mensagem = alerta('Ocorreu um erro ao processar a solicitação.', 'danger');
            break;
    }
}

// Excluir cliente
if (isset($_POST['excluir']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Verificar se cliente está vinculado a orçamentos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orcamentos WHERE cliente_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $vinculado = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    
    if ($vinculado) {
        $mensagem = alerta('Este cliente não pode ser excluído pois está vinculado a orçamentos.', 'danger');
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Redirecionar para atualizar a lista
            header('Location: clientes.php?mensagem=excluido');
            exit;
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao excluir cliente: ' . $e->getMessage(), 'danger');
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-users me-2"></i>Clientes</h1>
    <a href="cliente_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Cliente
    </a>
</div>

<?php echo $mensagem; ?>

<!-- Filtro de busca -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="clientes.php" class="row g-3">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="busca" name="busca" placeholder="Buscar por nome, email, telefone ou CPF/CNPJ" value="<?php echo $busca; ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Buscar</button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de clientes -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i>Lista de Clientes
    </div>
    <div class="card-body">
        <?php if (count($clientes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>CPF/CNPJ</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['id']; ?></td>
                                <td><?php echo $cliente['nome']; ?></td>
                                <td><?php echo $cliente['tipo'] == 'pf' ? 'Pessoa Física' : 'Pessoa Jurídica'; ?></td>
                                <td><?php echo $cliente['cpf_cnpj']; ?></td>
                                <td><?php echo $cliente['telefone']; ?></td>
                                <td><?php echo $cliente['email']; ?></td>
                                <td>
                                    <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-info" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Excluir" 
                                            onclick="confirmarExclusao(<?php echo $cliente['id']; ?>, '<?php echo addslashes($cliente['nome']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                            <a class="page-link" href="?pagina=<?php echo $pagina-1; ?>&busca=<?php echo $busca; ?>">Anterior</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo ($pagina == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo $busca; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $pagina+1; ?>&busca=<?php echo $busca; ?>">Próxima</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <p class="text-center">Nenhum cliente encontrado.</p>
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
                <p>Deseja realmente excluir o cliente <strong id="nomeCliente"></strong>?</p>
                <p class="text-danger">Esta ação não poderá ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" action="clientes.php">
                    <input type="hidden" name="id" id="idExcluir">
                    <button type="submit" name="excluir" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(id, nome) {
    document.getElementById('idExcluir').value = id;
    document.getElementById('nomeCliente').innerText = nome;
    
    var modal = new bootstrap.Modal(document.getElementById('modalExcluir'));
    modal.show();
}
</script>

<?php
require_once('includes/footer.php');
?>
