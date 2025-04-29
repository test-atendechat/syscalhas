<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Parâmetros de relatório
$periodo_start = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01'); // Primeiro dia do mês atual
$periodo_end = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t'); // Último dia do mês atual
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_fornecedor = isset($_GET['fornecedor']) ? $_GET['fornecedor'] : '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'todos';
$agrupar_por = isset($_GET['agrupar_por']) ? $_GET['agrupar_por'] : 'vencimento';

// Buscar categorias para o filtro
$stmt = $db->query("SELECT DISTINCT categoria FROM contas_pagar WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar fornecedores para o filtro
$stmt = $db->query("SELECT DISTINCT fornecedor FROM contas_pagar WHERE fornecedor IS NOT NULL AND fornecedor != '' ORDER BY fornecedor");
$fornecedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Condições SQL com base nos filtros
$condicoes = [];
$params = [];

if ($filtro_status != 'todos') {
    $condicoes[] = "status = ?"; 
    $params[] = $filtro_status;
}

// Determinar a coluna de data com base no agrupamento
$data_coluna = $agrupar_por == 'vencimento' ? 'data_vencimento' : 'data_pagamento';

// Adicionar condição de período
if ($agrupar_por == 'pagamento') {
    // Se agrupar por pagamento, filtrar por data de pagamento para contas pagas
    $condicoes[] = "(status != 'pago' OR (status = 'pago' AND data_pagamento BETWEEN ? AND ?))"; 
    $params[] = $periodo_start;
    $params[] = $periodo_end;
} else {
    // Se agrupar por vencimento, filtrar por data de vencimento
    $condicoes[] = "data_vencimento BETWEEN ? AND ?"; 
    $params[] = $periodo_start;
    $params[] = $periodo_end;
}

if (!empty($filtro_categoria)) {
    $condicoes[] = "categoria = ?"; 
    $params[] = $filtro_categoria;
}

if (!empty($filtro_fornecedor)) {
    $condicoes[] = "fornecedor = ?"; 
    $params[] = $filtro_fornecedor;
}

// Montar a string WHERE
$where = !empty($condicoes) ? 'WHERE ' . implode(' AND ', $condicoes) : '';

// Definir a ordenação com base no agrupamento
$order_by = $agrupar_por == 'vencimento' ? 'data_vencimento ASC' : 'data_pagamento ASC, status ASC';

// Buscar dados
$sql = "SELECT * FROM contas_pagar {$where} ORDER BY {$order_by}";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensagem = alerta('Erro ao gerar relatório: ' . $e->getMessage(), 'danger');
    $contas = [];
}

// Agrupar contas por mês se necessário
$contas_agrupadas = [];
$totais = [
    'pendente' => 0,
    'pago' => 0,
    'cancelado' => 0,
    'total' => 0,
    'vencido' => 0
];

$hoje = new DateTime();

foreach ($contas as $conta) {
    // Definir o agrupamento (mês/ano)
    if ($agrupar_por == 'vencimento') {
        $data = new DateTime($conta['data_vencimento']);
        $grupo = $data->format('Y-m');
        $nome_grupo = mesParaPtBr($data->format('m')) . '/' . $data->format('Y');
    } else { // pagamento
        if ($conta['status'] == 'pago' && !empty($conta['data_pagamento'])) {
            $data = new DateTime($conta['data_pagamento']);
            $grupo = $data->format('Y-m');
            $nome_grupo = mesParaPtBr($data->format('m')) . '/' . $data->format('Y');
        } else {
            $grupo = 'pendente';
            $nome_grupo = 'Contas Pendentes';
        }
    }
    
    // Inicializar o grupo se não existir
    if (!isset($contas_agrupadas[$grupo])) {
        $contas_agrupadas[$grupo] = [
            'nome' => $nome_grupo,
            'contas' => [],
            'total' => 0,
            'pendente' => 0,
            'pago' => 0,
            'cancelado' => 0,
            'vencido' => 0
        ];
    }
    
    // Adicionar a conta ao grupo
    $contas_agrupadas[$grupo]['contas'][] = $conta;
    
    // Atualizar totais do grupo e global
    $valor = $conta['status'] == 'pago' ? ($conta['valor_pago'] ?? $conta['valor']) : $conta['valor'];
    
    $contas_agrupadas[$grupo]['total'] += $valor;
    $totais['total'] += $valor;
    
    if ($conta['status'] == 'pendente') {
        $contas_agrupadas[$grupo]['pendente'] += $valor;
        $totais['pendente'] += $valor;
        
        // Verificar se está vencida
        $data_vencimento = new DateTime($conta['data_vencimento']);
        if ($data_vencimento < $hoje) {
            $contas_agrupadas[$grupo]['vencido'] += $valor;
            $totais['vencido'] += $valor;
        }
    } elseif ($conta['status'] == 'pago') {
        $contas_agrupadas[$grupo]['pago'] += $valor;
        $totais['pago'] += $valor;
    } elseif ($conta['status'] == 'cancelado') {
        $contas_agrupadas[$grupo]['cancelado'] += $valor;
        $totais['cancelado'] += $valor;
    }
}

