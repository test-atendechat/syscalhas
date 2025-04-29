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
} catch (PDOException $e) {
    // Em ambiente de produção, evite exibir detalhes do erro
    die('Erro de conexão com o banco de dados: ' . $e->getMessage());
}
?>