<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Definir dias para buscar os materiais (padrão: 2 dias)
$dias = isset($_GET['dias']) ? intval($_GET['dias']) : 2;

// Limitar entre 1 e 7 dias
$dias = max(1, min(7, $dias));

// Obter data atual e data limite
$data_atual = date('Y-m-d');
$data_limite = date('Y-m-d', strtotime("+{$dias} days"));

// Acessar a variável de conexão com o banco de dados
require_once('includes/db.php');

// Consultar materiais necessários para instalações próximas
try {
    $stmt = $conn->prepare("SELECT p.id, p.descricao, p.unidade, SUM(i.quantidade) as quantidade_necessaria,
                      o.id as orcamento_id, o.numero as orcamento_numero, c.nome as cliente_nome,
                      a.data_agendamento, a.data_inicio, a.hora_inicio,
                      cl.nome as colaborador_nome
                      FROM orcamento_itens i
                      JOIN produtos p ON i.produto_id = p.id
                      JOIN orcamentos o ON i.orcamento_id = o.id
                      JOIN clientes c ON o.cliente_id = c.id
                      JOIN agendamentos a ON o.id = a.orcamento_id
                      JOIN colaboradores cl ON a.instalador_id = cl.id
                      WHERE (a.status = 'instalacao_agendada') 
                      AND ((
                          (a.data_agendamento >= :data_atual AND a.data_agendamento <= :data_limite)
                      ) OR (
                          (a.data_inicio::date >= :data_atual AND a.data_inicio::date <= :data_limite)
                      ))
                      GROUP BY p.id, p.descricao, p.unidade, o.id, o.numero, c.nome, a.data_agendamento, a.data_inicio, a.hora_inicio, cl.nome
                      ORDER BY a.data_agendamento, a.data_inicio, o.numero");
    $stmt->bindParam(':data_atual', $data_atual);
    $stmt->bindParam(':data_limite', $data_limite);
    $stmt->execute();
    $materiais_proximas_instalacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar materiais por orçamento
    $materiais_por_orcamento = [];
    foreach ($materiais_proximas_instalacoes as $material) {
        $orcamento_id = $material['orcamento_id'];
        if (!isset($materiais_por_orcamento[$orcamento_id])) {
            $materiais_por_orcamento[$orcamento_id] = [
                'orcamento_id' => $orcamento_id,
                'orcamento_numero' => $material['orcamento_numero'],
                'cliente_nome' => $material['cliente_nome'],
                'data_agendamento' => $material['data_agendamento'],
                'data_inicio' => $material['data_inicio'],
                'hora_inicio' => $material['hora_inicio'],
                'colaborador_nome' => $material['colaborador_nome'],
                'materiais' => []
            ];
        }
        $materiais_por_orcamento[$orcamento_id]['materiais'][] = [
            'id' => $material['id'],
            'descricao' => $material['descricao'],
            'unidade' => $material['unidade'],
            'quantidade' => $material['quantidade_necessaria']
        ];
    }

    // Obter lista de todos os materiais agrupados para listagem total
    $stmt = $conn->prepare("SELECT p.id, p.descricao, p.unidade, SUM(i.quantidade) as quantidade_total,
                          COUNT(DISTINCT o.id) as total_orcamentos
                          FROM orcamento_itens i
                          JOIN produtos p ON i.produto_id = p.id
                          JOIN orcamentos o ON i.orcamento_id = o.id
                          JOIN agendamentos a ON o.id = a.orcamento_id
                          WHERE (a.status = 'instalacao_agendada') 
                          AND ((
                              (a.data_agendamento >= :data_atual AND a.data_agendamento <= :data_limite)
                          ) OR (
                              (a.data_inicio::date >= :data_atual AND a.data_inicio::date <= :data_limite)
                          ))
                          GROUP BY p.id, p.descricao, p.unidade
                          ORDER BY quantidade_total DESC");
    $stmt->bindParam(':data_atual', $data_atual);
    $stmt->bindParam(':data_limite', $data_limite);
    $stmt->execute();
    $lista_total_materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erro = $e->getMessage();
}

// Incluir cabeçalho
$titulo = 'Lista de Materiais para Instalações';
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-tools me-2"></i>Lista de Materiais para Instalações</h1>
    <div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
        <button onclick="imprimirTudo()" class="btn btn-primary ms-2">
            <i class="fas fa-print me-2"></i>Imprimir Tudo
        </button>
    </div>
</div>

<!-- Filtro de dias -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtro</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="dias" class="form-label">Período de Busca</label>
                <select class="form-select" id="dias" name="dias" onchange="this.form.submit()">
                    <option value="1" <?php echo $dias == 1 ? 'selected' : ''; ?>>Apenas Amanhã</option>
                    <option value="2" <?php echo $dias == 2 ? 'selected' : ''; ?>>Próximos 2 dias</option>
                    <option value="3" <?php echo $dias == 3 ? 'selected' : ''; ?>>Próximos 3 dias</option>
                    <option value="5" <?php echo $dias == 5 ? 'selected' : ''; ?>>Próximos 5 dias</option>
                    <option value="7" <?php echo $dias == 7 ? 'selected' : ''; ?>>Próxima Semana</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Resumo Total de Materiais -->
<div class="card mb-4" id="resumo-materiais">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Resumo Total de Materiais (<?php echo $dias; ?> dias)</h5>
    </div>
    <div class="card-body">
        <?php if (count($lista_total_materiais) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Unidade</th>
                        <th class="text-center">Quantidade Total</th>
                        <th class="text-center">Nº de Orçamentos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_total_materiais as $material): ?>
                    <tr>
                        <td><?php echo $material['descricao']; ?></td>
                        <td><?php echo $material['unidade']; ?></td>
                        <td class="text-center"><?php echo $material['quantidade_total']; ?></td>
                        <td class="text-center"><?php echo $material['total_orcamentos']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Não há instalações agendadas para os próximos <?php echo $dias; ?> dias.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lista de Materiais por Orçamento -->
<div class="card mb-4" id="materiais-por-orcamento">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Materiais por Orçamento</h5>
    </div>
    <div class="card-body">
        <?php if (count($materiais_por_orcamento) > 0): ?>
        <div class="accordion" id="materiaisAccordion">
            <?php foreach ($materiais_por_orcamento as $index => $orcamento_info): ?>
            <div class="accordion-item mb-3 border">
                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                        <div>
                            <strong>#<?php echo $orcamento_info['orcamento_numero']; ?></strong> - 
                            <?php echo $orcamento_info['cliente_nome']; ?> - 
                            <span class="text-primary">
                                <?php 
                                if ($orcamento_info['data_agendamento']) {
                                    echo dataParaBr($orcamento_info['data_agendamento']);
                                    if ($orcamento_info['hora_inicio']) {
                                        echo ' ' . substr($orcamento_info['hora_inicio'], 0, 5);
                                    }
                                } else if ($orcamento_info['data_inicio']) {
                                    echo dataParaBr(date('Y-m-d', strtotime($orcamento_info['data_inicio'])));
                                }
                                ?>
                            </span> - 
                            <span class="text-secondary">Instalador: <?php echo $orcamento_info['colaborador_nome']; ?></span>
                        </div>
                    </button>
                </h2>
                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#materiaisAccordion">
                    <div class="accordion-body">
                        <div class="mb-3">
                            <a href="orcamento_visualizar.php?id=<?php echo $orcamento_info['orcamento_id']; ?>" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fas fa-eye me-1"></i>Ver Orçamento
                            </a>
                            <button onclick="imprimirListaMateriais('lista-materiais-<?php echo $index; ?>', <?php echo $orcamento_info['orcamento_numero']; ?>, '<?php echo $orcamento_info['cliente_nome']; ?>')" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-print me-1"></i>Imprimir Lista
                            </button>
                        </div>
                        <div class="lista-materiais" id="lista-materiais-<?php echo $index; ?>">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th>Unidade</th>
                                        <th class="text-center">Quantidade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orcamento_info['materiais'] as $material): ?>
                                    <tr>
                                        <td><?php echo $material['descricao']; ?></td>
                                        <td><?php echo $material['unidade']; ?></td>
                                        <td class="text-center"><?php echo $material['quantidade']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Não há instalações agendadas para os próximos <?php echo $dias; ?> dias.
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function imprimirListaMateriais(elementId, numeroOrcamento, nomeCliente) {
    const conteudo = document.getElementById(elementId).innerHTML;
    const janela = window.open('', '', 'height=600,width=800');
    
    janela.document.write('<html><head><title>Lista de Materiais - Orçamento #' + numeroOrcamento + '</title>');
    janela.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    janela.document.write('<style>body { padding: 20px; }</style>');
    janela.document.write('</head><body>');
    janela.document.write('<h4 class="mb-3">Lista de Materiais - Orçamento #' + numeroOrcamento + '</h4>');
    janela.document.write('<h5 class="mb-4">Cliente: ' + nomeCliente + '</h5>');
    janela.document.write(conteudo);
    janela.document.write('</body></html>');
    
    janela.document.close();
    janela.focus();
    
    // Esperar o carregamento dos estilos
    setTimeout(() => {
        janela.print();
        janela.close();
    }, 500);
}

function imprimirTudo() {
    const resumoMateriais = document.getElementById('resumo-materiais').innerHTML;
    const materiaisPorOrcamento = document.getElementById('materiais-por-orcamento').innerHTML;
    const janela = window.open('', '', 'height=600,width=800');
    
    janela.document.write('<html><head><title>Lista Completa de Materiais para Instalações</title>');
    janela.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    janela.document.write('<style>body { padding: 20px; } .accordion-button::after { display: none; }</style>');
    janela.document.write('</head><body>');
    janela.document.write('<h3 class="mb-4">Lista Completa de Materiais para Instalações</h3>');
    janela.document.write('<div class="mb-5">' + resumoMateriais + '</div>');
    janela.document.write('<h4 class="mb-3">Detalhamento por Orçamento</h4>');
    
    // Expandir todas as seções para impressão
    let materiaisHTML = materiaisPorOrcamento;
    materiaisHTML = materiaisHTML.replace(/accordion-button collapsed/g, 'accordion-button');
    materiaisHTML = materiaisHTML.replace(/accordion-collapse collapse/g, 'accordion-collapse show');
    
    janela.document.write(materiaisHTML);
    janela.document.write('</body></html>');
    
    janela.document.close();
    janela.focus();
    
    // Esperar o carregamento dos estilos
    setTimeout(() => {
        janela.print();
        janela.close();
    }, 1000);
}
</script>

<?php require_once('includes/footer.php'); ?>