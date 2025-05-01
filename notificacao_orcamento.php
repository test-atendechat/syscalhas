<?php
/**
 * Script para adicionar notificações relacionadas a orçamentos
 */

require_once(__DIR__ . '/includes/notificacoes.php');

/**
 * Gera notificações para orçamentos com áudio personalizado
 * 
 * @param int $orcamento_id ID do orçamento
 * @param string $tipo Tipo de notificação (aprovar, rejeitar, finalizado, andamento, pendente, etc)
 * @param array $dados_adicionais Dados adicionais para a notificação
 * @return bool Sucesso ou falha
 */
function notificarOrcamento($orcamento_id, $tipo, $dados_adicionais = []) {
    global $pdo;
    
    // Buscar dados do orçamento se não foram fornecidos
    if (!isset($dados_adicionais['cliente_nome']) || !isset($dados_adicionais['numero'])) {
        $stmt = $pdo->prepare("SELECT o.numero, o.valor_total, c.nome as cliente_nome
                           FROM orcamentos o
                           JOIN clientes c ON c.id = o.cliente_id
                           WHERE o.id = :orcamento_id");
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->execute();
        $orcamento_dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Mesclar dados obtidos com os fornecidos
        if ($orcamento_dados) {
            $dados_adicionais = array_merge($orcamento_dados, $dados_adicionais);
        }
    }
    
    $link = "orcamento_visualizar.php?id={$orcamento_id}";
    $tipo_notificacao = 'info';
    
    // Definir mensagem e tipo de notificação com base no tipo de evento
    switch ($tipo) {
        case 'aprovar':
            $mensagem = "ORÇAMENTO APROVADO: #{$dados_adicionais['numero']} para {$dados_adicionais['cliente_nome']} no valor de R$ " . number_format($dados_adicionais['valor_total'], 2, ',', '.');
            $tipo_notificacao = 'success';
            break;
            
        case 'rejeitar':
            $mensagem = "ORÇAMENTO REJEITADO: #{$dados_adicionais['numero']} para {$dados_adicionais['cliente_nome']}";
            $tipo_notificacao = 'danger';
            break;
            
        case 'finalizado':
            $mensagem = "SERVIÇO FINALIZADO: Orçamento #{$dados_adicionais['numero']} para {$dados_adicionais['cliente_nome']} foi concluído";
            $tipo_notificacao = 'success';
            break;
            
        case 'andamento':
            $mensagem = "SERVIÇO EM ANDAMENTO: Orçamento #{$dados_adicionais['numero']} para {$dados_adicionais['cliente_nome']} entrou em execução";
            $tipo_notificacao = 'primary';
            break;
            
        case 'agendado':
            $mensagem = "SERVIÇO AGENDADO: Orçamento #{$dados_adicionais['numero']} para {$dados_adicionais['cliente_nome']} foi agendado";
            $tipo_notificacao = 'info';
            break;
            
        case 'pagamento':
            $valor = $dados_adicionais['valor'] ?? 0;
            $valor_formatado = number_format($valor, 2, ',', '.');
            $mensagem = "PAGAMENTO REGISTRADO: R$ {$valor_formatado} para Orçamento #{$dados_adicionais['numero']}";
            $tipo_notificacao = 'success';
            break;
            
        default:
            $mensagem = "ORÇAMENTO ATUALIZADO: #{$dados_adicionais['numero']} para {$dados_adicionais['cliente_nome']}";
            break;
    }
    
    // Adicionar notificação (sem som)
    return adicionarNotificacao($mensagem, $tipo_notificacao, $link);
}

/**
 * Notifica sobre pagamentos em orçamentos
 * 
 * @param int $orcamento_id ID do orçamento
 * @param float $valor Valor do pagamento
 * @param string $tipo Tipo de pagamento ('total', 'parcial')
 * @return bool Sucesso ou falha
 */
function notificarPagamentoOrcamento($orcamento_id, $valor, $tipo = 'parcial') {
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
    
    if ($tipo == 'total') {
        $mensagem = "PAGAMENTO TOTAL: Orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']} no valor de R$ {$valor_formatado}";
    } else {
        $mensagem = "PAGAMENTO PARCIAL: R$ {$valor_formatado} para Orçamento #{$orcamento['numero']} - Cliente: {$orcamento['cliente_nome']}";
    }
    
    // Adicionar notificação (sem som)
    return adicionarNotificacao($mensagem, 'success', $link);
}