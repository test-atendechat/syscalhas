<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Buscar dados do orçamento primeiro
    $stmt = $pdo->prepare("SELECT * FROM orcamentos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se o orçamento existe, buscar agendamento
    if ($orcamento) {
        echo "<h2>Dados do Orçamento #$id</h2>";
        echo "<pre>";
        echo "ID: " . $orcamento['id'] . "<br>";
        echo "Número: " . $orcamento['numero'] . "<br>";
        echo "Status: " . $orcamento['status'] . "<br>";
        echo "Status Execução: " . $orcamento['status_execucao'] . "<br>";
        echo "</pre>";
        
        // Consultar dados do agendamento com informações do colaborador
        $stmt = $pdo->prepare("SELECT a.*, 
                          c.nome as colaborador_nome, 
                          c.telefone as colaborador_telefone, 
                          c.tipo as tipo_colaborador
                          FROM agendamentos a 
                          JOIN colaboradores c ON a.instalador_id = c.id
                          WHERE a.orcamento_id = :orcamento_id 
                          AND (a.status = 'agendado' OR a.status = 'orcamento_agendado' OR a.status = 'instalacao_agendada' OR a.status = 'pendente')
                          ORDER BY a.data_agendamento DESC LIMIT 1");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<h2>Dados do Agendamento</h2>";
            echo "<pre>";
            echo "ID: " . $agendamento['id'] . "<br>";
            echo "Status: " . $agendamento['status'] . "<br>";
            echo "Data: " . $agendamento['data_agendamento'] . "<br>";
            echo "Hora Início: " . $agendamento['hora_inicio'] . "<br>";
            echo "Colaborador: " . $agendamento['colaborador_nome'] . "<br>";
            echo "</pre>";
        } else {
            echo "<h2>Nenhum Agendamento Encontrado</h2>";
            
            // Buscar todos os agendamentos para esse orçamento independente do status
            $stmt = $pdo->prepare("SELECT a.*, c.nome as colaborador_nome 
                                FROM agendamentos a 
                                JOIN colaboradores c ON a.instalador_id = c.id
                                WHERE a.orcamento_id = :orcamento_id");
            $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<h3>Agendamentos existentes (não corresponderam aos critérios):</h3>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Status</th><th>Data</th><th>Colaborador</th></tr>";
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>".$row['id']."</td>";
                    echo "<td>".$row['status']."</td>";
                    echo "<td>".$row['data_agendamento']."</td>";
                    echo "<td>".$row['colaborador_nome']."</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
        }
    } else {
        echo "<h2>Orçamento #$id não encontrado!</h2>";
    }
} else {
    echo "<h2>Nenhum ID de orçamento fornecido!</h2>";
}
