<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Inicialização de variáveis
$acesso_interno = true;
$orcamento = null;
$itens = [];
$cliente = null;
$mensagem = '';
$pagamentos = [];
$agendamentos = [];

// Verificar se existem mensagens vindas via GET
if (isset($_GET['mensagem'])) {
    if ($_GET['mensagem'] == 'pago') {
        $mensagem = alerta('Pagamento total registrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'pagamento_parcial') {
        $mensagem = alerta('Pagamento parcial registrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'cadastrado') {
        $mensagem = alerta('Orçamento cadastrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'agendado') {
        $mensagem = alerta('Instalação agendada com sucesso! Data: ' . (isset($_GET['data']) ? $_GET['data'] : '') . 
                       ' às ' . (isset($_GET['hora']) ? $_GET['hora'] : ''), 'success');
    }
}

// Processar ações como marcar como finalizado ou reabrir orçamento
if (isset($_GET['id']) && isset($_GET['acao'])) {
    // Verificar autenticação primeiro
    require_once('includes/auth.php');
    verificarAutenticacao();
    
    $id = intval($_GET['id']);
    $acao = $_GET['acao'];
    
    if ($acao == 'finalizar') {
        // Atualizar status de execução para finalizado
        $stmt = $db->prepare("UPDATE orcamentos SET 
                            status_execucao = 'finalizado', 
                            data_finalizacao = CURRENT_DATE 
                            WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento marcado como FINALIZADO com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao atualizar status do orçamento.', 'danger');
        }
    } elseif ($acao == 'reabrir') {
        // Reabrir orçamento finalizado
        $stmt = $db->prepare("UPDATE orcamentos SET 
                            status_execucao = 'em_andamento', 
                            data_finalizacao = NULL 
                            WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento reaberto com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao reabrir orçamento.', 'danger');
        }
    }
}

// Processar decisão do cliente (aprovar ou rejeitar orçamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decisao'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $decisao = $_POST['decisao'];
    
    if ($decisao === 'aprovar') {
        $stmt = $db->prepare("UPDATE orcamentos SET status = 'aprovado', data_aprovacao = CURRENT_DATE WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento aprovado com sucesso! Você pode agendar a instalação agora.', 'success');
        } else {
            $mensagem = alerta('Erro ao aprovar orçamento.', 'danger');
        }
    } elseif ($decisao === 'rejeitar') {
        $stmt = $db->prepare("UPDATE orcamentos SET status = 'rejeitado', data_rejeicao = CURRENT_DATE WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento rejeitado. Caso haja interesse em um novo orçamento, entre em contato conosco.', 'warning');
        } else {
            $mensagem = alerta('Erro ao rejeitar orçamento.', 'danger');
        }
    }
}

// Verificar se é acesso interno (com ID) ou externo (com código de acesso)
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // No acesso interno, verificar autenticação
    require_once('includes/auth.php');
    verificarAutenticacao();
    
} elseif (isset($_GET['codigo'])) {
    // Acesso externo via código de acesso
    $codigo = trim($_GET['codigo']);
    $acesso_interno = false;
    
    // Buscar orçamento pelo código de acesso
    $stmt = $db->prepare("SELECT id FROM orcamentos WHERE codigo_acesso = :codigo");
    $stmt->bindParam(':codigo', $codigo);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $resultado['id'];
    } else {
        // Código de acesso inválido
        echo "<div class='alert alert-danger'>Código de acesso inválido ou expirado.</div>";
        exit;
    }
} else {
    // Nem ID nem código fornecido
    header('Location: orcamentos.php');
    exit;
}

