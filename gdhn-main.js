/**
 * Google Drive Hierarchy Navigator - JavaScript Principal
 * Versão: 1.9.0
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
        
        // Obter configuração do container
        getConfig() {
            const container = this.container;
            
            // Obter valores dos data attributes
            const showDate = container.data('show-date');
            const showSize = container.data('show-size');
            const showDownload = container.data('show-download');
            const showView = container.data('show-view');
            
            return {
                folderId: container.data('folder-id'),
                apiKey: container.data('api-key'),
                levels: parseInt(container.data('levels')) || 2,
                cacheMinutes: parseInt(container.data('cache-minutes')) || 15,
                maxFiles: parseInt(container.data('max-files')) || 100,
                showDate: showDate === 'true',
                showSize: showSize === undefined || showSize === 'true',
                showDownload: showDownload === undefined || showDownload === 'true',
                showView: showView === undefined || showView === 'true',
                primaryColor: container.data('primary-color') || '#4285f4',
                secondaryColor: container.data('secondary-color') || '#34a853',
                level1Bg: container.data('level1-bg') || '#4285f4',
                level2Bg: container.data('level2-bg') || '#f8f9fa'
            };
        }
        
        // Inicializar o navegador
        init() {
            this.log('Inicializando navegador', this.config);
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
        
        // Função auxiliar para clarear/escurecer cores
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
        
        // Função auxiliar para converter hex para rgba
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
            
            // Navegação por chips
            this.container.on('click', '.gdhn-nav-chip', (e) => {
                e.preventDefault();
                const level = parseInt($(e.target).data('level'));
                const folderId = $(e.target).data('folder-id');
                const folderName = $(e.target).data('folder-name');
                
                this.navigateToFolder(level, folderId, folderName);
            });
            
            // Botões de ação dos arquivos
            this.container.on('click', '.gdhn-btn-view', (e) => {
                e.preventDefault();
                const url = $(e.target).attr('href');
                this.openFile(url);
            });
            
            this.container.on('click', '.gdhn-btn-download', (e) => {
                // Download direto - deixar comportamento padrão
            });
        }
        
        // Carregar pastas iniciais (nível 1)
        async loadInitialFolders() {
            try {
                this.log('Carregando pastas iniciais');
                
                // Verificar configuração básica
                if (!this.config.folderId || !this.config.apiKey) {
                    throw new Error('Configuração inválida: falta folder_id ou api_key');
                }
                
                if (!gdhn_ajax || !gdhn_ajax.ajax_url) {
                    throw new Error('AJAX não configurado corretamente');
                }
                
                this.showLoading();
                const folders = await this.getFolders(this.config.folderId);
                this.log('Pastas carregadas', folders);
                this.renderNavigationLevel(0, folders);
                this.hideLoading();
            } catch (error) {
                this.log('Erro ao carregar pastas', error);
                this.showError('Erro ao carregar pastas: ' + error.message);
            }
        }
        
        // Obter pastas via AJAX
        async getFolders(folderId) {
            const cacheKey = `folders_${folderId}`;
            
            this.log('getFolders chamado', {folderId, cacheKey});
            
            if (this.folderCache[cacheKey]) {
                this.log('Usando cache para pastas');
                return this.folderCache[cacheKey];
            }
            
            this.log('Fazendo requisição AJAX para pastas');
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
                        this.log('Resposta AJAX recebida', response);
                        if (response.success) {
                            this.folderCache[cacheKey] = response.data;
                            this.log('Dados salvos no cache', response.data);
                            resolve(response.data);
                        } else {
                            this.log('Erro na resposta AJAX', response.data);
                            reject(new Error(response.data || 'Erro desconhecido'));
                        }
                    },
                    error: (xhr, status, error) => {
                        this.log('Erro AJAX', {xhr, status, error, responseText: xhr.responseText});
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
        
        // Renderizar nível de navegação
        renderNavigationLevel(level, folders) {
            const navBars = this.container.find('.gdhn-navigation-bars');
            
            // Remover níveis subsequentes se existirem
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
                
                // Se não é o último nível configurado, carregar subpastas
                if (level < this.config.levels - 1) {
                    const subfolders = await this.getFolders(folderId);
                    this.renderNavigationLevel(level + 1, subfolders);
                    this.hideFilesSection();
                } else {
                    // É o último nível, carregar arquivos
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
            // Remover estado ativo de todos os chips do mesmo nível
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
            
            files.forEach(file => {
                const row = this.createFileRow(file);
                tbody.append(row);
            });
            
            // Resetar filtro
            this.container.find('.gdhn-filter-input').val('');
        }
        
        // Criar linha da tabela para um arquivo
        createFileRow(file) {
            const actionsHtml = this.createActionButtons(file);
            
            // URLs de fallback caso não venham do backend
            const viewUrl = file.view_url || (file.id ? `https://drive.google.com/file/d/${file.id}/view` : '#');
            
            const dateHtml = this.config.showDate ? `<td class="gdhn-date-col">${file.formatted_date || ''}</td>` : '';
            const sizeHtml = this.config.showSize ? `<td class="gdhn-size-col">${file.formatted_size || ''}</td>` : '';
            
            return $(`
                <tr data-file-name="${this.escapeHtml(file.name.toLowerCase())}">
                    <td class="gdhn-icon-col">
                        <span class="gdhn-file-icon">${file.file_icon}</span>
                    </td>
                    <td class="gdhn-name-col">
                        <a href="${viewUrl}" 
                           class="gdhn-file-name" 
                           target="_blank" 
                           rel="noopener noreferrer">
                            ${this.escapeHtml(file.name)}
                        </a>
                    </td>
                    ${dateHtml}
                    ${sizeHtml}
                    <td class="gdhn-actions-col">
                        <div class="gdhn-action-buttons">
                            ${actionsHtml}
                        </div>
                    </td>
                </tr>
            `);
        }
        
        // Criar botões de ação para um arquivo
        createActionButtons(file) {
            let buttons = '';

            // URLs de fallback caso não venham do backend
            const viewUrl = file.view_url || (file.id ? `https://drive.google.com/file/d/${file.id}/view` : null);
            const downloadUrl = file.download_url || (file.id ? `https://drive.google.com/uc?export=download&id=${file.id}` : null);

            this.log('Criando botões', {
                showView: this.config.showView,
                showDownload: this.config.showDownload,
                view_url: viewUrl,
                download_url: downloadUrl
            });

            // Apenas adicionar botão de download, já que o "ver" está no link do nome
            if (this.config.showDownload && downloadUrl) {
                buttons += `
                    <a href="${downloadUrl}"
                       class="gdhn-btn-icon gdhn-btn-download"
                       title="Descarregar arquivo">
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
        
        // Mostrar seção de arquivos
        showFilesSection() {
            this.container.find('.gdhn-filter-section').show();
            this.container.find('.gdhn-files-section').show();
        }
        
        // Esconder seção de arquivos
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
        
        // Mostrar overlay de loading
        showLoadingOverlay() {
            if (this.isLoading) return;
            this.isLoading = true;
            
            const overlay = $(`
                <div class="gdhn-loading-overlay">
                    <div class="gdhn-spinner"></div>
                </div>
            `);
            
            this.container.append(overlay);
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
            
            errorMessage.html(`❌ ${this.escapeHtml(message)}`);
            errorSection.show();
            
            // Auto-hide após 5 segundos
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
        
        // Sistema de logging
        log(message, data = null) {
            // Só fazer log se debug estiver ativo
            if (typeof gdhn_ajax !== 'undefined' && gdhn_ajax.debug && typeof console !== 'undefined' && console.log) {
                if (data !== null) {
                    console.log(`[GDHN] ${message}:`, data);
                } else {
                    console.log(`[GDHN] ${message}`);
                }
            }
        }
    }
    
    // Função para inicializar containers
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
        console.log('[GDHN] Elementor detectado, inicializando...');
        setTimeout(initializeContainers, 500);
    });
    
    // Monitorar mudanças no DOM (para page builders)
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
                console.log('[GDHN] Novos containers detectados, inicializando...');
                setTimeout(initializeContainers, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Também inicializar para containers adicionados dinamicamente
    $(document).on('gdhn:init', '.gdhn-container', function() {
        if (!$(this).data('gdhn-initialized')) {
            new GDriveHierarchyNavigator(this);
            $(this).data('gdhn-initialized', true);
        }
    });
    
    // Event para forçar reinicialização
    $(document).on('gdhn:reinit', function() {
        $('.gdhn-container').removeData('gdhn-initialized');
        initializeContainers();
    });
    
})(jQuery);
