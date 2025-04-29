<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Inicializar variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$cliente = [
    'id' => 0,
    'nome' => '',
    'tipo' => 'pf',
    'cpf_cnpj' => '',
    'telefone' => '',
    'email' => '',
    'endereco' => '',
    'numero' => '',
    'complemento' => '',
    'bairro' => '',
    'cidade' => '',
    'estado' => '',
    'cep' => '',
    'observacoes' => ''
];
$erro = '';
$sucesso = '';
$titulo = 'Cadastrar Novo Cliente';
$modo = 'cadastrar';

// Se for edição, buscar dados do cliente
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = 'Editar Cliente';
        $modo = 'editar';
    } else {
        $erro = 'Cliente não encontrado.';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturar dados do formulário
    $cliente = [
        'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
        'nome' => limpaString($_POST['nome'] ?? ''),
        'tipo' => limpaString($_POST['tipo'] ?? 'pf'),
        'cpf_cnpj' => limpaString($_POST['cpf_cnpj'] ?? ''),
        'telefone' => limpaString($_POST['telefone'] ?? ''),
        'email' => limpaString($_POST['email'] ?? ''),
        'endereco' => limpaString($_POST['endereco'] ?? ''),
        'numero' => limpaString($_POST['numero'] ?? ''),
        'complemento' => limpaString($_POST['complemento'] ?? ''),
        'bairro' => limpaString($_POST['bairro'] ?? ''),
        'cidade' => limpaString($_POST['cidade'] ?? ''),
        'estado' => limpaString($_POST['estado'] ?? ''),
        'cep' => limpaString($_POST['cep'] ?? ''),
        'observacoes' => limpaString($_POST['observacoes'] ?? '')
    ];
    
    // Validações
    if (empty($cliente['nome'])) {
        $erro = 'O nome do cliente é obrigatório.';
    } elseif (empty($cliente['cpf_cnpj'])) {
        $erro = 'O CPF ou CNPJ é obrigatório.';
    } elseif ($cliente['tipo'] == 'pf' && !validaCPF($cliente['cpf_cnpj'])) {
        $erro = 'CPF inválido.';
    } elseif ($cliente['tipo'] == 'pj' && !validaCNPJ($cliente['cpf_cnpj'])) {
        $erro = 'CNPJ inválido.';
    } elseif (empty($cliente['telefone'])) {
        $erro = 'O telefone é obrigatório.';
    } else {
        // Verificar se CPF/CNPJ já está cadastrado para outro cliente
        $stmt = $db->prepare("SELECT id FROM clientes WHERE cpf_cnpj = :cpf_cnpj AND id != :id");
        $stmt->bindParam(':cpf_cnpj', $cliente['cpf_cnpj']);
        $stmt->bindParam(':id', $cliente['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $erro = 'Este CPF/CNPJ já está cadastrado para outro cliente.';
        } else {
            try {
                // Inserir ou atualizar cliente
                if ($cliente['id'] > 0) {
                    // Atualizar
                    $stmt = $db->prepare("UPDATE clientes SET 
                        nome = :nome,
                        tipo = :tipo,
                        cpf_cnpj = :cpf_cnpj,
                        telefone = :telefone,
                        email = :email,
                        endereco = :endereco,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        estado = :estado,
                        cep = :cep,
                        observacoes = :observacoes
                        WHERE id = :id");
                    $stmt->bindParam(':id', $cliente['id'], PDO::PARAM_INT);
                    $mensagem = 'atualizado';
                } else {
                    // Inserir
                    $stmt = $db->prepare("INSERT INTO clientes (
                        nome, tipo, cpf_cnpj, telefone, email, endereco, numero, 
                        complemento, bairro, cidade, estado, cep, observacoes
                    ) VALUES (
                        :nome, :tipo, :cpf_cnpj, :telefone, :email, :endereco, :numero,
                        :complemento, :bairro, :cidade, :estado, :cep, :observacoes
                    )");
                    $mensagem = 'cadastrado';
                }
                
                // Bind de parâmetros
                $stmt->bindParam(':nome', $cliente['nome']);
                $stmt->bindParam(':tipo', $cliente['tipo']);
                $stmt->bindParam(':cpf_cnpj', $cliente['cpf_cnpj']);
                $stmt->bindParam(':telefone', $cliente['telefone']);
                $stmt->bindParam(':email', $cliente['email']);
                $stmt->bindParam(':endereco', $cliente['endereco']);
                $stmt->bindParam(':numero', $cliente['numero']);
                $stmt->bindParam(':complemento', $cliente['complemento']);
                $stmt->bindParam(':bairro', $cliente['bairro']);
                $stmt->bindParam(':cidade', $cliente['cidade']);
                $stmt->bindParam(':estado', $cliente['estado']);
                $stmt->bindParam(':cep', $cliente['cep']);
                $stmt->bindParam(':observacoes', $cliente['observacoes']);
                
                $stmt->execute();
                
                // Redirecionar para a listagem de clientes
                header("Location: clientes.php?mensagem={$mensagem}");
                exit;
                
            } catch (Exception $e) {
                $erro = 'Erro ao salvar cliente: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user me-2"></i><?php echo $titulo; ?></h1>
    <a href="clientes.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-user-edit me-2"></i>Formulário de Cliente
    </div>
    <div class="card-body">
        <form method="post" action="cliente_form.php" id="formCliente">
            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="nome" class="form-label">Nome/Razão Social <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $cliente['nome']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="pf" <?php echo $cliente['tipo'] == 'pf' ? 'selected' : ''; ?>>Pessoa Física</option>
                        <option value="pj" <?php echo $cliente['tipo'] == 'pj' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="cpf_cnpj" class="form-label">CPF/CNPJ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo $cliente['cpf_cnpj']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="telefone" class="form-label">Telefone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo $cliente['telefone']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $cliente['email']; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="endereco" class="form-label">Endereço</label>
                    <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo $cliente['endereco']; ?>">
                </div>
                <div class="col-md-2">
                    <label for="numero" class="form-label">Número</label>
                    <input type="text" class="form-control" id="numero" name="numero" value="<?php echo $cliente['numero']; ?>">
                </div>
                <div class="col-md-4">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" class="form-control" id="complemento" name="complemento" value="<?php echo $cliente['complemento']; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo $cliente['bairro']; ?>">
                </div>
                <div class="col-md-3">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo $cliente['cidade']; ?>">
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Selecione...</option>
                        <option value="AC" <?php echo $cliente['estado'] == 'AC' ? 'selected' : ''; ?>>Acre</option>
                        <option value="AL" <?php echo $cliente['estado'] == 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                        <option value="AP" <?php echo $cliente['estado'] == 'AP' ? 'selected' : ''; ?>>Amapá</option>
                        <option value="AM" <?php echo $cliente['estado'] == 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                        <option value="BA" <?php echo $cliente['estado'] == 'BA' ? 'selected' : ''; ?>>Bahia</option>
                        <option value="CE" <?php echo $cliente['estado'] == 'CE' ? 'selected' : ''; ?>>Ceará</option>
                        <option value="DF" <?php echo $cliente['estado'] == 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                        <option value="ES" <?php echo $cliente['estado'] == 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                        <option value="GO" <?php echo $cliente['estado'] == 'GO' ? 'selected' : ''; ?>>Goiás</option>
                        <option value="MA" <?php echo $cliente['estado'] == 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                        <option value="MT" <?php echo $cliente['estado'] == 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                        <option value="MS" <?php echo $cliente['estado'] == 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                        <option value="MG" <?php echo $cliente['estado'] == 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                        <option value="PA" <?php echo $cliente['estado'] == 'PA' ? 'selected' : ''; ?>>Pará</option>
                        <option value="PB" <?php echo $cliente['estado'] == 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                        <option value="PR" <?php echo $cliente['estado'] == 'PR' ? 'selected' : ''; ?>>Paraná</option>
                        <option value="PE" <?php echo $cliente['estado'] == 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                        <option value="PI" <?php echo $cliente['estado'] == 'PI' ? 'selected' : ''; ?>>Piauí</option>
                        <option value="RJ" <?php echo $cliente['estado'] == 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                        <option value="RN" <?php echo $cliente['estado'] == 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                        <option value="RS" <?php echo $cliente['estado'] == 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                        <option value="RO" <?php echo $cliente['estado'] == 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                        <option value="RR" <?php echo $cliente['estado'] == 'RR' ? 'selected' : ''; ?>>Roraima</option>
                        <option value="SC" <?php echo $cliente['estado'] == 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                        <option value="SP" <?php echo $cliente['estado'] == 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                        <option value="SE" <?php echo $cliente['estado'] == 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                        <option value="TO" <?php echo $cliente['estado'] == 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="cep" class="form-label">CEP</label>
                    <input type="text" class="form-control" id="cep" name="cep" value="<?php echo $cliente['cep']; ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $cliente['observacoes']; ?></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $modo == 'cadastrar' ? 'Cadastrar' : 'Atualizar'; ?> Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formatação de CPF/CNPJ
    const tipoPessoa = document.getElementById('tipo');
    const cpfCnpj = document.getElementById('cpf_cnpj');
    
    function formatarCpfCnpj() {
        let valor = cpfCnpj.value.replace(/\D/g, '');
        
        if (tipoPessoa.value === 'pf') {
            // Formatar CPF
            if (valor.length > 11) {
                valor = valor.substring(0, 11);
            }
            
            if (valor.length > 9) {
                cpfCnpj.value = valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            } else if (valor.length > 6) {
                cpfCnpj.value = valor.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            } else if (valor.length > 3) {
                cpfCnpj.value = valor.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            } else {
                cpfCnpj.value = valor;
            }
        } else {
            // Formatar CNPJ
            if (valor.length > 14) {
                valor = valor.substring(0, 14);
            }
            
            if (valor.length > 12) {
                cpfCnpj.value = valor.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            } else if (valor.length > 8) {
                cpfCnpj.value = valor.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
            } else if (valor.length > 5) {
                cpfCnpj.value = valor.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
            } else if (valor.length > 2) {
                cpfCnpj.value = valor.replace(/(\d{2})(\d{1,3})/, '$1.$2');
            } else {
                cpfCnpj.value = valor;
            }
        }
    }
    
    // Formatação de telefone
    const telefone = document.getElementById('telefone');
    
    function formatarTelefone() {
        let valor = telefone.value.replace(/\D/g, '');
        
        if (valor.length > 11) {
            valor = valor.substring(0, 11);
        }
        
        if (valor.length > 10) {
            telefone.value = valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (valor.length > 6) {
            telefone.value = valor.replace(/(\d{2})(\d{4})(\d{1,4})/, '($1) $2-$3');
        } else if (valor.length > 2) {
            telefone.value = valor.replace(/(\d{2})(\d{1,5})/, '($1) $2');
        } else {
            telefone.value = valor;
        }
    }
    
    // Formatação de CEP
    const cep = document.getElementById('cep');
    
    function formatarCep() {
        let valor = cep.value.replace(/\D/g, '');
        
        if (valor.length > 8) {
            valor = valor.substring(0, 8);
        }
        
        if (valor.length > 5) {
            cep.value = valor.replace(/(\d{5})(\d{1,3})/, '$1-$2');
        } else {
            cep.value = valor;
        }
    }
    
    // Eventos de mudança e input
    tipoPessoa.addEventListener('change', formatarCpfCnpj);
    cpfCnpj.addEventListener('input', formatarCpfCnpj);
    telefone.addEventListener('input', formatarTelefone);
    cep.addEventListener('input', formatarCep);
    
    // Formatar valores iniciais
    formatarCpfCnpj();
    formatarTelefone();
    formatarCep();
});
</script>

<?php
require_once('includes/footer.php');
?>
