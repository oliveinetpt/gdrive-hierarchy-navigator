<?php
/**
 * Exemplos de Uso do Google Drive Hierarchy Navigator
 * 
 * Este arquivo contém exemplos práticos de como usar o plugin
 * Copie e cole os shortcodes nos seus posts/páginas
 */

// IMPORTANTE: Este arquivo é apenas para referência
// Não inclua este arquivo no seu site WordPress

?>

<!-- EXEMPLO 1: Uso Básico -->
<!-- Substitua SEU_FOLDER_ID e SUA_API_KEY pelos valores reais -->
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"]

<!-- EXEMPLO 2: Configuração Completa para Escola -->
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"
    levels="3"
    show_date="true"
    show_size="true"
    show_hits="true"
    show_download="true"
    show_view="true"
    cache_minutes="20"
    max_files="150"
    primary_color="#4285f4"
    secondary_color="#34a853"
    filter_placeholder="Procurar documentos escolares..."]

<!-- EXEMPLO 3: Apenas Visualização (sem download) -->
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"
    levels="2"
    show_date="false"
    show_download="false"
    show_view="true"
    filter_placeholder="Filtrar arquivos..."]

<!-- EXEMPLO 4: Para Dispositivos Móveis (Simplificado) -->
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"
    levels="1"
    show_date="false"
    max_files="50"
    filter_placeholder="Buscar..."]

<!-- EXEMPLO 5: Com Sistema de Estatísticas Completo -->
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"
    levels="2"
    show_date="true"
    show_size="true"
    show_hits="true"
    show_download="true"
    show_view="true"
    cache_minutes="15"
    max_files="100"
    primary_color="#4285f4"
    filter_placeholder="Procurar documentos..."]

<!-- EXEMPLO 6: Sem Contador de Visualizações (Privacidade) -->
[gdrive_navigator 
    folder_id="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" 
    api_key="AIzaSyBhbIRjQZZU0YL7GcJKJQnRe34lJgHRgVM"
    levels="2"
    show_date="true"
    show_size="true"
    show_hits="false"
    show_download="true"
    show_view="true"
    filter_placeholder="Documentos confidenciais..."]

<?php
/*
ESTRUTURA DE PASTAS RECOMENDADA:

📁 Pasta Principal (folder_id do shortcode)
  ├── 📁 Planificações 2024/2025 (Nível 1)
  │   ├── 📁 Matemática (Nível 2)
  │   │   ├── 📄 teste1.pdf
  │   │   ├── 📄 ficha1.docx
  │   │   └── 📄 exercicios.xlsx
  │   ├── 📁 Português (Nível 2)
  │   │   ├── 📄 teste_gramatica.pdf
  │   │   └── 📄 composicao.docx
  │   └── 📁 História (Nível 2)
  │       ├── 📄 idade_media.pdf
  │       └── 📄 descobrimentos.pptx
  ├── 📁 Planificações 2023/2024 (Nível 1)
  │   ├── 📁 Matemática (Nível 2)
  │   └── 📁 Português (Nível 2)
  └── 📁 Recursos Gerais (Nível 1)
      ├── 📁 Modelos (Nível 2)
      └── 📁 Formulários (Nível 2)

SISTEMA DE ESTATÍSTICAS (NOVA FUNCIONALIDADE v2.1.0):

📊 CONTADOR AUTOMÁTICO:
   - Cada clique num arquivo é registado automaticamente
   - Badges coloridos mostram número de hits em tempo real
   - Dados armazenados permanentemente na base de dados

📈 PÁGINA DE ESTATÍSTICAS ADMIN:
   - Acesse: Ferramentas > GDrive Stats
   - Estatísticas gerais (total arquivos, hits, médias)
   - Top 10 arquivos mais visualizados
   - Top 10 pastas mais ativas
   - Atividade recente (últimos 7 dias)

🔒 CONTROLO DE PRIVACIDADE:
   - Use show_hits="false" para ocultar contadores
   - Ideal para documentos confidenciais
   - Dados continuam a ser registados (apenas ocultos na interface)

CONFIGURAÇÃO PARA DIFERENTES CASOS:

1. ESCOLA COM 3 NÍVEIS:
   levels="3" (Ano > Disciplina > Tipo de Documento)

2. EMPRESA COM 2 NÍVEIS:
   levels="2" (Departamento > Categoria)

3. ARQUIVO PESSOAL COM 1 NÍVEL:
   levels="1" (Categorias principais)

DICAS DE CONFIGURAÇÃO:

- cache_minutes: 15-30 minutos para conteúdo que muda frequentemente
- cache_minutes: 60+ minutos para conteúdo estático
- max_files: 50-100 para melhor performance
- max_files: 200+ apenas se necessário

PERSONALIZAÇÃO CSS:

Adicione ao seu tema:

.gdhn-container {
    border: 2px solid #your-color;
    border-radius: 10px;
}

.gdhn-nav-chip {
    background: #your-brand-color;
    color: white;
}

.gdhn-nav-chip:hover {
    background: #your-hover-color;
}

*/
?>

