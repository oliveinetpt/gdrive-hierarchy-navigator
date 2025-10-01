# 📦 Guia de Instalação - Google Drive Hierarchy Navigator

## 🚀 Instalação Rápida

### Método 1: Upload via WordPress Admin (Recomendado)

1. **Baixar o Plugin**
   - Faça o download do arquivo `gdrive-hierarchy-navigator.zip`

2. **Instalar no WordPress**
   - Acesse o painel administrativo do WordPress
   - Vá para **Plugins > Adicionar Novo**
   - Clique em **Enviar Plugin**
   - Selecione o arquivo ZIP baixado
   - Clique em **Instalar Agora**
   - Após a instalação, clique em **Ativar**

### Método 2: Upload via FTP

1. **Extrair Arquivos**
   ```bash
   # Extrair o ZIP para uma pasta temporária
   unzip gdrive-hierarchy-navigator.zip
   ```

2. **Upload via FTP**
   ```bash
   # Fazer upload da pasta para wp-content/plugins/
   /wp-content/plugins/gdrive-hierarchy-navigator/
   ```

3. **Ativar Plugin**
   - Acesse **Plugins > Plugins Instalados**
   - Localize "Google Drive Hierarchy Navigator"
   - Clique em **Ativar**

## ⚙️ Configuração Inicial

### 1. Obter API Key do Google Drive

```bash
# Passos detalhados:
1. Acesse: https://console.cloud.google.com/
2. Crie um novo projeto ou selecione um existente
3. Navegue para "APIs & Services > Library"
4. Procure por "Google Drive API"
5. Clique em "ENABLE" para ativar a API
6. Vá para "APIs & Services > Credentials"
7. Clique em "CREATE CREDENTIALS > API Key"
8. Copie a chave gerada (ex: AIzaSyABC123xyz...)
9. (Opcional) Configure restrições na API Key para maior segurança
```


### 2. Configurar Pasta do Google Drive

```bash
# Preparar pasta compartilhada:
1. Acesse Google Drive (drive.google.com)
2. Crie uma nova pasta ou selecione uma existente
3. Clique com botão direito na pasta > "Compartilhar"
4. Altere para "Qualquer pessoa com o link pode visualizar"
5. Copie o link da pasta
6. Extraia o ID da pasta do link:
   
   Link: https://drive.google.com/drive/folders/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
   ID: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
```

### 3. Estrutura de Pastas Recomendada

```
📁 Pasta Principal (ID que será usado no shortcode)
├── 📁 Planificações 2024/2025     ← Nível 1 (Anos Letivos)
│   ├── 📁 Matemática              ← Nível 2 (Disciplinas)
│   │   ├── 📄 teste1.pdf
│   │   ├── 📄 ficha1.docx
│   │   └── 📄 exercicios.xlsx
│   ├── 📁 Português
│   │   ├── 📄 teste_gramatica.pdf
│   │   └── 📄 composicao.docx
│   └── 📁 História
│       └── 📄 idade_media.pdf
├── 📁 Planificações 2023/2024     ← Nível 1
│   ├── 📁 Matemática              ← Nível 2
│   └── 📁 Português               ← Nível 2
└── 📁 Recursos Gerais             ← Nível 1
    ├── 📁 Modelos                 ← Nível 2
    └── 📁 Formulários             ← Nível 2
```

## 📝 Primeiro Uso

### 1. Shortcode Básico

Adicione em qualquer página ou post:

```php
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"]
```

### 2. Teste de Funcionamento

1. **Salve a página** com o shortcode
2. **Visualize a página** no frontend
3. **Verifique se**:
   - As pastas de nível 1 aparecem como chips clicáveis
   - Ao clicar numa pasta, aparecem as subpastas
   - Ao navegar até o último nível, aparecem os arquivos
   - O filtro de busca funciona

## 🔧 Resolução de Problemas

### ❌ Erro: "Falta folder_id e/ou api_key"

**Solução:**
```php
# Verifique se o shortcode tem ambos os parâmetros:
[gdrive_navigator folder_id="SEU_ID" api_key="SUA_KEY"]

# Certifique-se de que não há espaços extras
# ❌ Errado: folder_id=" 1ABC123 "
# ✅ Correto: folder_id="1ABC123"
```

### ❌ Erro: "Erro na chamada à Google Drive API"

**Possíveis causas e soluções:**

1. **API Key inválida**
   ```bash
   # Verifique se a API Key está correta
   # Regenere uma nova API Key se necessário
   ```

2. **Google Drive API não ativada**
   ```bash
   # No Google Cloud Console:
   # APIs & Services > Library > Google Drive API > ENABLE
   ```

