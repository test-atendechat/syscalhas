<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/auth.php');
require_once('includes/notificacoes.php');

// Verificar autenticação
verificarAutenticacao();

// Apenas admin pode adicionar notificações de teste
if ($_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=permissao');
    exit;
}

// Notificações de teste para demonstração
$notificacoes_teste = [
    [
        'mensagem' => 'Bem-vindo ao sistema de notificações! Clique em uma notificação para marcá-la como lida.',
        'tipo' => 'info',
        'link' => 'dashboard.php'
    ],
    [
        'mensagem' => 'Novo orçamento aprovado: #2025/0001',
        'tipo' => 'success',
        'link' => 'orcamento_visualizar.php?id=1'
    ],
    [
        'mensagem' => 'Estoque baixo para o produto: Calha 100mm',
        'tipo' => 'warning',
        'link' => 'produtos.php'
    ],
    [
        'mensagem' => 'Pagamento recusado para orçamento #2025/0002',
        'tipo' => 'danger',
        'link' => 'orcamento_visualizar.php?id=2'
    ],
    [
        'mensagem' => 'Novo agendamento para instalação em 15/05/2025',
        'tipo' => 'info',
        'link' => 'agendamento.php'
    ]
];

// Adicionar notificações de teste
$sucessos = 0;
foreach ($notificacoes_teste as $notificacao) {
    $resultado = adicionarNotificacao(
        $notificacao['mensagem'],
        $notificacao['tipo'],
        $notificacao['link']
    );
    
    if ($resultado) {
        $sucessos++;
    }
}

// Redirecionar para dashboard com mensagem
header("Location: dashboard.php?msg=notificacoes_teste_adicionadas&total=$sucessos");
exit;
?>