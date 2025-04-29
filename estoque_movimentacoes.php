<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Definir quantidade de registros por página
$por_pagina = 15;

// Verificar página atual
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$pagina = max(1, $pagina); // Garantir que a página seja pelo menos 1

// Calcular o offset para a consulta SQL
$offset = ($pagina - 1) * $por_pagina;

// Inicializar a cláusula WHERE
$where = "1=1";
$params = [];

// Filtro de produto
$produto_id = isset($_GET['produto_id']) ? intval($_GET['produto_id']) : 0;
if ($produto_id > 0) {
    $where .= " AND m.produto_id = :produto_id";
    $params[':produto_id'] = $produto_id;
}

// Filtro de tipo
$tipo = isset($_GET['tipo']) ? limpaString($_GET['tipo']) : '';
if (!empty($tipo)) {
    $where .= " AND m.tipo = :tipo";
    $params[':tipo'] = $tipo;
}

// Filtro de orçamento
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
if ($orcamento_id > 0) {
    $where .= " AND m.orcamento_id = :orcamento_id";
    $params[':orcamento_id'] = $orcamento_id;
}

// Filtro de período
$data_inicio = isset($_GET['data_inicio']) ? limpaString($_GET['data_inicio']) : '';
$data_fim = isset($_GET['data_fim']) ? limpaString($_GET['data_fim']) : '';

if (!empty($data_inicio)) {
    $data_inicio_mysql = dataParaMysql($data_inicio) . ' 00:00:00';
    $where .= " AND m.data_movimentacao >= :data_inicio";
    $params[':data_inicio'] = $data_inicio_mysql;
}

if (!empty($data_fim)) {
    $data_fim_mysql = dataParaMysql($data_fim) . ' 23:59:59';
    $where .= " AND m.data_movimentacao <= :data_fim";
    $params[':data_fim'] = $data_fim_mysql;
}

