# Instruções para Migração para a Nova Estrutura

Este documento contém instruções para migrar o sistema para a nova estrutura de diretórios e implementar URLs amigáveis.

## 1. Preparação

Antes de iniciar a migração, certifique-se de:

- Fazer um backup completo do sistema
- Fazer um backup do banco de dados
- Testar o sistema atual para garantir que está funcionando corretamente

## 2. Estrutura de Diretórios

A nova estrutura de diretórios organiza os arquivos em módulos e categorias:

```
/
├── modules/                  # Módulos do sistema
│   ├── agendamentos/         # Gerenciamento de agendamentos
│   ├── caixa/                # Controle de caixa
│   ├── clientes/             # Cadastro e gestão de clientes
│   ├── colaboradores/        # Gestão de colaboradores
│   ├── estoque/              # Controle de estoque e produtos
│   ├── financeiro/           # Gestão financeira
│   ├── orcamentos/           # Orçamentos
│   ├── relatorios/           # Relatórios diversos
│   ├── sistema/              # Configurações do sistema
│   ├── usuarios/             # Gestão de usuários
│   └── vendas/               # Vendas e faturamento
├── database/                 # Scripts e backups de banco de dados
│   ├── backups/              # Backups do banco
│   └── scripts/              # Scripts SQL
├── notificacoes/             # Sistema de notificações
├── utils/                    # Utilitários diversos
```

## 3. Passos para Migração

### 3.1. Verificar Requisitos

- Apache com mod_rewrite habilitado
- PHP 7.4 ou superior
- PostgreSQL 12 ou superior

### 3.2. Criar a Nova Estrutura de Diretórios

Execute os seguintes comandos para criar a estrutura de diretórios:

```bash
mkdir -p modules/{agendamentos,caixa,clientes,colaboradores,estoque,financeiro,orcamentos,produtos,relatorios,sistema,usuarios,vendas}
mkdir -p database/{backups,scripts}
mkdir -p utils
mkdir -p notificacoes
```

### 3.3. Copiar os Arquivos

Copie os arquivos para os diretórios apropriados conforme a organização definida.

### 3.4. Configurar URLs Amigáveis

1. Certifique-se de que o módulo mod_rewrite está habilitado no Apache:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. Verifique se o arquivo .htaccess está na raiz do projeto e contém as regras de rewrite.

3. Configure o Apache para permitir o uso de .htaccess:
   ```
   <Directory /var/www/html>
       AllowOverride All
   </Directory>
   ```

### 3.5. Testar a Nova Estrutura

1. Execute o script `verificar_reorganizacao.php` para verificar se os diretórios e arquivos foram criados corretamente.
2. Execute o script `testar_urls_amigaveis.php` para verificar se as URLs amigáveis estão funcionando.

### 3.6. Limpar Arquivos Duplicados

Após confirmar que tudo está funcionando corretamente:

1. Execute o script `limpar_arquivos_duplicados.php` para remover os arquivos duplicados.
2. Certifique-se de descomentar a linha `unlink($arquivo);` no script para realmente remover os arquivos.

## 4. Possíveis Problemas e Soluções

### 4.1. URLs Amigáveis Não Funcionam

- Verifique se o mod_rewrite está habilitado
- Verifique se o .htaccess está na raiz do projeto
- Verifique se o Apache está configurado para permitir .htaccess

### 4.2. Arquivos Não Encontrados

- Verifique se os caminhos nos includes estão corretos
- Verifique se os arquivos foram copiados para os diretórios corretos
- Verifique se as permissões dos arquivos estão corretas

### 4.3. Erros de Inclusão de Arquivos

- Verifique se o arquivo `includes/paths.php` está sendo incluído corretamente
- Verifique se as constantes de caminho estão sendo usadas corretamente

## 5. Conclusão

Após a migração, o sistema terá uma estrutura mais organizada e URLs mais amigáveis, facilitando a manutenção e a navegação.

Para qualquer problema ou dúvida, consulte a documentação ou entre em contato com o suporte técnico.