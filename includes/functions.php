<?php
/**
 * Arquivo com funções utilitárias para o sistema
 */

/**
 * Verifica se um usuário tem uma determinada permissão
 * 
 * @param string $permissao Nome da permissão a verificar
 * @return boolean True se tem permissão ou é administrador, False caso contrário
 */
function verificarPermissao($permissao) {
    global $db;
    
    // Se não há usuário logado, não tem permissão
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
        error_log("Usuário não logado tentando acessar permissão: $permissao");
        return false;
    }
    
    $usuario_id = $_SESSION['usuario']['id'];
    $usuario_nome = $_SESSION['usuario']['nome'] ?? 'Desconhecido';
    $usuario_nivel = $_SESSION['usuario']['nivel'] ?? 'Desconhecido';
    
    // Administradores têm todas as permissões automaticamente
    if (isset($_SESSION['usuario']['nivel']) && $_SESSION['usuario']['nivel'] === 'admin') {
        error_log("Usuário $usuario_nome (ID: $usuario_id) é admin, permissão $permissao concedida automaticamente");
        return true;
    }
    
    try {
        // Verifica se a permissão existe na tabela
        $verificar_coluna = $db->prepare("SELECT column_name FROM information_schema.columns 
                                         WHERE table_name = 'permissoes' AND column_name = :coluna");
        $verificar_coluna->bindParam(':coluna', $permissao);
        $verificar_coluna->execute();
        
        if ($verificar_coluna->rowCount() == 0) {
            error_log("ERRO: Permissão '$permissao' não existe na tabela permissoes. Usuário: $usuario_nome (ID: $usuario_id, Nível: $usuario_nivel)");
            return false;
        }
        
        // Verifica na tabela de permissões
        $sql = "SELECT $permissao FROM permissoes WHERE usuario_id = :usuario_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $tem_permissao = (isset($resultado[$permissao]) && $resultado[$permissao]);
            error_log("Verificando permissão '$permissao' para usuário $usuario_nome (ID: $usuario_id, Nível: $usuario_nivel): " . ($tem_permissao ? 'SIM' : 'NÃO'));
            return $tem_permissao;
        } else {
            error_log("ERRO: Usuário $usuario_nome (ID: $usuario_id, Nível: $usuario_nivel) não tem registro na tabela de permissões");
            return false;
        }
    } catch (Exception $e) {
        error_log("ERRO ao verificar permissão '$permissao': " . $e->getMessage() . " - Usuário: $usuario_nome (ID: $usuario_id, Nível: $usuario_nivel)");
    }
    
    return false;
}

/**
 * Verifica se o usuário tem permissão para excluir
 * 
 * @param string $area Área específica para verificar permissão (opcional)
 * @return boolean True se tem permissão, False caso contrário
 */
function podeDeletar($area = '') {
    // Administradores têm todas as permissões
    if (isset($_SESSION['usuario']['nivel']) && $_SESSION['usuario']['nivel'] === 'admin') {
        return true;
    }
    
    // Verificar permissões específicas por área usando as permissões existentes
    if (!empty($area)) {
        switch ($area) {
            case 'caixa':
                return verificarPermissao('gerenciar_caixa');
            case 'clientes':
                return verificarPermissao('gerenciar_clientes');
            case 'produtos':
                return verificarPermissao('gerenciar_produtos');
            case 'orcamentos':
                return verificarPermissao('gerenciar_orcamentos');
            case 'vendas':
                return verificarPermissao('gerenciar_vendas');
            case 'usuarios':
                return verificarPermissao('gerenciar_usuarios');
            case 'contas':
                return verificarPermissao('gerenciar_contas');
            case 'estoque':
                return verificarPermissao('gerenciar_estoque');
            default:
                return false;
        }
    }
    
    // Comportamento padrão - apenas admin pode excluir
    return false;
}

