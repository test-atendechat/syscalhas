<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/notificacoes.php');

// Certificar que o usuário está autenticado
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não autenticado',
        'notificacoes' => [],
        'quantidade' => 0
    ]);
    exit;
}

// Obter o tipo de solicitação
$acao = isset($_GET['acao']) ? $_GET['acao'] : 'listar';

// Definir resposta padrão
$resposta = [
    'sucesso' => true,
    'mensagem' => '',
    'notificacoes' => [],
    'quantidade' => 0
];

switch ($acao) {
    case 'listar':
        // Obter notificações recentes não lidas
        $notificacoes = obterNotificacoes(10, true);
        $resposta['notificacoes'] = $notificacoes;
        $resposta['quantidade'] = contarNotificacoesNaoLidas();
        break;
        
    case 'marcar_lida':
        // Marcar uma notificação como lida
        $notificacao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($notificacao_id > 0) {
            $sucesso = marcarNotificacaoComoLida($notificacao_id);
            $resposta['sucesso'] = $sucesso;
            
            if ($sucesso) {
                $resposta['mensagem'] = 'Notificação marcada como lida';
                $resposta['quantidade'] = contarNotificacoesNaoLidas();
            } else {
                $resposta['mensagem'] = 'Erro ao marcar notificação como lida';
            }
        } else {
            $resposta['sucesso'] = false;
            $resposta['mensagem'] = 'ID de notificação inválido';
        }
        break;
        
    case 'marcar_todas_lidas':
        // Marcar todas as notificações como lidas
        $sucesso = marcarTodasNotificacoesComoLidas();
        $resposta['sucesso'] = $sucesso;
        
        if ($sucesso) {
            $resposta['mensagem'] = 'Todas as notificações foram marcadas como lidas';
            $resposta['quantidade'] = 0;
        } else {
            $resposta['mensagem'] = 'Erro ao marcar todas as notificações como lidas';
        }
        break;
        
    default:
        $resposta['sucesso'] = false;
        $resposta['mensagem'] = 'Ação não reconhecida';
        break;
}

// Retornar resposta como JSON
header('Content-Type: application/json');
echo json_encode($resposta);
?>