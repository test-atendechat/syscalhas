<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Garantir acesso às variáveis globais de conexão
global $db, $pdo;

// Se por algum motivo ainda não estiverem definidas, tentar inicializá-las
if (!isset($pdo)) {
    // Tentar criar uma nova conexão como último recurso
    try {
        if (DB_TYPE == 'mysql') {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
        } else if (DB_TYPE == 'pgsql') {
            $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
        } else {
            throw new Exception("Tipo de banco de dados não suportado");
        }

        // Opções PDO
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        // Criar conexão
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die('Erro de conexão com o banco de dados: ' . $e->getMessage());
    }
}

// Inicialização de variáveis
$acesso_interno = true;
$orcamento = null;
$itens = [];
$cliente = null;
$mensagem = '';
$pagamentos = [];

// Verificar se existem mensagens vindas via GET
if (isset($_GET['mensagem'])) {
    if ($_GET['mensagem'] == 'pago') {
        $mensagem = alerta('Pagamento total registrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'pagamento_parcial') {
        $mensagem = alerta('Pagamento parcial registrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'cadastrado') {
        $mensagem = alerta('Orçamento cadastrado com sucesso!', 'success');
    }
}

// Verificar se há mensagem de agendamento
if (isset($_GET['mensagem_agendamento'])) {
    $tipo_alerta = $_GET['tipo'] ?? 'danger';
    $mensagem = alerta($_GET['mensagem_agendamento'], $tipo_alerta);
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
        $stmt = $pdo->prepare("UPDATE orcamentos SET 
                            status_execucao = 'finalizado', 
                            data_finalizacao = CURRENT_DATE 
                            WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento marcado como FINALIZADO com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao finalizar orçamento', 'danger');
        }
    } elseif ($acao == 'andamento') {
        // Atualizar status de execução para em andamento
        $stmt = $pdo->prepare("UPDATE orcamentos SET 
                            status_execucao = 'andamento' 
                            WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento marcado como EM ANDAMENTO com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao atualizar status de execução', 'danger');
        }
    } elseif ($acao == 'pendente') {
        // Atualizar status de execução para pendente
        $stmt = $pdo->prepare("UPDATE orcamentos SET 
                            status_execucao = 'pendente' 
                            WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Orçamento marcado como PENDENTE com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao atualizar status do orçamento.', 'danger');
        }
    } elseif ($acao == 'reabrir') {
        // Reabrir orçamento rejeitado (mudar status para pendente)
        $stmt = $pdo->prepare("UPDATE orcamentos SET 
                            status = 'pendente'
                            WHERE id = :id AND status = 'rejeitado'");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $mensagem = alerta('Orçamento reaberto com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao reabrir orçamento.', 'danger');
        }
    }
    
    // Redirecionar para remover a ação da URL
    header("Location: orcamento_visualizar.php?id={$id}");
    exit;
}

// Verificando tipo de acesso
if (isset($_GET['id'])) {
    // Acesso interno (painel administrativo)
    require_once('includes/auth.php');
    verificarAutenticacao();
    require_once('includes/header.php');

    $id = intval($_GET['id']);
    $orcamento = buscarOrcamento($id);

    if ($orcamento) {
        $itens = buscarItensOrcamento($id);
        $cliente = buscarCliente($orcamento['cliente_id']);
        
        // Buscar histórico de pagamentos do orçamento
        $stmt = $pdo->prepare("SELECT c.*, 
                          (SELECT nome FROM usuarios WHERE id = c.usuario_id) as usuario_nome
                          FROM caixa c 
                          WHERE c.orcamento_id = :orcamento_id AND c.tipo = 'entrada'
                          ORDER BY c.data_operacao DESC");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular o total pago
        $total_pago = 0;
        foreach ($pagamentos as $pagamento) {
            $total_pago += $pagamento['valor'];
        }
    } else {
        $mensagem = alerta('Orçamento não encontrado!', 'danger');
    }
} elseif (isset($_GET['codigo'])) {
    // Acesso externo (cliente)
    $acesso_interno = false;
    $codigo = $_GET['codigo'];

    $stmt = $pdo->prepare("SELECT id FROM orcamentos WHERE codigo_acesso = :codigo_acesso");
    $stmt->bindParam(':codigo_acesso', $codigo);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $resultado['id'];
        $orcamento = buscarOrcamento($id);
        $itens = buscarItensOrcamento($id);
        $cliente = buscarCliente($orcamento['cliente_id']);
        
        // Buscar histórico de pagamentos do orçamento
        // A variável $pdo já está definida
        $stmt = $pdo->prepare("SELECT c.*, 
                         (SELECT nome FROM usuarios WHERE id = c.usuario_id) as usuario_nome
                         FROM caixa c 
                         WHERE c.orcamento_id = :orcamento_id AND c.tipo = 'entrada'
                         ORDER BY c.data_operacao DESC");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular o total pago
        $total_pago = 0;
        foreach ($pagamentos as $pagamento) {
            $total_pago += $pagamento['valor'];
        }
    } else {
        // Template HTML mínimo para exibir erro
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Orçamento - <?php echo APP_NAME; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <link href="css/styles.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <h1 class="text-danger mb-4"><i class="fas fa-exclamation-triangle me-2"></i>Erro</h1>
                        <p class="lead">Código de orçamento inválido ou expirado.</p>
                        <p>O link que você tentou acessar não está disponível ou foi removido.</p>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit;
    }
} else {
    // Sem parâmetros, redirecionar para a página de orçamentos
    header('Location: orcamentos.php');
    exit;
}

// Processar decisão do cliente ou administrador (aprovar/rejeitar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['decisao'])) {
    $decisao = $_POST['decisao'];
    $id = intval($_POST['id']);

    if ($decisao == 'aprovar' || $decisao == 'rejeitar') {
        $novo_status = ($decisao == 'aprovar') ? 'aprovado' : 'rejeitado';

        $stmt = $pdo->prepare("UPDATE orcamentos SET status = :status WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $novo_status);
        $stmt->execute();

        // Se aprovado, processar baixa no estoque
        if ($novo_status == 'aprovado') {
            $pdo->beginTransaction();
            try {
                // Buscar itens atualizados
                $itens = buscarItensOrcamento($id);

                foreach ($itens as $item) {
                    if ($item['produto_id'] > 0) {
                        // Registrar movimentação no estoque
                        $stmt = $pdo->prepare("INSERT INTO estoque_movimentacoes 
                                        (produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id)
                                        VALUES 
                                        (:produto_id, 'saida', :quantidade, :valor_unitario, :valor_total, :observacao, :orcamento_id)");
                        $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':quantidade', $item['quantidade']);
                        $stmt->bindParam(':valor_unitario', $item['valor_unitario']);
                        $stmt->bindParam(':valor_total', $item['valor_total']);
                        $observacao = "Saída automática do orçamento #{$orcamento['numero']}";
                        $stmt->bindParam(':observacao', $observacao);
                        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
                        $stmt->execute();

                        // Atualizar estoque do produto
                        $stmt = $pdo->prepare("UPDATE produtos 
                                        SET estoque_atual = estoque_atual - :quantidade 
                                        WHERE id = :produto_id");
                        $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':quantidade', $item['quantidade']);
                        $stmt->execute();
                    }
                }

                $pdo->commit();
                $mensagem = alerta('Orçamento aprovado com sucesso!', 'success');
            } catch (Exception $e) {
                try {
                    $pdo->rollBack(); // Corrigido para rollBack() com B maiúsculo
                } catch (Exception $rollbackError) {
                    // Ignora erro de rollback
                }
                $mensagem = alerta('Erro ao processar baixa no estoque: ' . $e->getMessage(), 'danger');
            }
        } else {
            $mensagem = alerta('Orçamento rejeitado com sucesso.', 'warning');
        }

        // Recarregar orçamento com status atualizado
        $orcamento = buscarOrcamento($id);
    }
}

// Se for acesso externo (cliente)
if (!$acesso_interno) {
    // Início do HTML para cliente
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Orçamento #<?php echo $orcamento['numero']; ?> - <?php echo APP_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="css/styles.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Orçamento #<?php echo $orcamento['numero']; ?></h2>
                </div>
                <div class="card-body">
                    <?php echo $mensagem; ?>
    <?php
} else {
    // Cabeçalho para uso interno
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-file-invoice-dollar me-2"></i>Orçamento #<?php echo $orcamento['numero']; ?></h1>
        <div>
            <a href="orcamentos.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <div class="btn-group ms-2">
                <a href="orcamento_form.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>Editar
                </a>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="visually-hidden">Mais opções</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <!-- Opções de status -->
                    <?php if ($orcamento['status'] == 'aprovado'): ?>
                        <?php if ($orcamento['status_pagamento'] == 'pendente' || $orcamento['status_pagamento'] == 'pago_parcial'): ?>
                            <li>
                                <a class="dropdown-item" href="caixa_form.php?orcamento_id=<?php echo $orcamento['id']; ?>">
                                    <i class="fas fa-money-bill-wave me-2"></i>Registrar Pagamento
                                </a>
                            </li>
                        <?php endif; ?>
                        <!-- Opções de status de execução -->
                        <li>
                            <a class="dropdown-item text-secondary fw-bold">
                                <i class="fas fa-tasks me-2"></i>Status de Execução
                            </a>
                        </li>
                        <?php if ($orcamento['status_execucao'] != 'pendente'): ?>
                            <li>
                                <a class="dropdown-item" href="?id=<?php echo $orcamento['id']; ?>&acao=pendente">
                                    <i class="fas fa-circle me-2 text-secondary"></i>Marcar como Pendente
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($orcamento['status_execucao'] != 'agendado' && $orcamento['status_execucao'] != 'andamento'): ?>
                            <li>
                                <a class="dropdown-item" href="agendar_servico.php?orcamento_id=<?php echo $orcamento['id']; ?>">
                                    <i class="fas fa-calendar-alt me-2 text-info"></i>Agendar Serviço
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($orcamento['status_execucao'] != 'andamento'): ?>
                            <li>
                                <a class="dropdown-item" href="?id=<?php echo $orcamento['id']; ?>&acao=andamento">
                                    <i class="fas fa-spinner me-2 text-warning"></i>Marcar Em Andamento
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($orcamento['status_execucao'] != 'finalizado'): ?>
                            <li>
                                <a class="dropdown-item" href="?id=<?php echo $orcamento['id']; ?>&acao=finalizar">
                                    <i class="fas fa-check-circle me-2 text-success"></i>Marcar como Finalizado
                                </a>
                            </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    
                    <!-- Opções específicas para orçamentos rejeitados -->
                    <?php if ($orcamento['status'] == 'rejeitado'): ?>
                    <li>
                        <a class="dropdown-item" href="?id=<?php echo $orcamento['id']; ?>&acao=reabrir">
                            <i class="fas fa-redo-alt me-2"></i>Reabrir Orçamento
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    
                    <!-- Opções gerais -->
                    <li>
                        <a class="dropdown-item" href="#" onclick="imprimirOrcamento(); return false;">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" onclick="copiarLinkCliente(); return false;">
                            <i class="fas fa-link me-2"></i>Copiar Link do Cliente
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="mailto:<?php echo $cliente['email']; ?>?subject=Orçamento <?php echo $orcamento['numero']; ?>&body=Olá <?php echo $cliente['nome']; ?>, segue o link para acessar seu orçamento: <?php echo BASE_URL; ?>orcamento_visualizar.php?codigo=<?php echo $orcamento['codigo_acesso']; ?>">
                            <i class="fas fa-envelope me-2"></i>Enviar por Email
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="https://api.whatsapp.com/send?phone=<?php echo preg_replace('/\D/', '', $cliente['telefone']); ?>&text=Olá <?php echo urlencode($cliente['nome']); ?>, segue o link para acessar seu orçamento: <?php echo urlencode(BASE_URL . 'orcamento_visualizar.php?codigo=' . $orcamento['codigo_acesso']); ?>" target="_blank">
                            <i class="fab fa-whatsapp me-2 text-success"></i>Enviar por WhatsApp
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" onclick="confirmarExclusao(<?php echo $orcamento['id']; ?>, '<?php echo $orcamento['numero']; ?>', 'orcamentos.php'); return false;">
                            <i class="fas fa-trash me-2"></i>Excluir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <?php echo $mensagem; ?>
    <?php
}
?>

<!-- CONTEÚDO COMUM PARA AMBOS OS TIPOS DE ACESSO -->
<div class="orcamento-container" id="orcamento-imprimir">
    <div class="orcamento-header">
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h2 class="mb-0"><?php echo APP_NAME; ?></h2>
                <p class="text-muted mb-0">Orçamento de Calhas e Rufos</p>
            </div>
            <div class="col-md-6 text-md-end">
                <!-- Status do orçamento -->
                <span class="status-box status-<?php echo $orcamento['status']; ?>">
                    <?php echo ucfirst($orcamento['status']); ?>
                </span>
                
                <!-- Status de pagamento -->
                <?php if ($orcamento['status_pagamento'] == 'pago_total'): ?>
                <span class="status-box status-pago ms-2">
                    Pago Total
                </span>
                <?php elseif ($orcamento['status_pagamento'] == 'pago_parcial'): ?>
                <span class="status-box status-pago-parcial ms-2">
                    Pago Parcial
                </span>
                <?php endif; ?>
                
                <!-- Status de execução -->
                <?php if ($orcamento['status_execucao'] != 'pendente'): ?>
                    <?php 
                    $status_class = '';
                    switch ($orcamento['status_execucao']) {
                        case 'agendado':
                            $status_class = 'status-agendado';
                            break;
                        case 'andamento':
                            $status_class = 'status-andamento';
                            break;
                        case 'finalizado':
                            $status_class = 'status-finalizado';
                            break;
                    }
                    ?>
                    <span class="status-box <?php echo $status_class; ?> ms-2">
                        <?php echo ucfirst($orcamento['status_execucao']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Dados do Cliente</h5>
                <p class="mb-0"><strong>Nome:</strong> <?php echo $cliente['nome']; ?></p>
                <?php if (!empty($cliente['cpf_cnpj'])): ?>
                    <p class="mb-0"><strong>CPF/CNPJ:</strong> <?php echo $cliente['cpf_cnpj']; ?></p>
                <?php endif; ?>
                <?php if (!empty($cliente['telefone'])): ?>
                    <p class="mb-0"><strong>Telefone:</strong> <?php echo $cliente['telefone']; ?></p>
                <?php endif; ?>
                <?php if (!empty($cliente['email'])): ?>
                    <p class="mb-0"><strong>Email:</strong> <?php echo $cliente['email']; ?></p>
                <?php endif; ?>
                <?php if (!empty($cliente['endereco'])): ?>
                    <p class="mb-0"><strong>Endereço:</strong> <?php echo $cliente['endereco']; ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <h5>Dados do Orçamento</h5>
                <p class="mb-0"><strong>Número:</strong> <?php echo $orcamento['numero']; ?></p>
                <p class="mb-0"><strong>Data:</strong> <?php echo dataParaBr($orcamento['data_criacao']); ?></p>
                <p class="mb-0"><strong>Validade:</strong> <?php echo dataParaBr($orcamento['data_validade']); ?></p>
                <p class="mb-0"><strong>Forma de Pagamento:</strong> <?php echo ($orcamento['forma_pagamento'] == 'vista') ? 'À Vista' : 'Até 12x Sem Juros'; ?></p>

            </div>
        </div>
    </div>

    <h5 class="mb-3">Itens do Orçamento</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-orcamento">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Descrição</th>
                    <th>Unidade</th>
                    <th class="text-center">Quantidade</th>
                    <th class="text-end">Valor Unitário</th>
                    <th class="text-end">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($itens) > 0): ?>
                    <?php foreach ($itens as $index => $item): ?>
                        <?php 
                            $valor_unitario_com_mo = $item['valor_unitario'] * (1 + ($orcamento['taxa_mao_obra'] / 100));
                            $valor_total_com_mo = $valor_unitario_com_mo * $item['quantidade'];
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo $item['descricao']; ?></td>
                            <td><?php echo $item['unidade']; ?></td>
                            <td class="text-center"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo formataValor($valor_unitario_com_mo); ?></td>
                            <td class="text-end"><?php echo formataValor($valor_total_com_mo); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum item encontrado para este orçamento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <?php if ($orcamento['forma_pagamento'] == 'vista'): 
                    // Buscar a configuração do percentual de desconto à vista
                    $desconto_vista = 10; // Valor padrão de 10%
                    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'desconto_pagamento_vista'");
                    $stmt->execute();
                    if ($stmt->rowCount() > 0) {
                        $config = $stmt->fetch(PDO::FETCH_ASSOC);
                        $desconto_vista = floatval($config['valor']);
                    }
                    
                    // Calcular o subtotal (valor antes do desconto)
                    $subtotal = $orcamento['valor_total'] / (1 - ($desconto_vista/100));
                    $valor_desconto = $subtotal - $orcamento['valor_total'];
                ?>
                <tr>
                    <td colspan="5" class="text-end fw-bold">Subtotal:</td>
                    <td class="text-end"><?php echo formataValor($subtotal); ?></td>
                </tr>
                <tr>
                    <td colspan="5" class="text-end fw-bold">Desconto à Vista (<?php echo $desconto_vista; ?>%):</td>
                    <td class="text-end"><?php echo formataValor($valor_desconto); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="5" class="text-end fw-bold">Valor Total:</td>
                    <td class="text-end"><strong><?php echo formataValor($orcamento['valor_total']); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php if (count($pagamentos) > 0): ?>
    <div class="mt-4">
        <h5><i class="fas fa-history me-2"></i>Histórico de Pagamentos</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Forma de Pagamento</th>
                        <th>Descrição</th>
                        <?php if ($acesso_interno): ?>
                        <th>Usuário</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagamentos as $pagamento): ?>
                    <tr>
                        <td><?php echo dataParaBr($pagamento['data_operacao']); ?></td>
                        <td class="text-end"><?php echo formataValor($pagamento['valor']); ?></td>
                        <td>
                            <?php 
                            switch ($pagamento['forma_pagamento']) {
                                case 'dinheiro':
                                    echo '<span class="badge bg-success">Dinheiro</span>';
                                    break;
                                case 'cartao_credito':
                                    echo '<span class="badge bg-primary">Cartão de Crédito</span>';
                                    break;
                                case 'cartao_debito':
                                    echo '<span class="badge bg-info">Cartão de Débito</span>';
                                    break;
                                case 'pix':
                                    echo '<span class="badge bg-warning text-dark">PIX</span>';
                                    break;
                                default:
                                    echo '<span class="badge bg-secondary">Outros</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo $pagamento['descricao']; ?></td>
                        <?php if ($acesso_interno): ?>
                        <td><?php echo $pagamento['usuario_nome'] ?? 'Sistema'; ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-info">
                        <td colspan="1"><strong>Total Pago:</strong></td>
                        <td class="text-end"><strong><?php echo formataValor($total_pago); ?></strong></td>
                        <td colspan="<?php echo $acesso_interno ? '3' : '2'; ?>">
                            <?php if ($total_pago > 0 && $total_pago < $orcamento['valor_total']): ?>
                            <span class="text-primary">Valor Restante: <?php echo formataValor($orcamento['valor_total'] - $total_pago); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($orcamento['observacoes'])): ?>
        <div class="mt-4">
            <h5>Observações</h5>
            <div class="card">
                <div class="card-body bg-light">
                    <?php echo nl2br($orcamento['observacoes']); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="orcamento-footer mt-5">
        <div class="row">
            <div class="col-md-12 text-center">
                <p>Este orçamento é válido até <?php echo dataParaBr($orcamento['data_validade']); ?></p>
                
                <?php if ($acesso_interno && $orcamento['status'] == 'pendente'): ?>
                <div class="mt-4">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="id" value="<?php echo $orcamento['id']; ?>">
                        <button type="submit" name="decisao" value="aprovar" class="btn btn-success mx-2">
                            <i class="fas fa-check-circle me-2"></i>Aprovar Orçamento
                        </button>
                        <button type="submit" name="decisao" value="rejeitar" class="btn btn-danger mx-2">
                            <i class="fas fa-times-circle me-2"></i>Rejeitar Orçamento
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
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
                    Agradecemos por aprovar nosso orçamento. Você pode agendar a execução do serviço diretamente através deste link.
                <?php endif; ?>
            <?php else: ?>
                Você rejeitou este orçamento. Caso queira discutir alterações ou fazer uma nova cotação, entre em contato conosco.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['codigo']) && $orcamento['status'] == 'aprovado' && $orcamento['status_execucao'] != 'finalizado'): ?>
