<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if (!verificarPermissao('gerenciar_colaboradores')) {
    header('Location: dashboard.php?erro=sempermissao');
    exit;
}

// Inicialização de variáveis
$id = 0;
$instalador = null;
$mensagem = '';
$auxiliares_disponiveis = [];

// Verificar ID do instalador
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Buscar dados do instalador
    try {
        $stmt = $db->prepare("SELECT * FROM colaboradores WHERE id = :id AND tipo = 'instalador'");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $instalador = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$instalador) {
            header('Location: colaboradores.php?erro=naoinstalador');
            exit;
        }
    } catch (PDOException $e) {
        $mensagem = alerta('Erro ao buscar dados do instalador: ' . $e->getMessage(), 'danger');
    }
} else {
    header('Location: colaboradores.php');
    exit;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    
    // Adicionar auxiliar à equipe
    if ($acao === 'adicionar' && isset($_POST['auxiliar_id'])) {
        $auxiliar_id = intval($_POST['auxiliar_id']);
        
        try {
            // Verificar se o auxiliar já está em uma equipe ativa
            $stmt = $db->prepare("SELECT * FROM colaborador_equipe WHERE auxiliar_id = :auxiliar_id AND data_fim IS NULL");
            $stmt->bindParam(':auxiliar_id', $auxiliar_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $mensagem = alerta('Este auxiliar já está em outra equipe. Finalize sua participação primeiro.', 'warning');
            } else {
                // Adicionar à equipe
                $stmt = $db->prepare("INSERT INTO colaborador_equipe (instalador_id, auxiliar_id, usuario_id) VALUES (:instalador_id, :auxiliar_id, :usuario_id)");
                $stmt->bindParam(':instalador_id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':auxiliar_id', $auxiliar_id, PDO::PARAM_INT);
                $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                $stmt->execute();
                
                $mensagem = alerta('Auxiliar adicionado à equipe com sucesso!', 'success');
            }
        } catch (PDOException $e) {
            $mensagem = alerta('Erro ao adicionar auxiliar à equipe: ' . $e->getMessage(), 'danger');
        }
    }
    
    // Finalizar participação do auxiliar
    if ($acao === 'finalizar' && isset($_POST['equipe_id'])) {
        $equipe_id = intval($_POST['equipe_id']);
        
        try {
            $stmt = $db->prepare("UPDATE colaborador_equipe SET data_fim = CURRENT_DATE WHERE id = :equipe_id AND instalador_id = :instalador_id AND data_fim IS NULL");
            $stmt->bindParam(':equipe_id', $equipe_id, PDO::PARAM_INT);
            $stmt->bindParam(':instalador_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $mensagem = alerta('Auxiliar removido da equipe com sucesso!', 'success');
            } else {
                $mensagem = alerta('Não foi possível remover o auxiliar da equipe.', 'warning');
            }
        } catch (PDOException $e) {
            $mensagem = alerta('Erro ao remover auxiliar da equipe: ' . $e->getMessage(), 'danger');
        }
    }
}

// Buscar membros atuais da equipe
try {
    $stmt = $db->prepare("SELECT e.id, e.data_inicio, c.id as auxiliar_id, c.nome, c.cpf, c.telefone
                          FROM colaborador_equipe e 
                          JOIN colaboradores c ON e.auxiliar_id = c.id 
                          WHERE e.instalador_id = :instalador_id AND e.data_fim IS NULL
                          ORDER BY e.data_inicio ASC");
    $stmt->bindParam(':instalador_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $equipe_atual = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = alerta('Erro ao buscar membros da equipe: ' . $e->getMessage(), 'danger');
    $equipe_atual = [];
}

// Buscar histórico de membros da equipe
try {
    $stmt = $db->prepare("SELECT e.id, e.data_inicio, e.data_fim, c.nome, c.cpf
                          FROM colaborador_equipe e 
                          JOIN colaboradores c ON e.auxiliar_id = c.id 
                          WHERE e.instalador_id = :instalador_id AND e.data_fim IS NOT NULL
                          ORDER BY e.data_fim DESC, e.data_inicio DESC");
    $stmt->bindParam(':instalador_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $historico_equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = alerta('Erro ao buscar histórico da equipe: ' . $e->getMessage(), 'danger');
    $historico_equipe = [];
}

// Buscar auxiliares disponíveis (não estão em nenhuma equipe atualmente)
try {
    $stmt = $db->prepare("SELECT c.id, c.nome, c.cpf 
                          FROM colaboradores c 
                          WHERE c.tipo = 'auxiliar' AND c.status = 'ativo' 
                          AND c.id NOT IN (
                              SELECT ce.auxiliar_id FROM colaborador_equipe ce WHERE ce.data_fim IS NULL
                          )
                          ORDER BY c.nome ASC");
    $stmt->execute();
    
    $auxiliares_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = alerta('Erro ao buscar auxiliares disponíveis: ' . $e->getMessage(), 'danger');
    $auxiliares_disponiveis = [];
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-users me-2"></i>Equipe de <?php echo $instalador['nome']; ?></h1>
    <div>
        <a href="colaboradores.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar para Colaboradores
        </a>
        <a href="colaborador_form.php?id=<?php echo $id; ?>" class="btn btn-primary ms-2">
            <i class="fas fa-edit me-2"></i>Editar Instalador
        </a>
    </div>
</div>

<?php echo $mensagem; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-id-card me-2"></i>Dados do Instalador</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Nome:</dt>
                    <dd class="col-sm-8"><?php echo $instalador['nome']; ?></dd>
                    
                    <dt class="col-sm-4">CPF:</dt>
                    <dd class="col-sm-8"><?php echo !empty($instalador['cpf']) ? $instalador['cpf'] : '-'; ?></dd>
                    
                    <dt class="col-sm-4">Telefone:</dt>
                    <dd class="col-sm-8"><?php echo !empty($instalador['telefone']) ? $instalador['telefone'] : '-'; ?></dd>
                    
                    <dt class="col-sm-4">Admissão:</dt>
                    <dd class="col-sm-8"><?php echo date('d/m/Y', strtotime($instalador['data_admissao'])); ?></dd>
                    
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $instalador['status'] === 'ativo' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($instalador['status']); ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
        
        <?php if (count($auxiliares_disponiveis) > 0): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-plus me-2"></i>Adicionar à Equipe</h5>
            </div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="acao" value="adicionar">
                    
                    <div class="mb-3">
                        <label for="auxiliar_id" class="form-label">Selecione um Auxiliar</label>
                        <select class="form-select" id="auxiliar_id" name="auxiliar_id" required>
                            <option value="">Selecione um auxiliar...</option>
                            <?php foreach ($auxiliares_disponiveis as $auxiliar): ?>
                                <option value="<?php echo $auxiliar['id']; ?>"><?php echo $auxiliar['nome']; ?> <?php echo !empty($auxiliar['cpf']) ? ' - ' . $auxiliar['cpf'] : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Por favor, selecione um auxiliar.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus-circle me-2"></i>Adicionar à Equipe
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-8">
        <!-- Membros atuais da equipe -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>Membros Atuais da Equipe</h5>
            </div>
            <div class="card-body">
                <?php if (count($equipe_atual) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Telefone</th>
                                    <th>Data Início</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipe_atual as $membro): ?>
                                    <tr>
                                        <td><?php echo $membro['nome']; ?></td>
                                        <td><?php echo !empty($membro['cpf']) ? $membro['cpf'] : '-'; ?></td>
                                        <td><?php echo !empty($membro['telefone']) ? $membro['telefone'] : '-'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($membro['data_inicio'])); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmarFinalizacao(<?php echo $membro['id']; ?>, '<?php echo addslashes($membro['nome']); ?>')">
                                                <i class="fas fa-user-minus"></i> Remover
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Não há auxiliares na equipe atualmente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Histórico da equipe -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Histórico da Equipe</h5>
            </div>
            <div class="card-body">
                <?php if (count($historico_equipe) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Período</th>
                                    <th>Duração</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico_equipe as $membro): ?>
                                    <?php 
                                    $data_inicio = new DateTime($membro['data_inicio']);
                                    $data_fim = new DateTime($membro['data_fim']);
                                    $duracao = $data_inicio->diff($data_fim);
                                    
                                    $periodo_texto = $data_inicio->format('d/m/Y') . ' a ' . $data_fim->format('d/m/Y');
                                    
                                    // Formatar duração
                                    if ($duracao->y > 0) {
                                        $duracao_texto = $duracao->y . ' ano(s), ' . $duracao->m . ' mes(es)'; 
                                    } elseif ($duracao->m > 0) {
                                        $duracao_texto = $duracao->m . ' mes(es), ' . $duracao->d . ' dia(s)';
                                    } else {
                                        $duracao_texto = $duracao->d . ' dia(s)';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $membro['nome']; ?></td>
                                        <td><?php echo !empty($membro['cpf']) ? $membro['cpf'] : '-'; ?></td>
                                        <td><?php echo $periodo_texto; ?></td>
                                        <td><?php echo $duracao_texto; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Não há histórico de auxiliares nesta equipe.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação para finalizar participação -->
<div class="modal fade" id="modalFinalizacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Remoção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja remover <strong id="nomeAuxiliar"></strong> da equipe?</p>
                <p class="text-muted">Esta ação irá registrar a data atual como data de saída da equipe.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" id="formFinalizacao">
                    <input type="hidden" name="acao" value="finalizar">
                    <input type="hidden" name="equipe_id" id="equipe_id" value="">
                    <button type="submit" class="btn btn-danger">Confirmar Remoção</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarFinalizacao(equipeId, nomeAuxiliar) {
    document.getElementById('nomeAuxiliar').textContent = nomeAuxiliar;
    document.getElementById('equipe_id').value = equipeId;
    
    var modal = new bootstrap.Modal(document.getElementById('modalFinalizacao'));
    modal.show();
}

// Script para validar os formulários
(function() {
    'use strict';
    
    // Fetch all forms to which we want to apply validation
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php
require_once('includes/footer.php');
?>