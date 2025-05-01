<?php
/**
 * Sistema de notificações em tempo real
 * Este arquivo gerencia todas as funções relacionadas a notificações
 */

/**
 * Cria uma nova notificação no sistema
 * 
 * @param string $mensagem Mensagem da notificação
 * @param string $tipo Tipo (info, warning, success, danger)
 * @param string $link Link para onde a notificação deve direcionar (opcional)
 * @param int $destinatario_id ID do usuário destinatário, null para todos
 * @return int ID da notificação criada
 */
function criarNotificacao($mensagem, $tipo = 'info', $link = null, $destinatario_id = null) {
    global $db;
    
    // Obter o usuário atual (autor da notificação)
    $autor_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : null;
    
    // Inserir a notificação
    $stmt = $db->prepare("INSERT INTO notificacoes (mensagem, tipo, link, data_criacao, autor_id, destinatario_id) 
                          VALUES (:mensagem, :tipo, :link, NOW(), :autor_id, :destinatario_id)");
                          
    $stmt->bindParam(':mensagem', $mensagem);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':link', $link);
    $stmt->bindParam(':autor_id', $autor_id, PDO::PARAM_INT);
    $stmt->bindParam(':destinatario_id', $destinatario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $db->lastInsertId();
}

/**
 * Marcar uma notificação como lida
 * 
 * @param int $notificacao_id ID da notificação
 * @param int $usuario_id ID do usuário que leu a notificação
 * @return bool
 */
function marcarNotificacaoComoLida($notificacao_id, $usuario_id = null) {
    global $db;
    
    // Se não foi especificado um usuário, usa o usuário atual
    if ($usuario_id === null && isset($_SESSION['usuario']['id'])) {
        $usuario_id = $_SESSION['usuario']['id'];
    }
    
    // Verifica se a leitura já está registrada
    $stmt = $db->prepare("SELECT id FROM notificacoes_lidas 
                          WHERE notificacao_id = :notificacao_id 
                          AND usuario_id = :usuario_id");
    $stmt->bindParam(':notificacao_id', $notificacao_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Já foi marcada como lida
        return true;
    }
    
    // Registrar a leitura
    $stmt = $db->prepare("INSERT INTO notificacoes_lidas (notificacao_id, usuario_id, data_leitura) 
                          VALUES (:notificacao_id, :usuario_id, NOW())");
    $stmt->bindParam(':notificacao_id', $notificacao_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Marcar todas as notificações de um usuário como lidas
 * 
 * @param int $usuario_id ID do usuário
 * @return bool
 */
function marcarTodasNotificacoesComoLidas($usuario_id = null) {
    global $db;
    
    // Se não foi especificado um usuário, usa o usuário atual
    if ($usuario_id === null && isset($_SESSION['usuario']['id'])) {
        $usuario_id = $_SESSION['usuario']['id'];
    }
    
    // Buscar todas as notificações não lidas para este usuário
    $stmt = $db->prepare("SELECT n.id FROM notificacoes n 
                          LEFT JOIN notificacoes_lidas nl ON n.id = nl.notificacao_id AND nl.usuario_id = :usuario_id 
                          WHERE nl.id IS NULL 
                          AND (n.destinatario_id IS NULL OR n.destinatario_id = :usuario_id2)");
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $notificacoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($notificacoes)) {
        return true; // Não há notificações não lidas
    }
    
    // Preparar a consulta para inserção em lote
    $values = [];
    $params = [];
    $i = 0;
    
    foreach ($notificacoes as $notificacao_id) {
        $notif_param = ':notificacao' . $i;
        $values[] = "($notif_param, :usuario_id, NOW())"; 
        $params[$notif_param] = $notificacao_id;
        $i++;
    }
    
    $stmt = $db->prepare("INSERT INTO notificacoes_lidas (notificacao_id, usuario_id, data_leitura) 
                          VALUES " . implode(", ", $values));
    
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_INT);
    }
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Obter notificações do usuário atual
 * 
 * @param int $limite Limite de notificações a serem retornadas
 * @param bool $apenas_nao_lidas Retorna apenas notificações não lidas
 * @return array Array com as notificações
 */
function obterNotificacoes($limite = 10, $apenas_nao_lidas = true) {
    global $db;
    
    $usuario_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : 0;
    
    // Consulta base
    $sql = "SELECT n.id, n.mensagem, n.tipo, n.link, n.data_criacao, 
                  u.nome as autor_nome, 
                  CASE WHEN nl.id IS NULL THEN 0 ELSE 1 END as lida
           FROM notificacoes n
           LEFT JOIN usuarios u ON n.autor_id = u.id
           LEFT JOIN notificacoes_lidas nl ON n.id = nl.notificacao_id AND nl.usuario_id = :usuario_id
           WHERE (n.destinatario_id IS NULL OR n.destinatario_id = :usuario_id2)";
    
    // Adicionar filtro de não lidas se necessário
    if ($apenas_nao_lidas) {
        $sql .= " AND nl.id IS NULL";
    }
    
    // Ordenar e limitar
    $sql .= " ORDER BY n.data_criacao DESC LIMIT :limite";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Contar notificações não lidas para o usuário atual
 * 
 * @return int Número de notificações não lidas
 */
function contarNotificacoesNaoLidas() {
    global $db;
    
    $usuario_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notificacoes n 
                          LEFT JOIN notificacoes_lidas nl ON n.id = nl.notificacao_id AND nl.usuario_id = :usuario_id 
                          WHERE nl.id IS NULL 
                          AND (n.destinatario_id IS NULL OR n.destinatario_id = :usuario_id2)");
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id2', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return intval($resultado['total']);
}

/**
 * Criar tabelas de notificações se não existirem
 */
function criarTabelasNotificacoes() {
    global $db;
    
    // Tabela de notificações
    $db->exec("CREATE TABLE IF NOT EXISTS notificacoes (
        id SERIAL PRIMARY KEY,
        mensagem TEXT NOT NULL,
        tipo VARCHAR(20) NOT NULL DEFAULT 'info',
        link TEXT,
        data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        autor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
        destinatario_id INTEGER REFERENCES usuarios(id) ON DELETE CASCADE
    )");
    
    // Tabela de notificações lidas
    $db->exec("CREATE TABLE IF NOT EXISTS notificacoes_lidas (
        id SERIAL PRIMARY KEY,
        notificacao_id INTEGER NOT NULL REFERENCES notificacoes(id) ON DELETE CASCADE,
        usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
        data_leitura TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(notificacao_id, usuario_id)
    )");
}
?>