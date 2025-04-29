<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

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
    $where .= " AND (nome LIKE :busca OR cpf_cnpj LIKE :busca OR email LIKE :busca OR telefone LIKE :busca)";
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
$stmt = $db->prepare("SELECT * FROM clientes WHERE {$where} ORDER BY nome LIMIT :limit OFFSET :offset");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir'])) {
    $id = intval($_POST['id']);
    
    try {
        // Verificar se o cliente possui orçamentos
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM orcamentos WHERE cliente_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $orcamentos_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($orcamentos_count > 0) {
            // Cliente possui orçamentos, não pode ser excluído
            $mensagem = alerta('O cliente não pode ser excluído pois possui orçamentos vinculados.', 'danger');
        } else {
            // Excluir o cliente
            $stmt = $db->prepare("DELETE FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mensagem = alerta('Cliente excluído com sucesso!', 'success');
            
            // Recarregar a lista
            header('Location: clientes.php');
            exit;
        }
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao excluir cliente: ' . $e->getMessage(), 'danger');
    }
}

// Mensagem de alerta
$mensagem = '';
if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'cadastrado':
            $mensagem = alerta('Cliente cadastrado com sucesso!', 'success');
            break;
        case 'atualizado':
            $mensagem = alerta('Cliente atualizado com sucesso!', 'success');
            break;
    }
}

// Incluir o header apenas após processar o formulário (para evitar headers already sent)
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-users me-2"></i>Clientes</h1>
    <a href="cliente_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Cliente
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Cliente</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-10">
                <input type="text" class="form-control" id="busca" name="busca" value="<?php echo $busca; ?>" placeholder="Buscar por nome, CPF/CNPJ, e-mail ou telefone">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Buscar
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
                        <th>Nome</th>
                        <th>CPF/CNPJ</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['nome']; ?></td>
                                <td><?php echo $cliente['cpf_cnpj']; ?></td>
                                <td><?php echo $cliente['telefone']; ?></td>
                                <td><?php echo $cliente['email']; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (podeDeletar()): ?>
                                        <button type="button" class="btn btn-sm btn-danger" title="Excluir" onclick="confirmarExclusao(<?php echo $cliente['id']; ?>, '<?php echo $cliente['nome']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                        <a href="orcamento_form.php?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-success" title="Novo Orçamento">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum cliente encontrado.</td>
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
        
        <!-- Resumo -->
        <div class="alert alert-info mt-3">
            <p class="mb-0">Total de Clientes: <strong><?php echo $total_registros; ?></strong></p>
        </div>
    </div>
</div>

<script>
// Função para confirmação de exclusão
function confirmarExclusao(id, nome) {
    if (confirm('Tem certeza que deseja excluir o cliente "' + nome + '"?')) {
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