// Obter total de registros
$stmt = $db->prepare("SELECT COUNT(*) as total 
                     FROM estoque_movimentacoes m
                     WHERE {$where}");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_registros / $por_pagina);

// Obter movimentações
$stmt = $db->prepare("SELECT m.*, 
                     p.codigo as produto_codigo, 
                     p.descricao as produto_descricao, 
                     p.unidade as produto_unidade,
                     u.nome as usuario_nome,
                     o.numero as orcamento_numero
                     FROM estoque_movimentacoes m
                     LEFT JOIN produtos p ON m.produto_id = p.id
                     LEFT JOIN usuarios u ON m.usuario_id = u.id
                     LEFT JOIN orcamentos o ON m.orcamento_id = o.id
                     WHERE {$where}
                     ORDER BY m.data_movimentacao DESC
                     LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de produtos para o filtro
$stmt = $db->query("SELECT id, codigo, descricao FROM produtos ORDER BY descricao");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-exchange-alt me-2"></i>Movimentações de Estoque</h1>
    <div>
        <a href="estoque.php" class="btn btn-outline-secondary">
            <i class="fas fa-boxes me-2"></i>Estoque
        </a>
        <a href="estoque_entrada.php" class="btn btn-success">
            <i class="fas fa-arrow-alt-circle-down me-2"></i>Nova Entrada
        </a>
        <a href="estoque_saida.php" class="btn btn-danger">
            <i class="fas fa-arrow-alt-circle-up me-2"></i>Nova Saída
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="produto_id" class="form-label">Produto</label>
                <select class="form-select" id="produto_id" name="produto_id">
                    <option value="">Todos os produtos</option>
                    <?php foreach ($produtos as $produto): ?>
                        <option value="<?php echo $produto['id']; ?>" <?php echo ($produto_id == $produto['id']) ? 'selected' : ''; ?>>
                            <?php echo "{$produto['codigo']} - {$produto['descricao']}"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <option value="entrada" <?php echo ($tipo == 'entrada') ? 'selected' : ''; ?>>Entradas</option>
                    <option value="saida" <?php echo ($tipo == 'saida') ? 'selected' : ''; ?>>Saídas</option>
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
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
                <a href="estoque_movimentacoes.php" class="btn btn-outline-secondary">
                    <i class="fas fa-eraser me-2"></i>Limpar
                </a>
                <?php if ($produto_id > 0 || !empty($tipo) || !empty($data_inicio) || !empty($data_fim)): ?>
                    <a href="#" class="btn btn-outline-primary ms-2" onclick="imprimirRelatorio()">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (count($movimentacoes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Produto</th>
                            <th>Tipo</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-end">Valor Unitário</th>
                            <th class="text-end">Valor Total</th>
                            <th>Orçamento</th>
                            <th>Usuário</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimentacoes as $movimentacao): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($movimentacao['data_movimentacao'])); ?></td>
                                <td><?php echo "{$movimentacao['produto_codigo']} - {$movimentacao['produto_descricao']}"; ?></td>
                                <td>
                                    <?php if ($movimentacao['tipo'] == 'entrada'): ?>
                                        <span class="badge bg-success">Entrada</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Saída</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo number_format($movimentacao['quantidade'], 2, ',', '.') . ' ' . $movimentacao['produto_unidade']; ?></td>
                                <td class="text-end"><?php echo formataValor($movimentacao['valor_unitario']); ?></td>
                                <td class="text-end"><?php echo formataValor($movimentacao['valor_total']); ?></td>
                                <td>
                                    <?php if ($movimentacao['orcamento_id']): ?>
                                        <a href="orcamento_visualizar.php?id=<?php echo $movimentacao['orcamento_id']; ?>">
                                            <?php echo $movimentacao['orcamento_numero']; ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $movimentacao['usuario_nome']; ?></td>
                                <td><?php echo $movimentacao['observacao']; ?></td>
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
                            <a class="page-link" href="?pagina=<?php echo $pagina-1; ?>&produto_id=<?php echo $produto_id; ?>&tipo=<?php echo $tipo; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>">Anterior</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo ($pagina == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>&produto_id=<?php echo $produto_id; ?>&tipo=<?php echo $tipo; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($pagina >= $total_paginas) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $pagina+1; ?>&produto_id=<?php echo $produto_id; ?>&tipo=<?php echo $tipo; ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>">Próxima</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            
            <!-- Resumo do relatório -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Resumo</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="border-bottom pb-2">Resumo Geral</h5>
                        </div>
                        <?php
                        // Calcular totais gerais
                        $stmt = $db->prepare("
                            SELECT 
                                SUM(CASE WHEN m.tipo = 'entrada' THEN m.quantidade ELSE 0 END) as total_entradas,
                                SUM(CASE WHEN m.tipo = 'saida' THEN m.quantidade ELSE 0 END) as total_saidas,
                                SUM(CASE WHEN m.tipo = 'entrada' THEN m.valor_total ELSE 0 END) as valor_entradas,
                                SUM(CASE WHEN m.tipo = 'saida' THEN m.valor_total ELSE 0 END) as valor_saidas
                            FROM estoque_movimentacoes m
                            WHERE {$where}
                        ");
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $totais = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Calcular totais por unidade de medida
                        $stmt = $db->prepare("
                            SELECT 
                                p.unidade,
                                SUM(CASE WHEN m.tipo = 'entrada' THEN m.quantidade ELSE 0 END) as total_entradas,
                                SUM(CASE WHEN m.tipo = 'saida' THEN m.quantidade ELSE 0 END) as total_saidas,
                                SUM(CASE WHEN m.tipo = 'entrada' THEN m.valor_total ELSE 0 END) as valor_entradas,
                                SUM(CASE WHEN m.tipo = 'saida' THEN m.valor_total ELSE 0 END) as valor_saidas
                            FROM estoque_movimentacoes m
                            JOIN produtos p ON m.produto_id = p.id
                            WHERE {$where}
                            GROUP BY p.unidade
                            ORDER BY p.unidade
                        ");
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $totais_por_unidade = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Calcular o lucro total (valor de vendas - valor de investimento)
                        $lucro_total = ($totais['valor_saidas'] ?? 0) - ($totais['valor_entradas'] ?? 0);
                        $classe_lucro = $lucro_total >= 0 ? 'text-success' : 'text-danger';
                        ?>
                        <div class="col-md-6 text-center">
                            <h2 class="text-success"><?php echo formataValor($totais['valor_entradas'] ?? 0); ?></h2>
                            <p>Valor Total Investido (custo)</p>
                            <small class="text-muted">Baseado no preço de custo dos produtos</small>
                        </div>
                        <div class="col-md-6 text-center">
                            <h2 class="text-danger"><?php echo formataValor($totais['valor_saidas'] ?? 0); ?></h2>
                            <p>Valor Total de Vendas</p>
                            <small class="text-muted">Baseado no preço de venda dos produtos</small>
                        </div>
                        
                        <!-- Mostrar diferença entre vendas e investimento -->
                        <div class="col-12 mt-3 text-center">
                            <h4 class="<?php echo $lucro_total >= 0 ? 'text-success' : 'text-primary'; ?>">
                                <?php echo formataValor(abs($lucro_total)); ?>
                                <?php echo $lucro_total >= 0 ? 'de Lucro' : 'de Valor Investido no Estoque'; ?>
                            </h4>
                            <small class="text-muted">Diferença entre valor de vendas e valor investido</small>
                        </div>
                    </div>
                    
                    <!-- Resumo por Unidade de Medida -->
                    <div class="row">
                        <div class="col-12">
                            <h5 class="border-bottom pb-2">Resumo por Unidade de Medida</h5>
                        </div>
                        <?php if (count($totais_por_unidade) > 0): ?>
                            <div class="col-12 mt-3">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Unidade</th>
                                                <th class="text-center">Entradas (qtd)</th>
                                                <th class="text-center">Saídas (qtd)</th>
                                                <th class="text-center">Investimento (R$)</th>
                                                <th class="text-center">Vendas (R$)</th>
                                                <th class="text-center">Lucro/Estoque (R$)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($totais_por_unidade as $unidade): 
                                                $lucro_unidade = ($unidade['valor_saidas'] ?? 0) - ($unidade['valor_entradas'] ?? 0);
                                                $classe_lucro_unidade = $lucro_unidade >= 0 ? 'text-success' : 'text-danger';
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($unidade['unidade']); ?></strong></td>
                                                    <td class="text-center text-success"><?php echo number_format($unidade['total_entradas'] ?? 0, 2, ',', '.'); ?></td>
                                                    <td class="text-center text-danger"><?php echo number_format($unidade['total_saidas'] ?? 0, 2, ',', '.'); ?></td>
                                                    <td class="text-center text-success"><?php echo formataValor($unidade['valor_entradas'] ?? 0); ?></td>
                                                    <td class="text-center text-danger"><?php echo formataValor($unidade['valor_saidas'] ?? 0); ?></td>
                                                    <td class="text-center <?php echo $lucro_unidade >= 0 ? 'text-success' : 'text-primary'; ?>">
                                                        <?php echo formataValor(abs($lucro_unidade)); ?>
                                                        <?php echo $lucro_unidade >= 0 ? '(Lucro)' : '(Estoque)'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-12 mt-3">
                                <div class="alert alert-info">Nenhum dado disponível por unidade de medida.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nenhuma movimentação de estoque encontrada para os filtros aplicados.
            </div>
        <?php endif; ?>
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

// Função para imprimir o relatório
function imprimirRelatorio() {
    window.print();
}
</script>

<?php require_once('includes/footer.php'); ?>