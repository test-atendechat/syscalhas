<?php
// Script para corrigir a tabela agendamentos, ajustando os instalador_id para referenciar a tabela colaboradores
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

global $db, $pdo;

// Versão do script
echo "====================================================================\n";
echo "Correção da tabela de agendamentos - ".date('Y-m-d H:i:s')."\n";
echo "====================================================================\n\n";

// Verificar se existem registros na tabela agendamentos
echo "Verificando registros na tabela agendamentos...\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM agendamentos");
$total_agendamentos = $stmt->fetchColumn();

echo "Total de {$total_agendamentos} agendamentos encontrados.\n\n";

if ($total_agendamentos > 0) {
    echo "Iniciando correção de agendamentos...\n";
    
    // Iniciando uma transação
    $pdo->beginTransaction();
    
    try {
        // 1. Buscar registros com instalador_id que não existam na tabela colaboradores
        $stmt = $pdo->prepare("SELECT a.id, a.instalador_id 
                             FROM agendamentos a 
                             LEFT JOIN colaboradores c ON a.instalador_id = c.id 
                             WHERE a.instalador_id IS NOT NULL 
                             AND c.id IS NULL");
        $stmt->execute();
        $registros_inconsistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($registros_inconsistentes) > 0) {
            echo "Encontrados " . count($registros_inconsistentes) . " agendamentos com instaladores que não existem na tabela colaboradores.\n";
            
            // Para cada agendamento com instalador inválido, definir instalador_id como NULL
            $stmt = $pdo->prepare("UPDATE agendamentos SET instalador_id = NULL WHERE id = :id");
            
            foreach ($registros_inconsistentes as $registro) {
                echo "  - Corrigindo agendamento ID {$registro['id']} (instalador_id = {$registro['instalador_id']})\n";
                $stmt->bindParam(':id', $registro['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
        } else {
            echo "Todos os agendamentos têm instaladores válidos na tabela colaboradores.\n";
        }
        
        // Commit das alterações
        $pdo->commit();
        echo "\nCorreção concluída com sucesso!\n";
        
    } catch (Exception $e) {
        // Em caso de erro, fazer rollback
        $pdo->rollBack();
        echo "\nERRO: Não foi possível corrigir os agendamentos: " . $e->getMessage() . "\n";
    }
} else {
    echo "Não há registros na tabela agendamentos. Nenhuma correção necessária.\n";
}

echo "\n====================================================================\n";
echo "Script de correção finalizado - ".date('Y-m-d H:i:s')."\n";
echo "====================================================================\n";
