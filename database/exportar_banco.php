<?php
/**
 * Script para Exportar Estrutura do Banco de Dados
 * Este arquivo gera um script SQL que pode ser usado para recriar a estrutura do banco de dados
 */

// Verifica autenticação para segurança (opcional)
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['perfil'] != 'admin') {
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}

// Configurações de conexão com o banco de dados
// Inclui arquivo de conexão existente
require_once('includes/db.php');

// Define o nome do arquivo a ser baixado
$filename = "sistema_calhas_backup_" . date("Y-m-d") . ".sql";

// Define os cabeçalhos para download do arquivo
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Lista de tabelas no sistema
$tables = [
    'usuarios',
    'permissoes',
    'clientes',
    'produtos',
    'estoque_movimentacoes',
    'orcamentos',
    'orcamento_itens',
    'vendas',
    'venda_itens',
    'pagamentos',
    'caixa_controle',
    'caixa',
    'contas_pagar',
    'configuracoes'
];

// Função para obter a estrutura (esquema) de uma tabela
function getTableSchema($db, $table) {
    try {
        // Busca informações da estrutura da tabela
        $stmt = $db->prepare("
            SELECT column_name, data_type, character_maximum_length, 
                   is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = :table
            ORDER BY ordinal_position
        ");
        $stmt->bindParam(':table', $table);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca chaves primárias
        $stmt = $db->prepare("
            SELECT c.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.constraint_column_usage AS c 
                 ON c.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'PRIMARY KEY' 
                  AND tc.table_name = :table
        ");
        $stmt->bindParam(':table', $table);
        $stmt->execute();
        $primaryKeys = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name');
        
        // Busca chaves estrangeiras
        $stmt = $db->prepare("
            SELECT
                tc.constraint_name, 
                kcu.column_name, 
                ccu.table_name AS referenced_table,
                ccu.column_name AS referenced_column,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY' 
                  AND tc.table_name = :table
        ");
        $stmt->bindParam(':table', $table);
        $stmt->execute();
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gera o SQL CREATE TABLE
        $sql = "-- Estrutura da tabela `{$table}`\n";
        $sql .= "CREATE TABLE IF NOT EXISTS {$table} (\n";
        
        $columnDefinitions = [];
        foreach ($columns as $column) {
            $def = "    " . $column['column_name'] . " " . $column['data_type'];
            
            // Adiciona tamanho para tipos de caracteres
            if (!is_null($column['character_maximum_length'])) {
                $def .= "(" . $column['character_maximum_length'] . ")";
            }
            
            // Adiciona NOT NULL se necessário
            if ($column['is_nullable'] === 'NO') {
                $def .= " NOT NULL";
            }
            
            // Adiciona valor padrão
            if (!is_null($column['column_default'])) {
                // Se for SERIAL, não adiciona DEFAULT
                if (strpos($column['column_default'], 'nextval') === false) {
                    $def .= " DEFAULT " . $column['column_default'];
                }
            }
            
            $columnDefinitions[] = $def;
        }
        
        // Adiciona chave primária
        if (!empty($primaryKeys)) {
            $columnDefinitions[] = "    PRIMARY KEY (" . implode(", ", $primaryKeys) . ")";
        }
        
        // Adiciona chaves estrangeiras
        foreach ($foreignKeys as $fk) {
            $fkDef = "    CONSTRAINT " . $fk['constraint_name'] . " FOREIGN KEY (" . $fk['column_name'] . ") ";
            $fkDef .= "REFERENCES " . $fk['referenced_table'] . " (" . $fk['referenced_column'] . ") ";
            $fkDef .= "ON DELETE " . $fk['delete_rule'] . " ON UPDATE " . $fk['update_rule'];
            $columnDefinitions[] = $fkDef;
        }
        
        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n);\n\n";
        
        return $sql;
    } catch (PDOException $e) {
        return "-- Erro ao obter esquema para tabela {$table}: " . $e->getMessage() . "\n\n";
    }
}

// Função para extrair dados de uma tabela (opcional, limitada por exemplo às configurações)
function getTableData($db, $table, $limit = 1000) {
    try {
        // Para algumas tabelas sensíveis, pode querer exportar dados
        $exportDataTables = ['configuracoes', 'usuarios'];
        
        if (!in_array($table, $exportDataTables)) {
            return "-- Dados da tabela `{$table}` não exportados por questões de segurança ou volume\n\n";
        }
        
        // Para usuários, exportar apenas o administrador
        if ($table == 'usuarios') {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE perfil = 'admin' LIMIT 1");
        } else {
            $stmt = $db->prepare("SELECT * FROM {$table} LIMIT :limit");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return "-- Sem dados para exportar na tabela `{$table}`\n\n";
        }
        
        $sql = "-- Dados da tabela `{$table}`\n";
        
        foreach ($rows as $row) {
            // Se for senha de usuário, substitui por um hash conhecido
            if ($table == 'usuarios' && isset($row['senha'])) {
                $row['senha'] = 'SENHA_HASH_REMOVIDA_POR_SEGURANCA';
            }
            
            $columns = implode(", ", array_keys($row));
            $values = array_map(function($value) use ($db) {
                if (is_null($value)) return 'NULL';
                return $db->quote($value);
            }, array_values($row));
            
            $sql .= "INSERT INTO {$table} ({$columns}) VALUES (" . implode(", ", $values) . ");\n";
        }
        
        $sql .= "\n";
        return $sql;
    } catch (PDOException $e) {
        return "-- Erro ao obter dados para tabela {$table}: " . $e->getMessage() . "\n\n";
    }
}

// Inicia a geração do SQL
echo "-- Sistema de Orçamentos para Calhas - Backup do Banco de Dados\n";
echo "-- Data de exportação: " . date("Y-m-d H:i:s") . "\n\n";

// Exporta cada tabela
foreach ($tables as $table) {
    echo getTableSchema($db, $table);
    // Descomente a linha abaixo se quiser exportar dados
    // echo getTableData($db, $table);
}

// Adiciona permissões para o administrador
echo "-- Garante que o usuário administrador tenha todas as permissões\n";
echo "INSERT INTO permissoes (usuario_id, recurso, pode_ver, pode_cadastrar, pode_editar, pode_excluir)\n";
echo "SELECT u.id, recurso, TRUE, TRUE, TRUE, TRUE\n";
echo "FROM usuarios u\n";
echo "CROSS JOIN (VALUES ('dashboard'), ('produtos'), ('clientes'), ('orcamentos'), ('vendas'), ('usuarios'),\n";
echo "                  ('configuracoes'), ('estoque'), ('relatorios'), ('caixa'), ('contas_pagar')) AS r(recurso)\n";
echo "WHERE u.email = 'admin@admin.com' AND u.perfil = 'admin'\n";
echo "ON CONFLICT DO NOTHING;\n\n";

// Adiciona instruções sobre como usar o backup
echo "-- FIM DO BACKUP\n";
echo "-- Para restaurar este backup, execute:\n";
echo "-- psql -h hostname -d database_name -U username -f " . $filename . "\n";
?>