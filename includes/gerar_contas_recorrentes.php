<?php
require_once('config.php');
require_once('db.php');
require_once('functions.php');

// Esta função verifica se existem contas recorrentes que precisam ter novas parcelas geradas
function gerarContasRecorrentes($data_atual = null) {
    global $db;
    
    if ($data_atual === null) {
        $data_atual = date('Y-m-d');
    }
    
    // Obter contas recorrentes ativas
    $stmt = $db->prepare("SELECT * FROM contas_pagar WHERE recorrente = 1 AND status != 'cancelado'");
    $stmt->execute();
    $contas_recorrentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $contas_geradas = 0;
    
    foreach ($contas_recorrentes as $conta) {
        // Verificar se já existe uma conta para o próximo mês com esta conta pai
        $data_vencimento = $conta['data_vencimento'];
        $intervalo_dias = $conta['intervalo_dias'] ?? 30; // Padrão é mensal (30 dias)
        
        // Calcular a próxima data de vencimento (mesmo dia do mês seguinte)
        $data_venc_obj = new DateTime($data_vencimento);
        $dia_vencimento = $data_venc_obj->format('d');
        $mes_proximo = $data_venc_obj->modify("+1 month");
        $proxima_data = $mes_proximo->format('Y-m-').$dia_vencimento;
        
        // Ajustar para meses com menos dias
        $proxima_data_obj = new DateTime($proxima_data);
        if ($proxima_data_obj->format('d') != $dia_vencimento) {
            // Se o dia não corresponder, isso significa que estamos em um mês com menos dias
            // Ex: 31 de janeiro para 28/29 de fevereiro
            $proxima_data_obj->modify('last day of this month');
            $proxima_data = $proxima_data_obj->format('Y-m-d');
        }
        
        // Verificar se já existe uma conta gerada para esta data
        $stmt = $db->prepare("SELECT COUNT(*) FROM contas_pagar WHERE 
                           conta_pai_id = :conta_pai_id AND 
                           data_vencimento = :data_vencimento");
        $stmt->bindParam(':conta_pai_id', $conta['id'], PDO::PARAM_INT);
        $stmt->bindParam(':data_vencimento', $proxima_data);
        $stmt->execute();
        $existe = $stmt->fetchColumn() > 0;
        
        // Se ainda não existe, e a data atual está dentro de 7 dias da data de vencimento
        // vamos criar a próxima conta
        $dias_diferenca = ceil((strtotime($data_vencimento) - strtotime($data_atual)) / (60 * 60 * 24));
        
        if (!$existe && $dias_diferenca <= 7) {
            try {
                $db->beginTransaction();
                
                // Criar a próxima conta com vencimento no próximo mês
                $stmt = $db->prepare("INSERT INTO contas_pagar 
                            (descricao, fornecedor, data_emissao, data_vencimento, valor, 
                             status, observacoes, documento, categoria, usuario_id, 
                             recorrente, intervalo_dias, conta_pai_id) 
                            VALUES 
                            (:descricao, :fornecedor, :data_emissao, :data_vencimento, :valor, 
                             'pendente', :observacoes, :documento, :categoria, :usuario_id, 
                             :recorrente, :intervalo_dias, :conta_pai_id)");
                
                $data_atual = date('Y-m-d');
                $stmt->bindParam(':descricao', $conta['descricao']);
                $stmt->bindParam(':fornecedor', $conta['fornecedor']);
                $stmt->bindParam(':data_emissao', $data_atual);
                $stmt->bindParam(':data_vencimento', $proxima_data);
                $stmt->bindParam(':valor', $conta['valor']);
                $stmt->bindParam(':observacoes', $conta['observacoes']);
                $stmt->bindParam(':documento', $conta['documento']);
                $stmt->bindParam(':categoria', $conta['categoria']);
                $stmt->bindParam(':usuario_id', $conta['usuario_id'], PDO::PARAM_INT);
                $stmt->bindParam(':recorrente', $conta['recorrente'], PDO::PARAM_INT);
                $stmt->bindParam(':intervalo_dias', $intervalo_dias, PDO::PARAM_INT);
                $stmt->bindParam(':conta_pai_id', $conta['id'], PDO::PARAM_INT);
                
                $stmt->execute();
                $contas_geradas++;
                
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Erro ao gerar conta recorrente: " . $e->getMessage());
            }
        }
    }
    
    return $contas_geradas;
}

// Verificar se este arquivo está sendo executado diretamente ou incluído em outro script
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    $contas_geradas = gerarContasRecorrentes();
    echo "Contas recorrentes geradas: {$contas_geradas}\n";
}
