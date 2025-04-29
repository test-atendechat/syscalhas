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

// Processar decisão do cliente (aprovar/rejeitar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['decisao']) && !$acesso_interno) {
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
                    PAGO TOTAL
                </span>
                <?php elseif ($orcamento['status_pagamento'] == 'pago_parcial'): ?>
                <span class="status-box status-pago-parcial ms-2">
                    PAGO PARCIAL
                </span>
                <?php endif; ?>
                
                <!-- Status de execução -->
                <?php if ($orcamento['status_execucao'] == 'finalizado'): ?>
                <span class="status-box status-finalizado ms-2">
                    FINALIZADO
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
                    $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'desconto_pagamento_vista'");
                    $stmt->execute();
                    if ($stmt->rowCount() > 0) {
                        $config = $stmt->fetch(PDO::FETCH_ASSOC);
                        $desconto_vista = floatval($config['valor']);
                    }
                ?>
                <tr>
                    <td colspan="5" class="text-end fw-bold">Desconto à Vista (<?php echo $desconto_vista; ?>%):</td>
                    <td class="text-end"><?php 
                      $subtotal = $orcamento['valor_total'] / (1 - ($desconto_vista/100));
                      $valor_desconto = $subtotal - $orcamento['valor_total'];
                      echo formataValor($valor_desconto); 
                    ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="5" class="text-end fw-bold">Valor Total:</td>
                    <td class="text-end"><strong><?php echo formataValor($orcamento['valor_total']); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

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
                    Agradecemos por aprovar nosso orçamento. Em breve entraremos em contato para agendar a execução do serviço.
                <?php endif; ?>
            <?php else: ?>
                Você rejeitou este orçamento. Caso queira discutir alterações ou fazer uma nova cotação, entre em contato conosco.
            <?php endif; ?>
        </p>
    </div>
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