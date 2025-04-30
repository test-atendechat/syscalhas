<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação (opcional, remova se quiser permitir exportação sem login)
verificarAutenticacao();

// Verificar se o usuário é administrador
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    die('Acesso restrito. Apenas administradores podem exportar o banco de dados.');
}

// Lista de tabelas do banco de dados
$tabelas = [
    'configuracoes',
    'usuarios',
    'permissoes',
    'categorias',
    'produtos',
    'clientes',
    'orcamentos',
    'orcamento_itens',
    'vendas',
    'vendas_itens',
    'caixa',
    'caixa_controle',
    'estoque_movimentacoes',
    'contas_pagar',
    'pagamentos_contas',
    'estornos_pagamentos'
];

// Função para obter a estrutura de uma tabela
function getTableSchema($db, $table) {
    $schema = '';
    
    // Verifica se a tabela existe
    $stmt = $db->prepare("SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public'
        AND table_name = :table
    )");
    $stmt->bindParam(':table', $table);
    $stmt->execute();
    
    if (!$stmt->fetchColumn()) {
        return "-- Tabela {$table} não existe\n\n";
    }
    
    // Obtém a estrutura da tabela
    $sql = "SELECT column_name, data_type, 
               character_maximum_length, 
               column_default, 
               is_nullable
            FROM information_schema.columns 
            WHERE table_name = :table 
            ORDER BY ordinal_position";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':table', $table);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtém a chave primária
    $pkSql = "SELECT c.column_name
              FROM information_schema.table_constraints tc
              JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name)
              JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema
                AND tc.table_name = c.table_name AND ccu.column_name = c.column_name
              WHERE constraint_type = 'PRIMARY KEY' AND tc.table_name = :table";
    
    $stmt = $db->prepare($pkSql);
    $stmt->bindParam(':table', $table);
    $stmt->execute();
    $primaryKey = $stmt->fetchColumn();
    
    // Constrói a instrução CREATE TABLE
    $schema .= "-- Estrutura da tabela `{$table}`\n";
    $schema .= "DROP TABLE IF EXISTS {$table} CASCADE;\n";
    $schema .= "CREATE TABLE {$table} (\n";
    
    $columnDefs = [];
    foreach ($columns as $column) {
        $columnDef = "    " . $column['column_name'] . " " . $column['data_type'];
        
        // Adiciona o tamanho para tipos de caracteres
        if ($column['character_maximum_length'] !== null) {
            $columnDef .= "(" . $column['character_maximum_length'] . ")";
        }
        
        // Adiciona NOT NULL se aplicável
        if ($column['is_nullable'] === 'NO') {
            $columnDef .= " NOT NULL";
        }
        
        // Adiciona valor padrão se aplicável
        if ($column['column_default'] !== null) {
            $columnDef .= " DEFAULT " . $column['column_default'];
        }
        
        // Adiciona PRIMARY KEY se esta coluna for a PK
        if ($column['column_name'] === $primaryKey) {
            $columnDef .= " PRIMARY KEY";
        }
        
        $columnDefs[] = $columnDef;
    }
    
    $schema .= implode(",\n", $columnDefs);
    $schema .= "\n);\n\n";
    
    // Obtém as sequências associadas (para colunas serial/identity)
    $seqSql = "SELECT column_name, pg_get_serial_sequence('public.{$table}', column_name) as seq_name
               FROM information_schema.columns
               WHERE table_name = :table
               AND column_default LIKE 'nextval%'";
    
    $stmt = $db->prepare($seqSql);
    $stmt->bindParam(':table', $table);
    $stmt->execute();
    
    while ($seq = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($seq['seq_name']) {
            // Obtém o valor atual da sequência
            $seqValSql = "SELECT last_value FROM " . $seq['seq_name'];
            $seqValStmt = $db->query($seqValSql);
            $lastValue = $seqValStmt->fetchColumn();
            
            $schema .= "-- Resetar a sequência para a coluna {$seq['column_name']}\n";
            $schema .= "SELECT setval(pg_get_serial_sequence('{$table}', '{$seq['column_name']}'), COALESCE((SELECT MAX({$seq['column_name']}) FROM {$table}), 1), false);\n\n";
        }
    }
    
    return $schema;
}