<!-- Seção de agendamento para clientes -->
<div class="card mb-4" id="agendamento">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Agendar Serviço</h5>
    </div>
    <div class="card-body">
        <form id="formAgendamento" method="post" action="agendar_servico.php">
            <input type="hidden" name="orcamento_id" value="<?php echo $orcamento['id']; ?>">
            <input type="hidden" name="codigo" value="<?php echo $_GET['codigo']; ?>">
            <input type="hidden" name="tempo_previsto" value="<?php echo $orcamento['tempo_previsto']; ?>">
            <input type="hidden" name="unidade_tempo" value="<?php echo $orcamento['unidade_tempo']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="data_servico" class="form-label required-field">Data de Execução</label>
                    <input type="date" class="form-control" id="data_servico" name="data_servico" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    <small class="text-muted">Selecione a data desejada para a execução do serviço.</small>
                </div>
                <div class="col-md-6">
                    <label for="hora_inicio" class="form-label required-field">Horário de Início</label>
                    <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                        <option value="">Selecione o horário</option>
                        <?php
                        // Obter configurações de horário de funcionamento
                        $stmt_conf = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim')");
                        $config = $stmt_conf->fetchAll(PDO::FETCH_KEY_PAIR);

                        // Valores padrão caso não existam configurações
                        $horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
                        $horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';
                        
                        // Calcular hora máxima possível com base no tempo previsto
                        $tempo_previsto = $orcamento['tempo_previsto'];
                        $unidade_tempo = $orcamento['unidade_tempo'];
                        
                        // Converter tempo previsto para minutos
                        $duracao_minutos = $tempo_previsto;
                        if ($unidade_tempo === 'horas') {
                            $duracao_minutos = $tempo_previsto * 60;
                        } else if ($unidade_tempo === 'dias') {
                            $duracao_minutos = $tempo_previsto * 60 * 8; // 8 horas por dia
                        }
                        
                        // Extrair as horas e minutos do horário de fim
                        list($horas_fim, $minutos_fim) = explode(':', $horario_fim);
                        $minutos_total_fim = ($horas_fim * 60) + $minutos_fim;
                        
                        // Extrair as horas e minutos do horário de início
                        list($horas_inicio, $minutos_inicio) = explode(':', $horario_inicio);
                        $minutos_total_inicio = ($horas_inicio * 60) + $minutos_inicio;
                        
                        // Calcular hora máxima para início (hora_fim - duração em minutos)
                        $hora_maxima_minutos = $minutos_total_fim - $duracao_minutos;
                        $hora_maxima_horas = floor($hora_maxima_minutos / 60);
                        $hora_maxima_mins = $hora_maxima_minutos % 60;
                        
                        // Garantir que não seja menor que o horário de início
                        $hora_maxima_horas = max($horas_inicio, $hora_maxima_horas);
                        
                        // Criar intervalo de horas em incrementos de 1 hora
                        for ($hora = $horas_inicio; $hora <= $hora_maxima_horas; $hora++) {
                            // Para a primeira hora, considerar os minutos de início
                            $min_inicial = ($hora == $horas_inicio) ? $minutos_inicio : 0;
                            // Para a última hora, considerar os minutos calculados
                            $max_min = ($hora == $hora_maxima_horas) ? $hora_maxima_mins : 59;
                            
                            // Adicionar opções para cada hora disponvel em incrementos de 30 min
                            for ($min = $min_inicial; $min <= $max_min; $min += 30) {
                                if ($min == 60) continue; // Pular quando for exatamente 60 minutos
                                $hora_str = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':' . str_pad($min, 2, '0', STR_PAD_LEFT);
                                echo "<option value=\"{$hora_str}\">{$hora_str}</option>";
                            }
                        }
                        ?>
                    </select>
                    <small class="text-muted">Horário de trabalho: <?php echo $horario_inicio; ?> às <?php echo $horario_fim; ?>.</small>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="colaborador_id" class="form-label">Instalador Preferencial</label>
                    <select class="form-select" id="colaborador_id" name="colaborador_id">
                        <option value="">Selecione um instalador ou deixe em branco para qualquer disponível</option>
                        <?php
                        // A lista será carregada via JavaScript, dependendo da data e hora selecionadas
                        $selected_id = $orcamento['colaborador_id'] ?? 0;
                        if ($selected_id > 0) {
                            // Tentar encontrar o id correspondente na tabela instaladores
                            $stmt = $pdo->prepare("SELECT id, nome FROM instaladores WHERE id = :id AND ativo = TRUE");
                            $stmt->bindParam(':id', $selected_id, PDO::PARAM_INT);
                            $stmt->execute();
                            
                            if ($colaborador = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$colaborador['id']}\" selected>{$colaborador['nome']}</option>";
                            } else {
                                // Se não encontrar na tabela instaladores, verificar na tabela colaboradores
                                // (para compatibilidade com registros antigos)
                                $stmt = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE id = :id AND tipo = 'instalador'");
                                $stmt->bindParam(':id', $selected_id, PDO::PARAM_INT);
                                $stmt->execute();
                                if ($colaborador = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$colaborador['id']}\" selected>{$colaborador['nome']}</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                    <small class="text-muted">Tempo previsto para este serviço: <?php echo $orcamento['tempo_previsto']; ?> <?php echo $orcamento['unidade_tempo']; ?>.</small>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Importante:</strong> Apenas colaboradores disponíveis para este horário são mostrados na lista.
                Os horários de trabalho são das <?php echo $horario_inicio; ?> às <?php echo $horario_fim; ?> horas.
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-calendar-check me-2"></i>Confirmar Agendamento
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!$acesso_interno && $orcamento['status'] == 'aprovado' && $orcamento['status_execucao'] != 'finalizado'): ?>
<script>
    // Script para validar o horário e carregar colaboradores disponíveis
    document.addEventListener('DOMContentLoaded', function() {
        const dataServico = document.getElementById('data_servico');
        const horaInicio = document.getElementById('hora_inicio');
        const colaboradorSelect = document.getElementById('colaborador_id');
        const tempoPrevisto = <?php echo $orcamento['tempo_previsto']; ?>;
        const unidadeTempo = '<?php echo $orcamento['unidade_tempo']; ?>';
        
        // Converter tempo previsto para minutos
        let duracaoMinutos = tempoPrevisto;
        if (unidadeTempo === 'horas') {
            duracaoMinutos = tempoPrevisto * 60;
        } else if (unidadeTempo === 'dias') {
            duracaoMinutos = tempoPrevisto * 60 * 8; // Considerando 8 horas por dia
        }
        
        // Função para carregar colaboradores disponíveis
        function carregarColaboradoresDisponiveis() {
            const dataValue = dataServico.value;
            const horaValue = horaInicio.value;
            
            // Verificar se ambos os campos estão preenchidos
            if (!dataValue || !horaValue) return;
            
            // Limpar opções atuais exceto a primeira
            const firstOption = colaboradorSelect.options[0];
            colaboradorSelect.innerHTML = '';
            colaboradorSelect.appendChild(firstOption);
            
            // Definir mensagem de carregamento
            const loadingOption = document.createElement('option');
            loadingOption.text = 'Carregando colaboradores disponíveis...';
            loadingOption.disabled = true;
            colaboradorSelect.appendChild(loadingOption);
            colaboradorSelect.selectedIndex = 1;
            
            // Buscar colaboradores disponíveis via AJAX
            fetch(`ajax/verificar_colaboradores_disponiveis.php?data=${dataValue}&hora=${horaValue}&tempo_previsto=${tempoPrevisto}&unidade_tempo=${unidadeTempo}`)
                .then(response => response.json())
                .then(data => {
                    // Remover opção de carregamento
                    colaboradorSelect.removeChild(loadingOption);
                    
                    if (data.status === 'sucesso') {
                        // Preencher select com colaboradores disponíveis
                        if (data.colaboradores.length > 0) {
                            data.colaboradores.forEach(colaborador => {
                                const option = document.createElement('option');
                                option.value = colaborador.id;
                                option.text = colaborador.nome;
                                colaboradorSelect.appendChild(option);
                            });
                        } else {
                            // Se não há colaboradores disponíveis
                            const naoDisponivelOption = document.createElement('option');
                            naoDisponivelOption.text = 'Nenhum colaborador disponível neste horário';
                            naoDisponivelOption.disabled = true;
                            colaboradorSelect.appendChild(naoDisponivelOption);
                        }
                    } else {
                        // Exibir mensagem de erro
                        const erroOption = document.createElement('option');
                        erroOption.text = 'Erro ao carregar colaboradores: ' + data.mensagem;
                        erroOption.disabled = true;
                        colaboradorSelect.appendChild(erroOption);
                    }
                })
                .catch(error => {
                    // Remover opção de carregamento
                    colaboradorSelect.removeChild(loadingOption);
                    
                    // Exibir mensagem de erro de conexão
                    const erroOption = document.createElement('option');
                    erroOption.text = 'Erro de conexão ao buscar colaboradores';
                    erroOption.disabled = true;
                    colaboradorSelect.appendChild(erroOption);
                    console.error('Erro:', error);
                });
        }
        
        // Eventos para acionar a busca de colaboradores disponíveis
        dataServico.addEventListener('change', carregarColaboradoresDisponiveis);
        
        // Ao selecionar hora, validar se há tempo suficiente e buscar colaboradores
        horaInicio.addEventListener('change', function() {
            const horaInicioStr = this.value;
            if (!horaInicioStr) return;
            
            const [hora, minuto] = horaInicioStr.split(':').map(Number);
            const horaInicioMinutos = hora * 60 + minuto;
            
            // Obter horário de fim do expediente das configurações PHP
            const horarioFim = '<?php echo $horario_fim; ?>';
            const [horaFim, minutoFim] = horarioFim.split(':').map(Number);
            const fimExpedienteMinutos = horaFim * 60 + minutoFim;
            
            // Verificar se o serviço pode ser concluído no mesmo dia
            if (horaInicioMinutos + duracaoMinutos > fimExpedienteMinutos) {
                alert('Atenção: O serviço não poderá ser concluído no mesmo dia com este horário de início.\nO serviço será continuado no próximo dia disponível.');
            }
            
            carregarColaboradoresDisponiveis();
        });
    });
</script>
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
</script>