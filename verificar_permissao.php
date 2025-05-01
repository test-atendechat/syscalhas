<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
    echo "Usuário não está logado";
    exit;
}

$usuario_id = $_SESSION['usuario']['id'];
$usuario_nivel = $_SESSION['usuario']['nivel'] ?? 'desconhecido';
$usuario_nome = $_SESSION['usuario']['nome'] ?? 'desconhecido';
$usuario_email = $_SESSION['usuario']['email'] ?? 'desconhecido';

echo "<h1>Informações do Usuário</h1>";
echo "<p><strong>ID:</strong> $usuario_id</p>";
echo "<p><strong>Nome:</strong> $usuario_nome</p>";
echo "<p><strong>Email:</strong> $usuario_email</p>";
echo "<p><strong>Nível:</strong> $usuario_nivel</p>";

echo "<h2>Permissões</h2>";

try {
    // Buscar permissões
    $sql = "SELECT * FROM permissoes WHERE usuario_id = :usuario_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $permissoes = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<ul>";
        foreach ($permissoes as $chave => $valor) {
            if ($chave != 'id' && $chave != 'usuario_id' && $chave != 'data_criacao' && $chave != 'data_atualizacao') {
                $status = $valor ? "SIM" : "NÃO";
                echo "<li><strong>$chave:</strong> $status</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>Nenhuma permissão específica encontrada. ";
        if ($usuario_nivel === 'admin') {
            echo "Como administrador, você tem todas as permissões por padrão.</p>";
        } else {
            echo "Você não tem permissões configuradas.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>Erro ao buscar permissões: " . $e->getMessage() . "</p>";
}

// Verificar permissão específica para relatório financeiro
$temPermissaoFinanceiro = verificarPermissao('visualizar_relatorios_financeiros');
echo "<h2>Permissão para Relatório Financeiro</h2>";
echo $temPermissaoFinanceiro ? 
    "<p style='color: green;'>Você TEM permissão para acessar relatórios financeiros.</p>" : 
    "<p style='color: red;'>Você NÃO tem permissão para acessar relatórios financeiros.</p>";

// Verificar se o arquivo relatorio_financeiro.php está permitindo acesso
echo "<h2>Condição de Acesso do Arquivo</h2>";
$condicao = ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('visualizar_relatorios_financeiros'));
echo $condicao ? 
    "<p style='color: green;'>A condição de acesso PERMITE que você acesse relatórios financeiros.</p>" : 
    "<p style='color: red;'>A condição de acesso IMPEDE que você acesse relatórios financeiros.</p>";
?>

<p><a href="dashboard.php">Voltar ao Dashboard</a></p>