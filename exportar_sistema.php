<?php
// Definir cabeçalhos para download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="sistema_calhas_completo.zip"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Função para adicionar um diretório ao ZipArchive de forma recursiva
function adicionarDiretorio($zip, $diretorioBase, $diretorioRelativo = '') {
    $diretorio = $diretorioBase . '/' . $diretorioRelativo;
    
    // Abrir o diretório
    $handler = opendir($diretorio);
    
    // Percorrer todos os arquivos
    while (($arquivo = readdir($handler)) !== false) {
        // Ignorar diretórios especiais e arquivos de configuração local
        if ($arquivo == '.' || $arquivo == '..' || $arquivo == '.git' || 
            $arquivo == '.replit' || $arquivo == 'replit.nix' || 
            $arquivo == 'sistema_calhas_completo.zip' ||
            $arquivo == 'exportar_sistema.php' ||
            strpos($arquivo, '.local.php') !== false) {
            continue;
        }

        $caminhoCompleto = $diretorio . '/' . $arquivo;
        $caminhoRelativo = $diretorioRelativo != '' ? $diretorioRelativo . '/' . $arquivo : $arquivo;
        
        // Se for um diretório, chamar a função recursivamente
        if (is_dir($caminhoCompleto)) {
            // Adicionar o diretório vazio ao ZIP
            $zip->addEmptyDir($caminhoRelativo);
            
            // Adicionar o conteúdo do diretório
            adicionarDiretorio($zip, $diretorioBase, $caminhoRelativo);
        } else {
            // Se for um arquivo, adicionar ao ZIP
            $zip->addFile($caminhoCompleto, $caminhoRelativo);
        }
    }
    
    // Fechar o handler
    closedir($handler);
}

// Criar um arquivo ZIP
$zip = new ZipArchive();
$nomeArquivoZip = tempnam(sys_get_temp_dir(), 'zip');

if ($zip->open($nomeArquivoZip, ZipArchive::CREATE) === TRUE) {
    // Adicionar todos os diretórios e arquivos recursivamente
    adicionarDiretorio($zip, '.');
    
    // Adicionar um arquivo README com instruções
    $conteudoReadme = "# Sistema de Orçamentos para Calhas\n\n" .
                     "## Instruções de Instalação\n\n" .
                     "1. Descompacte todos os arquivos em seu servidor web\n" .
                     "2. Importe o arquivo `db_backup_completo_com_correcoes.sql` em seu banco de dados PostgreSQL\n" .
                     "3. Configure a conexão do banco de dados editando o arquivo `includes/config.php`\n" .
                     "4. Acesse a aplicação pelo navegador\n" .
                     "5. Faça login com as credenciais de administrador:\n" .
                     "   - Email: admin@admin.com\n" .
                     "   - Senha: admin123\n\n" .
                     "## Informações Adicionais\n\n" .
                     "- Esta exportação foi gerada em: " . date('d/m/Y H:i:s') . "\n" .
                     "- Em caso de dúvidas, consulte o arquivo README_INSTALACAO_BANCO.md\n";
    
    $zip->addFromString('README.md', $conteudoReadme);
    
    // Fechar o arquivo ZIP
    $zip->close();
    
    // Enviar o arquivo para download
    readfile($nomeArquivoZip);
    
    // Remover o arquivo temporário
    unlink($nomeArquivoZip);
} else {
    echo "Não foi possível criar o arquivo ZIP.";
}
?>