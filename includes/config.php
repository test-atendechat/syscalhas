<?php
// Configurações do sistema
define('APP_NAME', 'Grupo Sandro Calhas LTDA');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/');

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
define('DB_TYPE', 'pgsql'); // mysql ou pgsql

// Verifica se o arquivo de configuração local existe
$config_local = __DIR__ . '/config.local.php';
if (file_exists($config_local)) {
    // Se existir, inclui o arquivo de configuração local
    include($config_local);
} else {
    // Se não existir, usa as variáveis de ambiente ou valores padrão
    define('DB_HOST', getenv('PGHOST') ?: 'localhost');
    define('DB_NAME', getenv('PGDATABASE') ?: 'postgres');
    define('DB_USER', getenv('PGUSER') ?: 'postgres');
    define('DB_PASS', getenv('PGPASSWORD') ?: 'postgres');
    define('DB_PORT', getenv('PGPORT') ?: '5432');
}

// Configurações de data e hora
date_default_timezone_set('America/Sao_Paulo');

// Verifica se a atualização automática de status está ativada
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

// Configurações de exibição de erros (desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>