3. **Pasta não é pública**
   ```bash
   # No Google Drive:
   # Botão direito na pasta > Compartilhar
   # "Qualquer pessoa com o link pode visualizar"
   ```

### ❌ Pastas não aparecem

**Verificações:**
```bash
1. Confirme que há pastas dentro da pasta principal
2. Verifique se as pastas não estão na lixeira
3. Teste com uma pasta nova e simples
4. Verifique o parâmetro levels no shortcode
```

### ❌ Arquivos não aparecem

**Verificações:**
```bash
1. Navegue até o último nível configurado
2. Verifique o parâmetro max_files (padrão: 100)
3. Confirme que há arquivos (não pastas) na pasta selecionada
4. Limpe o cache do WordPress
```

## 🎯 Configurações Avançadas

### Para Escolas

```php
[gdrive_navigator 
    folder_id="SEU_ID" 
    api_key="SUA_KEY"
    levels="3"                    # Ano > Disciplina > Tipo
    show_date="true"              # Mostrar datas
    show_size="true"              # Mostrar tamanhos
    show_download="true"          # Permitir downloads
    cache_minutes="30"            # Cache longo para estabilidade
    max_files="200"               # Muitos arquivos por pasta
    filter_placeholder="Procurar documentos escolares..."]
```

### Para Empresas

```php
[gdrive_navigator 
    folder_id="SEU_ID" 
    api_key="SUA_KEY"
    levels="2"                    # Departamento > Categoria
    show_date="true"              # Importante para documentos
    show_size="true"              # Mostrar tamanhos
    show_download="false"         # Apenas visualização
    show_view="true"              # Abrir no Google Drive
    cache_minutes="15"            # Cache mais frequente
    filter_placeholder="Filtrar documentos..."]
```

### Para Uso Pessoal

```php
[gdrive_navigator 
    folder_id="SEU_ID" 
    api_key="SUA_KEY"
    levels="1"                    # Apenas categorias principais
    show_date="false"             # Não relevante
    max_files="50"                # Poucos arquivos
    filter_placeholder="Buscar..."]
```

## 🎨 Personalização Visual

### CSS Personalizado

Adicione ao seu tema (Aparência > Personalizar > CSS Adicional):

```css
/* Container principal */
.gdhn-container {
    border: 2px solid #4285f4;
    border-radius: 15px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

/* Chips de navegação */
.gdhn-nav-chip {
    background: linear-gradient(45deg, #4285f4, #34a853);
    color: white;
    font-weight: bold;
    padding: 10px 20px;
}

.gdhn-nav-chip:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
}

/* Tabela de arquivos */
.gdhn-files-table {
    font-family: 'Arial', sans-serif;
}

.gdhn-files-table th {
    background: #f8f9fa;
    color: #5f6368;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Botões de ação */
.gdhn-btn-primary {
    background: #4285f4;
    border: none;
    transition: all 0.3s ease;
}

.gdhn-btn-success {
    background: #34a853;
    border: none;
}
```

## 📱 Responsividade

O plugin é automaticamente responsivo, mas você pode personalizar:

```css
/* Dispositivos móveis */
@media (max-width: 768px) {
    .gdhn-nav-chip {
        font-size: 12px;
        padding: 6px 12px;
    }
    
    .gdhn-container {
        padding: 15px;
        margin: 10px 0;
    }
}
```

## 🔒 Segurança

### Proteção da API Key

⚠️ **IMPORTANTE**: Nunca exponha sua API Key publicamente

```php
# ❌ NÃO faça isso:
# Colocar a API Key em arquivos públicos
# Compartilhar a API Key em repositórios públicos

# ✅ Faça isso:
# Use a API Key apenas nos shortcodes
# Configure restrições no Google Cloud Console
# Monitore o uso da API regularmente
```

### Configurações de Segurança no Google Cloud

1. **Restringir API Key**
   ```bash
   # No Google Cloud Console:
   # Credentials > [Sua API Key] > Edit
   # Application restrictions > HTTP referrers
   # Adicione: https://seusite.com/*
   ```

2. **Monitorar Uso**
   ```bash
   # APIs & Services > Google Drive API > Metrics
   # Acompanhe requests/dia para detectar uso anômalo
   ```

## 📞 Suporte

### Documentação
- [README Completo](README.md)
- [Exemplos de Uso](exemplo-uso.php)

### Problemas Comuns
- Verifique se o WordPress está atualizado (5.0+)
- Confirme que o PHP está na versão 7.4+
- Teste com um tema padrão do WordPress
- Desative outros plugins temporariamente

### Contato
- GitHub: [Issues](https://github.com/oliveinet/gdrive-hierarchy-navigator/issues)
- Website: [oliveinet.com](https://oliveinet.com)

---

✅ **Instalação Concluída!** O plugin está pronto para uso.

