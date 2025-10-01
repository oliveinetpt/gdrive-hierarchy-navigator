=== Google Drive Hierarchy Navigator ===
Contributors: oliveinet
Tags: google-drive, file-manager, education, documents, shortcode
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress para navegação hierárquica de pastas e arquivos do Google Drive com barras de navegação e filtros.

== Description ==

O **Google Drive Hierarchy Navigator** é um plugin WordPress que permite criar um sistema de navegação hierárquica para pastas e arquivos do Google Drive. É especialmente útil para escolas, instituições educativas e organizações que precisam organizar documentos por anos letivos e categorias.

### Características Principais

* **Navegação Hierárquica**: Suporte para até 3 níveis de pastas (ex: Ano Letivo > Disciplina > Tipo de Documento)
* **Interface Moderna**: Barras horizontais com chips/botões clicáveis para navegação intuitiva
* **Filtro de Arquivos**: Caixa de pesquisa para filtrar arquivos rapidamente
* **Ícones por Tipo**: Ícones automáticos para PDF, DOC, XLS, TXT e outros formatos
* **Contador de Visualizações**: Sistema de tracking automático de hits por arquivo
* **Estatísticas Avançadas**: Página admin com relatórios detalhados de utilização
* **Botões de Ação**: Botões para visualizar e descarregar arquivos
* **Cache Inteligente**: Sistema de cache para melhor performance
* **Responsivo**: Interface adaptável para dispositivos móveis
* **Configurável**: Múltiplas opções de customização via shortcode

### Como Usar

1. Obtenha uma API Key do Google Drive
2. Configure uma pasta pública no Google Drive
3. Use o shortcode `[gdrive_navigator]` com os parâmetros necessários

### Exemplo de Uso Básico

```
[gdrive_navigator folder_id="SEU_FOLDER_ID" api_key="SUA_API_KEY"]
```

### Exemplo Completo

```
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

== Installation ==

### Instalação Automática

1. Acesse **Plugins > Adicionar Novo** no painel do WordPress
2. Pesquise por "Google Drive Hierarchy Navigator"
3. Clique em **Instalar Agora** e depois **Ativar**

### Instalação Manual

1. Descarregue o arquivo ZIP do plugin
2. Acesse **Plugins > Adicionar Novo > Enviar Plugin**
3. Selecione o arquivo ZIP e clique em **Instalar Agora**
4. Ative o plugin após a instalação

### Configuração

1. **Obter API Key do Google Drive**:
   - Acesse o [Google Cloud Console](https://console.cloud.google.com/)
   - Crie um projeto ou selecione um existente
   - Ative a Google Drive API
   - Crie credenciais (API Key)

2. **Configurar Pasta do Google Drive**:
   - Crie uma pasta no Google Drive
   - Torne a pasta pública (compartilhamento aberto)
   - Copie o ID da pasta da URL

3. **Adicionar Shortcode**:
   - Adicione o shortcode em qualquer página ou post
   - Configure os parâmetros conforme necessário

== Frequently Asked Questions ==

= Como obtenho o ID da pasta do Google Drive? =

O ID da pasta está na URL quando você acessa a pasta no Google Drive:
`https://drive.google.com/drive/folders/ID_DA_PASTA_AQUI`

= Como configuro uma API Key do Google Drive? =

1. Acesse o Google Cloud Console
2. Crie um projeto novo ou selecione um existente
3. Vá para "APIs & Services > Library"
4. Procure e ative a "Google Drive API"
5. Vá para "APIs & Services > Credentials"
6. Clique em "Create Credentials > API Key"
7. Copie a chave gerada

= O plugin funciona com pastas privadas? =

Não, o plugin funciona apenas com pastas públicas do Google Drive devido às limitações da API pública.

= Posso personalizar os estilos? =

Sim, o plugin usa classes CSS específicas que podem ser personalizadas no tema:
- `.gdhn-container`
- `.gdhn-nav-chip`
- `.gdhn-files-table`
- E muitas outras

= Quantos níveis de pastas são suportados? =

O plugin suporta entre 1 e 3 níveis de navegação hierárquica, configurável via parâmetro `levels`.

= O plugin é compatível com dispositivos móveis? =

Sim, o plugin é totalmente responsivo e otimizado para dispositivos móveis.

== Screenshots ==

