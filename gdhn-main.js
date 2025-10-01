/**
 * Google Drive Hierarchy Navigator - JavaScript Principal
 * VersÃ£o: 2.5.0
 */

(function($) {
    'use strict';
    
    
    // Classe principal do navegador
    class GDriveHierarchyNavigator {
        constructor(container) {
            this.container = $(container);
            this.config = this.getConfig();
            this.currentPath = [];
            this.folderCache = {};
            this.fileCache = {};
            this.isLoading = false;
            
            this.init();
        }
        
        // Obter configuraÃ§Ã£o do container
        getConfig() {
            const container = this.container;
            
            // Obter valores dos data attributes
            const showDate = container.data('show-date');
            const showSize = container.data('show-size');
            const showHits = container.data('show-hits');
            const showDownload = container.data('show-download');
            const showView = container.data('show-view');
            
            
            const config = {
                folderId: container.data('folder-id'),
                apiKey: container.data('api-key'),
                levels: parseInt(container.data('levels')) || 2,
                cacheMinutes: parseInt(container.data('cache-minutes')) || 15,
                maxFiles: parseInt(container.data('max-files')) || 100,
                showDate: showDate === undefined || showDate === true || showDate === 'true',
                showSize: showSize === undefined || showSize === true || showSize === 'true',
                showHits: showHits === undefined || showHits === true || showHits === 'true',
                showDownload: showDownload === undefined || showDownload === true || showDownload === 'true',
                showView: showView === undefined || showView === true || showView === 'true',
                primaryColor: container.data('primary-color') || '#4285f4',
                secondaryColor: container.data('secondary-color') || '#34a853',
                level1Bg: container.data('level1-bg') || '#4285f4',
                level2Bg: container.data('level2-bg') || '#f8f9fa'
            };
            
            
            return config;
        }
        
        // Inicializar o navegador
        init() {
            this.applyCustomColors();
            this.setupEventListeners();
            this.loadInitialFolders();
        }
        
        // Aplicar cores personalizadas
        applyCustomColors() {
            const containerId = 'gdhn-' + Math.random().toString(36).substr(2, 9);
            this.container.attr('id', containerId);
            
            // Criar CSS customizado
            const customCSS = `
                #${containerId} .gdhn-nav-level[data-level="0"] {
                    background: linear-gradient(135deg, ${this.hexToRgba(this.config.level1Bg, 0.1)} 0%, ${this.hexToRgba(this.config.level1Bg, 0.15)} 100%);
                    border-color: ${this.hexToRgba(this.config.level1Bg, 0.2)};
                }
                #${containerId} .gdhn-nav-chip:hover {
                    background: ${this.config.primaryColor} !important;
                    border-color: ${this.config.primaryColor} !important;
                    color: white !important;
                }
                #${containerId} .gdhn-nav-chip.active {
                    background: ${this.config.primaryColor} !important;
                    border-color: ${this.config.primaryColor} !important;
                    color: white !important;
                }
                #${containerId} .gdhn-btn-icon:hover {
                    background: ${this.config.primaryColor} !important;
                    border-color: ${this.config.primaryColor} !important;
                }
                #${containerId} .gdhn-file-name:hover {
                    color: ${this.config.primaryColor} !important;
                }
                #${containerId} .gdhn-nav-level[data-level="1"] {
                    background: linear-gradient(135deg, ${this.config.level2Bg} 0%, ${this.lightenColor(this.config.level2Bg, -5)} 100%);
                }
            `;
            
            // Adicionar CSS ao head
            if (!document.getElementById('gdhn-custom-css')) {
                const style = document.createElement('style');
                style.id = 'gdhn-custom-css';
                document.head.appendChild(style);
            }
            document.getElementById('gdhn-custom-css').textContent += customCSS;
        }
        
        // FunÃ§Ã£o auxiliar para clarear/escurecer cores
        lightenColor(color, percent) {
            const num = parseInt(color.replace("#",""),16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) + amt;
            const G = (num >> 8 & 0x00FF) + amt;
            const B = (num & 0x0000FF) + amt;
            return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
        }
        
        // FunÃ§Ã£o auxiliar para converter hex para rgba
        hexToRgba(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        
        // Configurar event listeners
        setupEventListeners() {
            // Filtro de arquivos
            this.container.on('input', '.gdhn-filter-input', (e) => {
                this.filterFiles($(e.target).val());
            });
            
            // NavegaÃ§Ã£o por chips
            this.container.on('click', '.gdhn-nav-chip', (e) => {
                e.preventDefault();
                const level = parseInt($(e.target).data('level'));
                const folderId = $(e.target).data('folder-id');
                const folderName = $(e.target).data('folder-name');
                
                this.navigateToFolder(level, folderId, folderName);
            });
            
            // BotÃ£o admin para abrir Google Drive
            this.container.on('click', '.gdhn-admin-drive-btn', (e) => {
                e.preventDefault();
                this.openGoogleDriveAdmin();
            });
            
            // BotÃµes de aÃ§Ã£o dos arquivos
            this.container.on('click', '.gdhn-btn-view', (e) => {
                e.preventDefault();
                // Garantir que pegamos o link mesmo se clicarem no Ã­cone
                const $target = $(e.target).closest('.gdhn-btn-view');
                const url = $target.attr('href');
                
                // Registrar hit quando botÃ£o "View" Ã© clicado
                const $row = $target.closest('tr');
                const fileName = $row.find('.gdhn-file-name').text().trim();
                const $hitsElement = $row.find('.gdhn-hits-count');
                
                if ($hitsElement.length) {
                    const fileId = $hitsElement.data('file-id');
                    if (fileId) {
                        // Registrar hit de forma assÃ­ncrona
                        this.trackFileHit(fileId, fileName, this.config.folderId);
                    }
                }
                
                this.openFile(url);
            });
            
            this.container.on('click', '.gdhn-btn-download', (e) => {
                // Registrar hit quando botÃ£o "Download" Ã© clicado
                const $target = $(e.target).closest('.gdhn-btn-download');
                const $row = $target.closest('tr');
                const fileName = $row.find('.gdhn-file-name').text().trim();
                const $hitsElement = $row.find('.gdhn-hits-count');
                
                if ($hitsElement.length) {
                    const fileId = $hitsElement.data('file-id');
                    if (fileId) {
                        // Registrar hit de forma assÃ­ncrona
                        this.trackFileHit(fileId, fileName, this.config.folderId);
                    }
                }
                
                // Download direto - deixar comportamento padrÃ£o
            });
            
            // Tracking de hits nos nomes dos arquivos
            this.container.on('click', '.gdhn-file-name', (e) => {
                
                // Registrar hit quando arquivo Ã© clicado
                const $link = $(e.target).closest('.gdhn-file-name');
                const $row = $link.closest('tr');
                const fileName = $link.text().trim();
                const $hitsElement = $row.find('.gdhn-hits-count');
                
                if ($hitsElement.length) {
                    const fileId = $hitsElement.data('file-id');
                    if (fileId) {
                        // Registrar hit de forma assÃ­ncrona
                        this.trackFileHit(fileId, fileName, this.config.folderId);
                    } else {
                    }
                } else {
                }
                
                // Permitir comportamento padrÃ£o (abrir link)
            });
        }
        
        // Carregar pastas iniciais (nÃ­vel 1)
        async loadInitialFolders() {
            try {
                
                // Verificar configuraÃ§Ã£o bÃ¡sica
                if (!this.config.folderId || !this.config.apiKey) {
                    throw new Error('ConfiguraÃ§Ã£o invÃ¡lida: falta folder_id ou api_key');
                }
                
                if (!gdhn_ajax || !gdhn_ajax.ajax_url) {
                    throw new Error('AJAX nÃ£o configurado corretamente');
                }
                
                this.showLoading();
                const folders = await this.getFolders(this.config.folderId);
                this.renderNavigationLevel(0, folders);
                this.hideLoading();
            } catch (error) {
                this.showError('Erro ao carregar pastas: ' + error.message);
            }
        }
        
        // Obter pastas via AJAX
        async getFolders(folderId) {
            const cacheKey = `folders_${folderId}`;
            
            
            if (this.folderCache[cacheKey]) {
                return this.folderCache[cacheKey];
            }
            
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: gdhn_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gdhn_load_folder',
                        action_type: 'folders',
                        folder_id: folderId,
                        api_key: this.config.apiKey,
                        cache_minutes: this.config.cacheMinutes,
                        nonce: gdhn_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            this.folderCache[cacheKey] = response.data;
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Erro desconhecido'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Erro AJAX: ${error}`));
                    }
                });
            });
        }
        
        // Obter arquivos via AJAX
        async getFiles(folderId) {
            const cacheKey = `files_${folderId}`;
            
            if (this.fileCache[cacheKey]) {
                return this.fileCache[cacheKey];
            }
            
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: gdhn_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gdhn_load_folder',
                        action_type: 'files',
                        folder_id: folderId,
                        api_key: this.config.apiKey,
                        cache_minutes: this.config.cacheMinutes,
                        max_files: this.config.maxFiles,
                        nonce: gdhn_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            
                            // Verificar cada ficheiro individualmente
                            response.data.forEach((file, index) => {
                            });
                            
                            this.fileCache[cacheKey] = response.data;
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Erro desconhecido'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Erro AJAX: ${error}`));
                    }
                });
            });
        }
        
        // Renderizar nÃ­vel de navegaÃ§Ã£o
        renderNavigationLevel(level, folders) {
            const navBars = this.container.find('.gdhn-navigation-bars');
            
            // Remover nÃ­veis subsequentes se existirem
            navBars.find(`.gdhn-nav-level[data-level="${level}"]`).nextAll().remove();
            navBars.find(`.gdhn-nav-level[data-level="${level}"]`).remove();
            
            if (folders.length === 0) {
                return;
            }
            
            const levelHtml = $(`
                <div class="gdhn-nav-level gdhn-fade-in" data-level="${level}">
                    <div class="gdhn-nav-chips"></div>
                </div>
            `);
            
            const chipsContainer = levelHtml.find('.gdhn-nav-chips');
            
            folders.forEach((folder, index) => {
                const chip = $(`
                    <a href="#" class="gdhn-nav-chip" 
                       data-level="${level}" 
                       data-folder-id="${folder.id}"
                       data-folder-name="${this.escapeHtml(folder.name)}">
                        <i class="fas fa-folder"></i>
                        ${this.escapeHtml(folder.name)}
                    </a>
                `);
                chipsContainer.append(chip);
            });
            
            navBars.append(levelHtml);
            
            // Selecionar automaticamente o primeiro item
            if (folders.length > 0) {
                setTimeout(() => {
                    const firstChip = chipsContainer.find('.gdhn-nav-chip').first();
                    firstChip.click();
                }, 100);
            }
        }
        
        // Navegar para uma pasta
        async navigateToFolder(level, folderId, folderName) {
            try {
                this.showLoadingOverlay();
                
                // Atualizar estado ativo
                this.updateActiveChip(level, folderId);
                
                // Atualizar caminho atual
                this.currentPath = this.currentPath.slice(0, level + 1);
                this.currentPath[level] = { id: folderId, name: folderName };
                
                // Se nÃ£o Ã© o Ãºltimo nÃ­vel configurado, carregar subpastas
                if (level < this.config.levels - 1) {
                    const subfolders = await this.getFolders(folderId);
                    this.renderNavigationLevel(level + 1, subfolders);
                    this.hideFilesSection();
                } else {
                    // Ã‰ o Ãºltimo nÃ­vel, carregar arquivos
                    const files = await this.getFiles(folderId);
                    this.renderFiles(files);
                    this.showFilesSection();
                }
                
                this.hideLoadingOverlay();
            } catch (error) {
                this.hideLoadingOverlay();
                this.showError('Erro ao navegar: ' + error.message);
            }
        }
        
        // Atualizar chip ativo
        updateActiveChip(level, folderId) {
            // Remover estado ativo de todos os chips do mesmo nÃ­vel
            this.container.find(`.gdhn-nav-chip[data-level="${level}"]`).removeClass('active');
            
            // Adicionar estado ativo ao chip selecionado
            this.container.find(`.gdhn-nav-chip[data-level="${level}"][data-folder-id="${folderId}"]`).addClass('active');
        }
        
        // Renderizar arquivos na tabela
        renderFiles(files) {
            
            const tbody = this.container.find('.gdhn-files-tbody');
            
            tbody.empty();
            
            if (files.length === 0) {
                this.container.find('.gdhn-no-files').show();
                this.container.find('.gdhn-files-table').hide();
                return;
            }
            
            this.container.find('.gdhn-no-files').hide();
            this.container.find('.gdhn-files-table').show();
            
            files.forEach((file, index) => {
                
                const row = this.createFileRow(file);
                
                tbody.append(row);
            });
            
            
            // Resetar filtro
            this.container.find('.gdhn-filter-input').val('');
            
            // Carregar hits dos arquivos
            this.loadFileHits();
        }
        
        // Criar linha da tabela para um arquivo
        createFileRow(file) {
            
            const actionsHtml = this.createActionButtons(file);
            
            // URLs de fallback caso nÃ£o venham do backend
            const viewUrl = file.view_url || (file.id ? `https://drive.google.com/file/d/${file.id}/view` : '#');
            
            // VerificaÃ§Ã£o mais robusta dos dados formatados
            const dateValue = file.formatted_date || file.modifiedTime || '';
            const sizeValue = file.formatted_size || (file.size ? this.formatFileSize(file.size) : '');
            
            const dateHtml = this.config.showDate ? `<td class="gdhn-date-col">${dateValue}</td>` : '';
            const sizeHtml = this.config.showSize ? `<td class="gdhn-size-col">${sizeValue}</td>` : '';
            const hitsHtml = this.config.showHits ? `<td class="gdhn-hits-col"><span class="gdhn-hits-count" data-file-id="${file.id}">0</span></td>` : '';
            
            
            const rowHtml = `
                <tr data-file-name="${this.escapeHtml(file.name.toLowerCase())}">
                    <td class="gdhn-icon-col">
                        <span class="gdhn-file-icon">${file.file_icon || ''}</span>
                    </td>
                    <td class="gdhn-name-col">
                        <a href="${viewUrl}" 
                           class="gdhn-file-name" 
                           target="_blank" 
                           rel="noopener noreferrer">
                            ${this.escapeHtml(file.name || 'Sem nome')}
                        </a>
                    </td>
                    ${dateHtml}
                    ${sizeHtml}
                    ${hitsHtml}
                    <td class="gdhn-actions-col">
                        <div class="gdhn-action-buttons">
                            ${actionsHtml}
                        </div>
                    </td>
                </tr>
            `;
            
            // Validar HTML antes de criar o objeto jQuery
            const openTags = (rowHtml.match(/<td/g) || []).length;
            const closeTags = (rowHtml.match(/<\/td>/g) || []).length;
            
            
            const $row = $(rowHtml);
            
            // Verificar alinhamento de colunas
            const headerCols = this.container.find('.gdhn-files-table thead th').length;
            const rowCols = $row.find('td').length;
            
            
            return $row;
        }
        
        // Criar botÃµes de aÃ§Ã£o para um arquivo
        createActionButtons(file) {
            let buttons = '';

            // URLs de fallback caso nÃ£o venham do backend
            const viewUrl = file.view_url || (file.id ? `https://drive.google.com/file/d/${file.id}/view` : null);
            const downloadUrl = file.download_url || (file.id ? `https://drive.google.com/uc?export=download&id=${file.id}` : null);

            // BotÃ£o Ver
            if (this.config.showView && viewUrl) {
                buttons += `
                    <a href="${viewUrl}"
                       class="gdhn-btn-icon gdhn-btn-view"
                       title="Ver arquivo"
                       target="_blank"
                       rel="noopener noreferrer">
                        <i class="fas fa-eye"></i>
                    </a>
                `;
            }

            // BotÃ£o Download
            if (this.config.showDownload && downloadUrl) {
                buttons += `
                    <a href="${downloadUrl}"
                       class="gdhn-btn-icon gdhn-btn-download"
                       title="Descarregar arquivo"
                       target="_blank"
                       rel="noopener noreferrer">
                        <i class="fas fa-download"></i>
                    </a>
                `;
            }

            return buttons;
        }
        
        // Filtrar arquivos
        filterFiles(query) {
            const rows = this.container.find('.gdhn-files-tbody tr');
            const normalizedQuery = query.toLowerCase().trim();
            
            if (!normalizedQuery) {
                rows.show();
                return;
            }
            
            rows.each(function() {
                const fileName = $(this).data('file-name');
                if (fileName.includes(normalizedQuery)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        // Abrir arquivo
        openFile(url) {
            if (url && url !== '#') {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        }
        
        // Carregar hits dos arquivos
        async loadFileHits() {
            
            if (!this.config.showHits) {
                return;
            }
            
            const fileElements = this.container.find('.gdhn-hits-count');
            
            fileElements.each((index, element) => {
                const $element = $(element);
                const fileId = $element.data('file-id');
                
                
                if (fileId) {
                    this.getFileHits(fileId).then(hits => {
                        $element.text(hits);
                    });
                } else {
                }
            });
        }
        
        // Obter hits de um arquivo especÃ­fico
        async getFileHits(fileId) {
            try {
                const response = await $.ajax({
                    url: gdhn_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gdhn_get_file_hits',
                        file_id: fileId,
                        nonce: gdhn_ajax.nonce
                    }
                });
                
                if (response.success) {
                    return response.data.hits || 0;
                }
                return 0;
            } catch (error) {
                return 0;
            }
        }
        
        // Obter nome da pasta atual
        getCurrentFolderName() {
            // Procurar pelo chip ativo do Ãºltimo nÃ­vel
            const activeChips = this.container.find('.gdhn-nav-chip.active');
            if (activeChips.length > 0) {
                // Pegar o Ãºltimo chip ativo (nÃ­vel mais profundo)
                const lastActiveChip = activeChips.last();
                const folderName = lastActiveChip.text().trim();
                if (folderName) {
                    return folderName;
                }
            }
            
            // Se nÃ£o houver chip ativo, tentar buscar o nome da pasta atual do cache
            const currentFolderId = this.getCurrentFolderId();
            if (currentFolderId && this.cache && this.cache[currentFolderId]) {
                const cachedData = this.cache[currentFolderId];
                // Se houver dados de pastas no cache, pegar o nome da primeira pasta pai
                if (cachedData.folders && cachedData.folders.length > 0) {
                    return cachedData.folders[0].name;
                }
            }
            
            // Ãšltimo recurso: buscar no currentPath
            if (this.currentPath.length > 0) {
                const currentFolder = this.currentPath[this.currentPath.length - 1];
                if (currentFolder && currentFolder.name) {
                    return currentFolder.name;
                }
            }
            
            return null;
        }
        
        // Obter ID da pasta atual (onde o ficheiro estÃ¡)
        getCurrentFolderId() {
            // Procurar pelo chip ativo do Ãºltimo nÃ­vel
            const activeChips = this.container.find('.gdhn-nav-chip.active');
            if (activeChips.length > 0) {
                // Pegar o Ãºltimo chip ativo (nÃ­vel mais profundo)
                const lastActiveChip = activeChips.last();
                return lastActiveChip.data('folder-id');
            }
            return this.config.folderId; // Fallback para pasta raiz
        }

        // Registrar hit de um arquivo
        async trackFileHit(fileId, fileName, originalFolderId) {
            // Obter informaÃ§Ãµes da pasta atual
            const currentFolderId = this.getCurrentFolderId();
            const currentFolderName = this.getCurrentFolderName();
            
            if (!this.config.showHits || !fileId) {
                return;
            }
            
            try {
                const response = await $.ajax({
                    url: gdhn_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gdhn_track_hit',
                        file_id: fileId,
                        file_name: fileName,
                        folder_id: currentFolderId,
                        folder_name: currentFolderName,
                        page_url: window.location.href,
                        page_title: document.title,
                        nonce: gdhn_ajax.nonce
                    }
                });
                
                if (response.success) {
                    // Atualizar contador na interface
                    const $counter = this.container.find(`.gdhn-hits-count[data-file-id="${fileId}"]`);
                    if ($counter.length) {
                        $counter.text(response.data.hits);
                    } else {
                    }
                    return response.data.hits;
                } else {
                    console.error('GDHN: Erro na resposta do servidor:', response);
                }
            } catch (error) {
                console.error('GDHN: Erro ao registrar hit:', error);
            }
            return 0;
        }
        
        // Abrir Google Drive para administradores
        openGoogleDriveAdmin() {
            if (this.config && this.config.folderId) {
                // URL para abrir a pasta diretamente no Google Drive
                const driveUrl = `https://drive.google.com/drive/folders/${this.config.folderId}`;
                window.open(driveUrl, '_blank', 'noopener,noreferrer');
            } else {
            }
        }
        
        // Mostrar seÃ§Ã£o de arquivos
        showFilesSection() {
            this.container.find('.gdhn-filter-section').show();
            this.container.find('.gdhn-files-section').show();
        }
        
        // Esconder seÃ§Ã£o de arquivos
        hideFilesSection() {
            this.container.find('.gdhn-filter-section').hide();
            this.container.find('.gdhn-files-section').hide();
        }
        
        // Mostrar loading inicial
        showLoading() {
            this.container.find('.gdhn-loading').show();
            this.container.find('.gdhn-navigation-bars').hide();
            this.hideFilesSection();
        }
        
        // Esconder loading inicial
        hideLoading() {
            this.container.find('.gdhn-loading').hide();
            this.container.find('.gdhn-navigation-bars').show();
        }
        
        // Mostrar overlay de loading (apenas na seÃ§Ã£o de arquivos)
        showLoadingOverlay() {
            if (this.isLoading) return;
            this.isLoading = true;
            
            // Aplicar overlay apenas na seÃ§Ã£o de arquivos, nÃ£o em todo o container
            const filesSection = this.container.find('.gdhn-files-section');
            if (filesSection.length === 0) return;
            
            const overlay = $(`
                <div class="gdhn-loading-overlay">
                    <div class="gdhn-spinner"></div>
                </div>
            `);
            
            // Adicionar position relative na seÃ§Ã£o de arquivos se nÃ£o tiver
            filesSection.css('position', 'relative');
            filesSection.append(overlay);
        }
        
        // Esconder overlay de loading
        hideLoadingOverlay() {
            this.isLoading = false;
            this.container.find('.gdhn-loading-overlay').remove();
        }
        
        // Mostrar erro
        showError(message) {
            this.hideLoading();
            this.hideLoadingOverlay();
            
            const errorSection = this.container.find('.gdhn-error-section');
            const errorMessage = errorSection.find('.gdhn-error-message');
            
            errorMessage.html(`âŒ ${this.escapeHtml(message)}`);
            errorSection.show();
            
            // Auto-hide apÃ³s 5 segundos
            setTimeout(() => {
                errorSection.hide();
            }, 5000);
        }
        
        // Escape HTML
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Formatar tamanho do arquivo (fallback)
        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '';
            
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let i = 0;
            let size = parseInt(bytes);
            
            while (size >= 1024 && i < units.length - 1) {
                size /= 1024;
                i++;
            }
            
            const decimals = i > 1 ? 2 : 0;
            return size.toFixed(decimals) + ' ' + units[i];
        }
    }
    
    // FunÃ§Ã£o para inicializar containers
    function initializeContainers() {
        $('.gdhn-container').each(function() {
            if (!$(this).data('gdhn-initialized')) {
                new GDriveHierarchyNavigator(this);
                $(this).data('gdhn-initialized', true);
            }
        });
    }
    
    // Inicializar quando o DOM estiver pronto
    $(document).ready(function() {
        initializeContainers();
    });
    
    // Compatibilidade com Elementor
    $(window).on('elementor/frontend/init', function() {
        setTimeout(initializeContainers, 500);
    });
    
    // Monitorar mudanÃ§as no DOM (para page builders)
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            let shouldInit = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            if ($(node).hasClass('gdhn-container') || $(node).find('.gdhn-container').length > 0) {
                                shouldInit = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldInit) {
                setTimeout(initializeContainers, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // TambÃ©m inicializar para containers adicionados dinamicamente
    $(document).on('gdhn:init', '.gdhn-container', function() {
        if (!$(this).data('gdhn-initialized')) {
            new GDriveHierarchyNavigator(this);
            $(this).data('gdhn-initialized', true);
        }
    });
    
    // Event para forÃ§ar reinicializaÃ§Ã£o
    $(document).on('gdhn:reinit', function() {
        $('.gdhn-container').removeData('gdhn-initialized');
        initializeContainers();
    });
    
})(jQuery);
