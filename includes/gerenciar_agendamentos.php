<?php
/**
 * Funções para gerenciar agendamentos
 */

/**
 * Verifica e gerencia agendamentos com base no status do orçamento
 * Esta função irá excluir agendamentos quando os status não fizerem mais sentido
 * 
 * @param int $orcamento_id ID do orçamento
 * @param string $novo_status Novo status do orçamento
 * @return bool|string True se tudo ocorrer bem, ou mensagem de erro
 */
function gerenciarAgendamentosStatus($orcamento_id, $novo_status) {
    global $pdo;
    
    try {
        // 1. Verificar status que devem ter agendamentos
        $manter_agendamento = in_array($novo_status, ['orcamento_agendado', 'instalacao_agendada', 'pendente']);
        
        // 2. Se o status novo não requer agendamento, excluir todos os agendamentos existentes
        if (!$manter_agendamento) {
            $stmt_delete = $pdo->prepare("DELETE FROM agendamentos WHERE orcamento_id = :orcamento_id");
            $stmt_delete->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt_delete->execute();
            return true;
        }
        
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