1. Interface principal com navegação hierárquica
2. Barras de navegação com chips clicáveis
3. Tabela de arquivos com filtro e ações
4. Interface responsiva em dispositivos móveis

== Changelog ==

= 2.5.0 =
* 🎉 **RELEASE MAJOR**: Versão completa com todas as funcionalidades implementadas
* 📚 **DOCUMENTAÇÃO**: README.md completamente reescrito com todos os recursos
* ✨ **CONSOLIDAÇÃO**: Todas as melhorias das versões 2.x unificadas
* 🎯 **PRODUÇÃO**: Plugin pronto para uso profissional em produção
* 📊 Sistema completo de estatísticas e analytics
* 🗑️ Gestão avançada de cache
* ♾️ Cache ilimitada configurável
* 🗂️ Menu admin dedicado
* 📌 Versão visível em todas as páginas
* 🔗 Links corrigidos e funcionais

= 2.4.3 =
* 📌 **MELHORIA**: Número da versão agora visível em todas as páginas admin do plugin
* 🔗 **CORREÇÃO**: Links de "Configurações" e "Diagnóstico" na lista de plugins agora funcionam corretamente
* ✨ **UX**: Versão exibida discretamente ao lado do título de cada página admin

= 2.4.2 =
* 🎨 **MELHORIA UX**: Loading overlay agora aparece apenas na tabela de arquivos
* ✨ **MELHORIA**: Navegação por chips permanece sempre visível e responsiva durante o carregamento
* 🔄 **MELHORIA**: Efeito de blur suave na área de loading para melhor feedback visual
* ⚡ **PERFORMANCE**: Animação mais rápida e suave (0.2s) no loading
* 👁️ **UX**: Usuário pode ver e interagir com a navegação enquanto os arquivos carregam

= 2.4.1 =
* ♾️ **MELHORIA CRÍTICA**: Removido limite máximo de cache - agora aceita valores ilimitados
* 📅 **NOVA OPÇÃO**: Adicionado preset de 30 dias (1 mês) e 365 dias (1 ano)
* ✏️ **NOVA FUNCIONALIDADE**: Opção "Definir valor personalizado" no dropdown de cache
* 🎯 **CASO DE USO**: Perfeito para arquivos históricos que nunca mudam (ex: `cache_minutes="999999999"`)
* 💡 **FLEXIBILIDADE**: Shortcode agora respeita qualquer valor de cache definido pelo utilizador
* 📚 **DOCUMENTAÇÃO**: Adicionada dica sobre uso de cache ilimitada para arquivos de arquivo

= 2.4.0 =
* 🗑️ **NOVA FUNCIONALIDADE**: Página de Gestão de Cache no menu admin
* 📊 **NOVA FUNCIONALIDADE**: Estatísticas de cache em tempo real (total, pastas, arquivos)
* 🧹 **NOVA FUNCIONALIDADE**: Botões para limpar cache (toda, só pastas, só arquivos)
* ⏰ **MELHORIA**: Tempo de cache expandido - agora suporta até 7 dias (10080 minutos)
* 🎛️ **MELHORIA**: Seletor dropdown com opções pré-definidas de cache (5min, 15min, 30min, 1h, 2h, 4h, 12h, 24h, 3 dias, 7 dias)
* 🚫 **NOVA OPÇÃO**: Possibilidade de desativar cache completamente (0 minutos)
* 💡 **MELHORIA**: Explicações detalhadas sobre quando e por que limpar a cache
* 🎯 Melhor controle sobre performance vs. atualização de conteúdo

= 2.3.2 =
* 📁 **NOVA FUNCIONALIDADE**: Link para pasta do Google Drive também no "Top 10 Arquivos Mais Visualizados"
* ✨ Melhor navegação: agora todas as seções de estatísticas incluem links diretos para pastas
* 🎯 Consistência: formato uniforme em todas as áreas de estatísticas

= 2.3.1 =
* 🔗 **NOVA FUNCIONALIDADE**: Link para pasta do Google Drive em cada arquivo nas estatísticas por página
* 📁 **CORREÇÃO**: Captura automática de nomes de pastas via API quando JavaScript não fornece
* 🔧 **MELHORIA**: Função `getCurrentFolderName()` mais robusta com múltiplos fallbacks
* ✨ Melhor identificação de pastas em estatísticas, eliminando registos "Não definido"