// Ordenar os grupos pela chave (que é a data em formato Y-m)
if ($agrupar_por == 'vencimento') {
    ksort($contas_agrupadas);
} else {
    // Para agrupamento por pagamento, mover 'pendente' para o final
    if (isset($contas_agrupadas['pendente'])) {
        $pendentes = $contas_agrupadas['pendente'];
        unset($contas_agrupadas['pendente']);
        ksort($contas_agrupadas);
        $contas_agrupadas['pendente'] = $pendentes;
    } else {
        ksort($contas_agrupadas);
    }
}

// Incluir o cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-chart-bar me-2"></i>Relatório de Contas a Pagar</h1>
    <div>
        <a href="contas_pagar.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
        <button onclick="window.print()" class="btn btn-outline-primary">
            <i class="fas fa-print me-2"></i>Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4 no-print">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros do Relatório</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $periodo_start; ?>">
            </div>
            <div class="col-md-2">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $periodo_end; ?>">
            </div>
            <div class="col-md-2">
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
            <div class="col-md-2">
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
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="todos" <?php echo $filtro_status == 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="pendente" <?php echo $filtro_status == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="pago" <?php echo $filtro_status == 'pago' ? 'selected' : ''; ?>>Pago</option>
                    <option value="cancelado" <?php echo $filtro_status == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="agrupar_por" class="form-label">Agrupar por</label>
                <select class="form-select" id="agrupar_por" name="agrupar_por">
                    <option value="vencimento" <?php echo $agrupar_por == 'vencimento' ? 'selected' : ''; ?>>Data de Vencimento</option>
                    <option value="pagamento" <?php echo $agrupar_por == 'pagamento' ? 'selected' : ''; ?>>Data de Pagamento</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Gerar Relatório</button>
            </div>
        </form>
    </div>
</div>

<!-- Resumo -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Resumo do Período: <?php echo dataParaBr($periodo_start); ?> a <?php echo dataParaBr($periodo_end); ?></h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col">
                <h5>Total</h5>
                <h3><?php echo formataValor($totais['total']); ?></h3>
            </div>
            <div class="col">
                <h5>Pendente</h5>
                <h3><?php echo formataValor($totais['pendente']); ?></h3>
            </div>
            <div class="col">
                <h5>Vencido</h5>
                <h3 class="text-danger"><?php echo formataValor($totais['vencido']); ?></h3>
            </div>
            <div class="col">
                <h5>Pago</h5>
                <h3 class="text-success"><?php echo formataValor($totais['pago']); ?></h3>
            </div>
            <div class="col">
                <h5>Cancelado</h5>
                <h3 class="text-secondary"><?php echo formataValor($totais['cancelado']); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Contas Agrupadas -->
<?php foreach ($contas_agrupadas as $grupo => $dados): ?>
<div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo $dados['nome']; ?></h5>
        <div>
            <span class="badge bg-primary me-2">Total: <?php echo formataValor($dados['total']); ?></span>
            <?php if ($dados['pendente'] > 0): ?>
            <span class="badge bg-warning text-dark me-2">Pendente: <?php echo formataValor($dados['pendente']); ?></span>
            <?php endif; ?>
            <?php if ($dados['vencido'] > 0): ?>
            <span class="badge bg-danger me-2">Vencido: <?php echo formataValor($dados['vencido']); ?></span>
            <?php endif; ?>
            <?php if ($dados['pago'] > 0): ?>
            <span class="badge bg-success me-2">Pago: <?php echo formataValor($dados['pago']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Fornecedor</th>
                        <th>Categoria</th>
                        <?php if ($agrupar_por == 'vencimento'): ?>
                        <th>Vencimento</th>
                        <?php else: ?>
                        <th>Vencimento</th>
                        <th>Pagamento</th>
                        <?php endif; ?>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados['contas'] as $conta): 
                        // Verificar se está vencida
                        $vencida = false;
                        if ($conta['status'] == 'pendente') {
                            $data_vencimento = new DateTime($conta['data_vencimento']);
                            $vencida = $data_vencimento < $hoje;
                        }
                        
                        // Definir classes de estilo
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
                        <td><?php echo dataParaBr($conta['data_vencimento']); ?></td>
                        <?php if ($agrupar_por == 'pagamento'): ?>
                        <td>
                            <?php if ($conta['status'] == 'pago' && !empty($conta['data_pagamento'])): ?>
                                <?php echo dataParaBr($conta['data_pagamento']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php 
                            $valor = $conta['status'] == 'pago' ? ($conta['valor_pago'] ?? $conta['valor']) : $conta['valor'];
                            echo formataValor($valor); 
                            ?>
                        </td>
                        <td>
                            <?php if ($conta['status'] == 'pendente'): ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
                                <?php if ($vencida): ?>
                                <span class="badge bg-danger ms-1">
                                    <?php 
                                    $dias_atraso = $hoje->diff($data_vencimento)->days;
                                    echo $dias_atraso . ' ' . ($dias_atraso == 1 ? 'dia' : 'dias');
                                    ?>
                                </span>
                                <?php endif; ?>
                            <?php elseif ($conta['status'] == 'pago'): ?>
                                <span class="badge bg-success">Pago</span>
                            <?php elseif ($conta['status'] == 'cancelado'): ?>
                                <span class="badge bg-secondary">Cancelado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once('includes/footer.php'); ?>