// Esta função foi movida para a versão abaixo

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
 * Converte uma cor no formato hexadecimal (#rrggbb) para RGB
 * 
 * @param string $hex_color Cor em formato hexadecimal (com ou sem #)
 * @return array Array com componentes R, G e B
 */
function hexToRgb($hex_color) {
    // Remover o # se existir
    $hex = str_replace('#', '', $hex_color);
    
    // Verificar se é um formato de 3 ou 6 dígitos
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    return array($r, $g, $b);
}

/**
 * Converte valores RGB para hexadecimal
 * 
 * @param int $r Componente vermelho (0-255)
 * @param int $g Componente verde (0-255)
 * @param int $b Componente azul (0-255)
 * @return string Cor em formato hexadecimal com #
 */
function rgbToHex($r, $g, $b) {
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Ajusta o brilho de uma cor em formato hexadecimal
 * 
 * @param string $hex_color Cor em formato hexadecimal (com ou sem #)
 * @param int $percent Percentual de ajuste (-100 a 100)
 * @return string Cor ajustada em formato hexadecimal com #
 */
function adjustBrightness($hex_color, $percent) {
    // Converter para RGB
    list($r, $g, $b) = hexToRgb($hex_color);
    
    // Ajustar cada componente
    if ($percent > 0) {
        // Clarear a cor
        $r = $r + (255 - $r) * $percent / 100;
        $g = $g + (255 - $g) * $percent / 100;
        $b = $b + (255 - $b) * $percent / 100;
    } else {
        // Escurecer a cor
        $percent = -$percent;
        $r = $r * (100 - $percent) / 100;
        $g = $g * (100 - $percent) / 100;
        $b = $b * (100 - $percent) / 100;
    }
    
    // Garantir que os valores estão no intervalo 0-255
    $r = max(0, min(255, $r));
    $g = max(0, min(255, $g));
    $b = max(0, min(255, $b));
    
    // Converter de volta para hexadecimal
    return rgbToHex($r, $g, $b);
}

/**
 * Formata um valor para exibição como moeda
 * 
 * @param float $valor Valor a ser formatado
 * @param string $simbolo Símbolo da moeda
 * @return string Valor formatado
 */
function formataValor($valor, $simbolo = 'R$') {
    if ($valor === null) $valor = 0;
    return $simbolo . ' ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata um número de telefone para uso no WhatsApp (remove caracteres não numéricos)
 * 
 * @param string $telefone Número de telefone a ser formatado
 * @return string Telefone formatado apenas com números
 */
function formataTelefoneWhatsApp($telefone) {
    // Remove todos os caracteres não numéricos
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    // Se o número começar com 0, remove o 0
    if (substr($telefone, 0, 1) === '0') {
        $telefone = substr($telefone, 1);
    }
    
    // Adiciona o código do país (Brasil = 55) se não existir
    if (strlen($telefone) <= 11 && substr($telefone, 0, 2) !== '55') {
        $telefone = '55' . $telefone;
    }
    
    return $telefone;
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
    switch ($forma) {
        case 'dinheiro':
            return 'Dinheiro';
        case 'cartao':
            return 'Cartão';
        case 'pix':
            return 'PIX';
        case 'transferencia':
            return 'Transferência';
        case 'cheque':
            return 'Cheque';
        default:
            return $forma ? ucfirst($forma) : 'Não informado';
    }
}

/**
 * Gera um número único para orçamento no formato MES/ANO/SEQUENCIAL
 * 
 * @return string Número do orçamento no formato MM/YY/0000
 */
function gerarNumeroOrcamento() {
    global $db;
    
    $mes = date('m');
    $ano = date('y');  // Ano com 2 dígitos
    $prefixo = "{$mes}/{$ano}/";
    
    // Buscar o último número de orçamento com este prefixo
    $stmt = $db->prepare("SELECT numero FROM orcamentos WHERE numero LIKE :prefixo ORDER BY id DESC LIMIT 1");
    $busca = $prefixo . '%';
    $stmt->bindParam(':prefixo', $busca);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $ultimoNumero = $stmt->fetch(PDO::FETCH_COLUMN);
        $partes = explode('/', $ultimoNumero);
        $sequencial = intval(end($partes)) + 1;
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
 * Formatar data e hora no padrão brasileiro
 * 
 * @param string $dataHora Data e hora no formato banco (Y-m-d H:i:s)
 * @return string Data e hora formatada (d/m/Y H:i)
 */
function dataHoraParaBr($dataHora) {
    if (empty($dataHora)) return '';
    
    $timestamp = strtotime($dataHora);
    if ($timestamp === false) return $dataHora;
    
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Buscar agendamento ativo para um orçamento
 * 
 * @param int $orcamento_id ID do orçamento
 * @return array|false Dados do agendamento ou false se não encontrado
 */
function buscarAgendamentoAtivo($orcamento_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT a.*, c.nome as colaborador_nome, c.tipo as colaborador_tipo, c.telefone as colaborador_telefone
                      FROM agendamentos a
                      LEFT JOIN colaboradores c ON a.instalador_id = c.id
                      WHERE a.orcamento_id = :orcamento_id AND a.status = 'agendado'
                      ORDER BY a.data_inicio DESC LIMIT 1");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
        // Armazenar em sessão para uso imediato na impressão
        $_SESSION['agendamento_info'] = [
            'data' => date('d/m/Y', strtotime($agendamento['data_agendamento'])),
            'hora' => substr($agendamento['hora_inicio'], 0, 5),
            'colaborador_nome' => $agendamento['colaborador_nome'],
            'colaborador_tipo' => $agendamento['colaborador_tipo'],
            'colaborador_telefone' => $agendamento['colaborador_telefone'],
            'observacoes' => $agendamento['observacoes']
        ];
        return $agendamento;
    }
    
    return false;
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