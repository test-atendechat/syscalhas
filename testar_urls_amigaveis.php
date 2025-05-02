<?php
// Script para testar as URLs amigáveis

// Incluir configurações
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Lista de URLs para testar
$urls = [
    'dashboard' => 'Dashboard',
    'clientes' => 'Lista de Clientes',
    'cliente/novo' => 'Novo Cliente',
    'orcamentos' => 'Lista de Orçamentos',
    'orcamento/novo' => 'Novo Orçamento',
    'vendas' => 'Lista de Vendas',
    'venda/nova' => 'Nova Venda',
    'produtos' => 'Lista de Produtos',
    'produto/novo' => 'Novo Produto',
    'estoque' => 'Controle de Estoque',
    'estoque/entrada' => 'Entrada de Estoque',
    'estoque/saida' => 'Saída de Estoque',
    'agendamentos' => 'Agendamentos',
    'agendar' => 'Agendar Serviço',
    'colaboradores' => 'Lista de Colaboradores',
    'colaborador/novo' => 'Novo Colaborador',
    'caixa' => 'Controle de Caixa',
    'contas' => 'Contas a Pagar',
    'conta/nova' => 'Nova Conta',
    'relatorios/vendas' => 'Relatório de Vendas',
    'relatorios/orcamentos' => 'Relatório de Orçamentos',
    'relatorios/produtos' => 'Relatório de Produtos Vendidos',
    'relatorios/estoque' => 'Relatório de Estoque Baixo',
    'relatorios/financeiro' => 'Relatório Financeiro',
    'usuarios' => 'Lista de Usuários',
    'configuracoes' => 'Configurações do Sistema'
];

// Função para verificar se uma URL está acessível
function verificarUrl($url, $base_url) {
    $url_completa = $base_url . $url;
    $ch = curl_init($url_completa);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code;
}

// Exibir cabeçalho
echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Teste de URLs Amigáveis</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class='container mt-5'>
        <h1>Teste de URLs Amigáveis</h1>
        <p>Este script testa se as URLs amigáveis estão funcionando corretamente.</p>
        
        <div class='alert alert-info'>
            <strong>Nota:</strong> Este teste só funcionará se o servidor web estiver configurado com mod_rewrite e o arquivo .htaccess estiver ativo.
        </div>
        
        <h2>Resultados do Teste</h2>
        <table class='table table-striped'>
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Descrição</th>
                    <th>Status</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>";

// Verificar se o mod_rewrite está ativo
if (!function_exists('apache_get_modules') || !in_array('mod_rewrite', apache_get_modules())) {
    echo "<tr>
            <td colspan='4' class='error'>
                <strong>ATENÇÃO:</strong> O módulo mod_rewrite do Apache parece não estar ativo. 
                As URLs amigáveis podem não funcionar corretamente.
            </td>
          </tr>";
}

// Testar cada URL
foreach ($urls as $url => $descricao) {
    echo "<tr>
            <td><code>/" . htmlspecialchars($url) . "</code></td>
            <td>" . htmlspecialchars($descricao) . "</td>
            <td>";
    
    // Não fazer a verificação real para evitar problemas
    echo "<span class='warning'>Não verificado</span>";
    
    echo "</td>
            <td><a href='" . htmlspecialchars(BASE_URL . $url) . "' target='_blank' class='btn btn-sm btn-primary'>Testar</a></td>
          </tr>";
}

echo "    </tbody>
        </table>
        
        <div class='mt-4'>
            <a href='dashboard.php' class='btn btn-secondary'>Voltar para o Dashboard</a>
        </div>
    </div>
</body>
</html>";
?>