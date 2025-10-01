# # Usando winget winget install --id GitHub.cli
# OU usando Chocolatey  choco install gh
# OU usando Scoop scoop install gh

# Script para criar release automático no GitHub
param(
    [Parameter(Mandatory=$true)]
    [string]$Version
)

Write-Host "=== Criador de Release GitHub ===" -ForegroundColor Green
Write-Host "Versão: $Version" -ForegroundColor Yellow

# 1. Gerar ZIP
Write-Host "`n1. Gerando ZIP..." -ForegroundColor Cyan
powershell -ExecutionPolicy Bypass -File "gerar-zip.ps1"

if (-not (Test-Path "gdrive-hierarchy-navigator.zip")) {
    Write-Host "❌ Erro: ZIP não foi gerado!" -ForegroundColor Red
    exit 1
}

# 2. Criar e enviar tag
Write-Host "`n2. Criando tag v$Version..." -ForegroundColor Cyan
git tag "v$Version"
git push origin "v$Version"

# 3. Criar release no GitHub
Write-Host "`n3. Criando release no GitHub..." -ForegroundColor Cyan

$releaseNotes = @"
## 🆕 Novidades da Versão $Version

### ✨ Novas Funcionalidades
- 🗂️ Colunas separadas para Data e Tamanho dos ficheiros
- ⚙️ Novos parâmetros ``show_date`` e ``show_size`` para controlo individual
- 📏 Melhor organização visual da tabela

### 🎨 Melhorias de Interface  
- 📅 Coluna de data com alinhamento central
- 📊 Coluna de tamanho com alinhamento à direita
- 📱 Responsividade melhorada em dispositivos móveis

### 🔧 Melhorias Técnicas
- ✅ Maior flexibilidade na configuração de colunas
- 🚀 Performance otimizada na renderização
- 🔄 Compatibilidade mantida com versões anteriores

## 📋 Como Instalar
1. Faça download do ``gdrive-hierarchy-navigator.zip``
2. No WordPress Admin → Plugins → Adicionar Novo → Enviar Plugin
3. Selecione o ficheiro ZIP e instale
4. Ative o plugin

## 🎯 Como Usar
``````php
// Padrão (ambas as colunas visíveis)
[gdrive_navigator folder_id="SEU_ID"]

// Apenas data
[gdrive_navigator folder_id="SEU_ID" show_size="false"]

// Apenas tamanho  
[gdrive_navigator folder_id="SEU_ID" show_date="false"]
``````
"@

gh release create "v$Version" `
  "gdrive-hierarchy-navigator.zip" `
  --title "Versão $Version" `
  --notes $releaseNotes

Write-Host "`n✅ Release v$Version criado com sucesso!" -ForegroundColor Green
Write-Host "🔗 Veja em: https://github.com/oliveinetpt/gdrive-hierarchy-navigator/releases" -ForegroundColor Blue
