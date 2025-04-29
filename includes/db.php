<?php
require_once('config.php');

/**
 * Classe de conexão com o banco de dados usando PDO
 */
class Database {
    private $type = DB_TYPE;
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $port = DB_PORT;
    
    private $conn;
    private $error;
    
    /**
     * Construtor - estabelece a conexão com o banco de dados
     */
    public function __construct() {
        // DSN - Baseado no tipo de banco de dados
        if ($this->type == 'mysql') {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
            // Opções do PDO para MySQL
            $options = array(
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            );
        } else if ($this->type == 'pgsql') {
            $dsn = 'pgsql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbname;
            // Opções do PDO para PostgreSQL
            $options = array(
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            );
        } else {
            die('Tipo de banco de dados não suportado');
        }
        
        // Criar instância do PDO
        try {
            $this->conn = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            echo 'Erro na conexão: ' . $this->error;
        }
    }
    
    /**
     * Método para executar consultas
     * @param string $sql - Query SQL a ser executada
     * @return PDOStatement
     */
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    /**
     * Método para preparar statements
     * @param string $sql - Query SQL a ser preparada
     * @return PDOStatement
     */
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    /**
     * Método para obter o último ID inserido
     * @param string $sequence Nome da sequência (apenas para PostgreSQL)
     * @return string
     */
    public function lastInsertId($sequence = null) {
        return $this->conn->lastInsertId($sequence);
    }
    
    /**
     * Método para iniciar uma transação
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Método para confirmar uma transação
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Método para reverter uma transação
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * Método para retornar o tipo de banco de dados
     * @return string
     */
    public function getDbType() {
        return $this->type;
    }
}

// Instância da conexão com o banco de dados
$db = new Database();
?>
