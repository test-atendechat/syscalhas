<?php
/**
 * Script para adicionar notificações relacionadas a pagamentos
 */

require_once(__DIR__ . '/includes/notificacoes.php');
require_once(__DIR__ . '/notificacao_orcamento.php');

/**
 * Gera notificações para pagamentos de orçamentos
 * 
 * @param int $orcamento_id ID do orçamento
 * @param float $valor Valor do pagamento
 * @param string $metodo Método de pagamento (dinheiro, pix, cartão, etc)
 * @param string $status Status do pagamento (total ou parcial)
 * @return bool Sucesso ou falha
 */
function notificarPagamento($orcamento_id, $valor, $metodo = 'dinheiro', $status = 'parcial') {
    // Usar a função especializada do arquivo notificacao_orcamento.php
    return notificarPagamentoOrcamento($orcamento_id, $valor, $status);
}

/**
 * Notifica sobre estorno de pagamentos
 * 
 * @param int $orcamento_id ID do orçamento
 * @param float $valor Valor do estorno
 * @param int $pagamento_id ID do pagamento estornado
 * @return bool Sucesso ou falha
 */
function notificarEstorno($orcamento_id, $valor, $pagamento_id) {
    global $pdo;
    
    // Buscar dados do orçamento
    $stmt = $pdo->prepare("SELECT o.numero, o.valor_total, c.nome as cliente_nome
                       FROM orcamentos o
                       JOIN clientes c ON c.id = o.cliente_id
                       WHERE o.id = :orcamento_id");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orcamento) {
        return false;
    }
    
    $valor_formatado = number_format($valor, 2, ',', '.');
    $link = "orcamento_visualizar.php?id={$orcamento_id}";
    
    $mensagem = "ESTORNO: R$ {$valor_formatado} do orçamento #{$orcamento['numero']} - Cliente: {$orcamento['cliente_nome']}";
    
    // Adicionar notificação categorizada sem som
    return adicionarNotificacao($mensagem, 'danger', $link, 'pagamentos');
}