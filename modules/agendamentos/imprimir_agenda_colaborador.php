<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Garantir que as variáveis globais estão disponíveis
global $db, $pdo;

// Verificar autenticação
verificarAutenticacao();

// Verificar se o ID do colaborador foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID do colaborador inválido</div>";
    exit;
}

$colaborador_id = intval($_GET['id']);

// Obter a data atual ou a data fornecida
$data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');

// Buscar informações do colaborador
$stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = :id");
$stmt->bindParam(':id', $colaborador_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    echo "<div class='alert alert-danger'>Colaborador não encontrado</div>";
    exit;
}

$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

// Determinar qual coluna usar de acordo com o tipo de colaborador
$tipo_colaborador = $colaborador['tipo'];
$where_condition = "";

if ($tipo_colaborador === 'instalador' || $tipo_colaborador === 'orcamentista') {
    // Todos os tipos de colaboradores usam a mesma coluna agora: instalador_id
    $where_condition = "a.instalador_id = :colaborador_id";
} else {
    // Se não for um tipo válido, mostra uma lista vazia
    $where_condition = "1=0";
}

// Buscar todos os agendamentos do dia para o colaborador
$stmt = $pdo->prepare("SELECT a.*, 
                          o.numero as orcamento_numero, 
                          c.nome as cliente_nome, 
                          c.telefone as cliente_telefone, 
                          c.endereco as cliente_endereco,
                          c.cidade as cliente_cidade,
                          c.estado as cliente_estado,
                          c.cep as cliente_cep
                     FROM agendamentos a
                     JOIN orcamentos o ON a.orcamento_id = o.id
                     JOIN clientes c ON o.cliente_id = c.id
                     WHERE {$where_condition}
                       AND a.data_agendamento = :data
                       AND (a.status = 'instalacao_agendada' OR a.status = 'orcamento_agendado')
                     ORDER BY a.hora_inicio ASC");

$stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
$stmt->bindParam(':data', $data);
$stmt->execute();

$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatar a data para exibição
$data_formatada = date('d/m/Y', strtotime($data));

// Início do HTML para a impressão
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de <?php echo $tipo_colaborador === 'instalador' ? 'Instalações' : 'Orçamentos'; ?> - <?php echo $colaborador['nome']; ?> - <?php echo $data_formatada; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="css/print.css" rel="stylesheet" media="print">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .page-break {
            page-break-after: always;
        }
        .agenda-header {
            margin-bottom: 20px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
        }
        .agenda-header h2 {
            color: #0d6efd;
            margin-bottom: 5px;
        }
        .agendamento-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .agendamento-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #ddd;
            padding: 10px 15px;
            font-weight: bold;
        }
        .agendamento-body {
            padding: 15px;
        }
        .agendamento-info {
            margin-bottom: 15px;
        }
        .cliente-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .servico-info {
            border-top: 1px dashed #ddd;
            padding-top: 15px;
        }
        .no-agendamentos {
            text-align: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 20px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12pt;
                margin: 0;
                padding: 0 !important;
            }
            .agendamento-card {
                border: 1px solid #000;
                page-break-after: always;
            }
            .agendamento-header {
                background-color: #eee !important;
            }
            .cliente-info {
                background-color: #eee !important;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1><i class="fas fa-calendar-day me-2"></i>Agenda de <?php echo $tipo_colaborador === 'instalador' ? 'Instalações' : 'Orçamentos'; ?></h1>
            <div>
                <a href="colaboradores.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>
            </div>
        </div>
        
        <form class="card mb-4 no-print" method="get" action="">
            <div class="card-body">
                <div class="row align-items-end">
                    <input type="hidden" name="id" value="<?php echo $colaborador_id; ?>">
                    
                    <div class="col-md-4">
                        <label for="data" class="form-label">Data dos Agendamentos</label>
                        <input type="date" class="form-control" id="data" name="data" value="<?php echo $data; ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        
                        <a href="imprimir_agenda_colaborador.php?id=<?php echo $colaborador_id; ?>" class="btn btn-outline-secondary ms-2" title="Exibir agenda de hoje">
                            <i class="fas fa-calendar-day me-2"></i>Hoje
                        </a>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="agenda-header">
            <h2>Agenda de <?php echo $tipo_colaborador === 'instalador' ? 'Instalações' : 'Orçamentos'; ?></h2>
            <p class="lead">Profissional: <?php echo $colaborador['nome']; ?> | Data: <?php echo $data_formatada; ?></p>
        </div>
        
        <?php if (count($agendamentos) > 0): ?>
            <?php foreach ($agendamentos as $agendamento): ?>
                <div class="agendamento-card">
                    <div class="agendamento-header">
                        <div class="row">
                            <div class="col-md-6">
                                <i class="fas fa-clock me-2"></i>Horário: <?php echo substr($agendamento['hora_inicio'], 0, 5); ?> até <?php echo substr($agendamento['hora_fim'], 0, 5); ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Orçamento: #<?php echo $agendamento['orcamento_numero']; ?>
                            </div>
                        </div>
                    </div>
                    <div class="agendamento-body">
                        <div class="cliente-info">
                            <h5><i class="fas fa-user me-2"></i>Dados do Cliente</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nome:</strong> <?php echo $agendamento['cliente_nome']; ?></p>
                                    <p><strong>Telefone:</strong> <?php echo $agendamento['cliente_telefone']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Endereço:</strong> <?php echo $agendamento['cliente_endereco']; ?></p>
                                    <p><strong>Localização:</strong> 
                                       <?php if (!empty($agendamento['cliente_cidade'])): ?>
                                       <?php echo $agendamento['cliente_cidade']; ?>/<?php echo $agendamento['cliente_estado']; ?>
                                       <?php endif; ?>
                                       <?php if (!empty($agendamento['cliente_cep'])): ?>
                                       - CEP: <?php echo $agendamento['cliente_cep']; ?>
                                       <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="servico-info">
                            <h5 class="mb-3">
                                <?php if ($agendamento['status'] === 'instalacao_agendada'): ?>
                                <i class="fas fa-tools me-2"></i>AGENDA DE INSTALAÇÃO
                                <?php else: ?>
                                <i class="fas fa-search me-2"></i>AGENDA DE ORÇAMENTO
                                <?php endif; ?>
                            </h5>
                            <div class="mb-3">
                                <p><strong>Status:</strong> 
                                <?php 
                                    if ($agendamento['status'] === 'orcamento_agendado') {
                                        echo "<span class='badge bg-info'>Orçamento Agendado</span>";
                                    } else if ($agendamento['status'] === 'instalacao_agendada') {
                                        echo "<span class='badge bg-primary'>Instalação Agendada</span>";
                                    // status 'agendado' foi substituído por 'instalacao_agendada'
                                    }
                                ?>
                                </p>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Data:</strong> <?php echo $data_formatada; ?></p>
                                    <p><strong>Horário:</strong> <?php echo substr($agendamento['hora_inicio'], 0, 5); ?> até <?php echo substr($agendamento['hora_fim'], 0, 5); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Profissional:</strong> <?php echo $colaborador['nome']; ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($agendamento['observacoes'])): ?>
                            <div class="mt-3">
                                <p><strong>Observações:</strong></p>
                                <div class="p-2 border rounded">
                                    <?php echo nl2br($agendamento['observacoes']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-agendamentos">
                <h3 class="text-muted"><i class="fas fa-calendar-times me-2"></i>Sem agendamentos para esta data</h3>
                <p>Não há <?php echo $tipo_colaborador === 'instalador' ? 'instalações' : 'orçamentos'; ?> agendados para <?php echo $colaborador['nome']; ?> na data de <?php echo $data_formatada; ?>.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Imprimir automaticamente ao carregar se vier da lista de colaboradores
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se há um parâmetro 'imprimir' na URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('imprimir') === 'true') {
            window.print();
        }
    });
    </script>
</body>
</html>