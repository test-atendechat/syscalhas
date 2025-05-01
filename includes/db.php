<?php
require_once('config.php');

try {
    // Construir DSN de acordo com o tipo de banco de dados
    if (DB_TYPE == 'mysql') {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
    } else if (DB_TYPE == 'pgsql') {
        $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
    } else {
        throw new Exception("Tipo de banco de dados não suportado");
    }

    // Opções PDO
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ];

    // Criar conexão
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Define $pdo como alias para $db para compatibilidade com código existente
    $pdo = $db;
    
    // Importar a funcionalidade de atualização automática
    require_once('atualizacao_automatica.php');
    
    // Importar o sincronizador de configurações
    require_once('sincronizador_config.php');
    
    // Verificar se deve executar a atualização automática
    if (isset($verificar_atualizacao_automatica) && $verificar_atualizacao_automatica && function_exists('verificarAtualizacaoAutomatica')) {
        try {
            verificarAtualizacaoAutomatica();
        } catch (Exception $e) {
            // Silencia erros para não atrapalhar o uso normal do sistema
        }
    }
    
    // Sincronizar configurações do banco com o arquivo config.php
    if (function_exists('executarSincronizacaoConfiguracoes')) {
        executarSincronizacaoConfiguracoes($pdo);
    }
} catch (PDOException $e) {
    // Em ambiente de produção, evite exibir detalhes do erro
    die('Erro de conexão com o banco de dados: ' . $e->getMessage());
}
?>