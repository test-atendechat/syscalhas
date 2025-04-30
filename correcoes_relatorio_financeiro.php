<?php
/**
 * Correções no Cálculo do Relatório Financeiro
 * 
 * Este arquivo contém todas as correções feitas no cálculo do relatório financeiro
 * para garantir que apenas valores efetivamente recebidos sejam contabilizados
 * no cálculo do lucro operacional e custos dos produtos vendidos.
 * 
 * Data: 30/04/2025
 * 
 * INSTRUÇÕES:
 * 1. Substitua o código em relatorio_financeiro.php conforme as alterações abaixo
 * 2. Todas as alterações estão marcadas com // CORREÇÃO: 
 */

// Obter os valores das vendas a partir da tabela vendas
$valor_total_vendas = $totais_vendas['valor_total_vendas'] ?? 0;
$valor_pago_vendas = $totais_vendas['valor_pago_vendas'] ?? 0;
$valor_vendas_confirmadas = $totais_vendas['valor_vendas_confirmadas'] ?? $valor_pago_vendas; // Se não existir, usamos o valor pago

// Calcular o custo médio das vendas (utilizando a mesma proporção da movimentação de estoque)
$proporcao_custo = ($valor_saidas > 0) ? ($custo_produtos_vendidos / $valor_saidas) : 0.5;

// CORREÇÃO: Calcular o custo dos produtos vendidos, com base APENAS nas vendas pagas (total ou parcialmente)
// Excluímos vendas a prazo sem pagamento para não contabilizar custo de algo que não foi recebido
$custo_total_vendas = $valor_pago_vendas * $proporcao_custo;

// CORREÇÃO: A variável custo_total_vendas já contém o custo dos produtos nas vendas PAGAS
$custo_vendas_pagas = $custo_total_vendas;

// Lucro das vendas diretas - considerando APENAS as vendas PAGAS para o lucro operacional
$lucro_vendas = $valor_pago_vendas - $custo_vendas_pagas;

// ******** ALTERAÇÕES NO HTML *********

// CORREÇÃO 1: Alterar o título "Valor Total de Vendas" para "Valor Total Recebido"
// <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
//     Valor Total Recebido</div>
// <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formataValor($valor_pago_vendas); ?></div>

// CORREÇÃO 2: Alterar o título na tabela
// <tr>
//     <td>Valor Recebido de Vendas</td>
//     <td class="text-end"><?php echo formataValor($valor_pago_vendas); ?></td>
// </tr>

// CORREÇÃO 3: Alterar "Custo dos Produtos Vendidos" para mostrar que é apenas dos produtos pagos
// <tr>
//     <td>Custo dos Produtos Vendidos (Pagos)</td>
//     <td class="text-end"><?php echo formataValor($custo_total_vendas); ?></td>
// </tr>

// CORREÇÃO 4: Alterar título do custo no resumo consolidado
// <tr>
//     <td>Custo dos Materiais (Vendas Pagas)</td>
//     <td class="text-end"><?php echo formataValor($custo_vendas_pagas); ?></td>
// </tr>

/**
 * EXPLICAÇÃO DAS ALTERAÇÕES:
 * 
 * 1. Alteramos o cálculo do custo dos produtos vendidos para considerar apenas
 *    o valor efetivamente pago, ignorando vendas a prazo sem pagamento.
 * 
 * 2. Modificamos os títulos para deixar claro que os valores apresentados
 *    se referem apenas ao que foi efetivamente recebido/pago.
 * 
 * 3. Simplificamos o código eliminando variáveis redundantes.
 * 
 * 4. Todos os cálculos de lucro operacional agora consideram apenas valores
 *    que realmente entraram no caixa, não contabilizando vendas a prazo sem
 *    pagamento como receita operacional.
 * 
 * Dessa forma, o relatório financeiro mostra uma visão mais precisa da saúde
 * financeira do negócio, baseada no fluxo de caixa real em vez de valores
 * potenciais de vendas não confirmadas.
 */
?>