// Função para obter os dados de uma tabela
function getTableData($db, $table) {
    $data = '';
    
    // Verifica se a tabela está vazia
    $countStmt = $db->prepare("SELECT COUNT(*) FROM {$table}");
    $countStmt->execute();
    $count = $countStmt->fetchColumn();
    
    if ($count == 0) {
        return "-- Tabela {$table} está vazia\n\n";
    }
    
    // Obtém as colunas da tabela
    $columnsStmt = $db->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table ORDER BY ordinal_position");
    $columnsStmt->bindParam(':table', $table);
    $columnsStmt->execute();
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtém os dados da tabela
    $dataStmt = $db->prepare("SELECT * FROM {$table}");
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        $data .= "-- Dados da tabela `{$table}`\n";
        
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                if ($row[$column] === null) {
                    $values[] = "NULL";
                } elseif (is_numeric($row[$column])) {
                    $values[] = $row[$column];
                } else {
                    // Escapar aspas simples
                    $escapedValue = str_replace("'", "''", $row[$column]);
                    $values[] = "'" . $escapedValue . "'";
                }
            }
            
            $data .= "INSERT INTO {$table} (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
        }
        
        $data .= "\n";
    }
    
    return $data;
}

// Iniciar buffer de saída
ob_start();

// Controle de header
$download = isset($_GET['download']) && $_GET['download'] == 1;
if ($download) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="db_backup_' . date('Y-m-d_H-i-s') . '.sql"');
} else {
    // Exibir como página web
    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Exportação de Banco de Dados</title>
    <link rel='stylesheet' href='css/styles.css'>
    <style>
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .copy-btn {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class='container mt-4'>
        <div class='card mb-4'>
            <div class='card-header bg-primary text-white'>
                <h1 class='h4 mb-0'>Exportação de Banco de Dados</h1>
            </div>
            <div class='card-body'>
                <div class='alert alert-info'>
                    <p><strong>Instruções:</strong></p>
                    <p>Este script gera um arquivo SQL com a estrutura e dados do banco. Você pode:</p>
                    <ul>
                        <li>Copiar o conteúdo abaixo e colar em um arquivo .sql</li>
                        <li><a href='?download=1' class='btn btn-sm btn-primary'>Baixar como arquivo SQL</a></li>
                        <li><a href='instalar_banco_completo.php' class='btn btn-sm btn-success'>Instalar no servidor atual</a></li>
                    </ul>
                </div>
                <button class='btn btn-secondary copy-btn' onclick='copyToClipboard()'>Copiar para área de transferência</button>
                <pre id='sql-content'>";
}

echo "-- --------------------------------------------------------\n";
echo "-- Sistema de Orçamentos e Vendas\n";
echo "-- Data de Exportação: " . date('Y-m-d H:i:s') . "\n";
echo "-- --------------------------------------------------------\n\n";

// Configurar o PostgreSQL para aceitar transações
echo "BEGIN;\n\n";

// Exportar cada tabela
foreach ($tabelas as $tabela) {
    // Obter e exibir a estrutura da tabela
    echo getTableSchema($db, $tabela);
    
    // Obter e exibir os dados da tabela
    echo getTableData($db, $tabela);
    
    echo "-- --------------------------------------------------------\n\n";
}

// Finalizar transação
echo "COMMIT;\n";

// Se não for download, terminar o HTML
if (!$download) {
    echo "</pre>
            </div>
        </div>
        <div class='mb-4'>
            <a href='dashboard.php' class='btn btn-primary'>Voltar para o Dashboard</a>
        </div>
    </div>
    
    <script>
    function copyToClipboard() {
        const el = document.getElementById('sql-content');
        const range = document.createRange();
        range.selectNode(el);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        document.execCommand('copy');
        window.getSelection().removeAllRanges();
        alert('Conteúdo copiado para a área de transferência!');
    }
    </script>
</body>
</html>";
}

// Enviar buffer de saída
ob_end_flush();
?>