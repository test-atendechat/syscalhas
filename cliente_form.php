<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

verificarAutenticacao();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$cliente = [
    'id' => 0,
    'nome' => '',
    'cpf_cnpj' => '',
    'telefone' => '',
    'email' => '',
    'endereco' => '',
    'cidade' => '',
    'estado' => '',
    'cep' => '',
    'observacoes' => ''
];

$titulo = "Novo Cliente";
$acao = "cadastrar";

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = "Editar Cliente";
        $acao = "atualizar";
    } else {
        header('Location: clientes.php');
        exit;
    }
}

$mensagem = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter e validar dados
    $cliente['nome'] = limpaString($_POST['nome']);
    $cliente['cpf_cnpj'] = limpaString($_POST['cpf_cnpj']);
    $cliente['telefone'] = limpaString($_POST['telefone']);
    $cliente['email'] = limpaString($_POST['email']);
    $cliente['endereco'] = limpaString($_POST['endereco']);
    $cliente['cidade'] = limpaString($_POST['cidade']);
    $cliente['estado'] = limpaString($_POST['estado']);
    $cliente['cep'] = limpaString($_POST['cep']);
    $cliente['observacoes'] = limpaString($_POST['observacoes']);
    
    $erros = [];
    
    // Validar campos obrigatórios
    if (empty($cliente['nome'])) {
        $erros[] = "O nome do cliente é obrigatório";
    }
    
    if (empty($erros)) {
        try {
            if ($acao == 'cadastrar') {
                // Inserir novo cliente
                $stmt = $db->prepare("INSERT INTO clientes 
                                    (nome, cpf_cnpj, telefone, email, endereco, cidade, estado, cep, observacoes) 
                                    VALUES 
                                    (:nome, :cpf_cnpj, :telefone, :email, :endereco, :cidade, :estado, :cep, :observacoes)");
                $mensagem_sucesso = 'cadastrado';
            } else {
                // Atualizar cliente existente
                $stmt = $db->prepare("UPDATE clientes SET 
                                     nome = :nome, 
                                     cpf_cnpj = :cpf_cnpj, 
                                     telefone = :telefone, 
                                     email = :email, 
                                     endereco = :endereco, 
                                     cidade = :cidade, 
                                     estado = :estado, 
                                     cep = :cep, 
                                     observacoes = :observacoes 
                                     WHERE id = :id");
                $stmt->bindParam(':id', $cliente['id'], PDO::PARAM_INT);
                $mensagem_sucesso = 'atualizado';
            }
            
            $stmt->bindParam(':nome', $cliente['nome']);
            $stmt->bindParam(':cpf_cnpj', $cliente['cpf_cnpj']);
            $stmt->bindParam(':telefone', $cliente['telefone']);
            $stmt->bindParam(':email', $cliente['email']);
            $stmt->bindParam(':endereco', $cliente['endereco']);
            $stmt->bindParam(':cidade', $cliente['cidade']);
            $stmt->bindParam(':estado', $cliente['estado']);
            $stmt->bindParam(':cep', $cliente['cep']);
            $stmt->bindParam(':observacoes', $cliente['observacoes']);
            
            $stmt->execute();
            
            if ($acao == 'cadastrar') {
                $cliente_id = $db->lastInsertId();
                
                // Verificar se veio de um orçamento
                if (isset($_GET['orcamento']) && $_GET['orcamento'] == 1) {
                    header("Location: orcamento_form.php?cliente_id={$cliente_id}");
                    exit;
                }
            }
            
            header("Location: clientes.php?mensagem={$mensagem_sucesso}");
            exit;
        } catch (Exception $e) {
            $mensagem = alerta("Erro ao {$acao} cliente: " . $e->getMessage(), 'danger');
        }
    } else {
        $mensagem = alerta("Erros encontrados:<br>" . implode("<br>", $erros), 'danger');
    }
}

// Incluir o header apenas após processar o formulário (para evitar headers already sent)
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user me-2"></i><?php echo $titulo; ?></h1>
    <a href="clientes.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card">
    <div class="card-body">
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $cliente['nome']; ?>" required>
            </div>
            
            <div class="col-md-3">
                <label for="cpf_cnpj" class="form-label">CPF/CNPJ</label>
                <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo $cliente['cpf_cnpj']; ?>">
            </div>
            
            <div class="col-md-3">
                <label for="telefone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo $cliente['telefone']; ?>">
            </div>
            
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo $cliente['email']; ?>">
            </div>
            
            <div class="col-md-6">
                <label for="endereco" class="form-label">Endereço</label>
                <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo $cliente['endereco']; ?>">
            </div>
            
            <div class="col-md-4">
                <label for="cidade" class="form-label">Cidade</label>
                <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo $cliente['cidade']; ?>">
            </div>
            
            <div class="col-md-4">
                <label for="estado" class="form-label">Estado</label>
                <input type="text" class="form-control" id="estado" name="estado" value="<?php echo $cliente['estado']; ?>">
            </div>
            
            <div class="col-md-4">
                <label for="cep" class="form-label">CEP</label>
                <input type="text" class="form-control" id="cep" name="cep" value="<?php echo $cliente['cep']; ?>">
            </div>
            
            <div class="col-12">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $cliente['observacoes']; ?></textarea>
            </div>
            
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo ucfirst($acao); ?> Cliente
                </button>
                <a href="clientes.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar máscaras
    if (typeof IMask !== 'undefined') {
        // Máscara para telefone
        const telefoneMask = IMask(document.getElementById('telefone'), {
            mask: '(00) 00000-0000'
        });
        
        // Máscara para CEP
        const cepMask = IMask(document.getElementById('cep'), {
            mask: '00000-000'
        });
        
        // Máscara para CPF/CNPJ dinâmico
        const cpfCnpjMask = IMask(document.getElementById('cpf_cnpj'), {
            mask: [
                {
                    mask: '000.000.000-00',
                    maxLength: 11
                },
                {
                    mask: '00.000.000/0000-00',
                    maxLength: 14
                }
            ],
            dispatch: function (appended, dynamicMasked) {
                const value = (dynamicMasked.value + appended).replace(/\D/g, '');
                return dynamicMasked.compiledMasks.find(function (m) {
                    return value.length <= m.maxLength;
                });
            }
        });
    }
});
</script>

<?php require_once('includes/footer.php'); ?>