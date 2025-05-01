<?php
// Função para verificar e executar a atualização automática de status
function verificarAtualizacaoAutomatica() {
    global $db, $pdo;
    
    try {
        // Verificar se a tabela de configurações existe
        $stmt = $pdo->prepare("SELECT to_regclass('configuracoes')");
        $stmt->execute();
        $existe_tabela = $stmt->fetchColumn();
        
        if (!$existe_tabela) {
            return false; // Tabela ainda não existe
        }
        
        // Verificar se a configuração existe
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'atualizacao_automatica_status'");
        $stmt->execute();
        $config = $stmt->fetchColumn();
        
        if (!$config) {
            return false; // Configuração não encontrada
        }
        
        $config = json_decode($config, true);
        
        // Verificar se está ativo
        if (!isset($config['ativo']) || !$config['ativo']) {
            return false; // Não está ativado
        }
        
        // Verificar a última execução
        $ultima_execucao = isset($config['ultima_execucao']) ? strtotime($config['ultima_execucao']) : 0;
        $intervalo = isset($config['intervalo']) ? intval($config['intervalo']) : 5; // Padrão 5 minutos
        $intervalo_segundos = $intervalo * 60;
        
        $tempo_atual = time();
        
        // Se o intervalo desde a última execução for maior que o intervalo configurado, executar novamente
        if (($tempo_atual - $ultima_execucao) >= $intervalo_segundos) {
            // Atualizar a data da última execução
            $nova_config = $config;
            $nova_config['ultima_execucao'] = date('Y-m-d H:i:s');
            $valor_json = json_encode($nova_config);
            
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor = :valor WHERE chave = 'atualizacao_automatica_status'");
            $stmt->bindParam(':valor', $valor_json);
            $stmt->execute();
            
            // Executar a atualização de status
            require_once dirname(__DIR__) . '/atualizar_status_agendamentos.php';
            return true;
        }
    } catch (Exception $e) {
        // Se houver qualquer erro, ignora e continua
        return false;
    }
    
    return false;
}

// Verificar se deve executar a atualização automática (apenas uma chance em cada 5 acessos)
// A função será chamada após a inclusão de db.php em cada página
$verificar_atualizacao_automatica = (rand(1, 5) == 1);
?>