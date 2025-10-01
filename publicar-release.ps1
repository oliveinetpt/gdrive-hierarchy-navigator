# Script para Publicar Release no GitHub
# Google Drive Hierarchy Navigator v2.5.0

$version = "2.5.0"
$tagName = "v$version"

Write-Host "=== Publicador de Release - GDrive Navigator ===" -ForegroundColor Cyan
Write-Host "Versao: $tagName" -ForegroundColor Green
Write-Host ""

# Passo 1: Git Status
Write-Host "=== Git Status ===" -ForegroundColor Yellow
git status
Write-Host ""

# Passo 2: Add
Write-Host "=== Adicionando arquivos ===" -ForegroundColor Yellow
git add .
Write-Host "OK - Arquivos adicionados" -ForegroundColor Green
Write-Host ""

# Passo 3: Commit
Write-Host "=== Criando commit ===" -ForegroundColor Yellow
git commit -m "Release $tagName - Versao completa com todas as funcionalidades"
Write-Host ""

# Passo 4: Push
Write-Host "=== Push para GitHub ===" -ForegroundColor Yellow
git push origin main
Write-Host ""

# Passo 5: Tag
Write-Host "=== Criando e enviando tag ===" -ForegroundColor Yellow
git tag -d $tagName 2>$null
git push origin --delete $tagName 2>$null
git tag -a $tagName -m "Release $tagName"
git push origin $tagName
Write-Host ""

# Passo 6: Instrucoes
Write-Host "=== CRIAR RELEASE NO GITHUB ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Acesse: https://github.com/oliveinetpt/gdrive-hierarchy-navigator/releases/new" -ForegroundColor White
Write-Host ""
Write-Host "Tag: $tagName" -ForegroundColor Gray
Write-Host "Titulo: Release $tagName - Versao Completa" -ForegroundColor Gray
Write-Host ""
Write-Host "Anexe o arquivo: gdrive-hierarchy-navigator.zip" -ForegroundColor White
Write-Host ""

# Abrir navegador
Write-Host "Abrir GitHub no navegador? (S/N)" -ForegroundColor Yellow
$resposta = Read-Host

if ($resposta -eq "S" -or $resposta -eq "s") {
    Start-Process "https://github.com/oliveinetpt/gdrive-hierarchy-navigator/releases/new"
}

Write-Host ""
Write-Host "Concluido!" -ForegroundColor Green
