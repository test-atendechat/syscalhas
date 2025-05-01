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
        // Reabrir orçamento rejeitado (mudar status para pendente)
        $stmt = $db->prepare("UPDATE orcamentos SET 
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
        $stmt = $db->prepare("SELECT c.*, 
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
        
        // Buscar agendamentos do orçamento (acesso interno)
        $stmt = $db->prepare("SELECT a.*, 
                          (SELECT string_agg(i.nome, ', ') 
                           FROM agendamento_instaladores ai 
                           JOIN instaladores i ON ai.instalador_id = i.id 
                           WHERE ai.agendamento_id = a.id) as instaladores
                          FROM agendamentos a 
                          WHERE a.orcamento_id = :orcamento_id AND a.status != 'cancelado'
                          ORDER BY a.data_agendamento DESC, a.hora_inicio ASC");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mensagem = alerta('Orçamento não encontrado!', 'danger');
    }
} elseif (isset($_GET['codigo'])) {
    // Acesso externo (cliente)
    $acesso_interno = false;
    $codigo = $_GET['codigo'];

    $stmt = $db->prepare("SELECT id FROM orcamentos WHERE codigo_acesso = :codigo_acesso");
    $stmt->bindParam(':codigo_acesso', $codigo);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $resultado['id'];
        $orcamento = buscarOrcamento($id);
        $itens = buscarItensOrcamento($id);
        $cliente = buscarCliente($orcamento['cliente_id']);
        
        // Buscar histórico de pagamentos do orçamento
        $stmt = $db->prepare("SELECT c.*, 
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
        
        // Buscar agendamentos do orçamento
        $stmt = $db->prepare("SELECT a.*, 
                         (SELECT string_agg(i.nome, ', ') 
                          FROM agendamento_instaladores ai 
                          JOIN instaladores i ON ai.instalador_id = i.id 
                          WHERE ai.agendamento_id = a.id) as instaladores
                         FROM agendamentos a 
                         WHERE a.orcamento_id = :orcamento_id AND a.status != 'cancelado'
                         ORDER BY a.data_agendamento DESC, a.hora_inicio ASC");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $stmt = $db->prepare("UPDATE orcamentos SET status = :status WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $novo_status);
        $stmt->execute();

        // Se aprovado, processar baixa no estoque
        if ($novo_status == 'aprovado') {
            $db->beginTransaction();
            try {
                // Buscar itens atualizados
                $itens = buscarItensOrcamento($id);

                foreach ($itens as $item) {
                    if ($item['produto_id'] > 0) {
                        // Registrar movimentação no estoque
                        $stmt = $db->prepare("INSERT INTO estoque_movimentacoes 
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
                        $stmt = $db->prepare("UPDATE produtos 
                                        SET estoque_atual = estoque_atual - :quantidade 
                                        WHERE id = :produto_id");
                        $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
                        $stmt->bindParam(':quantidade', $item['quantidade']);
                        $stmt->execute();
                    }
                }

                $db->commit();
                $mensagem = alerta('Orçamento aprovado com sucesso!', 'success');
            } catch (Exception $e) {
                $db->rollback();
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
                        <?php if ($orcamento['status_execucao'] == 'pendente'): ?>
                            <li>
                                <a class="dropdown-item" href="calendario_agendamento.php?orcamento_id=<?php echo $orcamento['id']; ?>">
                                    <i class="fas fa-calendar-check me-2"></i>Agendar Instalação
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?id=<?php echo $orcamento['id']; ?>&acao=finalizar">
                                    <i class="fas fa-check-circle me-2"></i>Marcar como Finalizado
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
                <?php if ($orcamento['status_execucao'] == 'finalizado'): ?>
                <span class="status-box status-finalizado ms-2">
                    Finalizado
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
                <p class="mb-0"><strong>Tempo Previsto:</strong> <?php echo isset($orcamento['tempo_previsto_horas']) ? $orcamento['tempo_previsto_horas'] . ' hora(s)' : '2 horas'; ?></p>

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
                    $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'desconto_pagamento_vista'");
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

    <?php if (!empty($agendamentos)): ?>
    <div class="mt-4">
        <h5><i class="fas fa-calendar-check me-2 text-primary"></i>Agendamento de Instalação</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Instalador(es)</th>
                        <th>Status</th>
                        <?php if (!$acesso_interno): ?>
                        <th>Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agendamentos as $agendamento): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($agendamento['data_agendamento'])); ?></td>
                        <td><?php echo substr($agendamento['hora_inicio'], 0, 5) . ' - ' . substr($agendamento['hora_fim'], 0, 5); ?></td>
                        <td><?php echo $agendamento['instaladores'] ?: 'A ser definido'; ?></td>
                        <td>
                            <?php 
                            switch ($agendamento['status']) {
                                case 'agendado':
                                    echo '<span class="badge bg-primary">Agendado</span>';
                                    break;
                                case 'concluido':
                                    echo '<span class="badge bg-success">Concluído</span>';
                                    break;
                                case 'reagendado':
                                    echo '<span class="badge bg-warning text-dark">Reagendado</span>';
                                    break;
                                default:
                                    echo '<span class="badge bg-secondary">'. ucfirst($agendamento['status']) .'</span>';
                            }
                            ?>
                        </td>
                        <?php if (!$acesso_interno): ?>
                        <td>
                            <?php
                            // Verificar se é possível reagendar (24 horas de antecedência)
                            $data_agendamento = new DateTime($agendamento['data_agendamento'] . ' ' . $agendamento['hora_inicio']);
                            $agora = new DateTime(); 
                            $diferenca = $agora->diff($data_agendamento);
                            $horas_ate_agendamento = ($diferenca->days * 24) + $diferenca->h;
                            $pode_reagendar = $horas_ate_agendamento >= 24;
                            
                            // Previsão do tempo (aqui poderíamos obter dados reais de uma API)
                            $previsao_chuva = rand(0, 1) ? true : false; // Simulação para exemplo
                            $temperatura = rand(15, 30);
                            $condicao_tempo = $previsao_chuva ? 'Possibilidade de chuva' : 'Ensolarado';
                            ?>
                            
                            <div class="d-flex flex-column gap-2">
                                <?php if ($pode_reagendar): ?>
                                <a href="calendario_agendamento.php?orcamento_id=<?php echo $orcamento['id']; ?>&codigo_acesso=<?php echo isset($codigo) ? $codigo : $orcamento['codigo_acesso']; ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-calendar-alt me-1"></i>Reagendar
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled title="Só é possível reagendar com 24h de antecedência">
                                    <i class="fas fa-calendar-alt me-1"></i>Reagendar
                                </button>
                                <?php endif; ?>
                                
                                <div class="small mt-1">
                                    <span class="d-block"><i class="fas <?php echo $previsao_chuva ? 'fa-cloud-rain text-primary' : 'fa-sun text-warning'; ?> me-1"></i> <?php echo $condicao_tempo; ?></span>
                                    <span class="d-block"><i class="fas fa-temperature-high text-danger me-1"></i> <?php echo $temperatura; ?>°C</span>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($orcamento['status'] == 'aprovado'): ?>
    <div class="alert alert-info mt-4">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-info-circle fa-2x text-primary"></i>
            </div>
            <div>
                <h5 class="alert-heading">Agendamento Pendente</h5>
                <p>Este orçamento foi aprovado, mas a instalação ainda não foi agendada.</p>
                <?php if (!$acesso_interno): ?>
                <a href="calendario_agendamento.php?orcamento_id=<?php echo $orcamento['id']; ?>&codigo_acesso=<?php echo isset($codigo) ? $codigo : $orcamento['codigo_acesso']; ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-plus me-2"></i>Agendar Instalação
                </a>
                <?php endif; ?>
            </div>
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
                        <div class="card border mb-3">
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
                                                
                                                // Verificar o tempo previsto de execução
                                                $tempo_previsto = isset($orcamento['tempo_previsto_horas']) ? intval($orcamento['tempo_previsto_horas']) : 2;
                                                
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
                                    
                                    <?php 
                                    // Calcular a hora de fim com base no tempo previsto
                                    $tempo_previsto = isset($orcamento['tempo_previsto_horas']) ? intval($orcamento['tempo_previsto_horas']) : 2;
                                    ?>
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
                    <div class="alert alert-success mb-3">
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
                    <div class="alert alert-danger mb-3">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Erro no Agendamento</h5>
                        <p class="mb-0"><?php echo $_GET['erro']; ?></p>
                        <p class="mt-2 mb-0">Por favor, tente novamente ou entre em contato conosco para assistência.</p>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['agendado']) && $_GET['agendado'] == 'true'): ?>
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
                    <div class="alert alert-danger mt-4 mb-4">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Erro no Agendamento</h5>
                        <p class="mb-0"><?php echo $_GET['erro']; ?></p>
                        <p class="mt-2 mb-0">Por favor, tente novamente ou entre em contato conosco para assistência.</p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
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
    
    // Calcular o tempo previsto de execução quando o horário for selecionado
    inputHora.addEventListener('change', function() {
        if (!this.value || !inputData.value) return;
        
        // Aqui poderia ter uma chamada AJAX para verificar conflitos de agendamento
        // mas por simplicidade, apenas calculamos o horário de término esperado
        const tempoPrevisto = <?php echo isset($orcamento['tempo_previsto_horas']) ? intval($orcamento['tempo_previsto_horas']) : 2; ?>;
        const horaInicio = this.value.split(':')[0];
        const minInicio = this.value.split(':')[1];
        
        let horaFim = parseInt(horaInicio) + Math.floor(tempoPrevisto);
        let minFim = parseInt(minInicio) + ((tempoPrevisto - Math.floor(tempoPrevisto)) * 60);
        
        if (minFim >= 60) {
            horaFim += Math.floor(minFim / 60);
            minFim = minFim % 60;
        }
        
        // Formatar para exibição
        const horaFimFormatada = String(horaFim).padStart(2, '0') + ':' + String(minFim).padStart(2, '0');
        const infoTempo = document.querySelector('.form-text:not(:first-child)');
        if (infoTempo) {
            infoTempo.innerHTML = `O serviço está previsto para terminar às <strong>${horaFimFormatada}</strong>, com base no tempo estimado.`;
        }
    });
});
</script>