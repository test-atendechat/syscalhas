<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Verificar se o usuário tem permissão para acessar esta página
// Administradores sempre têm acesso, outros usuários precisam de permissão específica
if (!isset($_SESSION['usuario']) || 
    ($_SESSION['usuario']['nivel'] !== 'admin' && !verificarPermissao('visualizar_relatorios_financeiros'))) {
    $_SESSION['erro'] = "Você não tem permissão para acessar esta página.";
    header("Location: dashboard.php");
    exit;
}

require_once('includes/header.php');

// Definir filtros padrões
$periodo = isset($_GET['periodo']) ? limpaString($_GET['periodo']) : 'mes';
$data_inicio = isset($_GET['data_inicio']) ? limpaString($_GET['data_inicio']) : date('d/m/Y', strtotime('-30 days'));
$data_fim = isset($_GET['data_fim']) ? limpaString($_GET['data_fim']) : date('d/m/Y');

// Converter para formato MySQL
$data_inicio_mysql = dataParaMysql($data_inicio) . ' 00:00:00';
$data_fim_mysql = dataParaMysql($data_fim) . ' 23:59:59';

// Definir período com base na seleção
if ($periodo == 'hoje') {
    $data_inicio = date('d/m/Y');
    $data_fim = date('d/m/Y');
    $data_inicio_mysql = date('Y-m-d 00:00:00');
    $data_fim_mysql = date('Y-m-d 23:59:59');
} elseif ($periodo == 'ontem') {
    $data_inicio = date('d/m/Y', strtotime('-1 day'));
    $data_fim = date('d/m/Y', strtotime('-1 day'));
    $data_inicio_mysql = date('Y-m-d 00:00:00', strtotime('-1 day'));
    $data_fim_mysql = date('Y-m-d 23:59:59', strtotime('-1 day'));
} elseif ($periodo == 'semana') {
    $data_inicio = date('d/m/Y', strtotime('monday this week'));
    $data_fim = date('d/m/Y', strtotime('sunday this week'));
    $data_inicio_mysql = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $data_fim_mysql = date('Y-m-d 23:59:59', strtotime('sunday this week'));
} elseif ($periodo == 'mes') {
    $data_inicio = date('d/m/Y', strtotime('first day of this month'));
    $data_fim = date('d/m/Y', strtotime('last day of this month'));
    $data_inicio_mysql = date('Y-m-d 00:00:00', strtotime('first day of this month'));
    $data_fim_mysql = date('Y-m-d 23:59:59', strtotime('last day of this month'));
} elseif ($periodo == 'ano') {
    $data_inicio = date('01/01/Y');
    $data_fim = date('31/12/Y');
    $data_inicio_mysql = date('Y-01-01 00:00:00');
    $data_fim_mysql = date('Y-12-31 23:59:59');
}

// Consultar dados financeiros do estoque
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN m.tipo = 'entrada' THEN (m.quantidade * p.custo_unitario) ELSE 0 END) as valor_entradas,
        SUM(CASE WHEN m.tipo = 'saida' THEN m.valor_total ELSE 0 END) as valor_saidas
    FROM estoque_movimentacoes m
    JOIN produtos p ON m.produto_id = p.id
    WHERE m.data_movimentacao BETWEEN :data_inicio AND :data_fim
");
$stmt->bindValue(':data_inicio', $data_inicio_mysql);
$stmt->bindValue(':data_fim', $data_fim_mysql);
$stmt->execute();
$totais_movimentacoes = $stmt->fetch(PDO::FETCH_ASSOC);

// Consultar dados de vendas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_vendas,
        SUM(valor_total) as valor_total_vendas,
        SUM(valor_total) as valor_pago_vendas,
        0 as valor_pendente_vendas
    FROM vendas
    WHERE data_venda BETWEEN :data_inicio AND :data_fim
");
$stmt->bindValue(':data_inicio', $data_inicio_mysql);
$stmt->bindValue(':data_fim', $data_fim_mysql);
$stmt->execute();
$totais_vendas = $stmt->fetch(PDO::FETCH_ASSOC);

