<?php
/**
 * Script para adicionar notificações relacionadas a orçamentos
 */

require_once('includes/notificacoes.php');

/**
 * Gera notificações para um orçamento específico
 * 
 * @param int $orcamento_id ID do orçamento
 * @param string $acao Ação realizada (visualizar, editar, aprovar, etc)
 * @param array $dados_adicionais Dados adicionais para a notificação
 * @return bool Sucesso ou falha
 */
function notificarOrcamento($orcamento_id, $acao = 'visualizar', $dados_adicionais = []) {
    global $db;
    
    // Verificar se o orçamento existe
    $stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome FROM orcamentos o 
                        LEFT JOIN clientes c ON o.cliente_id = c.id
                        WHERE o.id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        error_log("Erro: Orçamento ID {$orcamento_id} não encontrado para notificação");
        return false;
    }
    
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    $valor_formatado = 'R$ ' . number_format($orcamento['valor_total'], 2, ',', '.');
    $link = "orcamento_visualizar.php?id={$orcamento_id}";
    
    // Definir tipo de notificação baseado no status e ação
    $tipo = 'info';
    $mensagem = "";
    
    switch ($acao) {
        case 'visualizar':
            $mensagem = "Orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']} foi visualizado";
            break;
            
        case 'editar':
            $tipo = 'warning';
            $mensagem = "Orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']} foi editado";
            break;
            
        case 'aprovar':
            $tipo = 'success';
            $mensagem = "Orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']} foi APROVADO - {$valor_formatado}";
            break;
            
        case 'rejeitar':
            $tipo = 'danger';
            $mensagem = "Orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']} foi REJEITADO";
            break;
            
        case 'vencido':
            $tipo = 'warning';
            $mensagem = "ATENÇÃO: Orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']} está VENCIDO";
            break;
            
        default:
            $mensagem = "Atualização no orçamento #{$orcamento['numero']} para {$orcamento['cliente_nome']}";
            break;
    }
    
    // Adicionar notificação
    return adicionarNotificacao($mensagem, $tipo, $link);
}
