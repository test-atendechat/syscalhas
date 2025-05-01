<?php
/**
 * Funções para gerenciamento de notificações do sistema
 */

/**
 * Adiciona uma nova notificação no sistema
 * 
 * @param string $mensagem Texto da notificação
 * @param string $tipo Tipo de notificação (info, success, warning, danger)
 * @param string|null $link Link opcional para direcionar ao clicar
 * @param string $categoria Categoria da notificação (orcamentos, caixa, agendamentos, etc)
 * @param int|null $destinatario_id ID do usuário destinatário (null = todos)
 * @return int|bool ID da notificação adicionada ou false em caso de erro
 */
function adicionarNotificacao($mensagem, $tipo = 'info', $link = null, $categoria = 'geral', $destinatario_id = null) {
    global $db;
    
    // Verificar usuário atual como autor
    $autor_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : null;
    
    try {
        $query = "INSERT INTO notificacoes (mensagem, tipo, link, categoria, autor_id, destinatario_id) "
              . "VALUES (:mensagem, :tipo, :link, :categoria, :autor_id, :destinatario_id)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mensagem', $mensagem);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':link', $link);
        $stmt->bindParam(':categoria', $categoria);
        $stmt->bindParam(':autor_id', $autor_id);
        $stmt->bindParam(':destinatario_id', $destinatario_id);
        
        $stmt->execute();
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        // Em caso de falha, registrar erro e retornar false
        error_log("Erro ao adicionar notificação: " . $e->getMessage());
        return false;
    }
}

/**
 * Marcar uma notificação como lida para o usuário atual
 * 
 * @param int $notificacao_id ID da notificação
 * @return bool Sucesso ou falha
 */
