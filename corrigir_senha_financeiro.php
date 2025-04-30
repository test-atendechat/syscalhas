<?php
require_once('includes/config.php');
require_once('includes/db.php');

// Atualizar a senha do usuário financeiro
try {
    // Verificar se existe o usuário financeiro
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email");
    $email = 'financeiro@financeiro.com';
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Usuário financeiro encontrado (ID: {$usuario['id']}). Atualizando senha...<br>";
        
        $stmt = $db->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
        
        // Hash da senha 'financeiro'
        $senha = password_hash('financeiro', PASSWORD_DEFAULT);
        
        $stmt->bindParam(':senha', $senha);
        $stmt->bindParam(':id', $usuario['id']);
        
        $stmt->execute();
        
        echo "✅ Senha do usuário financeiro atualizada com sucesso para 'financeiro'<br>";
        
        // Verificar permissões
        $stmt = $db->prepare("SELECT * FROM permissoes WHERE usuario_id = :usuario_id");
        $stmt->bindParam(':usuario_id', $usuario['id']);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            // Criar permissões
            $stmt = $db->prepare("INSERT INTO permissoes (
                usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, 
                gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, 
                gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, 
                gerenciar_contas, visualizar_relatorios_gerais
            ) VALUES (
                :usuario_id, :gerenciar_usuarios, :visualizar_relatorios_financeiros,
                :gerenciar_estoque, :gerenciar_produtos, :gerenciar_orcamentos,
                :gerenciar_vendas, :gerenciar_clientes, :gerenciar_caixa,
                :gerenciar_contas, :visualizar_relatorios_gerais
            )");
            
            $true_val = true;
            $false_val = false;
            
            $stmt->bindParam(':usuario_id', $usuario['id']);
            $stmt->bindParam(':gerenciar_usuarios', $false_val);
            $stmt->bindParam(':visualizar_relatorios_financeiros', $true_val);
            $stmt->bindParam(':gerenciar_estoque', $false_val);
            $stmt->bindParam(':gerenciar_produtos', $false_val);
            $stmt->bindParam(':gerenciar_orcamentos', $false_val);
            $stmt->bindParam(':gerenciar_vendas', $false_val);
            $stmt->bindParam(':gerenciar_clientes', $false_val);
            $stmt->bindParam(':gerenciar_caixa', $true_val);
            $stmt->bindParam(':gerenciar_contas', $true_val);
            $stmt->bindParam(':visualizar_relatorios_gerais', $true_val);
            
            $stmt->execute();
            
            echo "✅ Permissões do usuário financeiro configuradas com sucesso<br>";
        } else {
            echo "As permissões do usuário financeiro já estão configuradas<br>";
        }
    } else {
        echo "❌ Usuário financeiro não encontrado<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao redefinir senha: " . $e->getMessage() . "<br>";
}

echo "<br><a href='login.php'>Voltar para a página de login</a>";
?>