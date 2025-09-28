# Google Drive Hierarchy Navigator

🚀 Plugin WordPress para navegação hierárquica de pastas e arquivos do Google Drive

![Versão](https://img.shields.io/badge/versão-1.9.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple)
![Licença](https://img.shields.io/badge/licença-GPL%20v2-orange)

## 📋 Descrição

O **Google Drive Hierarchy Navigator** é um plugin WordPress que permite criar um sistema de navegação hierárquica para pastas e arquivos do Google Drive. É especialmente útil para escolas, instituições educativas e organizações que precisam organizar documentos por anos letivos e categorias.

### ✨ Características Principais

- **🗂️ Navegação Hierárquica**: Suporte para até 3 níveis de pastas
- **🎨 Interface Moderna**: Barras horizontais com chips/botões clicáveis
- **🔍 Filtro de Arquivos**: Caixa de pesquisa para filtrar arquivos
- **📄 Ícones por Tipo**: Ícones automáticos para diferentes formatos
- **⚡ Cache Inteligente**: Sistema de cache para melhor performance
- **📱 Responsivo**: Interface adaptável para dispositivos móveis
- **⚙️ Configurável**: Múltiplas opções via shortcode

## 🚀 Instalação

### Método 1: Via WordPress Admin

1. Baixe o arquivo ZIP do plugin
2. Acesse **Plugins > Adicionar Novo > Enviar Plugin**
3. Selecione o arquivo ZIP e instale
4. Ative o plugin

### Método 2: Upload FTP

1. Extraia o plugin para `/wp-content/plugins/`
2. Ative o plugin no painel do WordPress

## ⚙️ Configuração

### 1. Obter API Key do Google Drive

```bash
# Passos:
1. Acesse console.cloud.google.com
2. Crie um projeto ou selecione um existente
3. Ative a Google Drive API
4. Crie credenciais (API Key)
5. Copie a chave gerada
```

### 2. Configurar Pasta do Google Drive

1. Crie uma pasta no Google Drive
2. Torne a pasta pública (compartilhamento aberto)
3. Copie o ID da pasta da URL: `https://drive.google.com/drive/folders/ID_AQUI`

## 📝 Uso

### Shortcode Básico

```php
[gdrive_navigator folder_id="SEU_FOLDER_ID" api_key="SUA_API_KEY"]
```

### Shortcode Completo

```php
[gdrive_navigator 
    folder_id="1ABC123xyz" 
    api_key="AIzaSyABC123xyz"
    levels="2"
    show_date="true"
    show_download="true"
    show_view="true"
    cache_minutes="15"
    max_files="100"
    filter_placeholder="Pesquisar documentos..."]
```

## 📊 Parâmetros

| Parâmetro | Tipo | Padrão | Descrição |
|-----------|------|--------|-----------|
| `folder_id` | string | - | **Obrigatório** - ID da pasta raiz |
| `api_key` | string | - | **Obrigatório** - Chave API do Google Drive |
| `levels` | integer | 2 | Número de níveis (1-3) |
| `show_date` | boolean | true | Mostrar data dos arquivos |
| `show_download` | boolean | true | Mostrar botão download |
| `show_view` | boolean | true | Mostrar botão visualizar |
| `cache_minutes` | integer | 15 | Minutos de cache (1-60) |
| `max_files` | integer | 100 | Máximo arquivos por pasta (10-500) |
| `filter_placeholder` | string | "Filtrar arquivos..." | Placeholder do filtro |

## 🎯 Casos de Uso

### Escola/Universidade
```
📁 Planificações 2024/2025
  📁 Matemática
    📁 Testes
    📁 Fichas
  📁 Português
    📁 Testes
    📁 Fichas
```

### Empresa
```
📁 Documentos 2024
  📁 Recursos Humanos
    📁 Contratos
    📁 Políticas
  📁 Financeiro
    📁 Relatórios
    📁 Faturas
```

## 🎨 Personalização CSS

```css
/* Container principal */
.gdhn-container {
    /* Seus estilos aqui */
}

/* Chips de navegação */
.gdhn-nav-chip {
    /* Seus estilos aqui */
}

/* Tabela de arquivos */
.gdhn-files-table {
    /* Seus estilos aqui */
}
```

## 📱 Responsividade

O plugin é totalmente responsivo e se adapta automaticamente a:
- 📱 Smartphones (< 480px)
- 📱 Tablets (480px - 768px)  
- 💻 Desktop (> 768px)

## 🔧 Desenvolvimento

### Estrutura de Arquivos

```
gdrive-hierarchy-navigator/
├── gdrive-hierarchy-navigator.php  # Arquivo principal
├── assets/
│   ├── gdhn-style.css             # Estilos CSS
│   └── gdhn-main.js               # JavaScript
├── readme.txt                     # Readme WordPress
└── README.md                      # Este arquivo
```

### Hooks Disponíveis

```php
// Filtrar configuração do shortcode
add_filter('gdhn_shortcode_atts', function($atts) {
    // Modificar $atts
    return $atts;
});

// Filtrar arquivos antes da renderização
add_filter('gdhn_files_data', function($files, $folder_id) {
    // Modificar $files
    return $files;
}, 10, 2);
```

## 🐛 Resolução de Problemas

### Erro: "Falta folder_id e/ou api_key"
- ✅ Verifique se forneceu ambos os parâmetros
- ✅ Confirme que não há espaços extras

### Erro: "Erro na chamada à Google Drive API"
- ✅ Verifique se a API Key está correta
- ✅ Confirme que a Google Drive API está ativada
- ✅ Verifique se a pasta é pública

### Arquivos não aparecem
- ✅ Confirme que há arquivos na pasta
- ✅ Verifique o parâmetro `max_files`
- ✅ Limpe o cache do WordPress

## 📋 Requisitos

- **WordPress**: 5.0 ou superior
- **PHP**: 7.4 ou superior
- **JavaScript**: Habilitado no navegador
- **Conexão**: Internet para API do Google Drive

## 🔒 Segurança

- ✅ Sanitização de todos os inputs
- ✅ Validação de nonces AJAX
- ✅ Escape de outputs HTML
- ✅ Verificação de permissões
- ✅ Rate limiting via cache

## 🚀 Roadmap

- [ ] Suporte para autenticação OAuth2
- [ ] Editor visual de configuração
- [ ] Múltiplos temas de interface
- [ ] Integração com outros serviços de cloud
- [ ] Sistema de permissões avançado

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-feature`)
3. Commit suas mudanças (`git commit -am 'Adiciona nova feature'`)
4. Push para a branch (`git push origin feature/nova-feature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está licenciado sob a GPL v2 ou posterior - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 👨‍💻 Autor

**oliveinet**
- Website: [oliveinet.com](https://oliveinet.com)
- GitHub: [@oliveinet](https://github.com/oliveinet)

## 🙏 Agradecimentos

- Google Drive API
- Comunidade WordPress
- Todos os contribuidores

---

⭐ **Se este plugin foi útil, considere dar uma estrela!**
