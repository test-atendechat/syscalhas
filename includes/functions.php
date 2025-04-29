<?php
/**
 * Arquivo com funções utilitárias para o sistema
 */

/**
 * Exibe um alerta formatado em HTML
 * 
 * @param string $mensagem Texto da mensagem
 * @param string $tipo Tipo do alerta (success, danger, warning, info)
 * @return string HTML do alerta
 */
function alerta($mensagem, $tipo = 'info') {
    return '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">
                ' . $mensagem . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>';
}

/**
 * Função para limpar strings de entrada
 * 
 * @param string $str String a ser limpa
 * @return string String limpa
 */
function limpaString($str) {
    if (is_null($str)) return '';
    return trim(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
}

/**
 * Formata um valor para exibição como moeda
 * 
 * @param float $valor Valor a ser formatado
 * @param string $simbolo Símbolo da moeda
 * @return string Valor formatado
 */
function formataValor($valor, $simbolo = 'R$') {
    return $simbolo . ' ' . number_format($valor, 2, ',', '.');
}

/**
 * Converte uma data no formato brasileiro (dd/mm/aaaa) para MySQL (aaaa-mm-dd)
 * 
 * @param string $data Data no formato brasileiro
 * @return string Data no formato MySQL ou null se inválida
 */
function dataParaMysql($data) {
    if (empty($data)) return null;
    
    $partes = explode('/', $data);
    if (count($partes) != 3) return null;
    
    return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
}

/**
 * Converte uma data do formato MySQL (aaaa-mm-dd) para brasileiro (dd/mm/aaaa)
 * 
 * @param string $data Data no formato MySQL
 * @return string Data no formato brasileiro ou string vazia se inválida
 */
function dataParaBr($data) {
    if (empty($data)) return '';
    
    $timestamp = strtotime($data);
    if ($timestamp === false) return '';
    
    return date('d/m/Y', $timestamp);
}

/**
 * Retorna a classe CSS baseada no status do orçamento
 * 
 * @param string $status Status do orçamento
 * @return string Classe CSS correspondente
 */
function statusOrcamentoClasse($status) {
    switch ($status) {
        case 'pendente':
            return 'status-box status-pendente';
        case 'aprovado':
            return 'status-box status-aprovado';
        case 'rejeitado':
            return 'status-box status-rejeitado';
        default:
            return 'status-box';
    }
}

/**
 * Converte o número do mês para o nome em português
 * 
 * @param string $mes Número do mês (01 a 12)
 * @return string Nome do mês em português
 */
function mesParaPtBr($mes) {
    $meses = [
        '01' => 'Janeiro',
        '02' => 'Fevereiro',
        '03' => 'Março',
        '04' => 'Abril',
        '05' => 'Maio',
        '06' => 'Junho',
        '07' => 'Julho',
        '08' => 'Agosto',
        '09' => 'Setembro',
        '10' => 'Outubro',
        '11' => 'Novembro',
        '12' => 'Dezembro'
    ];
    
    return isset($meses[$mes]) ? $meses[$mes] : $mes;
}

/**
 * Converte o código da forma de pagamento para texto legível
 * 
 * @param string $forma Código da forma de pagamento
 * @return string Texto legível da forma de pagamento
 */
function formaPagamentoParaTexto($forma) {
    $formas = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'PIX',
        'debito' => 'Cartão de Débito',
        'credito' => 'Cartão de Crédito',
        'transferencia' => 'Transferência Bancária',
        'boleto' => 'Boleto',
        'cheque' => 'Cheque',
        'outro' => 'Outro'
    ];
    
    return isset($formas[$forma]) ? $formas[$forma] : $forma;
}

/**
 * Gera um número único para orçamento no formato ANO/MÊS/SEQUENCIAL
 * 
 * @return string Número do orçamento
 */
function gerarNumeroOrcamento() {
    global $db;
    
    $ano = date('Y');
    $mes = date('m');
    $prefixo = "{$ano}/{$mes}/";
    
    // Buscar o último número de orçamento com este prefixo
    $stmt = $db->prepare("SELECT numero FROM orcamentos WHERE numero LIKE :prefixo ORDER BY id DESC LIMIT 1");
    $busca = $prefixo . '%';
    $stmt->bindParam(':prefixo', $busca);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $ultimoNumero = $stmt->fetch(PDO::FETCH_COLUMN);
        $partes = explode('/', $ultimoNumero);
        $sequencial = intval($partes[2]) + 1;
    } else {
        $sequencial = 1;
    }
    
    // Formatar o sequencial com zeros à esquerda
    $sequencialFormatado = str_pad($sequencial, 4, '0', STR_PAD_LEFT);
    
    return "{$prefixo}{$sequencialFormatado}";
}

/**
 * Buscar um orçamento pelo ID
 * 
 * @param int $id ID do orçamento
 * @return array|false Dados do orçamento ou false se não encontrado
 */
function buscarOrcamento($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM orcamentos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Buscar itens de um orçamento
 * 
 * @param int $orcamento_id ID do orçamento
 * @return array Itens do orçamento
 */
function buscarItensOrcamento($orcamento_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM orcamento_itens WHERE orcamento_id = :orcamento_id ORDER BY id");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Buscar um cliente pelo ID
 * 
 * @param int $id ID do cliente
 * @return array|false Dados do cliente ou false se não encontrado
 */
function buscarCliente($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Buscar um produto pelo ID
 * 
 * @param int $id ID do produto
 * @return array|false Dados do produto ou false se não encontrado
 */
function buscarProduto($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return false;
}

/**
 * Calcular o total de um orçamento
 * 
 * @param int $orcamento_id ID do orçamento
 * @return float Valor total dos produtos do orçamento
 */
function calcularTotalOrcamento($orcamento_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT SUM(valor_total) as total FROM orcamento_itens WHERE orcamento_id = :orcamento_id");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return floatval($resultado['total']);
}

/**
 * Verificar se um CPF é válido
 * 
 * @param string $cpf CPF para validar
 * @return boolean
 */
function validaCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
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
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica se os dígitos calculados batem com os dígitos informados
    return ($cpf[9] == $dv1 && $cpf[10] == $dv2);
}

/**
 * Verificar se um CNPJ é válido
 * 
 * @param string $cnpj CNPJ para validar
 * @return boolean
 */
function validaCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
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
        $soma += intval($cnpj[$i]) * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    $multiplicador = 6;
    
    for ($i = 0; $i < 13; $i++) {
        $soma += intval($cnpj[$i]) * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica se os dígitos calculados batem com os dígitos informados
    return ($cnpj[12] == $dv1 && $cnpj[13] == $dv2);
}
?>