= 2.3.0 =
* 📋 **MELHORIA**: Página de configurações com exemplo completo de shortcode e todos os 15 parâmetros
* 📖 **CORREÇÃO**: Link de documentação atualizado para oliveinetpt
* 🔗 **NOVA FUNCIONALIDADE**: Links diretos para Google Drive na Atividade Recente (últimos 7 dias)
* 📄 **NOVA FUNCIONALIDADE**: Card de filtro por página nas estatísticas
* 🎯 Possibilidade de selecionar uma página e ver exatamente quais arquivos foram visualizados
* 📊 Estatísticas detalhadas por página com links para arquivos
* 🔍 Caixa de seleção com todas as páginas que têm atividade registada
* ✨ Interface ainda mais profissional e completa

= 2.2.3 =
* 📊 **NOVA FUNCIONALIDADE**: Tracking de página/artigo onde cada arquivo foi clicado
* 📁 **MELHORIA**: Nomes reais de pastas em vez de hashes nas estatísticas
* 🏆 **NOVA SEÇÃO**: Top 10 Páginas Mais Ativas nas estatísticas
* 🗂️ **MELHORIA**: Menu dedicado "GDrive Navigator" com todas as opções organizadas
* 🎨 Ícone sugestivo no menu principal do WordPress
* 🔄 Sistema automático de atualização de nomes de pastas via API

= 2.2.0 =
* 📥 **NOVA FUNCIONALIDADE**: Botão Download também conta visualizações
* 🗂️ **MELHORIA**: Menu dedicado "GDrive Navigator" organizando todas as ferramentas
* 📊 **MELHORIA**: Página de estatísticas mostra nomes reais de pastas
* 📄 **NOVA FUNCIONALIDADE**: Estatísticas por página/artigo WordPress
* 🔍 Filtros avançados para análise de utilização

= 2.1.0 =
* 📊 **NOVA FUNCIONALIDADE**: Sistema de contador de visualizações por arquivo
* 📈 **NOVA FUNCIONALIDADE**: Página de estatísticas avançadas no admin (Ferramentas > GDrive Stats)
* 🎯 Tracking automático de hits quando utilizadores clicam nos arquivos
* 📊 Badges coloridos mostram número de visualizações em cada arquivo
* 📈 Relatórios detalhados: estatísticas gerais, top arquivos, pastas mais ativas
* 🕒 Atividade recente dos últimos 7 dias
* ⚙️ Parâmetro `show_hits="true/false"` para controlar exibição da coluna
* 🗄️ Tabela de base de dados para armazenar dados de visualizações
* 💡 Funcionalidade ideal para escolas e organizações monitorizarem utilização

= 2.0.2 =
* 🔧 Corrigido erro de sintaxe JavaScript que impedia carregamento
* ✅ Validação completa da sintaxe em todos os ficheiros
* 🚀 Plugin totalmente funcional e estável

= 2.0.1 =
* 🔧 Corrigidos problemas de renderização da tabela
* 📊 Melhorado parsing dos parâmetros show_date e show_size
* 👤 Botão "Google Drive" para administradores abrirem pasta diretamente
* 🎨 Melhorados estilos dos botões de ação
* ✅ Funcionalidade "Ver" totalmente operacional

= 2.0.0 =
* 🎨 Interface completamente redesenhada com navegação por chips
* 📊 Colunas separadas para data e tamanho dos arquivos
* ⚙️ Parâmetros show_date e show_size para controlar colunas
* 🎨 Sistema de cores personalizáveis (primary_color, level1_bg, level2_bg)
* 🔍 Filtro melhorado sem ícone de pesquisa sobreposto
* 📱 Design responsivo otimizado
* 🎯 Botões de ação simplificados com ícones Font Awesome
* 🔧 Compatibilidade melhorada com Elementor e page builders

= 1.9.0 =
* 🗂️ Coluna da data alterada para mostrar tamanho do ficheiro
* 🎨 Removido fundo dos links dos ficheiros (design mais limpo)
* 👁️ Removido botão "Ver" das ações (funcionalidade já no nome do ficheiro)
* 📏 Melhorado alinhamento da coluna de tamanho à direita
* ✨ Interface mais intuitiva e funcional