// Consultar dados de orçamentos
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_orcamentos,
        COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as orcamentos_aprovados,
        COUNT(CASE WHEN status = 'rejeitado' THEN 1 END) as orcamentos_rejeitados,
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as orcamentos_pendentes,
        SUM(valor_total) as valor_total_orcamentos,
        SUM(valor_produtos) as valor_total_produtos,
        SUM(valor_mao_obra) as valor_total_mao_obra,
        SUM(CASE WHEN status = 'aprovado' THEN valor_total ELSE 0 END) as valor_orcamentos_aprovados,
        SUM(CASE WHEN status = 'aprovado' THEN valor_produtos ELSE 0 END) as valor_produtos_aprovados,
        SUM(CASE WHEN status = 'aprovado' THEN valor_mao_obra ELSE 0 END) as valor_mao_obra_aprovados
    FROM orcamentos
    WHERE data_criacao BETWEEN :data_inicio AND :data_fim
");
$stmt->bindValue(':data_inicio', $data_inicio_mysql);
$stmt->bindValue(':data_fim', $data_fim_mysql);
$stmt->execute();
$totais_orcamentos = $stmt->fetch(PDO::FETCH_ASSOC);

// Consultar valor atual dos produtos em estoque
$stmt = $db->query("
    SELECT 
        SUM(estoque_atual * valor_unitario) as valor_venda_estimado, 
        SUM(estoque_atual * custo_unitario) as valor_custo_estimado 
    FROM produtos 
    WHERE estoque_atual > 0
");
$valores_estimados = $stmt->fetch(PDO::FETCH_ASSOC);
$valor_venda_estimado = $valores_estimados['valor_venda_estimado'] ?? 0;
$valor_custo_estimado = $valores_estimados['valor_custo_estimado'] ?? 0;
$lucro_estimado = $valor_venda_estimado - $valor_custo_estimado;

// Consultar total de contas pagas e a pagar no período
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as contas_pagas,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as contas_pendentes,
        COUNT(CASE WHEN status = 'pago' THEN 1 END) as num_contas_pagas,
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as num_contas_pendentes
    FROM contas_pagar
    WHERE data_vencimento BETWEEN :data_inicio AND :data_fim
");
$stmt->bindValue(':data_inicio', $data_inicio_mysql);
$stmt->bindValue(':data_fim', $data_fim_mysql);
$stmt->execute();
$totais_contas = $stmt->fetch(PDO::FETCH_ASSOC);

// Consultar produtos mais vendidos
$stmt = $db->prepare("
    SELECT 
        p.id, 
        p.codigo, 
        p.descricao, 
        p.unidade, 
        p.valor_unitario,
        p.custo_unitario,
        SUM(i.quantidade) as quantidade_vendida,
        SUM(i.valor_total) as valor_total_vendido
    FROM vendas_itens i
    JOIN produtos p ON i.produto_id = p.id
    JOIN vendas v ON i.venda_id = v.id
    WHERE v.data_venda BETWEEN :data_inicio AND :data_fim
    GROUP BY p.id, p.codigo, p.descricao, p.unidade, p.valor_unitario, p.custo_unitario
    ORDER BY quantidade_vendida DESC
    LIMIT 10
");
$stmt->bindValue(':data_inicio', $data_inicio_mysql);
$stmt->bindValue(':data_fim', $data_fim_mysql);
$stmt->execute();
$produtos_mais_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculos de lucratividade
$valor_entradas = $totais_movimentacoes['valor_entradas'] ?? 0;
$valor_saidas = $totais_movimentacoes['valor_saidas'] ?? 0;
$lucro_operacional = $valor_saidas - $valor_entradas;
$margem_lucro = $valor_saidas > 0 ? ($lucro_operacional / $valor_saidas) * 100 : 0;

// Cálculos de lucratividade dos orçamentos
$valor_total_produtos = $totais_orcamentos['valor_total_produtos'] ?? 0;
$valor_total_mao_obra = $totais_orcamentos['valor_total_mao_obra'] ?? 0;
$valor_produtos_aprovados = $totais_orcamentos['valor_produtos_aprovados'] ?? 0;
$valor_mao_obra_aprovados = $totais_orcamentos['valor_mao_obra_aprovados'] ?? 0;
$valor_orcamentos_aprovados = $totais_orcamentos['valor_orcamentos_aprovados'] ?? 0;

// Obter o custo real dos produtos de orçamentos a partir das entradas de estoque
// Como estamos usando estoque_movimentacoes para calcular entradas, vamos usar o mesmo método
// para ter consistência nos cálculos - não vamos mais usar a estimativa de 50%

// Assumimos que o custo real dos produtos é aproximadamente metade do valor de venda
// Esta é uma estimativa baseada nos dados disponíveis
$custo_medio_produtos_percentual = 0.5; // Estimativa: 50% do valor de venda é o custo
$custo_produtos = $valor_produtos_aprovados * $custo_medio_produtos_percentual;

// Calculamos o lucro bruto em produtos (valor total - custo)
$lucro_produtos = $valor_produtos_aprovados - $custo_produtos;

// Lucro total dos orçamentos (produtos + mão de obra)
// A mão de obra é considerada totalmente como lucro (menos os custos operacionais gerais)
$lucro_total_orcamentos = $valor_orcamentos_aprovados - $custo_produtos;

// Mão de obra é calculada como valor total menos valor dos produtos
$lucro_mao_obra = $valor_mao_obra_aprovados;

// Margem de lucro dos orçamentos
$margem_lucro_orcamentos = $valor_orcamentos_aprovados > 0 ? 
    ($lucro_total_orcamentos / $valor_orcamentos_aprovados) * 100 : 0;

// Calcular o fluxo de caixa do período
$valor_recebido = $totais_vendas['valor_pago_vendas'] ?? 0;
$valor_pago = $totais_contas['contas_pagas'] ?? 0;
$fluxo_caixa = $valor_recebido - $valor_pago;

// Estimativa de vendas futuras com base no estoque atual
$valor_total_estimado = ($totais_movimentacoes['valor_saidas'] ?? 0) + $valor_venda_estimado;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-chart-line me-2"></i>Relatório Financeiro</h1>
    <div>
        <a href="#" class="btn btn-outline-primary" onclick="imprimirRelatorio()">
            <i class="fas fa-print me-2"></i>Imprimir Relatório
        </a>
    </div>
</div>

<!-- Alerta de acesso restrito -->
<div class="alert alert-warning mb-4">
    <i class="fas fa-user-shield me-2"></i>
    <strong>Acesso Restrito!</strong> Esta página é acessível apenas para administradores e usuários com permissão específica.
</div>

<!-- Filtros do relatório -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros do Relatório</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label for="periodo" class="form-label">Período Predefinido</label>
                <select class="form-select" id="periodo" name="periodo" onchange="this.form.submit()">
                    <option value="hoje" <?php echo ($periodo == 'hoje') ? 'selected' : ''; ?>>Hoje</option>
                    <option value="ontem" <?php echo ($periodo == 'ontem') ? 'selected' : ''; ?>>Ontem</option>
                    <option value="semana" <?php echo ($periodo == 'semana') ? 'selected' : ''; ?>>Esta Semana</option>
                    <option value="mes" <?php echo ($periodo == 'mes') ? 'selected' : ''; ?>>Este Mês</option>
                    <option value="ano" <?php echo ($periodo == 'ano') ? 'selected' : ''; ?>>Este Ano</option>
                    <option value="personalizado" <?php echo ($periodo == 'personalizado') ? 'selected' : ''; ?>>Personalizado</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="text" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="text" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-2"></i>Aplicar Filtros
                </button>
                <a href="relatorio_financeiro.php" class="btn btn-outline-secondary">
                    <i class="fas fa-eraser me-2"></i>Limpar Filtros
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Dashboard financeiro -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Valor Total Investido (Custo)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formataValor($valor_entradas); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Valor Total de Vendas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formataValor($valor_saidas); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Lucro Operacional</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formataValor($lucro_operacional); ?></div>
                        <div class="row no-gutters align-items-center">
                            <div class="col">
                                <div class="progress progress-sm mr-2 mt-2">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo max(0, min(100, $margem_lucro)); ?>%"></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="text-xs font-weight-bold text-info text-uppercase">
                                    <?php echo number_format($margem_lucro, 2, ',', '.'); ?>% Margem
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Fluxo de Caixa</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formataValor($fluxo_caixa); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resumo de lucratividade -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-success text-white">
        <h6 class="m-0 font-weight-bold">Resumo de Lucratividade</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">Visão Simplificada de Lucratividade</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Descrição</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-secondary">
                                        <td colspan="2"><strong>Vendas</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Custo dos Produtos</td>
                                        <td class="text-end"><?php echo formataValor($valor_entradas); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Valor Total de Vendas</td>
                                        <td class="text-end"><?php echo formataValor($valor_saidas); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Lucro em Vendas</td>
                                        <td class="text-end"><?php echo formataValor($valor_saidas - $valor_entradas); ?></td>
                                    </tr>
                                    
                                    <tr class="table-light">
                                        <td colspan="2"></td>
                                    </tr>
                                    
                                    <tr class="table-secondary">
                                        <td colspan="2"><strong>Orçamentos</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Custo dos Orçamentos</td>
                                        <td class="text-end"><?php echo formataValor($custo_produtos); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Valor Total dos Orçamentos</td>
                                        <td class="text-end"><?php echo formataValor($valor_orcamentos_aprovados); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Lucro em Orçamentos</td>
                                        <td class="text-end"><?php echo formataValor($valor_orcamentos_aprovados - $custo_produtos); ?></td>
                                    </tr>
                                    
                                    <tr class="table-light">
                                        <td colspan="2"></td>
                                    </tr>
                                    
                                    <tr class="table-success">
                                        <td><strong>Lucro Total (Vendas + Orçamentos)</strong></td>
                                        <td class="text-end"><strong><?php echo formataValor(($valor_saidas - $valor_entradas) + ($valor_orcamentos_aprovados - $custo_produtos)); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">Lucro Consolidado</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Descrição</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Faturamento Bruto (Vendas)</td>
                                        <td class="text-end"><?php echo formataValor($valor_saidas); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Faturamento Bruto (Orçamentos)</td>
                                        <td class="text-end"><?php echo formataValor($valor_orcamentos_aprovados); ?></td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td><strong>Faturamento Total</strong></td>
                                        <td class="text-end"><strong><?php echo formataValor($valor_saidas + $valor_orcamentos_aprovados); ?></strong></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr>
                                        <td>Custo dos Materiais (Vendas)</td>
                                        <td class="text-end"><?php echo formataValor($valor_entradas); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Custo dos Materiais (Orçamentos)</td>
                                        <td class="text-end"><?php echo formataValor($custo_produtos); ?></td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td><strong>Custo Total</strong></td>
                                        <td class="text-end"><strong><?php echo formataValor($valor_entradas + $custo_produtos); ?></strong></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td><strong>Lucro Líquido</strong></td>
                                        <td class="text-end"><strong><?php echo formataValor(($valor_saidas + $valor_orcamentos_aprovados) - ($valor_entradas + $custo_produtos)); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>Margem de Lucro Líquido</td>
                                        <td class="text-end">
                                            <?php 
                                            $faturamento_total = $valor_saidas + $valor_orcamentos_aprovados;
                                            $lucro_liquido = $faturamento_total - ($valor_entradas + $custo_produtos);
                                            $margem_liquida = $faturamento_total > 0 ? ($lucro_liquido / $faturamento_total) * 100 : 0;
                                            echo number_format($margem_liquida, 2, ',', '.') . '%';
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resumo financeiro detalhado -->
<div class="row">
    <!-- Resumo de Vendas e Orçamentos -->
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Resumo de Vendas e Orçamentos</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Descrição</th>
                                <th class="text-end">Quantidade</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Total de Vendas</td>
                                <td class="text-end"><?php echo $totais_vendas['total_vendas'] ?? 0; ?></td>
                                <td class="text-end"><?php echo formataValor($totais_vendas['valor_total_vendas'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Valor Recebido</td>
                                <td class="text-end">-</td>
                                <td class="text-end"><?php echo formataValor($totais_vendas['valor_pago_vendas'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Valor Pendente</td>
                                <td class="text-end">-</td>
                                <td class="text-end"><?php echo formataValor($totais_vendas['valor_pendente_vendas'] ?? 0); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="3"></td>
                            </tr>
                            <tr>
                                <td>Total de Orçamentos</td>
                                <td class="text-end"><?php echo $totais_orcamentos['total_orcamentos'] ?? 0; ?></td>
                                <td class="text-end"><?php echo formataValor($totais_orcamentos['valor_total_orcamentos'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Orçamentos Aprovados</td>
                                <td class="text-end"><?php echo $totais_orcamentos['orcamentos_aprovados'] ?? 0; ?></td>
                                <td class="text-end"><?php echo formataValor($totais_orcamentos['valor_orcamentos_aprovados'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Orçamentos Pendentes</td>
                                <td class="text-end"><?php echo $totais_orcamentos['orcamentos_pendentes'] ?? 0; ?></td>
                                <td class="text-end">-</td>
                            </tr>
                            <tr>
                                <td>Orçamentos Rejeitados</td>
                                <td class="text-end"><?php echo $totais_orcamentos['orcamentos_rejeitados'] ?? 0; ?></td>
                                <td class="text-end">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo de Contas e Estoque -->
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Resumo de Contas e Estoque</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Descrição</th>
                                <th class="text-end">Quantidade</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Contas Pagas</td>
                                <td class="text-end"><?php echo $totais_contas['num_contas_pagas'] ?? 0; ?></td>
                                <td class="text-end"><?php echo formataValor($totais_contas['contas_pagas'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Contas Pendentes</td>
                                <td class="text-end"><?php echo $totais_contas['num_contas_pendentes'] ?? 0; ?></td>
                                <td class="text-end"><?php echo formataValor($totais_contas['contas_pendentes'] ?? 0); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="3"></td>
                            </tr>
                            <tr>
                                <td>Valor de Custo do Estoque Atual</td>
                                <td class="text-end">-</td>
                                <td class="text-end"><?php echo formataValor($valor_custo_estimado); ?></td>
                            </tr>
                            <tr>
                                <td>Valor de Venda do Estoque Atual</td>
                                <td class="text-end">-</td>
                                <td class="text-end"><?php echo formataValor($valor_venda_estimado); ?></td>
                            </tr>
                            <tr>
                                <td>Lucro Potencial no Estoque</td>
                                <td class="text-end">-</td>
                                <td class="text-end"><?php echo formataValor($lucro_estimado); ?></td>
                            </tr>
                            <tr>
                                <td>Valor Total Estimado Final</td>
                                <td class="text-end">-</td>
                                <td class="text-end fw-bold"><?php echo formataValor($valor_total_estimado); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Produtos mais vendidos -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Produtos Mais Vendidos no Período</h6>
    </div>
    <div class="card-body">
        <?php if (count($produtos_mais_vendidos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Unidade</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-end">Valor Unitário</th>
                            <th class="text-end">Valor Total</th>
                            <th class="text-end">Custo Total</th>
                            <th class="text-end">Lucro</th>
                            <th class="text-center">Margem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos_mais_vendidos as $produto): 
                            $custo_total = $produto['quantidade_vendida'] * $produto['custo_unitario'];
                            $lucro = $produto['valor_total_vendido'] - $custo_total;
                            $margem = $produto['valor_total_vendido'] > 0 ? ($lucro / $produto['valor_total_vendido']) * 100 : 0;
                        ?>
                            <tr>
                                <td><?php echo $produto['codigo']; ?></td>
                                <td><?php echo $produto['descricao']; ?></td>
                                <td><?php echo $produto['unidade']; ?></td>
                                <td class="text-center"><?php echo number_format($produto['quantidade_vendida'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo formataValor($produto['valor_unitario']); ?></td>
                                <td class="text-end"><?php echo formataValor($produto['valor_total_vendido']); ?></td>
                                <td class="text-end"><?php echo formataValor($custo_total); ?></td>
                                <td class="text-end"><?php echo formataValor($lucro); ?></td>
                                <td class="text-center"><?php echo number_format($margem, 2, ',', '.'); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nenhum produto vendido no período selecionado.
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
    
    // Se o período selecionado é personalizado, mostrar campos de data
    const periodoSelect = document.getElementById('periodo');
    const dataInicioField = document.getElementById('data_inicio');
    const dataFimField = document.getElementById('data_fim');
    
    periodoSelect.addEventListener('change', function() {
        const isPeriodoPersonalizado = this.value === 'personalizado';
        if (!isPeriodoPersonalizado) {
            this.form.submit();
        }
    });
});

// Função para imprimir o relatório
function imprimirRelatorio() {
    window.print();
}
</script>

<?php require_once('includes/footer.php'); ?>