<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar se o caixa já está aberto
$data_hoje = date('Y-m-d');
$caixa_aberto = false;

try {
    $stmt = $db->prepare("SELECT * FROM caixa_controle WHERE data_abertura = :data_hoje AND data_fechamento IS NULL");
    $stmt->bindParam(':data_hoje', $data_hoje);
    $stmt->execute();
    $caixa_aberto = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Se a tabela não existir, o caixa não está aberto
    $caixa_aberto = false;
}

// Se o caixa já estiver aberto, redirecionar para o caixa
if ($caixa_aberto) {
    header('Location: caixa.php?mensagem=ja_aberto');
    exit;
}

// Processar formulário
$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validar campos
        $valor_inicial = isset($_POST['valor_inicial']) ? str_replace(['.', ','], ['', '.'], $_POST['valor_inicial']) : 0;
        $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';
        
        if ($valor_inicial < 0) {
            throw new Exception('O valor inicial do caixa não pode ser negativo.');
        }
        
        // Data e hora atual
        $data_hora_atual = date('Y-m-d H:i:s');
        $data_atual = date('Y-m-d');
        
        // Iniciar transação
        $db->beginTransaction();
        
        // Registrar abertura de caixa
        $stmt = $db->prepare("INSERT INTO caixa_controle 
                           (data_abertura, hora_abertura, valor_inicial, usuario_abertura_id, observacoes_abertura)
                           VALUES 
                           (:data_abertura, :hora_abertura, :valor_inicial, :usuario_id, :observacoes)");
        $stmt->bindParam(':data_abertura', $data_atual);
        $stmt->bindParam(':hora_abertura', $data_hora_atual);
        $stmt->bindParam(':valor_inicial', $valor_inicial, PDO::PARAM_STR);
        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->execute();
        
        $caixa_controle_id = $db->lastInsertId();
        
        // Registrar entrada no caixa com o valor inicial
        if ($valor_inicial > 0) {
            $stmt = $db->prepare("INSERT INTO caixa 
                               (data_operacao, tipo, descricao, valor, forma_pagamento, usuario_id, observacoes, caixa_controle_id)
                               VALUES 
                               (:data_operacao, :tipo, :descricao, :valor, :forma_pagamento, :usuario_id, :observacoes, :caixa_controle_id)");
                               
            $tipo = 'entrada';
            $descricao = 'Valor inicial para abertura do caixa';
            $forma_pagamento = 'dinheiro';
            $observacao_caixa = 'Entrada de valor para troco/operação inicial do caixa';
            
            $stmt->bindParam(':data_operacao', $data_atual);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':valor', $valor_inicial, PDO::PARAM_STR);
            $stmt->bindParam(':forma_pagamento', $forma_pagamento);
            $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $observacao_caixa);
            $stmt->bindParam(':caixa_controle_id', $caixa_controle_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Finalizar transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        header('Location: caixa.php?mensagem=aberto');
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $erro = 'Erro ao abrir o caixa: ' . $e->getMessage();
    }
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="container pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-cash-register me-2"></i>Abertura de Caixa</h1>
        <a href="caixa.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
    
    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-lock-open me-2"></i>Abertura de Caixa - <?php echo date('d/m/Y'); ?>
        </div>
        <div class="card-body">
            <form method="post" action="caixa_abrir.php">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="valor_inicial" class="form-label">Valor Inicial (R$)*</label>
                        <input type="text" class="form-control money" id="valor_inicial" name="valor_inicial" required
                               placeholder="Informe o valor inicial do caixa (troco)" value="0,00">
                        <div class="form-text">Informe o valor de dinheiro disponível para troco.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="operador" class="form-label">Operador</label>
                        <input type="text" class="form-control" id="operador" value="<?php echo $_SESSION['usuario_nome']; ?>" readonly>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" 
                              placeholder="Observações sobre a abertura do caixa (opcional)"></textarea>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-lock-open me-2"></i>Abrir Caixa
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