function marcarNotificacaoComoLida($notificacao_id) {
    global $db;
    
    // Verificar usuário atual
    $usuario_id = $_SESSION['usuario']['id'];
    
    try {
        // Verificar se já está marcada como lida
        $verificar = $db->prepare("SELECT id FROM notificacoes_lidas WHERE notificacao_id = :notificacao_id AND usuario_id = :usuario_id");
        $verificar->bindParam(':notificacao_id', $notificacao_id);
        $verificar->bindParam(':usuario_id', $usuario_id);
        $verificar->execute();
        
        if ($verificar->rowCount() > 0) {
            // Já está marcada como lida
            return true;
        }
        
        // Marcar como lida
        $stmt = $db->prepare("INSERT INTO notificacoes_lidas (notificacao_id, usuario_id) VALUES (:notificacao_id, :usuario_id)");
        $stmt->bindParam(':notificacao_id', $notificacao_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter notificações para o usuário atual
 * 
 * @param int $limite Número máximo de notificações (0 = sem limite)
 * @param bool $apenas_nao_lidas Retornar apenas notificações não lidas
 * @return array Array com as notificações
 */
function obterNotificacoes($limite = 0, $apenas_nao_lidas = false) {
    global $db;
    
    // Verificar usuário atual
    $usuario_id = $_SESSION['usuario']['id'];
    
    try {
        // Construir a consulta base
        $sql = "SELECT n.*, 
                CASE WHEN nl.id IS NOT NULL THEN 1 ELSE 0 END AS lida 
                FROM notificacoes n 
                LEFT JOIN notificacoes_lidas nl ON n.id = nl.notificacao_id AND nl.usuario_id = :usuario_id 
                WHERE (n.destinatario_id IS NULL OR n.destinatario_id = :usuario_id2) ";
        
        // Adicionar filtro para apenas não lidas se solicitado
        if ($apenas_nao_lidas) {
            $sql .= "AND nl.id IS NULL ";
        }
        
        // Ordenar por data mais recente
        $sql .= "ORDER BY n.data_criacao DESC ";
        
        // Adicionar limite se especificado
        if ($limite > 0) {
            $sql .= "LIMIT :limite";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':usuario_id2', $usuario_id);
        
        if ($limite > 0) {
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter notificações: " . $e->getMessage());
        return [];
    }
}

/**
 * Obter notificações não lidas para o usuário atual
 * 
 * @return array Array com as notificações não lidas
 */
function obterNotificacoesNaoLidas() {
    return obterNotificacoes(0, true);
}

/**
 * Obter notificações por categoria para o usuário atual
 * 
 * @param string $categoria Categoria das notificações a serem obtidas
 * @param int $limite Número máximo de notificações (0 = sem limite)
 * @param bool $apenas_nao_lidas Retornar apenas notificações não lidas
 * @return array Array com as notificações da categoria especificada
 */
function obterNotificacoesPorCategoria($categoria, $limite = 0, $apenas_nao_lidas = false) {
    global $db;
    
    // Verificar usuário atual
    $usuario_id = $_SESSION['usuario']['id'];
    
    try {
        // Construir a consulta base
        $sql = "SELECT n.*, 
                CASE WHEN nl.id IS NOT NULL THEN 1 ELSE 0 END AS lida 
                FROM notificacoes n 
                LEFT JOIN notificacoes_lidas nl ON n.id = nl.notificacao_id AND nl.usuario_id = :usuario_id 
                WHERE (n.destinatario_id IS NULL OR n.destinatario_id = :usuario_id2) 
                AND n.categoria = :categoria ";
        
        // Adicionar filtro para apenas não lidas se solicitado
        if ($apenas_nao_lidas) {
            $sql .= "AND nl.id IS NULL ";
        }
        
        // Ordenar por data mais recente
        $sql .= "ORDER BY n.data_criacao DESC ";
        
        // Adicionar limite se especificado
        if ($limite > 0) {
            $sql .= "LIMIT :limite";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':usuario_id2', $usuario_id);
        $stmt->bindParam(':categoria', $categoria);
        
        if ($limite > 0) {
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter notificações por categoria: " . $e->getMessage());
        return [];
    }
}

/**
 * Marcar todas as notificações como lidas para o usuário atual
 * 
 * @return bool Sucesso ou falha
 */
function marcarTodasNotificacoesComoLidas() {
    global $db;
    
    // Verificar usuário atual
    $usuario_id = $_SESSION['usuario']['id'];
    
    try {
        // Obter todas as notificações não lidas para o usuário
        $notificacoes = obterNotificacoesNaoLidas();
        
        if (empty($notificacoes)) {
            return true; // Não há notificações para marcar
        }
        
        // Iniciar transação
        $db->beginTransaction();
        
        // Marcar cada notificação como lida
        $stmt = $db->prepare("INSERT INTO notificacoes_lidas (notificacao_id, usuario_id) VALUES (:notificacao_id, :usuario_id)");
        
        foreach ($notificacoes as $notificacao) {
            $stmt->bindParam(':notificacao_id', $notificacao['id']);
            $stmt->bindParam(':usuario_id', $usuario_id);
            $stmt->execute();
        }
        
        // Confirmar transação
        return $db->commit();
    } catch (PDOException $e) {
        // Reverter em caso de erro
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Erro ao marcar todas notificações como lidas: " . $e->getMessage());
        return false;
    }
}

/**
 * Contar notificações não lidas para o usuário atual
 * 
 * @return int Quantidade de notificações não lidas
 */
function contarNotificacoesNaoLidas() {
    global $db;
    
    // Verificar usuário atual
    $usuario_id = $_SESSION['usuario']['id'];
    
    try {
        $sql = "SELECT COUNT(*) FROM notificacoes n 
                LEFT JOIN notificacoes_lidas nl ON n.id = nl.notificacao_id AND nl.usuario_id = :usuario_id 
                WHERE (n.destinatario_id IS NULL OR n.destinatario_id = :usuario_id2) 
                AND nl.id IS NULL";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':usuario_id2', $usuario_id);
        $stmt->execute();
        
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro ao contar notificações não lidas: " . $e->getMessage());
        return 0;
    }
}

/**
 * Excluir notificações antigas (mais de 30 dias)
 * 
 * @param int $dias_manter Dias para manter as notificações (padrão: 30)
 * @return bool Sucesso ou falha
 */
function limparNotificacoesAntigas($dias_manter = 30) {
    global $db;
    
    try {
        $sql = "DELETE FROM notificacoes WHERE data_criacao < NOW() - INTERVAL :dias DAY";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':dias', $dias_manter, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao limpar notificações antigas: " . $e->getMessage());
        return false;
    }
}
