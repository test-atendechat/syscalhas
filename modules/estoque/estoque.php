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
    $where .= " AND (p.descricao LIKE :busca OR p.codigo LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

// Filtro de categoria
$categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 0;
if ($categoria_id > 0) {
    $where .= " AND p.categoria_id = :categoria_id";
    $params[':categoria_id'] = $categoria_id;
}

// Filtro de status de estoque
$status_estoque = isset($_GET['status_estoque']) ? limpaString($_GET['status_estoque']) : '';
if ($status_estoque === 'baixo') {
    $where .= " AND p.estoque_atual <= p.estoque_minimo AND p.estoque_minimo > 0";
} elseif ($status_estoque === 'zerado') {
    $where .= " AND p.estoque_atual = 0";
} elseif ($status_estoque === 'normal') {
    $where .= " AND p.estoque_atual > p.estoque_minimo";
}

// Obter total de registros
$stmt = $db->prepare("SELECT COUNT(*) as total 
                      FROM produtos p
                      WHERE {$where}");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_registros / $por_pagina);

// Obter produtos
$stmt = $db->prepare("SELECT p.*, c.nome as categoria_nome 
                      FROM produtos p
                      LEFT JOIN categorias c ON p.categoria_id = c.id
                      WHERE {$where}
                      ORDER BY p.descricao
                      LIMIT :limit OFFSET :offset");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter categorias para o filtro
$stmt = $db->query("SELECT id, nome FROM categorias ORDER BY nome");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mensagem de alerta
$mensagem = '';
if (isset($_GET['mensagem'])) {
    switch ($_GET['mensagem']) {
        case 'estoque_atualizado':
            $mensagem = alerta('Estoque atualizado com sucesso!', 'success');
            break;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-boxes me-2"></i>Controle de Estoque</h1>
    <div>
        <a href="estoque_entrada.php" class="btn btn-success">
            <i class="fas fa-arrow-alt-circle-down me-2"></i>Entrada
        </a>
        <a href="estoque_saida.php" class="btn btn-danger">
            <i class="fas fa-arrow-alt-circle-up me-2"></i>Saída
        </a>
        <a href="estoque_movimentacoes.php" class="btn btn-info text-white">
            <i class="fas fa-exchange-alt me-2"></i>Movimentações
        </a>
    </div>
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
                <input type="text" class="form-control" id="busca" name="busca" value="<?php echo $busca; ?>" placeholder="Código ou descrição do produto">
            </div>
            
            <div class="col-md-3">
                <label for="categoria_id" class="form-label">Categoria</label>
                <select class="form-select" id="categoria_id" name="categoria_id">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo $categoria['id']; ?>" <?php echo ($categoria_id == $categoria['id']) ? 'selected' : ''; ?>>
                            <?php echo $categoria['nome']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="status_estoque" class="form-label">Status do Estoque</label>
                <select class="form-select" id="status_estoque" name="status_estoque">
                    <option value="">Todos</option>
                    <option value="baixo" <?php echo ($status_estoque == 'baixo') ? 'selected' : ''; ?>>Estoque Baixo</option>
                    <option value="zerado" <?php echo ($status_estoque == 'zerado') ? 'selected' : ''; ?>>Estoque Zerado</option>
                    <option value="normal" <?php echo ($status_estoque == 'normal') ? 'selected' : ''; ?>>Estoque Normal</option>
                </select>
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
                        <th>Código</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Unidade</th>
                        <th class="text-end">Estoque Atual</th>
                        <th class="text-end">Estoque Mínimo</th>
                        <th class="text-end">Valor Unitário</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($produtos) > 0): ?>
                        <?php foreach ($produtos as $produto): ?>
                            <?php 
                            $classe_estoque = '';
                            if ($produto['estoque_atual'] <= 0) {
                                $classe_estoque = 'estoque-critico';
                            } elseif ($produto['estoque_atual'] <= $produto['estoque_minimo']) {
                                $classe_estoque = 'estoque-baixo';
                            }
                            ?>
                            <tr class="<?php echo $classe_estoque; ?>">
                                <td><?php echo $produto['codigo']; ?></td>
                                <td><?php echo $produto['descricao']; ?></td>
                                <td><?php echo $produto['categoria_nome']; ?></td>
                                <td><?php echo $produto['unidade']; ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($produto['estoque_minimo'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo formataValor($produto['valor_unitario']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="estoque_entrada.php?produto_id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-success" title="Entrada">
                                            <i class="fas fa-arrow-alt-circle-down"></i>
                                        </a>
                                        <a href="estoque_saida.php?produto_id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-danger" title="Saída">
                                            <i class="fas fa-arrow-alt-circle-up"></i>
                                        </a>
                                        <a href="estoque_movimentacoes.php?produto_id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-info text-white" title="Movimentações">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum produto encontrado.</td>
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
                        <a class="page-link" href="?pagina=<?php echo $pagina-1; ?>&busca=<?php echo $busca; ?>&categoria_id=<?php echo $categoria_id; ?>&status_estoque=<?php echo $status_estoque; ?>">Anterior</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($pagina == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo $busca; ?>&categoria_id=<?php echo $categoria_id; ?>&status_estoque=<?php echo $status_estoque; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina+1; ?>&busca=<?php echo $busca; ?>&categoria_id=<?php echo $categoria_id; ?>&status_estoque=<?php echo $status_estoque; ?>">Próxima</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        
        <!-- Resumo -->
        <div class="alert alert-info mt-3">
            <div class="row">
                <div class="col-md-4">
                    <strong>Total de Produtos:</strong> <?php echo $total_registros; ?>
                </div>
                <?php
                // Contar produtos com estoque baixo
                $stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE estoque_atual <= estoque_minimo AND estoque_minimo > 0");
                $estoque_baixo = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Contar produtos com estoque zerado
                $stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE estoque_atual = 0");
                $estoque_zerado = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                ?>
                <div class="col-md-4">
                    <strong>Produtos com Estoque Baixo:</strong> <?php echo $estoque_baixo; ?>
                </div>
                <div class="col-md-4">
                    <strong>Produtos com Estoque Zerado:</strong> <?php echo $estoque_zerado; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>