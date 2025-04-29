<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['nivel'] !== 'admin') {
    $_SESSION['erro'] = "Você não tem permissão para acessar esta página.";
    header("Location: dashboard.php");
    exit;
}

// Processar solicitação para atualizar permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = isset($_POST['usuario_id']) ? intval($_POST['usuario_id']) : 0;
    
    if ($usuario_id > 0) {
        // Preparar dados das permissões
        $permissoes = [
            'gerenciar_usuarios' => isset($_POST['gerenciar_usuarios']),
            'visualizar_relatorios_financeiros' => isset($_POST['visualizar_relatorios_financeiros']),
            'gerenciar_estoque' => isset($_POST['gerenciar_estoque']),
            'gerenciar_produtos' => isset($_POST['gerenciar_produtos']),
            'gerenciar_orcamentos' => isset($_POST['gerenciar_orcamentos']),
            'gerenciar_vendas' => isset($_POST['gerenciar_vendas']),
            'gerenciar_clientes' => isset($_POST['gerenciar_clientes']),
            'gerenciar_caixa' => isset($_POST['gerenciar_caixa']),
            'gerenciar_contas' => isset($_POST['gerenciar_contas']),
            'visualizar_relatorios_gerais' => isset($_POST['visualizar_relatorios_gerais'])
        ];
        
        // Verificar se já existe registro para este usuário
        $stmt = $db->prepare("SELECT id FROM permissoes WHERE usuario_id = :usuario_id");
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Atualizar permissões existentes
            $sql = "UPDATE permissoes SET 
                    gerenciar_usuarios = :gerenciar_usuarios,
                    visualizar_relatorios_financeiros = :visualizar_relatorios_financeiros,
                    gerenciar_estoque = :gerenciar_estoque,
                    gerenciar_produtos = :gerenciar_produtos,
                    gerenciar_orcamentos = :gerenciar_orcamentos,
                    gerenciar_vendas = :gerenciar_vendas,
                    gerenciar_clientes = :gerenciar_clientes,
                    gerenciar_caixa = :gerenciar_caixa,
                    gerenciar_contas = :gerenciar_contas,
                    visualizar_relatorios_gerais = :visualizar_relatorios_gerais,
                    data_atualizacao = NOW()
                    WHERE usuario_id = :usuario_id";
        } else {
            // Inserir novas permissões
            $sql = "INSERT INTO permissoes (
                    usuario_id, 
                    gerenciar_usuarios, 
                    visualizar_relatorios_financeiros, 
                    gerenciar_estoque, 
                    gerenciar_produtos, 
                    gerenciar_orcamentos, 
                    gerenciar_vendas, 
                    gerenciar_clientes, 
                    gerenciar_caixa, 
                    gerenciar_contas, 
                    visualizar_relatorios_gerais
                ) VALUES (
                    :usuario_id,
                    :gerenciar_usuarios,
                    :visualizar_relatorios_financeiros,
                    :gerenciar_estoque,
                    :gerenciar_produtos,
                    :gerenciar_orcamentos,
                    :gerenciar_vendas,
                    :gerenciar_clientes,
                    :gerenciar_caixa,
                    :gerenciar_contas,
                    :visualizar_relatorios_gerais
                )";
        }
        
        // Executar a query
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        
        foreach ($permissoes as $campo => $valor) {
            $stmt->bindValue(':' . $campo, $valor ? true : false, PDO::PARAM_BOOL);
        }
        
        if ($stmt->execute()) {
            $_SESSION['sucesso'] = "Permissões do usuário atualizadas com sucesso!";
        } else {
            $_SESSION['erro'] = "Erro ao atualizar permissões do usuário.";
        }
        
        // Redirecionar para a mesma página
        header("Location: usuario_permissoes.php?id=" . $usuario_id);
        exit;
    }
}

// Obter ID do usuário a ser editado
$usuario_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Buscar dados do usuário
if ($usuario_id > 0) {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['erro'] = "Usuário não encontrado.";
        header("Location: usuarios.php");
        exit;
    }
    
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar permissões existentes
    $stmt = $db->prepare("SELECT * FROM permissoes WHERE usuario_id = :usuario_id");
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $permissoes = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Permissões padrão (nenhuma habilitada)
        $permissoes = [
            'gerenciar_usuarios' => false,
            'visualizar_relatorios_financeiros' => false,
            'gerenciar_estoque' => false,
            'gerenciar_produtos' => false,
            'gerenciar_orcamentos' => false,
            'gerenciar_vendas' => false,
            'gerenciar_clientes' => false,
            'gerenciar_caixa' => false,
            'gerenciar_contas' => false,
            'visualizar_relatorios_gerais' => false
        ];
    }
} else {
    // Se não há ID, redirecionar para a lista de usuários
    header("Location: usuarios.php");
    exit;
}

