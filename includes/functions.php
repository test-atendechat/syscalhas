<?php
require_once('config.php');
require_once('db.php');

/**
 * Formata valor como moeda brasileira
 * 
 * @param float $valor Valor a ser formatado
 * @return string Valor formatado como R$ 0,00
 */
function formataValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Converte uma data do formato brasileiro para o formato do MySQL
 * 
 * @param string $data Data no formato dd/mm/aaaa
 * @return string Data no formato aaaa-mm-dd
 */
function dataParaMysql($data) {
    if (!$data) return null;
    $partes = explode('/', $data);
    if (count($partes) != 3) return $data;
    return "{$partes[2]}-{$partes[1]}-{$partes[0]}";
}

/**
 * Converte uma data do formato MySQL para o formato brasileiro
 * 
 * @param string $data Data no formato aaaa-mm-dd
 * @return string Data no formato dd/mm/aaaa
 */
function dataParaBr($data) {
    if (!$data || $data == '0000-00-00') return '';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

/**
 * Limpa string para evitar SQL injection e XSS
 * 
 * @param string $str String a ser limpa
 * @return string String limpa
 */
function limpaString($str) {
    $str = trim($str);
    $str = stripslashes($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    return $str;
}

/**
 * Gera um slug a partir de uma string
 * 
 * @param string $str String a ser convertida em slug
 * @return string Slug
 */
function gerarSlug($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-z0-9]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    $str = trim($str, '-');
    return $str;
}

/**
 * Verifica se uma string é um CPF válido
 * 
 * @param string $cpf CPF a ser validado
 * @return boolean
 */
function validaCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se o CPF tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica se os dígitos verificadores estão corretos
    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

/**
 * Verifica se uma string é um CNPJ válido
 * 
 * @param string $cnpj CNPJ a ser validado
 * @return boolean
 */
function validaCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se o CNPJ tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    $multiplicador = 5;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    $multiplicador = 6;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica se os dígitos verificadores estão corretos
    return ($cnpj[12] == $digito1 && $cnpj[13] == $digito2);
}

/**
 * Verifica o status de um orçamento e retorna a classe CSS correspondente
 * 
 * @param string $status Status do orçamento
 * @return string Classe CSS
 */
function statusOrcamentoClasse($status) {
    switch ($status) {
        case 'pendente':
            return 'text-warning';
        case 'aprovado':
            return 'text-success';
        case 'rejeitado':
            return 'text-danger';
        default:
            return '';
    }
}

/**
 * Gera um número único para orçamento
 * 
 * @return string Número de orçamento no formato ANO-MÊS-SEQUENCIAL
 */
function gerarNumeroOrcamento() {
    global $db;
    
    $ano = date('Y');
    $mes = date('m');
    
    $stmt = $db->prepare("SELECT MAX(SUBSTRING_INDEX(numero, '-', -1)) as ultimo FROM orcamentos WHERE numero LIKE :padrao");
    $padrao = $ano . '-' . $mes . '-%';
    $stmt->bindParam(':padrao', $padrao);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimo = (int)$resultado['ultimo'];
    $proximo = $ultimo + 1;
    
    return $ano . '-' . $mes . '-' . str_pad($proximo, 3, '0', STR_PAD_LEFT);
}

/**
 * Busca cliente pelo ID
 * 
 * @param int $id ID do cliente
 * @return array|boolean Dados do cliente ou false se não encontrado
 */
function buscarCliente($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Busca produto pelo ID
 * 
 * @param int $id ID do produto
 * @return array|boolean Dados do produto ou false se não encontrado
 */
function buscarProduto($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Busca orçamento pelo ID
 * 
 * @param int $id ID do orçamento
 * @return array|boolean Dados do orçamento ou false se não encontrado
 */
function buscarOrcamento($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome, c.telefone as cliente_telefone, c.email as cliente_email
                           FROM orcamentos o
                           LEFT JOIN clientes c ON o.cliente_id = c.id
                           WHERE o.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Busca itens de um orçamento
 * 
 * @param int $orcamento_id ID do orçamento
 * @return array Itens do orçamento
 */
function buscarItensOrcamento($orcamento_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT i.*, p.descricao as produto_descricao
                           FROM orcamento_itens i
                           LEFT JOIN produtos p ON i.produto_id = p.id
                           WHERE i.orcamento_id = :orcamento_id
                           ORDER BY i.id");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcula total do orçamento baseado nos itens
 * 
 * @param int $orcamento_id ID do orçamento
 * @return float Valor total do orçamento
 */
function calcularTotalOrcamento($orcamento_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT SUM(quantidade * valor_unitario) as total 
                           FROM orcamento_itens 
                           WHERE orcamento_id = :orcamento_id");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return floatval($resultado['total']);
}

/**
 * Gera mensagem de alerta
 * 
 * @param string $mensagem Mensagem a ser exibida
 * @param string $tipo Tipo de alerta (success, warning, danger, info)
 * @return string HTML do alerta
 */
function alerta($mensagem, $tipo = 'info') {
    return '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">
                ' . $mensagem . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>';
}
?>
