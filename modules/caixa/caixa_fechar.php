<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar se o caixa está aberto
$data_hoje = date('Y-m-d');
$caixa_aberto = false;
$caixa_atual = null;

try {
    $stmt = $db->prepare("SELECT * FROM caixa_controle WHERE data_abertura = :data_hoje AND data_fechamento IS NULL");
    $stmt->bindParam(':data_hoje', $data_hoje);
    $stmt->execute();
    $caixa_aberto = $stmt->rowCount() > 0;
    
    // Se o caixa não estiver aberto, redirecionar para o caixa
    if ($caixa_aberto == false) {
        header('Location: caixa.php?mensagem=nao_aberto');
        exit;
    }
    
    $caixa_atual = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, redirecionar para o caixa com mensagem de erro
    header('Location: caixa.php?mensagem=nao_aberto');
    exit;
}

$caixa_id = $caixa_atual['id'];

// Buscar todas as movimentações do caixa atual
$stmt = $db->prepare("SELECT 
                      SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as total_entradas,
                      SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas,
                      SUM(CASE WHEN tipo = 'entrada' AND forma_pagamento = 'dinheiro' THEN valor ELSE 0 END) as dinheiro_entradas,
                      SUM(CASE WHEN tipo = 'saida' AND forma_pagamento = 'dinheiro' THEN valor ELSE 0 END) as dinheiro_saidas,
                      SUM(CASE WHEN tipo = 'entrada' AND forma_pagamento = 'cartao_credito' THEN valor ELSE 0 END) as credito_entradas,
                      SUM(CASE WHEN tipo = 'entrada' AND forma_pagamento = 'cartao_debito' THEN valor ELSE 0 END) as debito_entradas,
                      SUM(CASE WHEN tipo = 'entrada' AND forma_pagamento = 'pix' THEN valor ELSE 0 END) as pix_entradas,
                      SUM(CASE WHEN tipo = 'entrada' AND forma_pagamento = 'outro' THEN valor ELSE 0 END) as outro_entradas
                      FROM caixa 
                      WHERE caixa_controle_id = :caixa_id");
$stmt->bindParam(':caixa_id', $caixa_id, PDO::PARAM_INT);
$stmt->execute();
$totais = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular saldo de caixa
$saldo_inicial = floatval($caixa_atual['valor_inicial'] ?? 0);
$total_entradas = floatval($totais['total_entradas'] ?? 0);
$total_saidas = floatval($totais['total_saidas'] ?? 0);
$saldo_calculado = $saldo_inicial + $total_entradas - $total_saidas;

// Totais por forma de pagamento
$dinheiro_entradas = floatval($totais['dinheiro_entradas'] ?? 0);
$dinheiro_saidas = floatval($totais['dinheiro_saidas'] ?? 0);
$saldo_dinheiro = $saldo_inicial + $dinheiro_entradas - $dinheiro_saidas;

$credito_entradas = floatval($totais['credito_entradas'] ?? 0);
$debito_entradas = floatval($totais['debito_entradas'] ?? 0);
$pix_entradas = floatval($totais['pix_entradas'] ?? 0);
$outro_entradas = floatval($totais['outro_entradas'] ?? 0);

// Processar formulário
$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validar campos
        $valor_fechamento = isset($_POST['valor_fechamento']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_fechamento']) : 0;
        $valor_conferido_dinheiro = isset($_POST['valor_conferido_dinheiro']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_conferido_dinheiro']) : 0;
        $valor_conferido_credito = isset($_POST['valor_conferido_credito']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_conferido_credito']) : 0;
        $valor_conferido_debito = isset($_POST['valor_conferido_debito']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_conferido_debito']) : 0;
        $valor_conferido_pix = isset($_POST['valor_conferido_pix']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_conferido_pix']) : 0;
        $valor_conferido_outro = isset($_POST['valor_conferido_outro']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_conferido_outro']) : 0;
        $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';
        
        // Calcular a diferença
        $diferenca_dinheiro = $valor_conferido_dinheiro - $saldo_dinheiro;
        $diferenca_credito = $valor_conferido_credito - $credito_entradas;
        $diferenca_debito = $valor_conferido_debito - $debito_entradas;
        $diferenca_pix = $valor_conferido_pix - $pix_entradas;
        $diferenca_outro = $valor_conferido_outro - $outro_entradas;
        
        $diferenca_total = $diferenca_dinheiro + $diferenca_credito + $diferenca_debito + $diferenca_pix + $diferenca_outro;
        
        // Data e hora atual
        $data_hora_atual = date('Y-m-d H:i:s');
        $data_atual = date('Y-m-d');
        
        // Iniciar transação
        $db->beginTransaction();
        
        // Registrar fechamento de caixa
        $stmt = $db->prepare("UPDATE caixa_controle SET 
                            data_fechamento = :data_fechamento, 
                            hora_fechamento = :hora_fechamento, 
                            valor_final = :valor_final,
                            valor_conferido_dinheiro = :valor_conferido_dinheiro,
                            valor_conferido_credito = :valor_conferido_credito,
                            valor_conferido_debito = :valor_conferido_debito,
                            valor_conferido_pix = :valor_conferido_pix,
                            valor_conferido_outro = :valor_conferido_outro,
                            diferenca = :diferenca,
                            usuario_fechamento_id = :usuario_id, 
                            observacoes_fechamento = :observacoes
                            WHERE id = :id");
                            
        $stmt->bindParam(':data_fechamento', $data_atual);
        $stmt->bindParam(':hora_fechamento', $data_hora_atual);
        $stmt->bindParam(':valor_final', $saldo_calculado, PDO::PARAM_STR);
        $stmt->bindParam(':valor_conferido_dinheiro', $valor_conferido_dinheiro, PDO::PARAM_STR);
        $stmt->bindParam(':valor_conferido_credito', $valor_conferido_credito, PDO::PARAM_STR);
        $stmt->bindParam(':valor_conferido_debito', $valor_conferido_debito, PDO::PARAM_STR);
        $stmt->bindParam(':valor_conferido_pix', $valor_conferido_pix, PDO::PARAM_STR);
        $stmt->bindParam(':valor_conferido_outro', $valor_conferido_outro, PDO::PARAM_STR);
        $stmt->bindParam(':diferenca', $diferenca_total, PDO::PARAM_STR);
        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':id', $caixa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar sangria final do caixa se tiver valor em dinheiro
        if ($valor_conferido_dinheiro > 0) {
            $stmt = $db->prepare("INSERT INTO caixa 
                               (data_operacao, tipo, descricao, valor, forma_pagamento, usuario_id, observacoes, caixa_controle_id)
                               VALUES 
                               (:data_operacao, :tipo, :descricao, :valor, :forma_pagamento, :usuario_id, :observacoes, :caixa_controle_id)");
                               
            $tipo = 'saida';
            $descricao = 'Sangria para fechamento do caixa';
            $forma_pagamento = 'dinheiro';
            $observacao_caixa = 'Retirada do valor em dinheiro no fechamento do caixa';
            
            $stmt->bindParam(':data_operacao', $data_atual);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':valor', $valor_conferido_dinheiro, PDO::PARAM_STR);
            $stmt->bindParam(':forma_pagamento', $forma_pagamento);
            $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $observacao_caixa);
            $stmt->bindParam(':caixa_controle_id', $caixa_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Finalizar transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        header('Location: caixa.php?mensagem=fechado');
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $erro = 'Erro ao fechar o caixa: ' . $e->getMessage();
    }
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="container pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-cash-register me-2"></i>Fechamento de Caixa</h1>
        <a href="caixa.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
    
    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-lock me-2"></i>Fechamento de Caixa - <?php echo date('d/m/Y'); ?>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="card-title">Saldo Inicial</h6>
                            <h4 class="mb-0"><?php echo formataValor($saldo_inicial); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Entradas</h6>
                            <h4 class="mb-0"><?php echo formataValor($total_entradas); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Saídas</h6>
                            <h4 class="mb-0"><?php echo formataValor($total_saidas); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Saldo Final</h6>
                            <h4 class="mb-0"><?php echo formataValor($saldo_calculado); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Resumo por Forma de Pagamento</h5>
                </div>
                <div class="col-md-6 col-lg-4 mt-3">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">Dinheiro</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>Saldo Inicial:</td>
                                    <td class="text-end"><?php echo formataValor($saldo_inicial); ?></td>
                                </tr>
                                <tr>
                                    <td>Entradas:</td>
                                    <td class="text-end text-success">+ <?php echo formataValor($dinheiro_entradas); ?></td>
                                </tr>
                                <tr>
                                    <td>Saídas:</td>
                                    <td class="text-end text-danger">- <?php echo formataValor($dinheiro_saidas); ?></td>
                                </tr>
                                <tr>
                                    <th>Saldo Dinheiro:</th>
                                    <th class="text-end"><?php echo formataValor($saldo_dinheiro); ?></th>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mt-3">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">Cartão</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>Cartão de Crédito:</td>
                                    <td class="text-end text-success"><?php echo formataValor($credito_entradas); ?></td>
                                </tr>
                                <tr>
                                    <td>Cartão de Débito:</td>
                                    <td class="text-end text-success"><?php echo formataValor($debito_entradas); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Cartões:</th>
                                    <th class="text-end"><?php echo formataValor($credito_entradas + $debito_entradas); ?></th>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mt-3">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">Outros Pagamentos</div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>PIX:</td>
                                    <td class="text-end text-success"><?php echo formataValor($pix_entradas); ?></td>
                                </tr>
                                <tr>
                                    <td>Outros:</td>
                                    <td class="text-end text-success"><?php echo formataValor($outro_entradas); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Outros:</th>
                                    <th class="text-end"><?php echo formataValor($pix_entradas + $outro_entradas); ?></th>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="caixa_fechar.php">
                <div class="row mb-3">
                    <div class="col-12">
                        <h5 class="border-bottom pb-2">Conferência de Valores</h5>
                        <p class="text-muted">Informe os valores conferidos fisicamente:</p>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="valor_conferido_dinheiro" class="form-label">Valor em Dinheiro Conferido*</label>
                            <input type="text" class="form-control money" id="valor_conferido_dinheiro" name="valor_conferido_dinheiro" required
                                   value="<?php echo number_format($saldo_dinheiro, 2, ',', '.'); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="valor_conferido_credito" class="form-label">Valor em Cartão de Crédito Conferido*</label>
                            <input type="text" class="form-control money" id="valor_conferido_credito" name="valor_conferido_credito" required
                                   value="<?php echo number_format($credito_entradas, 2, ',', '.'); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="valor_conferido_debito" class="form-label">Valor em Cartão de Débito Conferido*</label>
                            <input type="text" class="form-control money" id="valor_conferido_debito" name="valor_conferido_debito" required
                                   value="<?php echo number_format($debito_entradas, 2, ',', '.'); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="valor_conferido_pix" class="form-label">Valor em PIX Conferido*</label>
                            <input type="text" class="form-control money" id="valor_conferido_pix" name="valor_conferido_pix" required
                                   value="<?php echo number_format($pix_entradas, 2, ',', '.'); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="valor_conferido_outro" class="form-label">Valor em Outros Meios Conferido*</label>
                            <input type="text" class="form-control money" id="valor_conferido_outro" name="valor_conferido_outro" required
                                   value="<?php echo number_format($outro_entradas, 2, ',', '.'); ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="operador" class="form-label">Operador</label>
                            <input type="text" class="form-control" id="operador" value="<?php echo $_SESSION['usuario_nome']; ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" 
                              placeholder="Observações sobre o fechamento do caixa (opcional)"></textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja fechar o caixa?');">
                        <i class="fas fa-lock me-2"></i>Fechar Caixa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar máscaras para campos monetários se a biblioteca estiver disponível
    if (typeof IMask !== 'undefined') {
        const moneyMasks = document.querySelectorAll('.money');
        moneyMasks.forEach(function(el) {
            IMask(el, {
                mask: Number,
                scale: 2,
                signed: false,
                thousandsSeparator: '.',
                padFractionalZeros: true,
                normalizeZeros: true,
                radix: ','
            });
        });
    }
});
</script>

<?php require_once('includes/footer.php'); ?>