// Buscar dados do orçamento
$stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome, c.cpf_cnpj, c.email, c.telefone, c.endereco, c.cidade, c.estado, c.cep
                     FROM orcamentos o
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE o.id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar itens do orçamento
    $stmt = $db->prepare("SELECT oi.*, p.nome as produto_nome, p.codigo as produto_codigo
                         FROM orcamento_itens oi
                         INNER JOIN produtos p ON oi.produto_id = p.id
                         WHERE oi.orcamento_id = :orcamento_id
                         ORDER BY oi.id ASC");
    $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar informações do cliente
    $cliente = [
        'nome' => $orcamento['cliente_nome'],
        'cpf_cnpj' => formatarCpfCnpj($orcamento['cpf_cnpj']),
        'email' => $orcamento['email'],
        'telefone' => formatarTelefone($orcamento['telefone']),
        'endereco' => $orcamento['endereco'],
        'cidade' => $orcamento['cidade'],
        'estado' => $orcamento['estado'],
        'cep' => formatarCep($orcamento['cep'])
    ];
    
    // Verificar se há pagamentos registrados
    $stmt = $db->prepare("SELECT * FROM pagamentos WHERE orcamento_id = :orcamento_id ORDER BY data_pagamento DESC");
    $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar se há agendamentos para este orçamento
    $stmt = $db->prepare("SELECT a.*, 
                         GROUP_CONCAT(DISTINCT i.nome SEPARATOR ', ') as instaladores
                         FROM agendamentos a
                         LEFT JOIN agendamento_instaladores ai ON a.id = ai.agendamento_id
                         LEFT JOIN instaladores i ON ai.instalador_id = i.id
                         WHERE a.orcamento_id = :orcamento_id 
                         AND a.status NOT IN ('cancelado', 'reagendado')
                         GROUP BY a.id
                         ORDER BY a.data_agendamento DESC, a.hora_inicio ASC");
    $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
    
    try {
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Em caso de erro, apenas não exibe os agendamentos
    }
} else {
    echo "<div class='alert alert-danger'>Orçamento não encontrado.</div>";
    exit;
}

// Verificar se é acesso externo e renderizar template simplificado
if (!$acesso_interno) {
    // Template simplificado para cliente
    // Não inclui o header/footer padrão do sistema
    ?><!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Orçamento #<?php echo str_pad($orcamento['numero'], 6, '0', STR_PAD_LEFT); ?> - <?php echo APP_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="css/styles.css">
        
        <style>
            body {
                background-color: #f8f9fa;
            }
            .header-logo {
                max-height: 60px;
            }
            .header-orcamento {
                background: linear-gradient(135deg, #4a6fdc 0%, #6b8ae8 100%);
                color: white;
                border-radius: 8px 8px 0 0;
                padding: 20px;
            }
            .status-badge-aprovado {
                background-color: #28a745;
                color: white;
            }
            .status-badge-rejeitado {
                background-color: #dc3545;
                color: white;
            }
            .status-badge-pendente {
                background-color: #ffc107;
                color: #212529;
            }
            .footer-orcamento {
                background-color: #f1f1f1;
                border-radius: 0 0 8px 8px;
                padding: 15px;
                font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="container my-5">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card shadow-sm mb-4">
                        <div class="header-orcamento d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 mb-0">Orçamento #<?php echo str_pad($orcamento['numero'], 6, '0', STR_PAD_LEFT); ?></h1>
                                <p class="mb-0">Emitido em <?php echo date('d/m/Y', strtotime($orcamento['data_criacao'])); ?></p>
                            </div>
                            <img src="<?php echo LOGO_URL; ?>" alt="<?php echo APP_NAME; ?>" class="header-logo">
                        </div>
                        
                        <div class="card-body p-4">
<?php } else {
    // Template normal para acesso interno/administrativo
    require_once('includes/header.php');
    // Exibir mensagens se houver
    echo $mensagem;
    
    // Título da página
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Orçamento #<?php echo str_pad($orcamento['numero'], 6, '0', STR_PAD_LEFT); ?></h1>
        <div>
            <a href="orcamentos.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <a href="javascript:imprimirOrcamento()" class="btn btn-info me-2">
                <i class="fas fa-print me-2"></i>Imprimir
            </a>
            <a href="javascript:copiarLinkCliente()" class="btn btn-primary">
                <i class="fas fa-link me-2"></i>Link para Cliente
            </a>
        </div>
    </div>
<?php } ?>

<!-- Detalhes do Orçamento - Comum para acesso interno e externo -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Detalhes do Orçamento</h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Informações do Cliente</h6>
                <p class="mb-1"><strong>Nome:</strong> <?php echo $cliente['nome']; ?></p>
                <p class="mb-1"><strong>CPF/CNPJ:</strong> <?php echo $cliente['cpf_cnpj']; ?></p>
                <p class="mb-1"><strong>Telefone:</strong> <?php echo $cliente['telefone']; ?></p>
                <p class="mb-1"><strong>E-mail:</strong> <?php echo $cliente['email']; ?></p>
                <p class="mb-0">
                    <strong>Endereço:</strong> <?php echo $cliente['endereco']; ?>, 
                    <?php echo $cliente['cidade']; ?>/<?php echo $cliente['estado']; ?> - 
                    CEP: <?php echo $cliente['cep']; ?>
                </p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Informações do Orçamento</h6>
                <p class="mb-1"><strong>Número:</strong> #<?php echo str_pad($orcamento['numero'], 6, '0', STR_PAD_LEFT); ?></p>
                <p class="mb-1"><strong>Data de Emissão:</strong> <?php echo date('d/m/Y', strtotime($orcamento['data_criacao'])); ?></p>
                <p class="mb-1">
                    <strong>Status:</strong> 
                    <span class="badge status-badge-<?php echo $orcamento['status']; ?>">
                        <?php 
                        if ($orcamento['status'] == 'aprovado') echo 'Aprovado';
                        elseif ($orcamento['status'] == 'rejeitado') echo 'Rejeitado';
                        else echo 'Pendente';
                        ?>
                    </span>
                </p>
                <?php if ($orcamento['status'] == 'aprovado'): ?>
                    <p class="mb-1"><strong>Data de Aprovação:</strong> <?php echo date('d/m/Y', strtotime($orcamento['data_aprovacao'])); ?></p>
                    <p class="mb-1">
                        <strong>Status de Execução:</strong> 
                        <span class="badge bg-<?php echo $orcamento['status_execucao'] == 'finalizado' ? 'success' : 'primary'; ?>">
                            <?php echo $orcamento['status_execucao'] == 'finalizado' ? 'Finalizado' : 'Em Andamento'; ?>
                        </span>
                    </p>
                    <?php if ($orcamento['status_execucao'] == 'finalizado'): ?>
                        <p class="mb-0"><strong>Data de Finalização:</strong> <?php echo date('d/m/Y', strtotime($orcamento['data_finalizacao'])); ?></p>
                    <?php endif; ?>
                <?php elseif ($orcamento['status'] == 'rejeitado'): ?>
                    <p class="mb-0"><strong>Data de Rejeição:</strong> <?php echo date('d/m/Y', strtotime($orcamento['data_rejeicao'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <h6 class="text-muted mb-3">Produtos e Serviços</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Produto/Serviço</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-end">Valor Unit.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                    <tr>
                        <td><?php echo $item['produto_codigo']; ?></td>
                        <td><?php echo $item['produto_nome']; ?></td>
                        <td class="text-center"><?php echo $item['quantidade']; ?></td>
                        <td class="text-end"><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                        <td class="text-end"><?php echo formatarMoeda($item['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-group-divider">
                    <?php if ($orcamento['valor_desconto'] > 0): ?>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end"><?php echo formatarMoeda($orcamento['valor_total'] + $orcamento['valor_desconto']); ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Desconto:</strong></td>
                        <td class="text-end text-danger">- <?php echo formatarMoeda($orcamento['valor_desconto']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong><?php echo formatarMoeda($orcamento['valor_total']); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <?php if (!empty($orcamento['observacoes'])): ?>
        <div class="mt-4">
            <h6 class="text-muted">Observações</h6>
            <div class="p-3 bg-light rounded">
                <?php echo nl2br(htmlspecialchars($orcamento['observacoes'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($acesso_interno && $orcamento['status'] == 'aprovado'): ?>
            <div class="row mt-4">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Informações de Pagamento</h6>
                    <p>
                        <strong>Valor Pago:</strong> 
                        <?php 
                        $valor_pago = 0;
                        foreach ($pagamentos as $pagamento) {
                            $valor_pago += $pagamento['valor'];
                        }
                        echo formatarMoeda($valor_pago);
                        ?>
                    </p>
                    <p>
                        <strong>Valor Restante:</strong> 
                        <?php echo formatarMoeda(max(0, $orcamento['valor_total'] - $valor_pago)); ?>
                    </p>
                    <p>
                        <strong>Status Pagamento:</strong>
                        <?php if ($valor_pago >= $orcamento['valor_total']): ?>
                            <span class="badge bg-success">Pago</span>
                        <?php elseif ($valor_pago > 0): ?>
                            <span class="badge bg-warning text-dark">Pagamento Parcial</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Não Pago</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($orcamento['status_execucao'] != 'finalizado'): ?>
                        <div class="mt-3">
                            <a href="registrar_pagamento.php?orcamento_id=<?php echo $orcamento['id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-cash-register me-1"></i>Registrar Pagamento
                            </a>
                            
                            <?php if ($valor_pago > 0): ?>
                                <a href="estornar_pagamento.php?orcamento_id=<?php echo $orcamento['id']; ?>" class="btn btn-outline-danger btn-sm ms-1">
                                    <i class="fas fa-undo me-1"></i>Estornar Pagamento
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Ações do Orçamento</h6>
                    <?php if ($orcamento['status_execucao'] != 'finalizado'): ?>
                        <a href="?id=<?php echo $orcamento['id']; ?>&acao=finalizar" class="btn btn-primary btn-sm" onclick="return confirm('Confirma a finalização deste orçamento?')">
                            <i class="fas fa-check-circle me-1"></i>Marcar como Finalizado
                        </a>
                    <?php else: ?>
                        <a href="?id=<?php echo $orcamento['id']; ?>&acao=reabrir" class="btn btn-warning btn-sm" onclick="return confirm('Confirma a reabertura deste orçamento?')">
                            <i class="fas fa-redo me-1"></i>Reabrir Orçamento
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$acesso_interno && $orcamento['status'] == 'pendente'): ?>
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Avaliação do Orçamento</h5>
        </div>
        <div class="card-body">
            <p>Prezado(a) <?php echo $cliente['nome']; ?>, avalie o orçamento acima e informe sua decisão:</p>

            <form method="post" class="mt-3">
                <input type="hidden" name="id" value="<?php echo $orcamento['id']; ?>">

                <div class="d-flex justify-content-center">
                    <button type="submit" name="decisao" value="aprovar" class="btn btn-success btn-lg mx-2">
                        <i class="fas fa-check-circle me-2"></i>Aprovar Orçamento
                    </button>

                    <button type="submit" name="decisao" value="rejeitar" class="btn btn-danger btn-lg mx-2">
                        <i class="fas fa-times-circle me-2"></i>Rejeitar Orçamento
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php elseif (!$acesso_interno && $orcamento['status'] != 'pendente'): ?>
    <!-- Status do Orçamento -->
    <div class="alert alert-<?php echo ($orcamento['status'] == 'aprovado') ? 'success' : 'danger'; ?> mt-4">
        <h5 class="alert-heading">
            <?php if ($orcamento['status'] == 'aprovado'): ?>
                <?php if ($orcamento['status_execucao'] == 'finalizado'): ?>
                    <i class="fas fa-check-double me-2"></i>Orçamento Finalizado!
                <?php else: ?>
                    <i class="fas fa-check-circle me-2"></i>Orçamento Aprovado!
                <?php endif; ?>
            <?php else: ?>
                <i class="fas fa-times-circle me-2"></i>Orçamento Rejeitado!
            <?php endif; ?>
        </h5>
        <p class="mb-0">
            <?php if ($orcamento['status'] == 'aprovado'): ?>
                <?php if ($orcamento['status_execucao'] == 'finalizado'): ?>
                    Nossa equipe já realizou o serviço. Agradecemos pela confiança em nosso trabalho. Caso precise de algum esclarecimento adicional ou tenha qualquer questão, estamos à disposição.
                <?php else: ?>
                    Seu orçamento foi aprovado com sucesso! Verifique abaixo as informações sobre o tempo previsto e agendamento.
                <?php endif; ?>
            <?php else: ?>
                Você rejeitou este orçamento. Caso queira discutir alterações ou fazer uma nova cotação, entre em contato conosco.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($orcamento['status'] == 'aprovado' && $orcamento['status_execucao'] != 'finalizado'): ?>
        <?php
        // Formatar tempo previsto para exibição (em horas ou dias)
        $tempo_texto = "2 horas"; // Valor padrão
        if (isset($orcamento['tempo_previsto_horas'])) {
            $horas = intval($orcamento['tempo_previsto_horas']);
            if ($horas <= 24) {
                $tempo_texto = "{$horas} hora" . ($horas > 1 ? 's' : '');
            } else {
                $dias = ceil($horas / 24);
                $tempo_texto = "até {$dias} dia" . ($dias > 1 ? 's' : '');
            }
        }
        $tempo_previsto = isset($orcamento['tempo_previsto_horas']) ? intval($orcamento['tempo_previsto_horas']) : 2;
        ?>
        
        <!-- Informação do Tempo Previsto -->
        <div class="alert alert-info mt-4 mb-4">
            <h5 class="alert-heading"><i class="fas fa-clock me-2"></i>Tempo Previsto</h5>
            <p class="mb-0">O tempo previsto para conclusão após o início do serviço é de <strong><?php echo $tempo_texto; ?></strong>.</p>
        </div>
        
        <?php if (!empty($agendamentos)): ?>
            <!-- Informação de Agendamento -->
            <div class="alert alert-primary mt-4 mb-4">
                <h5 class="alert-heading"><i class="fas fa-calendar-check me-2"></i>Serviço Agendado</h5>
                <p class="mb-0">Seu serviço está agendado para <strong><?php echo date('d/m/Y', strtotime($agendamentos[0]['data_agendamento'])); ?></strong> às <strong><?php echo substr($agendamentos[0]['hora_inicio'], 0, 5); ?></strong>.</p>
                <div class="mt-2 small">
                    <i class="fas fa-info-circle me-1"></i> Em caso de chuva na data agendada, o serviço poderá ser reagendado para o próximo dia útil disponível. Nossa equipe entrará em contato para confirmar.
                </div>
                <?php 
                // Verificar se ainda é possível reagendar (24h antes da execução)
                $now = new DateTime();
                $agenda = new DateTime($agendamentos[0]['data_agendamento'] . ' ' . $agendamentos[0]['hora_inicio']);
                $intervalo = $now->diff($agenda);
                $horas_ate_execucao = ($intervalo->days * 24) + $intervalo->h;
                
                if ($horas_ate_execucao >= 24): 
                ?>
                <div class="d-grid gap-2 mt-3">
                    <a href="agendamento_form.php?orcamento_id=<?php echo $orcamento['id']; ?>&id=<?php echo $agendamentos[0]['id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Reagendar
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card border mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Agendamento do Serviço</h5>
                </div>
                <div class="card-body">
                    <form action="salvar_agendamento.php" method="post" id="form-agendamento">
                        <input type="hidden" name="orcamento_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="codigo_acesso" value="<?php echo isset($codigo) ? $codigo : $orcamento['codigo_acesso']; ?>">
                        <input type="hidden" name="redirect_url" value="orcamento_visualizar.php?<?php echo $acesso_interno ? 'id='.$id : 'codigo='.$codigo; ?>&agendado=true">
                        
                        <!-- Script para verificar disponibilidade de instaladores -->
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const dataInput = document.getElementById('data_agendamento');
                            const horaSelect = document.getElementById('hora_inicio');
                            const instaladorSelect = document.getElementById('instalador_id');
                            const btnConfirmar = document.querySelector('button[type="submit"]');
                            
                            // Função para verificar disponibilidade
                            function verificarDisponibilidade() {
                                const data = dataInput.value;
                                const hora = horaSelect.value;
                                
                                if (data && hora) {
                                    // Atualizar mensagem de carregamento
                                    instaladorSelect.innerHTML = '<option value="">Verificando disponibilidade...</option>';
                                    document.getElementById('div-instaladores').style.display = 'block';
                                    document.getElementById('msg-sem-instaladores').style.display = 'none';
                                    
                                    // Fazer chamada AJAX para verificar instaladores disponíveis
                                    fetch(`ajax/verificar_instaladores_disponiveis.php?data=${data}&hora=${hora}&tempo_previsto=<?php echo $tempo_previsto; ?>`)
                                        .then(response => response.json())
                                        .then(data => {
                                            instaladorSelect.innerHTML = '';
                                            
                                            if (data.disponiveis && data.disponiveis.length > 0) {
                                                // Adicionar opção padrão
                                                instaladorSelect.innerHTML = '<option value="">Selecione um instalador...</option>';
                                                
                                                // Adicionar instaladores disponíveis
                                                data.disponiveis.forEach(instalador => {
                                                    const option = document.createElement('option');
                                                    option.value = instalador.id;
                                                    option.textContent = instalador.nome;
                                                    instaladorSelect.appendChild(option);
                                                });
                                                
                                                // Ativar botão e campo
                                                instaladorSelect.disabled = false;
                                                document.getElementById('div-instaladores').style.display = 'block';
                                                btnConfirmar.disabled = false;
                                            } else {
                                                // Se não há instaladores disponíveis
                                                instaladorSelect.innerHTML = '<option value="">Nenhum instalador disponível</option>';
                                                instaladorSelect.disabled = true;
                                                document.getElementById('div-instaladores').style.display = 'block';
                                                document.getElementById('msg-sem-instaladores').style.display = 'block';
                                                btnConfirmar.disabled = true;
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Erro ao verificar disponibilidade:', error);
                                            instaladorSelect.innerHTML = '<option value="">Erro ao verificar disponibilidade</option>';
                                            btnConfirmar.disabled = true;
                                        });
                                }
                            }
                            
                            // Listener para alterações nos campos de data e hora
                            dataInput.addEventListener('change', verificarDisponibilidade);
                            horaSelect.addEventListener('change', verificarDisponibilidade);
                        });
                        </script>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="data_agendamento" class="form-label">Data da Instalação</label>
                                <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" required 
                                       min="<?php echo date('Y-m-d'); ?>">
                                <div class="form-text">Selecione uma data a partir de hoje, preferencialmente em dia útil.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="hora_inicio" class="form-label">Horário de Início</label>
                                <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                                    <option value="">Selecione o horário</option>
                                    <?php 
                                    // Horários disponíveis (das 7h às 16h - 17h é fim do expediente)
                                    $horarios = array('07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00');
                                    
                                    foreach ($horarios as $hora) {
                                        $partes = explode(':', $hora);
                                        $hora_inicio = intval($partes[0]);
                                        $hora_fim = $hora_inicio + $tempo_previsto;
                                        
                                        // Se o serviço termina depois das 17h, este horário não deve estar disponível
                                        if ($hora_fim <= 17) {
                                            echo "<option value=\"{$hora}\">{$hora}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <div class="form-text">Horário de chegada dos instaladores ao local.</div>
                            </div>
                        </div>
                        
                        <!-- Seleção de Instalador -->
                        <div class="mb-3" id="div-instaladores" style="display: none;">
                            <label for="instalador_id" class="form-label">Instalador</label>
                            <select class="form-select" id="instalador_id" name="instalador_id[]" required>
                                <option value="">Selecione primeiro a data e horário</option>
                            </select>
                            <div class="form-text">Selecione o instalador disponível para realizar o serviço.</div>
                            
                            <div class="alert alert-warning mt-2" id="msg-sem-instaladores" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-2"></i>Não há instaladores disponíveis para a data e horário selecionados. Por favor, escolha outra data ou horário.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tempo Previsto de Execução</label>
                            <input type="text" class="form-control" value="<?php echo $tempo_texto; ?>" readonly>
                            <div class="form-text">O horário de término será calculado automaticamente com base neste tempo.</div>
                        </div>
                        
                        <div class="alert alert-warning small">
                            <i class="fas fa-umbrella me-1"></i> Em caso de previsão de chuva na data selecionada, o serviço poderá ser reagendado para o próximo dia útil disponível.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-check me-2"></i>Confirmar Agendamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['agendado']) && $_GET['agendado'] == 'true'): ?>
        <!-- Mensagem de confirmação de agendamento -->
        <div class="alert alert-success mt-4 mb-4">
            <h5 class="alert-heading"><i class="fas fa-calendar-check me-2"></i>Serviço Agendado!</h5>
            <p class="mb-0">O serviço foi agendado para <strong><?php echo isset($_GET['data']) ? $_GET['data'] : 'data selecionada'; ?></strong> às <strong><?php echo isset($_GET['hora']) ? $_GET['hora'] : 'hora selecionada'; ?></strong>.</p>
            <div class="d-flex align-items-center mt-3 p-2 bg-light rounded border">
                <i class="fas fa-info-circle text-primary me-2 fs-4"></i>
                <div>
                    <p class="mb-1"><strong>Próximos passos:</strong></p>
                    <ul class="mb-0 ps-3">
                        <li>Nossa equipe estará no local na data e horário agendados</li>
                        <li>Em caso de mau tempo, você será notificado com antecedência sobre um possível reagendamento</li>
                        <li>Caso precise reagendar, entre em contato conosco com pelo menos 24 horas de antecedência</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['erro'])): ?>
        <!-- Mensagem de erro no agendamento -->
        <div class="alert alert-danger mt-4 mb-4">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Erro no Agendamento</h5>
            <p class="mb-0"><?php echo $_GET['erro']; ?></p>
            <p class="mt-2 mb-0">Por favor, tente novamente ou entre em contato conosco para assistência.</p>
        </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php 
// Finalizando HTML para acesso externo
if (!$acesso_interno): 
?>
                </div>
            </div>

            <footer class="text-center text-muted my-5">
                <p>&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?></p>
            </footer>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
<?php 
    exit; // Encerrar script para acesso externo
else: 
?>

<script>
// Função para imprimir orçamento
function imprimirOrcamento() {
    window.print();
}

// Função para copiar link para o cliente
function copiarLinkCliente() {
    const link = '<?php echo BASE_URL; ?>orcamento_visualizar.php?codigo=<?php echo $orcamento['codigo_acesso']; ?>';

    // Criar elemento de texto temporário
    const temp = document.createElement('input');
    temp.value = link;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);

    alert('Link copiado para a área de transferência!');
}
</script>

<?php 
    require_once('includes/footer.php'); 
endif;
?>

<script>
// Verificar se há uma mensagem de pagamento registrado e recarregar a página uma vez
if (window.location.href.includes('mensagem=pago') || window.location.href.includes('mensagem=pagamento_parcial')) {
    // Verificar se já recarregamos a página
    if (!sessionStorage.getItem('recarregouPaginaOrcamento')) {
        // Marcar que já recarregamos a página
        sessionStorage.setItem('recarregouPaginaOrcamento', 'true');
        // Recarregar a página após um pequeno atraso
        setTimeout(function() {
            window.location.reload();
        }, 300);
    } else {
        // Limpar a marca após a recarga
        sessionStorage.removeItem('recarregouPaginaOrcamento');
    }
}

// Script para formulário de agendamento
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se o formulário de agendamento existe
    const formAgendamento = document.getElementById('form-agendamento');
    if (!formAgendamento) return;
    
    const inputData = document.getElementById('data_agendamento');
    const inputHora = document.getElementById('hora_inicio');
    
    // Definir a data mínima como hoje
    const hoje = new Date();
    const dataMinima = hoje.toISOString().split('T')[0];
    inputData.min = dataMinima;
    
    // Validar dias da semana (não permitir finais de semana)
    inputData.addEventListener('change', function() {
        const dataEscolhida = new Date(this.value);
        const diaSemana = dataEscolhida.getDay(); // 0 = Domingo, 6 = Sábado
        
        // Verificar se é final de semana
        if (diaSemana === 0 || diaSemana === 6) {
            alert('Por favor, selecione um dia útil (segunda a sexta-feira) para o agendamento.');
            this.value = '';
            return;
        }
        
        // Verificar se a data é no passado
        if (dataEscolhida < hoje) {
            alert('Por favor, selecione uma data futura para o agendamento.');
            this.value = '';
            return;
        }
    });
});
</script>
