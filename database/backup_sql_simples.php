<?php
require_once('includes/config.php');
require_once('includes/db.php');

// Definir cabeçalhos para download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');

// Tabelas para exportar
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

// Iniciar a saída do arquivo SQL
echo "-- Backup do banco de dados\n";
echo "-- Data: " . date('Y-m-d H:i:s') . "\n";
echo "-- --------------------------------------------------------\n\n";

// Para cada tabela
foreach ($tabelas as $tabela) {
    echo "-- Estrutura da tabela {$tabela}\n";
    echo "DROP TABLE IF EXISTS {$tabela} CASCADE;\n";
    
    // Obter a estrutura da tabela
    $stmt = $db->query("SELECT column_name, data_type, 
                       character_maximum_length, 
                       column_default, 
                       is_nullable
                     FROM information_schema.columns 
                     WHERE table_name = '{$tabela}' 
                     ORDER BY ordinal_position");
    
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se a tabela existir
    if (count($colunas) > 0) {
        echo "CREATE TABLE {$tabela} (\n";
        
        $colunasArray = [];
        foreach ($colunas as $coluna) {
            $def = "    " . $coluna['column_name'] . " " . $coluna['data_type'];
            
            // Adicionar tamanho para tipos de caracteres
            if ($coluna['character_maximum_length'] != null) {
                $def .= "(" . $coluna['character_maximum_length'] . ")";
            }
            
            // Adicionar NOT NULL se aplicável
            if ($coluna['is_nullable'] == 'NO') {
                $def .= " NOT NULL";
            }
            
            // Adicionar valor padrão se aplicável
            if ($coluna['column_default'] != null) {
                $def .= " DEFAULT " . $coluna['column_default'];
            }
            
            $colunasArray[] = $def;
        }
        
        echo implode(",\n", $colunasArray);
        echo "\n);\n\n";
        
        // Exportar dados
        echo "-- Dados da tabela {$tabela}\n";
        
        // Obter os dados
        $stmt = $db->query("SELECT * FROM {$tabela}");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $colunas = array_keys($row);
            $valores = [];
            
            foreach ($row as $valor) {
                if ($valor === null) {
                    $valores[] = "NULL";
                } elseif (is_numeric($valor)) {
                    $valores[] = $valor;
                } else {
                    // Escapar aspas simples
                    $escapado = str_replace("'", "''", $valor);
                    $valores[] = "'" . $escapado . "'";
                }
            }
            
            echo "INSERT INTO {$tabela} (" . implode(", ", $colunas) . ") VALUES (" . implode(", ", $valores) . ");\n";
        }
        
        echo "\n-- --------------------------------------------------------\n\n";
    } else {
        echo "-- Tabela {$tabela} não encontrada\n\n";
    }
}

// Para criar sequências automaticamente
echo "-- Sequências\n";
$stmt = $db->query("SELECT c.relname as seq_name
                  FROM pg_class c 
                  WHERE c.relkind = 'S'
                  AND c.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public')");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $seq_name = $row['seq_name'];
    echo "DROP SEQUENCE IF EXISTS {$seq_name} CASCADE;\n";
    echo "CREATE SEQUENCE {$seq_name} START 1;\n";
    
    // Verificar a qual tabela/coluna a sequência está associada
    $stmt2 = $db->query("SELECT pg_get_serial_sequence(c.relname, a.attname) as seq_name
                      FROM pg_class c
                      JOIN pg_attribute a ON (c.oid = a.attrelid)
                      WHERE c.relkind = 'r'
                      AND a.attnum > 0
                      AND pg_get_serial_sequence(c.relname, a.attname) = 'public.{$seq_name}'");
    
    if ($stmt2->rowCount() > 0) {
        $info = $stmt2->fetch(PDO::FETCH_ASSOC);
        list($schema, $tabela, $coluna) = explode('.', $info['seq_name']);
        echo "ALTER SEQUENCE {$seq_name} OWNED BY {$tabela}.{$coluna};\n";
    }
    
    echo "\n";
}

echo "-- Backup completo\n";
?>