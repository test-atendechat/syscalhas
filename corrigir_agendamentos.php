<?php
// Script de diagnóstico e correção de agendamentos dessincronizados
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Garantir acesso à variável de conexão
global $pdo;

// Identificar agendamentos dessincronizados onde o orçamento está como 'orcamento_agendado' 
// mas o agendamento ainda está como 'pendente'
$stmt = $pdo->prepare("SELECT a.id as agendamento_id, a.status as agendamento_status, 
                      o.id as orcamento_id, o.numero as orcamento_numero, 
                      o.status_execucao as orcamento_status 
                      FROM agendamentos a 
                      JOIN orcamentos o ON a.orcamento_id = o.id 
                      WHERE o.status_execucao = 'orcamento_agendado' 
                      AND a.status = 'pendente'");
$stmt->execute();
$dessincronizados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Diagnóstico de Agendamentos</h1>";

if (count($dessincronizados) > 0) {
    echo "<p>Encontrados " . count($dessincronizados) . " agendamentos dessincronizados:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Agendamento ID</th><th>Status Agendamento</th><th>Orçamento ID</th><th>Número Orçamento</th><th>Status Orçamento</th></tr>";
    
    foreach ($dessincronizados as $item) {
        echo "<tr>";
        echo "<td>{$item['agendamento_id']}</td>";
        echo "<td>{$item['agendamento_status']}</td>";
        echo "<td>{$item['orcamento_id']}</td>";
        echo "<td>{$item['orcamento_numero']}</td>";
        echo "<td>{$item['orcamento_status']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Se enviou o formulário para corrigir
    if (isset($_POST['corrigir']) && $_POST['corrigir'] == 'sim') {
        echo "<h2>Corrigindo agendamentos...</h2>";
        
        try {
            $pdo->beginTransaction();
            
            // Atualizar todos os agendamentos dessincronizados
            $stmt = $pdo->prepare("UPDATE agendamentos 
                                  SET status = 'orcamento_agendado' 
                                  WHERE id IN (SELECT a.id 
                                              FROM agendamentos a 
                                              JOIN orcamentos o ON a.orcamento_id = o.id 
                                              WHERE o.status_execucao = 'orcamento_agendado' 
                                              AND a.status = 'pendente')");
            $stmt->execute();
            $total_atualizados = $stmt->rowCount();
            
            $pdo->commit();
            
            echo "<div style='color: green; margin: 20px 0;'>✓ Atualizados $total_atualizados agendamentos com sucesso!</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div style='color: red; margin: 20px 0;'>⚠ Erro: " . $e->getMessage() . "</div>";
        }
    } else {
        // Mostrar formulário para confirmar a correção
        echo "<form method='post' style='margin-top: 20px;'>";
        echo "<input type='hidden' name='corrigir' value='sim'>";
        echo "<button type='submit' style='background-color: #0d6efd; color: white; padding: 10px 15px; border: none; border-radius: 4px;'>Corrigir Agendamentos Dessincronizados</button>";
        echo "</form>";
    }
} else {
    echo "<p style='color: green;'>✓ Nenhum agendamento dessincronizado encontrado.</p>";
}

// Botão para voltar
echo "<a href='dashboard.php' style='display: inline-block; margin-top: 20px; text-decoration: none; padding: 10px 15px; background-color: #6c757d; color: white; border-radius: 4px;'>Voltar ao Dashboard</a>";
?>