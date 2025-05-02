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
    $where .= " AND (descricao LIKE :busca OR codigo LIKE :busca OR categoria LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

// Filtro de categoria
$categoria = isset($_GET['categoria']) ? limpaString($_GET['categoria']) : '';
if (!empty($categoria)) {
    $where .= " AND c.nome = :categoria";
    $params[':categoria'] = $categoria;
}

// Obter categorias distintas para o filtro
$categorias = $db->query("SELECT DISTINCT c.nome FROM produtos p 
                         JOIN categorias c ON p.categoria_id = c.id 
                         ORDER BY c.nome")->fetchAll(PDO::FETCH_COLUMN);

// Obter total de registros
$stmt = $db->prepare("SELECT COUNT(*) as total FROM produtos p 
                     JOIN categorias c ON p.categoria_id = c.id 
                     WHERE {$where}");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_registros / $por_pagina);

// Obter produtos
$stmt = $db->prepare("SELECT p.*, c.nome as categoria_nome,
                      (p.valor_unitario - p.custo_unitario) as lucro,
                      CASE WHEN p.custo_unitario > 0 THEN ((p.valor_unitario - p.custo_unitario) / p.custo_unitario * 100) ELSE 0 END as margem_lucro,
                      CASE WHEN p.estoque_atual < p.estoque_minimo THEN true ELSE false END as estoque_baixo
                      FROM produtos p 
                      JOIN categorias c ON p.categoria_id = c.id 
                      WHERE {$where} 
                      ORDER BY p.descricao 
                      LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se existe uma mensagem na URL
$mensagem = '';
if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'cadastrado':
            $mensagem = alerta('Produto cadastrado com sucesso!', 'success');
            break;
        case 'atualizado':
            $mensagem = alerta('Produto atualizado com sucesso!', 'success');
            break;
        case 'excluido':
            $mensagem = alerta('Produto excluído com sucesso!', 'success');
            break;
        case 'erro':
            $mensagem = alerta('Ocorreu um erro ao processar a solicitação.', 'danger');
            break;
    }
}

// Excluir produto
if (isset($_POST['excluir']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Verificar se produto está vinculado a orçamentos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orcamento_itens WHERE produto_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $vinculado = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

    if ($vinculado) {
        $mensagem = alerta('Este produto não pode ser excluído pois está vinculado a orçamentos.', 'danger');
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM produtos WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Redirecionar para atualizar a lista
            header('Location: produtos.php?mensagem=excluido');
            exit;
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao excluir produto: ' . $e->getMessage(), 'danger');
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-boxes me-2"></i>Produtos</h1>
    <a href="produto_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Produto
    </a>
</div>

<?php echo $mensagem; ?>

<!-- Filtro de busca -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="produtos.php" class="row g-3">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="busca" name="busca" placeholder="Buscar por descrição, código" value="<?php echo $busca; ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="categoria" name="categoria">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $categoria == $cat ? 'selected' : ''; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de produtos -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i>Lista de Produtos
    </div>
    <div class="card-body">
        <?php if (count($produtos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Unidade</th>
                            <th>Estoque</th>
                            <th>Custo</th>
                            <th>Valor Venda</th>
                            <th>Lucro</th>
                            <th>Margem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr <?php echo $produto['estoque_baixo'] ? 'class="table-danger"' : ''; ?>>
                                <td><?php echo $produto['codigo']; ?></td>
                                <td>
                                    <?php echo $produto['descricao']; ?>
                                    <?php if ($produto['estoque_baixo']): ?>
                                        <span class="badge bg-danger ms-1" title="Estoque abaixo do mínimo">!</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $produto['categoria_nome']; ?></td>
                                <td><?php echo $produto['unidade']; ?></td>
                                <td>
                                    <?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?>
                                    <?php if ($produto['estoque_minimo'] > 0): ?>
                                        <small class="text-muted d-block">Min: <?php echo number_format($produto['estoque_minimo'], 2, ',', '.'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formataValor($produto['custo_unitario']); ?></td>
                                <td><?php echo formataValor($produto['valor_unitario']); ?></td>
                                <td>
                                    <?php if ($produto['custo_unitario'] > 0): ?>
                                        <?php echo formataValor($produto['lucro']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($produto['custo_unitario'] > 0): ?>
                                        <span class="badge <?php echo $produto['margem_lucro'] < 20 ? 'bg-danger' : ($produto['margem_lucro'] < 40 ? 'bg-warning' : 'bg-success'); ?>">
                                            <?php echo number_format($produto['margem_lucro'], 0); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="produto_form.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-info" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (podeDeletar()): ?>
                                    <button type="button" class="btn btn-sm btn-danger" title="Excluir" 
                                            onclick="confirmarExclusao(<?php echo $produto['id']; ?>, '<?php echo addslashes($produto['descricao']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
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
                            <a class="page-link" href="?pagina=<?php echo $pagina-1; ?>&busca=<?php echo $busca; ?>&categoria=<?php echo $categoria; ?>">Anterior</a>
                        </li>

                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo ($pagina == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo $busca; ?>&categoria=<?php echo $categoria; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $pagina+1; ?>&busca=<?php echo $busca; ?>&categoria=<?php echo $categoria; ?>">Próxima</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-center">Nenhum produto encontrado.</p>
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
                <p>Deseja realmente excluir o produto <strong id="nomeProduto"></strong>?</p>
                <p class="text-danger">Esta ação não poderá ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" action="produtos.php">
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
    document.getElementById('nomeProduto').innerText = nome;

    var modal = new bootstrap.Modal(document.getElementById('modalExcluir'));
    modal.show();
}
</script>

<?php
require_once('includes/footer.php');
?>
