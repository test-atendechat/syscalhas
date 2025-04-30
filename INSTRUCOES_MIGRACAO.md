# Instruções para Migração do Sistema de Orçamentos para Calhas

Este documento contém instruções detalhadas para exportar o sistema Replit e importá-lo em um novo servidor.

## 1. Exportar o Sistema

### 1.1 Exportar os Arquivos

1. Acesse a URL: http://localhost:5000/exportar_sistema.php
2. Seu navegador fará o download de um arquivo ZIP contendo todos os arquivos do sistema
3. Salve este arquivo em um local seguro

### 1.2 Exportar o Banco de Dados

1. **Usando a interface web**:
   - Acesse a URL: http://localhost:5000/exportar_banco_completo_atualizado.php (faça login como admin se necessário)
   - Clique no botão "Exportar Banco de Dados"
   - Baixe o arquivo SQL gerado

2. **Usando o comando pg_dump diretamente** (alternativa):
   - Execute o comando a seguir, substituindo os valores entre colchetes:
   ```
   pg_dump -h [host] -p [porta] -U [usuario] -d [nome_banco] --clean --if-exists --no-owner --no-privileges > backup_completo.sql
   ```

## 2. Preparação do Novo Servidor

### 2.1 Requisitos do Servidor

- Servidor web (Apache/Nginx) com PHP 8.0 ou superior
- PostgreSQL 13 ou superior
- Extensões PHP necessárias:
  - pdo_pgsql
  - zip
  - gd
  - mbstring
  - json
  - session

### 2.2 Configurar o Servidor Web

#### Para Apache:
1. Certifique-se que mod_rewrite está habilitado:
   ```
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. Configure o VirtualHost (exemplo):
   ```apache
   <VirtualHost *:80>
       ServerName seudominio.com
       DocumentRoot /var/www/html/sistema_calhas
       
       <Directory /var/www/html/sistema_calhas>
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/sistema_calhas_error.log
       CustomLog ${APACHE_LOG_DIR}/sistema_calhas_access.log combined
   </VirtualHost>
   ```

#### Para Nginx:
1. Configure o site (exemplo):
   ```nginx
   server {
       listen 80;
       server_name seudominio.com;
       root /var/www/html/sistema_calhas;
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
       }
       
       location ~ /\.ht {
           deny all;
       }
   }
   ```

### 2.3 Criar o Banco de Dados PostgreSQL

1. Acesse o PostgreSQL:
   ```
   sudo -u postgres psql
   ```

2. Crie um novo banco de dados e usuário:
   ```sql
   CREATE DATABASE sistema_calhas;
   CREATE USER sistema_user WITH ENCRYPTED PASSWORD 'senha_segura';
   GRANT ALL PRIVILEGES ON DATABASE sistema_calhas TO sistema_user;
   \q
   ```

## 3. Importação do Sistema

### 3.1 Importar os Arquivos

1. Descompacte o arquivo ZIP no diretório raiz do servidor web:
   ```
   unzip sistema_calhas_completo.zip -d /var/www/html/sistema_calhas
   ```

2. Configure as permissões:
   ```
   sudo chown -R www-data:www-data /var/www/html/sistema_calhas
   sudo chmod -R 755 /var/www/html/sistema_calhas
   ```

### 3.2 Configurar a Conexão com o Banco de Dados

1. Edite o arquivo `includes/config.php` no diretório do sistema:
   ```php
   // Configurações do banco de dados
   define('DB_TYPE', 'pgsql');
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sistema_calhas');
   define('DB_USER', 'sistema_user');
   define('DB_PASS', 'senha_segura');
   define('DB_PORT', '5432');
   
   // Configurações do sistema
   define('APP_NAME', 'Sistema de Orçamentos para Calhas');
   define('BASE_URL', 'https://seudominio.com/');
   ```

### 3.3 Importar o Banco de Dados

1. Importe o arquivo SQL gerado anteriormente:
   ```
   psql -h localhost -p 5432 -U sistema_user -d sistema_calhas -f db_backup_completo_com_correcoes.sql
   ```

## 4. Verificação da Instalação

1. Acesse o sistema pelo navegador: `https://seudominio.com/`
2. Faça login com as credenciais:
   - Email: admin@admin.com
   - Senha: admin123
3. Verifique as seguintes funcionalidades:
   - Dashboard
   - Criação de orçamentos
   - Gerenciamento de clientes
   - Relatórios financeiros

## 5. Passos Adicionais de Segurança

1. Altere a senha do administrador após o primeiro login
2. Configure HTTPS para seu domínio
3. Implemente backups regulares do banco de dados
4. Considere adicionar um arquivo `.htaccess` para restrições adicionais

## 6. Solução de Problemas

### 6.1 Problemas de Conexão com o Banco de Dados

- Verifique se as credenciais no arquivo `includes/config.php` estão corretas
- Confirme se o usuário do PostgreSQL tem as permissões adequadas
- Verifique se o PostgreSQL está configurado para aceitar conexões do servidor web

### 6.2 Problemas de Permissão de Arquivos

- Execute novamente os comandos de permissão:
  ```
  sudo chown -R www-data:www-data /var/www/html/sistema_calhas
  sudo chmod -R 755 /var/www/html/sistema_calhas
  ```

### 6.3 Erros de PHP

- Verifique o log de erros do PHP: `/var/log/php/error.log`
- Certifique-se de que todas as extensões PHP necessárias estão instaladas e habilitadas

## 7. Contato para Suporte

Em caso de dúvidas ou problemas durante a migração, entre em contato com o desenvolvedor do sistema.

---

Documento gerado em: <?php echo date('d/m/Y H:i:s'); ?>