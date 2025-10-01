# Google Drive Hierarchy Navigator

🚀 Plugin WordPress profissional para navegação hierárquica de pastas e arquivos do Google Drive

![Versão](https://img.shields.io/badge/versão-2.5.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple)
![Licença](https://img.shields.io/badge/licença-GPL%20v2-orange)

## 📋 Descrição

O **Google Drive Hierarchy Navigator** é um plugin WordPress completo que permite criar um sistema de navegação hierárquica para pastas e arquivos do Google Drive. Ideal para escolas, instituições educativas, empresas e organizações que precisam organizar e disponibilizar documentos de forma profissional.

### ✨ Características Principais

#### 🗂️ **Navegação e Interface**
- **Navegação Hierárquica**: Suporte para até 3 níveis de pastas
- **Interface Moderna**: Barras horizontais com chips/botões clicáveis
- **Distinção Visual**: Cores diferentes para cada nível de navegação
- **Filtro Inteligente**: Caixa de pesquisa para filtrar arquivos em tempo real
- **Ícones por Tipo**: Ícones automáticos Font Awesome para diferentes formatos
- **Responsivo**: Interface 100% adaptável para dispositivos móveis

#### 📊 **Estatísticas e Analytics**
- **Contador de Visualizações**: Sistema automático de tracking de hits por arquivo
- **Estatísticas por Página**: Veja quais páginas WordPress geram mais acessos
- **Top Arquivos**: Ranking dos documentos mais populares
- **Pastas Mais Ativas**: Estatísticas por pasta com nomes reais
- **Atividade Recente**: Visualizações dos últimos 7 dias
- **Links Diretos**: Acesso rápido ao Google Drive de cada arquivo/pasta
- **Tracking de Origem**: Registra de qual página/artigo o arquivo foi acessado

#### 🗑️ **Gestão de Cache Avançada**
- **Cache Configurável**: De 0 minutos (sem cache) até ilimitado
- **Presets Inteligentes**: 5min, 15min, 30min, 1h, 2h, 4h, 12h, 24h, 3 dias, 7 dias, 30 dias, 1 ano
- **Valores Personalizados**: Defina qualquer tempo de cache
- **Limpeza Seletiva**: Limpar cache de pastas, arquivos ou tudo
- **Estatísticas de Cache**: Visualize quantos itens estão em cache
- **Página de Gestão**: Interface dedicada para gerenciar cache

#### ⚙️ **Configuração e Personalização**
- **15+ Parâmetros de Shortcode**: Controle total sobre a exibição
- **Cores Personalizáveis**: Defina cores para cada nível de navegação
- **Configuração Global**: API Key e configurações centralizadas
- **Botões de Ação**: Configuráveis (View, Download, Hits)
- **Colunas Flexíveis**: Mostre/oculte data, tamanho e visualizações

#### 🛠️ **Admin e Diagnóstico**
- **Menu Dedicado**: "GDrive Navigator" com todas as ferramentas
- **Página Principal**: Dashboard com estatísticas rápidas
- **Configurações**: Interface intuitiva com exemplos
- **Diagnóstico**: Ferramentas para troubleshooting
- **Debug Hits**: Página para verificar sistema de estatísticas
- **Versão Visível**: Número da versão em todas as páginas admin

## 🚀 Instalação

### Método 1: Via WordPress Admin (Recomendado)

1. Baixe o arquivo `gdrive-hierarchy-navigator.zip`
2. Acesse **Plugins > Adicionar Novo > Enviar Plugin**
3. Selecione o arquivo ZIP e clique em **Instalar Agora**
4. Clique em **Ativar Plugin**
5. Vá em **GDrive Navigator > Configurações**

### Método 2: Upload FTP

1. Extraia o plugin para `/wp-content/plugins/gdrive-hierarchy-navigator/`
2. Ative o plugin no painel do WordPress
3. Configure em **GDrive Navigator > Configurações**

## ⚙️ Configuração

### 1. Obter API Key do Google Drive

```bash
Passos:
1. Acesse https://console.cloud.google.com
2. Crie um projeto ou selecione um existente
3. Ative a Google Drive API
4. Crie credenciais (API Key)
5. Restrinja a API Key (opcional mas recomendado)
6. Copie a chave gerada
```

### 2. Configurar no WordPress

1. Vá em **GDrive Navigator > ⚙️ Configurações**
2. Cole a API Key no campo apropriado
3. Configure o tempo de cache (padrão: 15 minutos)
4. Salve as configurações

### 3. Preparar Pasta do Google Drive

1. Crie uma pasta no Google Drive
2. Organize subpastas (até 3 níveis)
3. Torne a pasta pública:
   - Clique direito > Compartilhar
   - "Qualquer pessoa com o link"
4. Copie o ID da pasta da URL: `https://drive.google.com/drive/folders/ID_AQUI`

## 📝 Uso

### Shortcode Básico

```php
[gdrive_navigator folder_id="1ABC123xyz"]
```

### Shortcode Completo

```php
[gdrive_navigator 
    folder_id="1ABC123xyz"
    api_key="SUA_API_KEY"
    levels="2"
    show_date="true"
    show_size="true"
    show_hits="true"
    show_download="true"
    show_view="true"
    cache_minutes="30"
    max_files="100"
    filter_placeholder="Procurar arquivos..."
    primary_color="#4285f4"
    secondary_color="#34a853"
    level1_bg="#4285f4"
    level2_bg="#f8f9fa"
]
```

### Parâmetros Disponíveis

| Parâmetro | Tipo | Padrão | Descrição |
|-----------|------|--------|-----------|
| `folder_id` | string | - | **Obrigatório**. ID da pasta raiz do Google Drive |
| `api_key` | string | (global) | Chave API (usa configuração global se não fornecida) |
| `levels` | int | 2 | Níveis de navegação (1-3) |
| `show_date` | bool | true | Mostrar coluna de data |
| `show_size` | bool | true | Mostrar coluna de tamanho |
| `show_hits` | bool | true | Mostrar coluna de visualizações |
| `show_download` | bool | true | Mostrar botão de download |
| `show_view` | bool | true | Mostrar botão de visualização |
| `cache_minutes` | int | 15 | Tempo de cache (0 = sem cache, sem limite máximo) |
| `max_files` | int | 100 | Máximo de arquivos por pasta (10-500) |
| `filter_placeholder` | string | "Filtrar arquivos..." | Texto do campo de pesquisa |
| `primary_color` | hex | #4285f4 | Cor primária do tema |
| `secondary_color` | hex | #34a853 | Cor secundária |
| `level1_bg` | hex | #4285f4 | Cor de fundo do nível 1 |
| `level2_bg` | hex | #f8f9fa | Cor de fundo do nível 2 |

## 📊 Sistema de Estatísticas

### Como Funciona

1. **Tracking Automático**: Cada clique num arquivo é registado
2. **View/Download**: Ambos os botões contam como visualização
3. **Armazenamento**: Dados salvos na base de dados WordPress
4. **Origem**: Registra a página/artigo de origem do acesso

### Acessar Estatísticas

Vá em **GDrive Navigator > 📊 Estatísticas** para ver:

- **📈 Estatísticas Gerais**: Total de arquivos, visualizações totais, média
- **🏆 Top 10 Arquivos**: Os documentos mais populares com links
- **📁 Top 10 Pastas**: Pastas com mais atividade (nomes reais)
- **📄 Top 10 Páginas**: Páginas WordPress que mais geram acessos
- **🕒 Atividade Recente**: Últimas visualizações (7 dias)

### Estatísticas por Página

Filtre estatísticas por página/artigo específico:

1. Acesse **GDrive Navigator > 📊 Estatísticas**
2. Role até "📄 Estatísticas por Página"
3. Selecione a página desejada no dropdown
4. Veja quais arquivos foram acessados dessa página

### Controlar Visualização

```php
// Mostrar coluna de hits (padrão)
[gdrive_navigator folder_id="ID" show_hits="true"]

// Ocultar coluna de hits (privacidade)
[gdrive_navigator folder_id="ID" show_hits="false"]
```

## 🗑️ Gestão de Cache

### Acessar Gestão de Cache

Vá em **GDrive Navigator > 🗑️ Gestão de Cache**

### Opções Disponíveis

#### Estatísticas de Cache
- Total de itens em cache
- Número de pastas em cache
- Número de arquivos em cache

#### Ações de Limpeza
1. **Limpar Toda a Cache**: Remove tudo (pastas + arquivos)
2. **Limpar Cache de Pastas**: Apenas pastas
3. **Limpar Cache de Arquivos**: Apenas arquivos

### Quando Limpar Cache?

- ✅ Adicionou novos arquivos e não aparecem
- ✅ Renomeou pastas ou arquivos
- ✅ Reorganizou a estrutura de pastas
- ✅ Está testando configurações

### Configurar Tempo de Cache

```php
// Cache personalizada no shortcode
[gdrive_navigator folder_id="ID" cache_minutes="120"]  // 2 horas

// Arquivos históricos (nunca mudam)
[gdrive_navigator folder_id="ID" cache_minutes="999999999"]

// Sem cache (não recomendado)
[gdrive_navigator folder_id="ID" cache_minutes="0"]
```

## 🎨 Personalização

### Cores Personalizadas

```php
[gdrive_navigator 
    folder_id="ID"
    primary_color="#2196f3"
    secondary_color="#4caf50"
    level1_bg="#2196f3"
    level2_bg="#e3f2fd"
]
```

### Ocultar Elementos

```php
// Sem data e tamanho
[gdrive_navigator folder_id="ID" show_date="false" show_size="false"]

// Sem botões de ação
[gdrive_navigator folder_id="ID" show_download="false" show_view="false"]

// Sem estatísticas (privacidade)
[gdrive_navigator folder_id="ID" show_hits="false"]
```

## 🛠️ Diagnóstico

### Página de Diagnóstico

Acesse **GDrive Navigator > 🔧 Diagnóstico** para:

- ✅ Verificar se o plugin está ativo
- ✅ Testar API Key
- ✅ Verificar permissões de pastas
- ✅ Ver informações do sistema

### Troubleshooting Comum

#### "Erro ao carregar pastas"
- Verifique se a API Key está correta
- Confirme que a pasta é pública
- Teste na página de Diagnóstico

#### "Nenhum arquivo encontrado"
- Verifique se há arquivos na pasta
- Limpe a cache
- Confirme que a pasta tem permissão pública

#### Arquivos novos não aparecem
- Vá em **Gestão de Cache**
- Clique em "Limpar Toda a Cache"

## 📖 Exemplos de Uso

### Escola - Anos Letivos

```php
// Ano atual (cache curta)
[gdrive_navigator 
    folder_id="xxx_2024_2025"
    cache_minutes="15"
    show_hits="true"
]

// Ano anterior (cache longa)
[gdrive_navigator 
    folder_id="xxx_2023_2024"
    cache_minutes="525600"
    show_hits="false"
]
```

### Empresa - Documentos Públicos

```php
[gdrive_navigator 
    folder_id="docs_publicos"
    levels="3"
    primary_color="#ff5722"
    show_hits="true"
    max_files="200"
]
```

### Biblioteca - Arquivos Históricos

```php
[gdrive_navigator 
    folder_id="arquivo_historico"
    cache_minutes="999999999"
    show_date="true"
    show_hits="false"
]
```

## 🔧 Requisitos

- **WordPress**: 5.0 ou superior
- **PHP**: 7.4 ou superior
- **Navegador**: Qualquer navegador moderno
- **Google Drive API**: Conta Google com API ativada

## 📄 Licença

GPL v2 or later

## 🤝 Suporte

- **GitHub**: [oliveinetpt/gdrive-hierarchy-navigator](https://github.com/oliveinetpt/gdrive-hierarchy-navigator)
- **Issues**: Reporte bugs e sugestões no GitHub
- **Documentação**: README completo e exemplos incluídos

## 📝 Changelog

### v2.5.0 (Atual)
- Sistema completo de estatísticas e analytics
- Gestão avançada de cache com interface dedicada
- Cache ilimitada para arquivos históricos
- Menu admin dedicado com todas as ferramentas
- Versão visível em todas as páginas admin
- Loading localizado apenas na tabela
- 15+ parâmetros de configuração
- Links diretos para Google Drive
- Estatísticas por página WordPress

### Versões Anteriores
Veja o arquivo `readme.txt` para histórico completo.

---

**Desenvolvido para organizações que precisam de uma solução profissional para compartilhar documentos do Google Drive no WordPress.**

