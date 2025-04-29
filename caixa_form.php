<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
$orcamento = null;
$cliente = null;

$movimentacao = [
    'id' => 0,
    'data_operacao' => date('Y-m-d'),
    'tipo' => 'entrada',
    'descricao' => '',
    'valor' => '',
    'forma_pagamento' => 'dinheiro',
    'orcamento_id' => null,
    'cliente_id' => null,
    'observacoes' => '',
    'is_fechamento' => false
];
$erro = '';
$sucesso = '';
$titulo = 'Registrar Nova Movimentação';
$modo = 'cadastrar';

// Se for uma movimentação relacionada a um orçamento
if ($orcamento_id > 0) {
    $stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome, c.id as cliente_id 
                        FROM orcamentos o
                        JOIN clientes c ON o.cliente_id = c.id
                        WHERE o.id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        $movimentacao['orcamento_id'] = $orcamento_id;
        $movimentacao['cliente_id'] = $orcamento['cliente_id'];
        $movimentacao['descricao'] = "Pagamento do Orçamento #{$orcamento_id}";
        $cliente = ['id' => $orcamento['cliente_id'], 'nome' => $orcamento['cliente_nome']];
        $titulo = "Registro de Pagamento do Orçamento #{$orcamento_id}";
        
        // Buscar valor do orçamento e calcular valores para exibição imediata
        $valor_orcamento = floatval($orcamento['valor_total']);
        
        // Verificar pagamentos já realizados
        $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa 
                           WHERE orcamento_id = :orcamento_id AND tipo = 'entrada'");
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_ja_pago = floatval($pagamentos['total_pago'] ?? 0);
        
        // Guardar valores para uso no formulário
        $movimentacao['valor_total_orcamento'] = $valor_orcamento;
        $movimentacao['total_ja_pago'] = $total_ja_pago;
        $movimentacao['valor_restante'] = max(0, $valor_orcamento - $total_ja_pago);
        
        // Sugerir o valor exato restante como valor padrão para pagamento
        if ($movimentacao['valor_restante'] > 0) {
            $movimentacao['valor'] = number_format($movimentacao['valor_restante'], 2, '.', '');
        }
    }
}