require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user-shield me-2"></i>Gerenciar Permissões</h1>
    <a href="usuarios.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Voltar para Usuários
    </a>
</div>

<?php
// Exibir mensagens de sucesso ou erro
if (isset($_SESSION['sucesso'])) {
    echo alerta($_SESSION['sucesso'], 'success');
    unset($_SESSION['sucesso']);
}

if (isset($_SESSION['erro'])) {
    echo alerta($_SESSION['erro'], 'danger');
    unset($_SESSION['erro']);
}
?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Permissões para: <?php echo htmlspecialchars($usuario['nome']); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($usuario['nivel'] === 'admin'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Administrador!</strong> Este usuário é um administrador e já possui todas as permissões.
            </div>
        <?php else: ?>
            <form method="post" action="usuario_permissoes.php">
                <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="border-bottom pb-2">Permissões do Sistema</h5>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_usuarios" name="gerenciar_usuarios" <?php echo ($permissoes['gerenciar_usuarios'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_usuarios">
                                <strong>Gerenciar Usuários</strong><br>
                                <small class="text-muted">Permite cadastrar, editar e gerenciar usuários do sistema</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_clientes" name="gerenciar_clientes" <?php echo ($permissoes['gerenciar_clientes'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_clientes">
                                <strong>Gerenciar Clientes</strong><br>
                                <small class="text-muted">Permite cadastrar, editar e gerenciar clientes</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_produtos" name="gerenciar_produtos" <?php echo ($permissoes['gerenciar_produtos'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_produtos">
                                <strong>Gerenciar Produtos</strong><br>
                                <small class="text-muted">Permite cadastrar, editar e gerenciar produtos</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_estoque" name="gerenciar_estoque" <?php echo ($permissoes['gerenciar_estoque'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_estoque">
                                <strong>Gerenciar Estoque</strong><br>
                                <small class="text-muted">Permite gerenciar entrada e saída de produtos no estoque</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_contas" name="gerenciar_contas" <?php echo ($permissoes['gerenciar_contas'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_contas">
                                <strong>Gerenciar Contas a Pagar</strong><br>
                                <small class="text-muted">Permite registrar e gerenciar contas a pagar</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="border-bottom pb-2">Operações do Negócio</h5>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_orcamentos" name="gerenciar_orcamentos" <?php echo ($permissoes['gerenciar_orcamentos'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_orcamentos">
                                <strong>Gerenciar Orçamentos</strong><br>
                                <small class="text-muted">Permite criar e gerenciar orçamentos para clientes</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_vendas" name="gerenciar_vendas" <?php echo ($permissoes['gerenciar_vendas'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_vendas">
                                <strong>Gerenciar Vendas</strong><br>
                                <small class="text-muted">Permite registrar e gerenciar vendas diretas</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="gerenciar_caixa" name="gerenciar_caixa" <?php echo ($permissoes['gerenciar_caixa'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="gerenciar_caixa">
                                <strong>Gerenciar Caixa</strong><br>
                                <small class="text-muted">Permite abrir/fechar caixa e registrar operações financeiras</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="visualizar_relatorios_gerais" name="visualizar_relatorios_gerais" <?php echo ($permissoes['visualizar_relatorios_gerais'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="visualizar_relatorios_gerais">
                                <strong>Visualizar Relatórios Gerais</strong><br>
                                <small class="text-muted">Permite acessar relatórios gerais de vendas, estoque, etc.</small>
                            </label>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="visualizar_relatorios_financeiros" name="visualizar_relatorios_financeiros" <?php echo ($permissoes['visualizar_relatorios_financeiros'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="visualizar_relatorios_financeiros">
                                <strong>Visualizar Relatórios Financeiros</strong><br>
                                <small class="text-muted">Permite acessar relatórios financeiros detalhados (lucro, margem, etc)</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar Permissões
                    </button>
                    <button type="button" class="btn btn-outline-success" id="marcarTodos">
                        <i class="fas fa-check-double me-2"></i>Marcar Todos
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="desmarcarTodos">
                        <i class="fas fa-times me-2"></i>Desmarcar Todos
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Botões para marcar/desmarcar todas as permissões
    const marcarTodosBtn = document.getElementById('marcarTodos');
    const desmarcarTodosBtn = document.getElementById('desmarcarTodos');
    
    if (marcarTodosBtn) {
        marcarTodosBtn.addEventListener('click', function() {
            document.querySelectorAll('.form-check-input').forEach(function(checkbox) {
                checkbox.checked = true;
            });
        });
    }
    
    if (desmarcarTodosBtn) {
        desmarcarTodosBtn.addEventListener('click', function() {
            document.querySelectorAll('.form-check-input').forEach(function(checkbox) {
                checkbox.checked = false;
            });
        });
    }
});
</script>

<?php require_once('includes/footer.php'); ?>