= 1.8.0 =
* 📁 Assets movidos para a raiz do plugin (resolve problemas de instalação)
* 🎨 Fundo do primeiro nível muito mais suave e discreto
* 🔗 Links dos nomes dos ficheiros seguem esquema de cores personalizado
* ✨ Cores mais harmoniosas e profissionais
* 🔧 Estrutura de ficheiros simplificada para melhor compatibilidade

= 1.7.1 =
* 🎨 Corrida cor do texto dos chips para cinzento escuro (melhor legibilidade)
* ✨ Texto só fica colorido no hover/ativo, normal é cinzento escuro

= 1.7.0 =
* 🎨 Cores personalizáveis via shortcode (primary_color, secondary_color, level1_bg, level2_bg)
* 🔍 Lupa removida do campo de filtro para interface mais limpa
* 🔘 Ações dos arquivos simplificadas para apenas símbolos Font Awesome
* ✨ Botões circulares com hover e animações suaves
* 🎯 Interface totalmente adaptável às cores do site

= 1.6.0 =
* 🎨 Design diferenciado para níveis de navegação (primeiro nível com fundo azul, segundo com fundo claro)
* 🔧 Carregamento melhorado do Font Awesome com detecção de conflitos
* 🛡️ Ícones de fallback (emojis) caso Font Awesome não carregue
* ✨ Interface mais apelativa e níveis visualmente distintos
* 📱 Melhorias no layout responsivo dos níveis

= 1.5.0 =
* ✨ Seleção automática do primeiro item de cada nível
* 🔧 Botões Ver e Descarregar corrigidos e funcionais
* 🎨 Substituição de emojis por ícones Font Awesome
* 🧹 Remoção de labels "Anos Letivos" e "Categorias" para interface mais limpa
* 🔍 Correção do posicionamento do ícone de filtro
* 📱 Melhorias na experiência do usuário
* 🚀 Carregamento automático de conteúdo na inicialização

= 1.0.0 =
* Lançamento inicial
* Navegação hierárquica de pastas
* Interface com chips/botões clicáveis
* Filtro de arquivos
* Ícones por tipo de arquivo
* Sistema de cache
* Interface responsiva
* Suporte para múltiplos níveis configuráveis

== Upgrade Notice ==

= 1.0.0 =
Primeira versão do plugin. Instalação limpa recomendada.

== Parameters Reference ==

### Parâmetros do Shortcode

* **folder_id** (obrigatório): ID da pasta raiz do Google Drive
* **api_key** (obrigatório): Chave API do Google Drive
* **levels** (opcional, padrão: 2): Número de níveis de navegação (1-3)
* **show_date** (opcional, padrão: true): Mostrar data dos arquivos
* **show_download** (opcional, padrão: true): Mostrar botão de download
* **show_view** (opcional, padrão: true): Mostrar botão de visualização
* **cache_minutes** (opcional, padrão: 15): Minutos de cache (1-60)
* **max_files** (opcional, padrão: 100): Máximo de arquivos por pasta (10-500)
* **filter_placeholder** (opcional): Texto do placeholder do filtro

== Technical Details ==

### Requisitos do Sistema

* WordPress 5.0 ou superior
* PHP 7.4 ou superior
* Conexão à internet para API do Google Drive
* JavaScript habilitado no navegador

### Tipos de Arquivo Suportados

O plugin reconhece automaticamente os seguintes tipos:
* PDF (📄)
* Word (📝)
* Excel (📊)
* PowerPoint (📋)
* Texto (📃)
* Imagens (🖼️)
* Vídeos (🎬)
* Áudio (🎵)
* Outros (📎)

### Performance e Cache

* Cache automático de pastas e arquivos
* Tempo de cache configurável
* Lazy loading para melhor performance
* Otimização para grandes quantidades de arquivos

== Support ==

Para suporte e documentação adicional, visite:
* [Documentação Oficial](https://github.com/oliveinet/gdrive-hierarchy-navigator)
* [Reportar Bugs](https://github.com/oliveinet/gdrive-hierarchy-navigator/issues)
* [Fórum de Suporte WordPress](https://wordpress.org/support/plugin/gdrive-hierarchy-navigator/)

== Credits ==

Desenvolvido por [oliveinet](https://oliveinet.com)

Este plugin utiliza a Google Drive API v3 e é otimizado para uso educacional e organizacional.