// Se for editar uma movimentação existente
if ($id > 0) {
    $stmt = $db->prepare("SELECT c.*, 
                         (SELECT cl.nome FROM clientes cl WHERE cl.id = c.cliente_id) as cliente_nome
                         FROM caixa c WHERE c.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = "Editar Movimentação #{$id}";
        $modo = 'editar';
        
        if ($movimentacao['cliente_id']) {
            $cliente = ['id' => $movimentacao['cliente_id'], 'nome' => $movimentacao['cliente_nome']];
        }
        
        if ($movimentacao['orcamento_id']) {
            $stmt = $db->prepare("SELECT id, numero FROM orcamentos WHERE id = :id");
            $stmt->bindParam(':id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}

// Se for fechamento de caixa
if (isset($_GET['fechamento']) && $_GET['fechamento'] == 1) {
    // Calcular os saldos do dia
    $hoje = date('Y-m-d');
    $titulo = "Fechamento de Caixa - " . date('d/m/Y');
    $movimentacao['is_fechamento'] = true;
    $movimentacao['tipo'] = 'fechamento';
    
    // Buscar totais por forma de pagamento
    $sql = "SELECT forma_pagamento, SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END) as saldo
            FROM caixa 
            WHERE DATE(data_operacao) = :data_operacao AND tipo != 'fechamento'
            GROUP BY forma_pagamento";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':data_operacao', $hoje);
    $stmt->execute();
    $saldos_por_forma = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Calcular os saldos por forma de pagamento
    $saldo_dinheiro = isset($saldos_por_forma['dinheiro']) ? floatval($saldos_por_forma['dinheiro']) : 0;
    $saldo_cartao_credito = isset($saldos_por_forma['cartao_credito']) ? floatval($saldos_por_forma['cartao_credito']) : 0;
    $saldo_cartao_debito = isset($saldos_por_forma['cartao_debito']) ? floatval($saldos_por_forma['cartao_debito']) : 0;
    $saldo_pix = isset($saldos_por_forma['pix']) ? floatval($saldos_por_forma['pix']) : 0;
    $saldo_outros = isset($saldos_por_forma['outros']) ? floatval($saldos_por_forma['outros']) : 0;
    
    // Calcular saldo total
    $saldo_total = $saldo_dinheiro + $saldo_cartao_credito + $saldo_cartao_debito + $saldo_pix + $saldo_outros;
    
    // Preencher movimentação com esses dados
    $movimentacao['data_operacao'] = $hoje;
    $movimentacao['tipo'] = 'saida'; // Fechamento é uma saída para zerar o saldo
    $movimentacao['descricao'] = 'Fechamento de Caixa - ' . date('d/m/Y');
    $movimentacao['valor'] = $saldo_dinheiro > 0 ? $saldo_dinheiro : 0;
    $movimentacao['forma_pagamento'] = 'dinheiro';
    $movimentacao['observacoes'] = "Fechamento do caixa:\n";
    $movimentacao['observacoes'] .= "- Dinheiro: R$ " . number_format($saldo_dinheiro, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- Cartão de Crédito: R$ " . number_format($saldo_cartao_credito, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- Cartão de Débito: R$ " . number_format($saldo_cartao_debito, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- PIX: R$ " . number_format($saldo_pix, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- Outros: R$ " . number_format($saldo_outros, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "\nTotal do dia: R$ " . number_format($saldo_total, 2, ',', '.');
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturar dados do formulário
    $movimentacao = [
        'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
        'data_operacao' => $_POST['data_operacao'] ?? date('Y-m-d'),
        'tipo' => $_POST['tipo'] ?? 'entrada',
        'descricao' => limpaString($_POST['descricao'] ?? ''),
        'valor' => str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')),
        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'dinheiro',
        'orcamento_id' => !empty($_POST['orcamento_id']) ? intval($_POST['orcamento_id']) : null,
        'cliente_id' => !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null,
        'observacoes' => $_POST['observacoes'] ?? ''
    ];
    
    // Validações
    if (empty($movimentacao['descricao'])) {
        $erro = 'A descrição é obrigatória.';
    } elseif (empty($movimentacao['valor']) || !is_numeric($movimentacao['valor']) || $movimentacao['valor'] <= 0) {
        $erro = 'O valor é obrigatório e deve ser um número válido maior que zero.';
    } else {
        // Primeira parte de validação para orçamentos
        if ($movimentacao['orcamento_id'] && $movimentacao['tipo'] == 'entrada') {
            // Verificar se o pagamento ultrapassa o valor total do orçamento
            
            // Buscar valor do orçamento
            $stmt = $db->prepare("SELECT valor_total FROM orcamentos WHERE id = :orcamento_id");
            $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
            $stmt->execute();
            $orcamento_valor = $stmt->fetch(PDO::FETCH_ASSOC);
            $valor_orcamento = floatval($orcamento_valor['valor_total'] ?? 0);
            
            // Verificar pagamentos já realizados
            $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa 
                               WHERE orcamento_id = :orcamento_id AND tipo = 'entrada'");
            $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
            $stmt->execute();
            $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_ja_pago = floatval($pagamentos['total_pago'] ?? 0);
            
            // Garantir que não estamos contando duas vezes o mesmo pagamento em caso de edição
            if ($movimentacao['id'] > 0) {
                // Se for edição, descontar o valor anterior
                $stmt = $db->prepare("SELECT valor FROM caixa WHERE id = :id");
                $stmt->bindParam(':id', $movimentacao['id'], PDO::PARAM_INT);
                $stmt->execute();
                $movimento_atual = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($movimento_atual) {
                    $total_ja_pago -= floatval($movimento_atual['valor']);
                }
            }
            
            // Verificar se o valor atual + já pago ultrapassa o valor total do orçamento
            $valor_atual = floatval($movimentacao['valor']);
            $valor_total_apos_pagamento = $total_ja_pago + $valor_atual;
            
            // Arredondamos os valores para 2 casas decimais para evitar problemas de comparação com números de ponto flutuante
            $valor_total_apos_pagamento_arredondado = round($valor_total_apos_pagamento, 2);
            $valor_orcamento_arredondado = round($valor_orcamento, 2);
            
            // Tolerância mínima para considerar valores iguais (0.01 centavo)
            if ($valor_total_apos_pagamento_arredondado > $valor_orcamento_arredondado + 0.01) {
                $valor_maximo_permitido = $valor_orcamento - $total_ja_pago;
                if ($valor_maximo_permitido < 0) $valor_maximo_permitido = 0;
                $erro = "O valor de R$ " . number_format($valor_atual, 2, ',', '.') . " ultrapassa o valor restante do orçamento. O valor máximo permitido é R$ " . number_format($valor_maximo_permitido, 2, ',', '.');
            }
            
            // Guardar informações do orçamento para uso posterior
            if (empty($erro)) {
                $movimentacao['valor_total_orcamento'] = $valor_orcamento;
                $movimentacao['total_ja_pago'] = $total_ja_pago;
            }
        }
        
        // Verificar erros e processar
        if (empty($erro)) {
            try {
                // Iniciar transação para garantir integridade
                $db->beginTransaction();
                
                // Inserir ou atualizar movimentação
                if ($movimentacao['id'] > 0) {
                    // Atualizar
                    $stmt = $db->prepare("UPDATE caixa SET 
                        data_operacao = :data_operacao,
                        tipo = :tipo,
                        descricao = :descricao,
                        valor = :valor,
                        forma_pagamento = :forma_pagamento,
                        orcamento_id = :orcamento_id,
                        cliente_id = :cliente_id,
                        observacoes = :observacoes
                        WHERE id = :id");
                    $stmt->bindParam(':id', $movimentacao['id'], PDO::PARAM_INT);
                    $mensagem = 'atualizado';
                } else {
                    // Inserir
                    $stmt = $db->prepare("INSERT INTO caixa (
                        data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, cliente_id, usuario_id, observacoes
                    ) VALUES (
                        :data_operacao, :tipo, :descricao, :valor, :forma_pagamento, :orcamento_id, :cliente_id, :usuario_id, :observacoes
                    )");
                    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                    $mensagem = 'cadastrado';
                }
                
                // Bind de parâmetros
                $stmt->bindParam(':data_operacao', $movimentacao['data_operacao']);
                $stmt->bindParam(':tipo', $movimentacao['tipo']);
                $stmt->bindParam(':descricao', $movimentacao['descricao']);
                $stmt->bindParam(':valor', $movimentacao['valor']);
                $stmt->bindParam(':forma_pagamento', $movimentacao['forma_pagamento']);
                $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                $stmt->bindParam(':cliente_id', $movimentacao['cliente_id'], PDO::PARAM_INT);
                $stmt->bindParam(':observacoes', $movimentacao['observacoes']);
                
                $stmt->execute();
                
                // Se estiver vinculado a um orçamento e for uma entrada, atualizar status do pagamento
                if ($movimentacao['orcamento_id'] && $movimentacao['tipo'] == 'entrada') {
                    // Buscar valor do orçamento
                    $stmt = $db->prepare("SELECT valor_total FROM orcamentos WHERE id = :orcamento_id");
                    $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $orcamento_valor = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Verificar pagamentos já realizados
                    $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa 
                                          WHERE orcamento_id = :orcamento_id AND tipo = 'entrada'");
                    $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
                    // Calcular o total considerando o valor atual sendo registrado
                    $total_pago = floatval($pagamentos['total_pago'] ?? 0);
                    // Determinar automaticamente se é pagamento total ou parcial
                    $valor_orcamento = floatval($orcamento_valor['valor_total'] ?? 0);
                    $diferenca = abs($total_pago - $valor_orcamento);
                    
                    // Verificar valores para debug
                    $debug_info = "Orcamento ID: {$movimentacao['orcamento_id']}, Valor Total: {$valor_orcamento}, Total Pago: {$total_pago}, Diferença: {$diferenca}";
                    error_log($debug_info);
                    
                    // Determinar se é pagamento total baseado na soma
                    // Considerar como pago_total apenas quando valor pago é maior ou igual ao valor total
                    $pagamento_total = $total_pago >= $valor_orcamento;
                    
                    // Configurar status de pagamento como total ou parcial
                    $status_pagamento = $pagamento_total ? 'pago_total' : 'pago_parcial';
                    
                    $stmt = $db->prepare("UPDATE orcamentos SET 
                        status_pagamento = :status_pagamento, 
                        data_pagamento = :data_pagamento 
                        WHERE id = :orcamento_id");
                    $stmt->bindParam(':status_pagamento', $status_pagamento);
                    $stmt->bindParam(':data_pagamento', $movimentacao['data_operacao']);
                    $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                // Finalizar transação
                $db->commit();

                // Verificar se devemos redirecionar de volta para o orçamento
                if (!empty($movimentacao['orcamento_id'])) {
                    $orcamento_id_redirect = intval($movimentacao['orcamento_id']);
                    // Registrar no log para depuração
                    error_log("Redirecionando após salvar pagamento para orçamento ID: {$orcamento_id_redirect}");
                    
                    // Se o orçamento foi totalmente pago, direcionar para a vizualização do orçamento
                    $stmt = $db->prepare("SELECT status_pagamento FROM orcamentos WHERE id = :id");
                    $stmt->bindParam(':id', $orcamento_id_redirect, PDO::PARAM_INT);
                    $stmt->execute();
                    $status_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($status_data && $status_data['status_pagamento'] == 'pago_total') {
                        header("Location: orcamento_visualizar.php?id={$orcamento_id_redirect}&mensagem=pago");
                    } else {
                        // Forçar recarregamento completo para atualizar todos os valores calculados
                        header("Location: caixa_form.php?orcamento_id={$orcamento_id_redirect}&mensagem={$mensagem}&ts=".time());
                    }
                    exit;
                } elseif (isset($_POST['orcamento_id_get']) && !empty($_POST['orcamento_id_get'])) {
                    $orcamento_id_redirect = intval($_POST['orcamento_id_get']);
                    // Se o orçamento foi totalmente pago, direcionar para a vizualização do orçamento
                    $stmt = $db->prepare("SELECT status_pagamento FROM orcamentos WHERE id = :id");
                    $stmt->bindParam(':id', $orcamento_id_redirect, PDO::PARAM_INT);
                    $stmt->execute();
                    $status_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($status_data && $status_data['status_pagamento'] == 'pago_total') {
                        header("Location: orcamento_visualizar.php?id={$orcamento_id_redirect}&mensagem=pago");
                    } else {
                        // Forçar recarregamento completo para atualizar todos os valores calculados
                        header("Location: caixa_form.php?orcamento_id={$orcamento_id_redirect}&mensagem={$mensagem}&ts=".time());
                    }
                    exit;
                } else {
                    // Redirecionar para a listagem de movimentações
                    header("Location: caixa.php?mensagem={$mensagem}");
                    exit;
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $erro = 'Erro ao salvar movimentação: ' . $e->getMessage();
            }
        }
    }
} // Fecha o if do REQUEST_METHOD

// Buscar orçamentos pendentes de pagamento para vincular
$orcamentos_pendentes = [];
if ($movimentacao['id'] == 0 || $movimentacao['orcamento_id'] != null) {
    $sql = "SELECT id, numero, status, forma_pagamento, valor_total, data_criacao, 
           (SELECT nome FROM clientes WHERE id = orcamentos.cliente_id) as cliente_nome,
           status_pagamento
           FROM orcamentos 
           WHERE status = 'aprovado' AND (status_pagamento = 'pendente' OR status_pagamento = 'pago_parcial')
           OR id = :orcamento_id 
           ORDER BY data_criacao DESC";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
    $stmt->execute();
    $orcamentos_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar todos os clientes para vincular
$clientes = [];
$sql = "SELECT id, nome, telefone FROM clientes ORDER BY nome ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agora podemos incluir o header, depois de qualquer possível redirecionamento
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-money-bill-wave me-2"></i><?php echo $titulo; ?></h1>
    <a href="caixa.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-edit me-2"></i>Formulário de Movimentação
    </div>
    <div class="card-body">
        <form method="post" id="formCaixa">
            <input type="hidden" name="id" value="<?php echo $movimentacao['id']; ?>">
            <?php if ($orcamento_id > 0): ?>
            <input type="hidden" name="orcamento_id_get" value="<?php echo $orcamento_id; ?>">
            <?php if (isset($movimentacao['valor_total_orcamento'])): ?>
            <input type="hidden" name="valor_total_orcamento" value="<?php echo $movimentacao['valor_total_orcamento']; ?>">
            <?php endif; ?>
            <?php if (isset($movimentacao['total_ja_pago'])): ?>
            <input type="hidden" name="total_ja_pago" value="<?php echo $movimentacao['total_ja_pago']; ?>">
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($movimentacao['orcamento_id']) && $movimentacao['orcamento_id'] > 0 && isset($movimentacao['valor_total_orcamento'])): ?>
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <strong>Valor Total do Orçamento:</strong> R$ <?php echo number_format($movimentacao['valor_total_orcamento'], 2, ',', '.'); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <strong>Valor Já Pago:</strong> R$ <?php echo number_format($movimentacao['total_ja_pago'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <strong>Valor Restante a Pagar:</strong> R$ <?php echo number_format($movimentacao['valor_total_orcamento'] - $movimentacao['total_ja_pago'], 2, ',', '.'); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="data_operacao" class="form-label">Data da Operação</label>
                    <input type="date" class="form-control" id="data_operacao" name="data_operacao" value="<?php echo $movimentacao['data_operacao']; ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="tipo" class="form-label">Tipo de Movimentação</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="entrada" <?php echo $movimentacao['tipo'] == 'entrada' ? 'selected' : ''; ?>>Entrada</option>
                        <option value="saida" <?php echo $movimentacao['tipo'] == 'saida' ? 'selected' : ''; ?>>Saída</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?php echo htmlspecialchars($movimentacao['descricao']); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="valor" class="form-label">Valor (R$)</label>
                    <input type="text" class="form-control" id="valor" name="valor" value="<?php echo $movimentacao['valor'] ? number_format($movimentacao['valor'], 2, ',', '.') : ''; ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                    <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                        <option value="dinheiro" <?php echo $movimentacao['forma_pagamento'] == 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="cartao_credito" <?php echo $movimentacao['forma_pagamento'] == 'cartao_credito' ? 'selected' : ''; ?>>Cartão de Crédito</option>
                        <option value="cartao_debito" <?php echo $movimentacao['forma_pagamento'] == 'cartao_debito' ? 'selected' : ''; ?>>Cartão de Débito</option>
                        <option value="pix" <?php echo $movimentacao['forma_pagamento'] == 'pix' ? 'selected' : ''; ?>>PIX</option>
                        <option value="outros" <?php echo $movimentacao['forma_pagamento'] == 'outros' ? 'selected' : ''; ?>>Outros</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="orcamento_id" class="form-label">Orçamento</label>
                    <select class="form-select" id="orcamento_id" name="orcamento_id">
                        <option value="">Selecione um orçamento (opcional)</option>
                        <?php foreach ($orcamentos_pendentes as $orc): ?>
                            <option value="<?php echo $orc['id']; ?>" 
                                    <?php echo ($movimentacao['orcamento_id'] == $orc['id']) ? 'selected' : ''; ?>
                                    data-valor="<?php echo $orc['valor_total']; ?>"
                                    data-status="<?php echo $orc['status_pagamento']; ?>">
                                #<?php echo $orc['id']; ?> - <?php echo $orc['cliente_nome']; ?> - 
                                R$ <?php echo number_format($orc['valor_total'], 2, ',', '.'); ?> - 
                                <?php echo $orc['status_pagamento'] == 'pago_parcial' ? 'parcialmente pago' : 'pendente'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cliente_id" class="form-label">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id">
                        <option value="">Selecione um cliente (opcional)</option>
                        <?php foreach ($clientes as $cli): ?>
                            <option value="<?php echo $cli['id']; ?>" 
                                    <?php echo ($movimentacao['cliente_id'] == $cli['id']) ? 'selected' : ''; ?>>
                                <?php echo $cli['nome']; ?> (<?php echo $cli['telefone']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($orcamento_id > 0 && isset($orcamento) && isset($orcamento['status_pagamento']) && $orcamento['status_pagamento'] == 'pendente'): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Pagamento Total?</label>
                    <div class="form-check mt-2">
                        <input type="radio" id="pagamento_total_sim" name="pagamento_total" value="sim" class="form-check-input">
                        <label for="pagamento_total_sim" class="form-check-label">Sim, este pagamento quita o orçamento</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="pagamento_total_nao" name="pagamento_total" value="nao" class="form-check-input" checked>
                        <label for="pagamento_total_nao" class="form-check-label">Não, este é um pagamento parcial</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($movimentacao['observacoes']); ?></textarea>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formCaixa');
    const valorInput = document.getElementById('valor');
    const orcamentoSelect = document.getElementById('orcamento_id');
    const clienteSelect = document.getElementById('cliente_id');
    const tipoSelect = document.getElementById('tipo');
    const pagamentoTotalNao = document.getElementById('pagamento_total_nao');
    
    // Inicializar valores
    const orcamentoId = orcamentoSelect?.value || '';
    const clienteId = clienteSelect?.value || '';
    
    // Verificar orçamento selecionado
    function verificarOrcamento() {
        // Se tiver um orçamento selecionado e seu status for 'pendente'
        if (orcamentoId && orcamentoSelect.options[orcamentoSelect.selectedIndex].text.includes('pendente')) {
            // Preencher automaticamente o valor com o valor do orçamento
            const valorOrcamento = parseFloat(orcamentoSelect.selectedOptions[0].dataset.valor || 0);
            
            if (valorInput.value === '' && orcamentoSelect.selectedOptions[0].dataset.valor) {
                valorInput.value = valorOrcamento.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            
            // Verificar o tipo para garantir que seja entrada
            if (tipoSelect.value !== 'entrada') {
                tipoSelect.value = 'entrada';
            }
        } else {
            // Sem orçamento selecionado, sem ação específica
        }
    }
    
    // Verificar cliente selecionado
    function verificarCliente() {
        // Se tiver um cliente selecionado
        if (clienteId) {
            // Buscar as informações do cliente selecionado
            // Isso pode ser expandido para mais funcionalidades se necessário
        } else {
            // Sem cliente selecionado, sem ação específica
        }
    }
    
    // Inicializar verificações
    verificarOrcamento();
    verificarCliente();
    
    // Adicionar listeners para alterações
    orcamentoSelect?.addEventListener('change', verificarOrcamento);
    clienteSelect?.addEventListener('change', verificarCliente);
    
    // Adicionar listener para rádio de pagamento total/parcial
    pagamentoTotalNao?.addEventListener('change', function() {
        // Permitir que o usuário insira o valor desejado para pagamento parcial
        valorInput.readOnly = false;
    });
    
    document.getElementById('pagamento_total_sim')?.addEventListener('change', function() {
        // Preencher automaticamente com o valor total do orçamento
        const valorOrcamento = parseFloat(orcamentoSelect.selectedOptions[0].dataset.valor || 0);
        valorInput.value = valorOrcamento.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        valorInput.readOnly = true;
    });
    
    // Formatação do campo de valor
    valorInput?.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        
        if (valor.length === 0) {
            e.target.value = '';
            return;
        }
        
        // Converter para formato de moeda
        valor = (parseInt(valor) / 100).toFixed(2);
        e.target.value = valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });
    
    // Validação do formulário
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<?php
require_once('includes/footer.php');
?>