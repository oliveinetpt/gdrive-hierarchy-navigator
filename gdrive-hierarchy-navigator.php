<?php
/**
 * Plugin Name: Google Drive Hierarchy Navigator
 * Plugin URI: https://github.com/oliveinet/gdrive-hierarchy-navigator
 * Description: Plugin WordPress para navegação hierárquica de pastas e arquivos do Google Drive com barras de navegação e filtros.
 * Version: 2.5.0
 * Author: oliveinet
 * Author URI: https://oliveinet.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gdrive-hierarchy-navigator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
    
}

// Definir constantes do plugin
define('GDHN_VERSION', '2.5.0');
define('GDHN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GDHN_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Incluir sistema de atualizações automáticas
require_once GDHN_PLUGIN_PATH . 'gdhn-updater.php';

/**
 * Classe principal do plugin
 */
class GDriveHierarchyNavigator {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_gdhn_load_folder', array($this, 'ajax_load_folder'));
        add_action('wp_ajax_nopriv_gdhn_load_folder', array($this, 'ajax_load_folder'));
        add_action('wp_ajax_gdhn_track_hit', array($this, 'ajax_track_hit'));
        add_action('wp_ajax_nopriv_gdhn_track_hit', array($this, 'ajax_track_hit'));
        add_action('wp_ajax_gdhn_get_file_hits', array($this, 'ajax_get_file_hits'));
        add_action('wp_ajax_nopriv_gdhn_get_file_hits', array($this, 'ajax_get_file_hits'));
        add_shortcode('gdrive_navigator', array($this, 'shortcode_handler'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Ativação do plugin
        register_activation_hook(__FILE__, array($this, 'create_hits_table'));
        
        // Hook para verificar tabela em cada carregamento (temporário para debug)
        add_action('init', array($this, 'ensure_hits_table_exists'));
    }
    
    public function init() {
        // Inicialização do plugin
    }
    
    /**
     * Garantir que a tabela de hits existe (temporário para debug)
     */
    public function ensure_hits_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Tabela não existe no init, criando...");
            }
            $this->create_hits_table();
        }
    }
    
    public function enqueue_scripts() {
        // Só carregar se estivermos numa página que tem o shortcode ou no admin
        if (is_admin() || $this->has_shortcode()) {
            // Enfileirar Font Awesome (só se não estiver já carregado)
            if (!wp_style_is('font-awesome', 'enqueued') && !wp_style_is('fontawesome', 'enqueued')) {
                wp_enqueue_style('gdhn-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
            }
            
            wp_enqueue_script('gdhn-main', GDHN_PLUGIN_URL . 'gdhn-main.js', array('jquery'), GDHN_VERSION . '.' . time(), true);
            wp_enqueue_style('gdhn-style', GDHN_PLUGIN_URL . 'gdhn-style.css', array(), GDHN_VERSION . '.' . time());
            
            // Localizar script para AJAX
            wp_localize_script('gdhn-main', 'gdhn_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdhn_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'is_admin' => current_user_can('manage_options')
            ));
        }
    }
    
    /**
     * Verificar se a página atual tem o shortcode
     */
    private function has_shortcode() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Verificar no conteúdo do post
        if (has_shortcode($post->post_content, 'gdrive_navigator')) {
            return true;
        }
        
        // Verificar em widgets/Elementor/outros builders
        if (function_exists('elementor_load_plugin_textdomain')) {
            return true; // Se Elementor estiver ativo, sempre carregar
        }
        
        return false;
    }
    
    /**
     * Handler do shortcode principal
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'folder_id' => '',                    // ID da pasta raiz (obrigatório)
            'api_key' => '',                      // Chave API do Google Drive (obrigatório)
            'levels' => 2,                        // Número de níveis de navegação (1-3)
            'show_date' => 'true',               // Mostrar data dos arquivos
            'show_size' => 'true',               // Mostrar tamanho dos arquivos
            'show_hits' => 'true',               // Mostrar número de visualizações
            'show_download' => 'true',           // Mostrar botão download
            'show_view' => 'true',               // Mostrar botão visualizar
            'cache_minutes' => 15,               // Minutos de cache
            'max_files' => 100,                  // Máximo de arquivos por pasta
            'filter_placeholder' => 'Filtrar arquivos...', // Placeholder do filtro
            'primary_color' => '#4285f4',        // Cor primária dos botões
            'secondary_color' => '#34a853',      // Cor secundária
            'level1_bg' => '#4285f4',           // Cor de fundo do primeiro nível
            'level2_bg' => '#f8f9fa',           // Cor de fundo do segundo nível
        ), $atts, 'gdrive_navigator');
        
        // Usar API Key global se não fornecida no shortcode
        if (empty($atts['api_key'])) {
            $global_settings = get_option('gdhn_settings', array());
            if (!empty($global_settings['api_key'])) {
                $atts['api_key'] = $global_settings['api_key'];
            }
        }
        
        // Usar configurações globais como padrão
        if (empty($atts['cache_minutes'])) {
            $global_settings = get_option('gdhn_settings', array());
            if (!empty($global_settings['default_cache_minutes'])) {
                $atts['cache_minutes'] = $global_settings['default_cache_minutes'];
            }
        }
        
        if (empty($atts['max_files'])) {
            $global_settings = get_option('gdhn_settings', array());
            if (!empty($global_settings['default_max_files'])) {
                $atts['max_files'] = $global_settings['default_max_files'];
            }
        }
        
        // Validação de parâmetros obrigatórios
        if (empty($atts['folder_id']) || empty($atts['api_key'])) {
            $config_link = admin_url('options-general.php?page=gdhn-settings');
            return '<div class="gdhn-error">❌ Erro: É necessário fornecer "folder_id" e "api_key" no shortcode ou configurar a API Key globalmente. <a href="' . $config_link . '">Configurar agora</a></div>';
        }
        
        // Sanitizar parâmetros
        $atts['levels'] = max(1, min(3, intval($atts['levels'])));
        
        $atts['cache_minutes'] = max(0, min(10080, intval($atts['cache_minutes']))); // 0 = sem cache, máx 7 dias        $atts['cache_minutes'] = max(0, intval($atts['cache_minutes'])); // 0 = sem cache, sem limite máximo
        $atts['max_files'] = max(10, min(500, intval($atts['max_files'])));
        $atts['show_date'] = ($atts['show_date'] === 'true');
        $atts['show_size'] = ($atts['show_size'] === 'true');
        $atts['show_hits'] = ($atts['show_hits'] === 'true');
        $atts['show_download'] = ($atts['show_download'] === 'true');
        $atts['show_view'] = ($atts['show_view'] === 'true');
        
        return $this->render_navigator($atts);
    }
    
    /**
     * Renderizar o navegador principal
     */
    private function render_navigator($atts) {
        $unique_id = 'gdhn_' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($unique_id); ?>" class="gdhn-container" 
             data-folder-id="<?php echo esc_attr($atts['folder_id']); ?>"
             data-api-key="<?php echo esc_attr($atts['api_key']); ?>"
             data-levels="<?php echo esc_attr($atts['levels']); ?>"
             data-cache-minutes="<?php echo esc_attr($atts['cache_minutes']); ?>"
             data-max-files="<?php echo esc_attr($atts['max_files']); ?>"
             data-show-date="<?php echo $atts['show_date'] ? 'true' : 'false'; ?>"
             data-show-size="<?php echo $atts['show_size'] ? 'true' : 'false'; ?>"
             data-show-hits="<?php echo $atts['show_hits'] ? 'true' : 'false'; ?>"
             data-show-download="<?php echo $atts['show_download'] ? 'true' : 'false'; ?>"
             data-show-view="<?php echo $atts['show_view'] ? 'true' : 'false'; ?>"
             data-primary-color="<?php echo esc_attr($atts['primary_color']); ?>"
             data-secondary-color="<?php echo esc_attr($atts['secondary_color']); ?>"
             data-level1-bg="<?php echo esc_attr($atts['level1_bg']); ?>"
             data-level2-bg="<?php echo esc_attr($atts['level2_bg']); ?>">
            
            <!-- Loading inicial -->
            <div class="gdhn-loading">
                <div class="gdhn-spinner"></div>
                <p>Carregando pastas...</p>
            </div>
            
            <!-- Barras de navegação (serão preenchidas via JavaScript) -->
            <div class="gdhn-navigation-bars"></div>
            
            <!-- Filtro de arquivos e botão admin -->
            <div class="gdhn-filter-section" style="display: none;">
                <div class="gdhn-filter-container">
                    <input type="text" 
                           class="gdhn-filter-input" 
                           placeholder="<?php echo esc_attr($atts['filter_placeholder']); ?>">
                    
                    <?php if (current_user_can('manage_options')): ?>
                    <button type="button" 
                            class="gdhn-admin-drive-btn" 
                            title="Gerir pasta no Google Drive">
                        <i class="fas fa-folder-open"></i>
                        <span>Google Drive</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tabela de arquivos -->
            <div class="gdhn-files-section" style="display: none;">
                <table class="gdhn-files-table">
                    <thead>
                        <tr>
                            <th class="gdhn-icon-col"></th>
                            <th class="gdhn-name-col">Nome</th>
                            <?php if ($atts['show_date']): ?>
                                <th class="gdhn-date-col">Data</th>
                            <?php endif; ?>
                            <?php if ($atts['show_size']): ?>
                                <th class="gdhn-size-col">Tamanho</th>
                            <?php endif; ?>
                            <?php if ($atts['show_hits']): ?>
                                <th class="gdhn-hits-col">Visualizações</th>
                            <?php endif; ?>
                            <th class="gdhn-actions-col">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="gdhn-files-tbody">
                        <!-- Arquivos serão inseridos aqui via JavaScript -->
                    </tbody>
                </table>
                
                <div class="gdhn-no-files" style="display: none;">
                    <p><i class="fas fa-folder-open" style="color: #6c757d; margin-right: 8px;"></i>Nenhum arquivo encontrado nesta pasta.</p>
                </div>
            </div>
            
            <!-- Mensagens de erro -->
            <div class="gdhn-error-section" style="display: none;">
                <div class="gdhn-error-message"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handler AJAX para carregar pastas
     */
    public function ajax_load_folder() {
        // Log de debug
        $this->log_debug('AJAX chamado', $_POST);
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gdhn_nonce')) {
            $this->log_debug('Erro: Nonce inválido');
            wp_die('Nonce inválido');
        }
        
        $folder_id = sanitize_text_field($_POST['folder_id']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $cache_minutes = intval($_POST['cache_minutes']);
        $action_type = sanitize_text_field($_POST['action_type']); // 'folders' ou 'files'
        
        $this->log_debug('Parâmetros recebidos', [
            'folder_id' => $folder_id,
            'api_key' => substr($api_key, 0, 10) . '...',
            'action_type' => $action_type
        ]);
        
        if (empty($folder_id) || empty($api_key)) {
            $this->log_debug('Erro: Parâmetros inválidos');
            wp_send_json_error('Parâmetros inválidos');
        }
        
        if ($action_type === 'folders') {
            $this->log_debug('Buscando pastas para folder_id: ' . $folder_id);
            $result = $this->get_folders($folder_id, $api_key, $cache_minutes);
        } else {
            $max_files = intval($_POST['max_files']);
            $this->log_debug('Buscando arquivos para folder_id: ' . $folder_id);
            $result = $this->get_files($folder_id, $api_key, $cache_minutes, $max_files);
        }
        
        if (is_wp_error($result)) {
            $this->log_debug('Erro no resultado', ['error' => $result->get_error_message()]);
            wp_send_json_error($result->get_error_message());
        }
        
        $this->log_debug('Sucesso', ['count' => count($result)]);
        wp_send_json_success($result);
    }
    
    /**
     * Obter pastas de uma pasta pai
     */
    private function get_folders($folder_id, $api_key, $cache_minutes = 15) {
        $cache_key = 'gdhn_folders_' . md5($folder_id . $api_key);
        $folders = get_transient($cache_key);
        
        $this->log_debug('Cache check', ['cache_key' => $cache_key, 'cached' => ($folders !== false)]);
        
        if ($folders === false) {
            $url = add_query_arg(array(
                'q' => "'{$folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
                'key' => $api_key,
                'fields' => 'files(id,name,modifiedTime)',
                'orderBy' => 'name'
            ), 'https://www.googleapis.com/drive/v3/files');
            
            $this->log_debug('API URL construída', ['url' => $url]);
            
            $response = wp_remote_get($url, array('timeout' => 15));
            
            $this->log_debug('Resposta HTTP', [
                'is_error' => is_wp_error($response),
                'status_code' => is_wp_error($response) ? 'ERROR' : wp_remote_retrieve_response_code($response)
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('json_error', 'Erro ao decodificar resposta da API');
            }
            
            if (isset($data['error'])) {
                return new WP_Error('api_error', $data['error']['message']);
            }
            
            $folders = isset($data['files']) ? $data['files'] : array();
            set_transient($cache_key, $folders, $cache_minutes * MINUTE_IN_SECONDS);
        }
        
        return $folders;
    }
    
    /**
     * Obter arquivos de uma pasta
     */
    private function get_files($folder_id, $api_key, $cache_minutes = 15, $max_files = 100) {
        $cache_key = 'gdhn_files_' . md5($folder_id . $api_key . $max_files);
        $files = get_transient($cache_key);
        
        if ($files === false) {
            $url = add_query_arg(array(
                'q' => "'{$folder_id}' in parents and mimeType!='application/vnd.google-apps.folder' and trashed=false",
                'key' => $api_key,
                'fields' => 'files(id,name,mimeType,size,modifiedTime,iconLink,webViewLink)',
                'orderBy' => 'name',
                'pageSize' => min($max_files, 100)
            ), 'https://www.googleapis.com/drive/v3/files');
            
            $response = wp_remote_get($url, array('timeout' => 15));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('json_error', 'Erro ao decodificar resposta da API');
            }
            
            if (isset($data['error'])) {
                return new WP_Error('api_error', $data['error']['message']);
            }
            
            $files = isset($data['files']) ? $data['files'] : array();
            
            // Processar arquivos para adicionar informações extras
            foreach ($files as &$file) {
                $file['file_icon'] = $this->get_file_icon($file['mimeType']);
                $file['download_url'] = "https://drive.google.com/uc?export=download&id=" . $file['id'];
                $file['view_url'] = $file['webViewLink'];
                $file['formatted_date'] = $this->format_date($file['modifiedTime']);
                $file['formatted_size'] = $this->format_file_size($file['size'] ?? 0);
            }
            
            set_transient($cache_key, $files, $cache_minutes * MINUTE_IN_SECONDS);
        }
        
        return $files;
    }
    
    /**
     * Obter ícone baseado no tipo de arquivo
     */
    private function get_file_icon($mime_type) {
        $icons = array(
            'application/pdf' => '<i class="fas fa-file-pdf" style="color: #dc3545;"></i>',
            'application/msword' => '<i class="fas fa-file-word" style="color: #0d6efd;"></i>',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '<i class="fas fa-file-word" style="color: #0d6efd;"></i>',
            'application/vnd.ms-excel' => '<i class="fas fa-file-excel" style="color: #198754;"></i>',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '<i class="fas fa-file-excel" style="color: #198754;"></i>',
            'application/vnd.ms-powerpoint' => '<i class="fas fa-file-powerpoint" style="color: #fd7e14;"></i>',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '<i class="fas fa-file-powerpoint" style="color: #fd7e14;"></i>',
            'text/plain' => '<i class="fas fa-file-alt" style="color: #6c757d;"></i>',
            'image/jpeg' => '<i class="fas fa-file-image" style="color: #20c997;"></i>',
            'image/png' => '<i class="fas fa-file-image" style="color: #20c997;"></i>',
            'image/gif' => '<i class="fas fa-file-image" style="color: #20c997;"></i>',
            'video/mp4' => '<i class="fas fa-file-video" style="color: #e83e8c;"></i>',
            'audio/mpeg' => '<i class="fas fa-file-audio" style="color: #20c997;"></i>',
        );
        
        return isset($icons[$mime_type]) ? $icons[$mime_type] : '<i class="fas fa-file" style="color: #6c757d;"></i>';
    }
    
    /**
     * Formatar data
     */
    private function format_date($iso_date) {
        if (empty($iso_date)) {
            return '';
        }
        
        $timestamp = strtotime($iso_date);
        return date_i18n('d/m/Y H:i', $timestamp);
    }
    
    /**
     * Formatar tamanho do arquivo
     */
    private function format_file_size($bytes) {
        if (!is_numeric($bytes) || $bytes == 0) {
            return '';
        }
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return number_format_i18n($bytes, ($i > 1 ? 2 : 0)) . ' ' . $units[$i];
    }
    
    /**
     * Sistema de logging para debug
     */
    private function log_debug($message, $data = null) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_message = '[GDHN] ' . $message;
        if ($data !== null) {
            $log_message .= ': ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        error_log($log_message);
        
        // Também salvar em arquivo específico para fácil visualização
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/gdhn-debug.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] {$log_message}\n";
        
        file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Adicionar link de configurações na lista de plugins
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=gdhn-settings') . '">⚙️ Configurações</a>';
        $diagnostico_link = '<a href="' . admin_url('admin.php?page=gdhn-diagnostico') . '">🔧 Diagnóstico</a>';
        array_unshift($links, $settings_link, $diagnostico_link);
        return $links;
    }
    
    /**
     * Adicionar páginas no menu admin
     */
    public function add_admin_menu() {
        // Menu principal personalizado
        add_menu_page(
            'GDrive Navigator',                    // Page title
            'GDrive Navigator',                    // Menu title
            'manage_options',                      // Capability
            'gdhn-main',                          // Menu slug
            array($this, 'main_admin_page'),      // Function
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h8l6-6V8l-6-6H6zm7 7V3.5L18.5 9H13z"/></svg>'), // Icon
            30                                    // Position
        );
        
        // Submenu - Configurações
        add_submenu_page(
            'gdhn-main',
            'Configurações - GDrive Navigator',
            '⚙️ Configurações',
            'manage_options',
            'gdhn-settings',
            array($this, 'settings_page')
        );
        
        // Submenu - Estatísticas
        add_submenu_page(
            'gdhn-main',
            'Estatísticas - GDrive Navigator',
            '📊 Estatísticas',
            'manage_options',
            'gdhn-stats',
            array($this, 'stats_page')
        );
        
        // Submenu - Diagnóstico
        add_submenu_page(
            'gdhn-main',
            'Diagnóstico - GDrive Navigator',
            '🔧 Diagnóstico',
            'manage_options',
            'gdhn-diagnostico',
            array($this, 'diagnostico_page')
        );
        
        // Submenu - Debug (temporário)
        add_submenu_page(
            'gdhn-main',
            'Debug Hits - GDrive Navigator',
            '🐛 Debug Hits',
            'manage_options',
            'gdhn-debug-hits',
            array($this, 'debug_hits_page')
        );
        
        // Submenu - Gestão de Cache
        add_submenu_page(
            'gdhn-main',
            'Gestão de Cache - GDrive Navigator',
            '🗑️ Gestão de Cache',
            'manage_options',
            'gdhn-cache-manager',
            array($this, 'cache_manager_page')
        );
    }
    
    /**
     * Página principal do admin
     */
    public function main_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        // Estatísticas rápidas
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_hits = $wpdb->get_var("SELECT SUM(hits) FROM $table_name");
        $settings = get_option('gdhn_settings', array());
        
        ?>
        <div class="wrap">
            <h1>🗂️ GDrive Navigator - Painel Principal <small style="color: #666; font-size: 14px; font-weight: normal;">v<?php echo GDHN_VERSION; ?></small></h1>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                
                <!-- Estatísticas Rápidas -->
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">📊 Estatísticas Rápidas</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #4285f4;"><?php echo number_format($total_files); ?></div>
                            <div style="color: #666; font-size: 14px;">Arquivos Únicos</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #34a853;"><?php echo number_format($total_hits); ?></div>
                            <div style="color: #666; font-size: 14px;">Total de Hits</div>
                        </div>
                    </div>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=gdhn-stats'); ?>" class="button button-primary">Ver Estatísticas Completas</a>
                    </p>
                </div>
                
                <!-- Configuração -->
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">⚙️ Configuração</h2>
                    <p><strong>API Key:</strong> <?php echo !empty($settings['api_key']) ? '✅ Configurada' : '❌ Não configurada'; ?></p>
                    <p><strong>Cache:</strong> <?php echo isset($settings['cache_minutes']) ? $settings['cache_minutes'] . ' minutos' : '15 minutos (padrão)'; ?></p>
                    <p><strong>Max Files:</strong> <?php echo isset($settings['max_files']) ? $settings['max_files'] : '100 (padrão)'; ?></p>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=gdhn-settings'); ?>" class="button button-primary">Configurar Plugin</a>
                    </p>
                </div>
                
                <!-- Ações Rápidas -->
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">🚀 Ações Rápidas</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="<?php echo admin_url('admin.php?page=gdhn-diagnostico'); ?>" class="button">🔧 Executar Diagnóstico</a>
                        <a href="<?php echo admin_url('admin.php?page=gdhn-debug-hits'); ?>" class="button">🐛 Debug Sistema de Hits</a>
                        <a href="<?php echo admin_url('post-new.php'); ?>" class="button">📝 Criar Nova Página</a>
                    </div>
                </div>
                
                <!-- Documentação -->
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">📚 Documentação</h2>
                    <h3>Shortcode Básico:</h3>
                    <code style="background: #f5f5f5; padding: 10px; display: block; border-radius: 4px; font-size: 12px;">
                        [gdrive_navigator folder_id="SEU_ID" api_key="SUA_KEY"]
                    </code>
                    <h3>Com Estatísticas:</h3>
                    <code style="background: #f5f5f5; padding: 10px; display: block; border-radius: 4px; font-size: 12px;">
                        [gdrive_navigator folder_id="SEU_ID" show_hits="true"]
                    </code>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="https://github.com/oliveinetpt/gdrive-hierarchy-navigator" target="_blank" class="button">📖 Documentação Completa</a>
                    </p>
                </div>
                
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Inicializar configurações admin
     */
    public function admin_init() {
        register_setting('gdhn_settings_group', 'gdhn_settings', array($this, 'validate_settings'));
        
        add_settings_section(
            'gdhn_main_section',
            'Configurações Principais',
            array($this, 'main_section_callback'),
            'gdhn-settings'
        );
        
        add_settings_field(
            'api_key',
            'API Key do Google Drive',
            array($this, 'api_key_field_callback'),
            'gdhn-settings',
            'gdhn_main_section'
        );
        
        add_settings_field(
            'default_cache_minutes',
            'Cache Padrão (minutos)',
            array($this, 'cache_field_callback'),
            'gdhn-settings',
            'gdhn_main_section'
        );
        
        add_settings_field(
            'default_max_files',
            'Máximo de Arquivos Padrão',
            array($this, 'max_files_field_callback'),
            'gdhn-settings',
            'gdhn_main_section'
        );
    }
    
    /**
     * Validar configurações
     */
    public function validate_settings($input) {
        $validated = array();
        
        if (isset($input['api_key'])) {
            $validated['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['default_cache_minutes'])) {
            // Aceitar valores do dropdown OU valores personalizados maiores
            $value = intval($input['default_cache_minutes']);
            $validated['default_cache_minutes'] = max(0, $value); // 0 = sem cache, sem limite máximo
        }
        
        if (isset($input['default_max_files'])) {
            $validated['default_max_files'] = max(10, min(500, intval($input['default_max_files'])));
        }
        
        return $validated;
    }
    
    /**
     * Callbacks dos campos de configuração
     */
    public function main_section_callback() {
        echo '<p>Configure as opções globais do plugin. Estas configurações serão usadas como padrão quando não especificadas no shortcode.</p>';
    }
    
    public function api_key_field_callback() {
        $settings = get_option('gdhn_settings', array());
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        echo '<input type="text" name="gdhn_settings[api_key]" value="' . esc_attr($api_key) . '" style="width: 400px;" />';
        echo '<p class="description">Chave API do Google Drive. <a href="https://console.cloud.google.com/" target="_blank">Obter API Key</a></p>';
    }
    
    public function cache_field_callback() {
        $settings = get_option('gdhn_settings', array());
        $cache = isset($settings['default_cache_minutes']) ? $settings['default_cache_minutes'] : 15;
        
        // Verificar se é um valor personalizado (não está na lista)
        $predefined = array(0, 5, 15, 30, 60, 120, 240, 720, 1440, 4320, 10080, 43200, 525600);
        $is_custom = !in_array($cache, $predefined);
        ?>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select id="gdhn_cache_select" name="gdhn_settings[default_cache_minutes]" style="min-width: 250px;" onchange="if(this.value!=='custom'){document.getElementById('gdhn_cache_custom').style.display='none';}else{document.getElementById('gdhn_cache_custom').style.display='inline-block';document.getElementById('gdhn_cache_custom_input').focus();}">
                <option value="0" <?php selected($cache, 0); ?>>❌ Sem cache (não recomendado)</option>
                <option value="5" <?php selected($cache, 5); ?>>⚡ 5 minutos</option>
                <option value="15" <?php selected($cache, 15); ?>>✅ 15 minutos (padrão)</option>
                <option value="30" <?php selected($cache, 30); ?>>🕐 30 minutos</option>
                <option value="60" <?php selected($cache, 60); ?>>🕐 1 hora</option>
                <option value="120" <?php selected($cache, 120); ?>>🕑 2 horas</option>
                <option value="240" <?php selected($cache, 240); ?>>🕓 4 horas</option>
                <option value="720" <?php selected($cache, 720); ?>>🕗 12 horas</option>
                <option value="1440" <?php selected($cache, 1440); ?>>📅 24 horas (1 dia)</option>
                <option value="4320" <?php selected($cache, 4320); ?>>📅 3 dias</option>
                <option value="10080" <?php selected($cache, 10080); ?>>📅 7 dias (1 semana)</option>
                <option value="43200" <?php selected($cache, 43200); ?>>📅 30 dias (1 mês)</option>
                <option value="525600" <?php selected($cache, 525600); ?>>📅 365 dias (1 ano)</option>
                <?php if ($is_custom): ?>
                    <option value="<?php echo esc_attr($cache); ?>" selected>🔧 Personalizado (<?php echo number_format($cache); ?> min)</option>
                <?php endif; ?>
                <option value="custom">✏️ Definir valor personalizado...</option>
            </select>
            <input type="number" id="gdhn_cache_custom_input" min="0" step="1" placeholder="Minutos" style="width: 150px; display: none;" onchange="document.getElementById('gdhn_cache_select').innerHTML += '<option value=\'' + this.value + '\' selected>🔧 Personalizado (' + parseInt(this.value).toLocaleString() + ' min)</option>'; document.getElementById('gdhn_cache_select').value = this.value;">
            <span id="gdhn_cache_custom" style="display: none; color: #666; font-size: 11px;"></span>
        </div>
        <p class="description">
            Tempo de cache para pastas e arquivos. Cache reduz chamadas à API do Google Drive.<br>
            <strong>Recomendado:</strong> 15-60 minutos para conteúdo que muda frequentemente, 1-7 dias para conteúdo estático.<br>
            <strong>💡 Dica:</strong> Para arquivos históricos que nunca mudam, use valores muito altos (ex: 1 ano ou mais) ou defina diretamente no shortcode: <code>cache_minutes="999999999"</code>
        </p>
        <?php
    }
    
    public function max_files_field_callback() {
        $settings = get_option('gdhn_settings', array());
        $max_files = isset($settings['default_max_files']) ? $settings['default_max_files'] : 100;
        echo '<input type="number" name="gdhn_settings[default_max_files]" value="' . esc_attr($max_files) . '" min="10" max="500" />';
        echo '<p class="description">Máximo de arquivos por pasta (10-500)</p>';
    }
    
    /**
     * Página de configurações
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>⚙️ Google Drive Navigator - Configurações <small style="color: #666; font-size: 14px; font-weight: normal;">v<?php echo GDHN_VERSION; ?></small></h1>
            
            <?php
            // Mostrar mensagens de sucesso/erro
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Configurações salvas com sucesso!</strong></p></div>';
            }
            ?>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
                <h2>📋 Como Usar</h2>
                <p>Após configurar a API Key aqui, você pode usar o shortcode de forma simplificada:</p>
                <code>[gdrive_navigator folder_id="SEU_FOLDER_ID"]</code>
                <p>A API Key será automaticamente utilizada das configurações globais.</p>
                
                <h3>Shortcode Completo (sobrescreve configurações globais):</h3>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">[gdrive_navigator 
    folder_id="SEU_FOLDER_ID" 
    api_key="SUA_API_KEY"
    levels="2"
    show_date="true"
    show_size="true"
    show_hits="true"
    show_download="true"
    show_view="true"
    cache_minutes="15"
    max_files="100"
    filter_placeholder="Procurar arquivos..."
    primary_color="#4285f4"
    secondary_color="#34a853"
    level1_bg="#4285f4"
    level2_bg="#f8f9fa"]</pre>
                
                <h3>Parâmetros Disponíveis:</h3>
                <ul>
                    <li><strong>folder_id</strong>: ID da pasta raiz do Google Drive (obrigatório)</li>
                    <li><strong>api_key</strong>: Chave API (usa configuração global se não fornecida)</li>
                    <li><strong>levels</strong>: Níveis de navegação (1-3, padrão: 2)</li>
                    <li><strong>show_date</strong>: Mostrar data (true/false, padrão: true)</li>
                    <li><strong>show_size</strong>: Mostrar tamanho (true/false, padrão: true)</li>
                    <li><strong>show_hits</strong>: Mostrar visualizações (true/false, padrão: true)</li>
                    <li><strong>show_download</strong>: Botão download (true/false, padrão: true)</li>
                    <li><strong>show_view</strong>: Botão visualizar (true/false, padrão: true)</li>
                    <li><strong>cache_minutes</strong>: Minutos de cache (0=sem cache, sem limite máximo, padrão: 15)</li>
                    <li><strong>max_files</strong>: Máximo de arquivos (10-500, padrão: 100)</li>
                    <li><strong>filter_placeholder</strong>: Texto do filtro de pesquisa</li>
                    <li><strong>primary_color</strong>: Cor primária (hex, padrão: #4285f4)</li>
                    <li><strong>secondary_color</strong>: Cor secundária (hex, padrão: #34a853)</li>
                    <li><strong>level1_bg</strong>: Cor fundo nível 1 (hex, padrão: #4285f4)</li>
                    <li><strong>level2_bg</strong>: Cor fundo nível 2 (hex, padrão: #f8f9fa)</li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('gdhn_settings_group');
                do_settings_sections('gdhn-settings');
                submit_button('💾 Salvar Configurações');
                ?>
            </form>
            
            <div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                <h3>🔗 Links Úteis</h3>
                <p>
                    <a href="<?php echo admin_url('tools.php?page=gdhn-diagnostico'); ?>" class="button">🔧 Diagnóstico e Testes</a>
                    <a href="https://console.cloud.google.com/" target="_blank" class="button">🔑 Google Cloud Console</a>
                    <a href="https://drive.google.com" target="_blank" class="button">📁 Google Drive</a>
                </p>
            </div>
            
            <?php
            // Teste rápido da API se estiver configurada
            $settings = get_option('gdhn_settings', array());
            if (!empty($settings['api_key'])) {
                ?>
                <div style="background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3>🧪 Teste Rápido da API</h3>
                    <p>API Key configurada: <code><?php echo esc_html(substr($settings['api_key'], 0, 10) . '...'); ?></code></p>
                    
                    <form method="post" style="margin: 10px 0;">
                        <input type="text" name="test_folder_id" placeholder="Cole um Folder ID para testar" style="width: 300px;" />
                        <input type="submit" name="test_api" value="🧪 Testar" class="button" />
                    </form>
                    
                    <?php
                    if (isset($_POST['test_api']) && !empty($_POST['test_folder_id'])) {
                        $folder_id = sanitize_text_field($_POST['test_folder_id']);
                        $this->test_api_quick($settings['api_key'], $folder_id);
                    }
                    ?>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Teste rápido da API
     */
    private function test_api_quick($api_key, $folder_id) {
        $url = add_query_arg(array(
            'q' => "'{$folder_id}' in parents and trashed=false",
            'key' => $api_key,
            'fields' => 'files(id,name,mimeType)',
            'pageSize' => 5
        ), 'https://www.googleapis.com/drive/v3/files');
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            echo '<div style="color: red;">❌ Erro: ' . esc_html($response->get_error_message()) . '</div>';
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($status_code === 200 && isset($data['files'])) {
                echo '<div style="color: green;">✅ API funcionando! Encontrados ' . count($data['files']) . ' item(s)</div>';
            } else {
                echo '<div style="color: red;">❌ Erro na API (Status: ' . $status_code . ')</div>';
                if (isset($data['error']['message'])) {
                    echo '<div style="color: red;">Detalhes: ' . esc_html($data['error']['message']) . '</div>';
                }
            }
        }
    }
    
    /**
     * Página de diagnóstico (movida do arquivo separado)
     */
    public function diagnostico_page() {
        ?>
        <div class="wrap">
            <h1>🔧 Google Drive Navigator - Diagnóstico <small style="color: #666; font-size: 14px; font-weight: normal;">v<?php echo GDHN_VERSION; ?></small></h1>
            
            <?php
            // Função para mostrar status
            function mostrar_status($condicao, $sucesso, $erro) {
                if ($condicao) {
                    echo "<p style='color: green;'>✅ $sucesso</p>";
                    return true;
                } else {
                    echo "<p style='color: red;'>❌ $erro</p>";
                    return false;
                }
            }
            
            function mostrar_warning($condicao, $warning) {
                if ($condicao) {
                    echo "<p style='color: orange;'>⚠️ $warning</p>";
                }
            }
            
            echo "<h2>1. Verificações Básicas</h2>";
            
            // WordPress
            mostrar_status(
                defined('ABSPATH'),
                'WordPress detectado',
                'WordPress não detectado'
            );
            
            // Versão do WordPress
            $wp_version = get_bloginfo('version');
            mostrar_status(
                version_compare($wp_version, '5.0', '>='),
                "WordPress $wp_version (compatível)",
                "WordPress $wp_version (requer 5.0+)"
            );
            
            // PHP
            $php_version = phpversion();
            mostrar_status(
                version_compare($php_version, '7.4', '>='),
                "PHP $php_version (compatível)",
                "PHP $php_version (requer 7.4+)"
            );
            
            // Plugin ativo
            $plugin_ativo = class_exists('GDriveHierarchyNavigator');
            mostrar_status(
                $plugin_ativo,
                'Plugin está carregado',
                'Plugin não está carregado'
            );
            
            echo "<h2>2. Configurações</h2>";
            
            $settings = get_option('gdhn_settings', array());
            mostrar_status(
                !empty($settings['api_key']),
                'API Key configurada globalmente',
                'API Key não configurada - configure nas <a href="' . admin_url('options-general.php?page=gdhn-settings') . '">Configurações</a>'
            );
            
            // WP_DEBUG
            mostrar_warning(
                !defined('WP_DEBUG') || !WP_DEBUG,
                'WP_DEBUG está desativado - ative para ver logs detalhados'
            );
            
            echo "<h2>3. Teste da API</h2>";
            
            if (isset($_POST['testar_api_diag'])) {
                $api_key = !empty($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : ($settings['api_key'] ?? '');
                $folder_id = sanitize_text_field($_POST['folder_id']);
                
                if (!empty($api_key) && !empty($folder_id)) {
                    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
                    echo "<h4>Testando API...</h4>";
                    
                    $this->test_api_detailed($api_key, $folder_id);
                    
                    echo "</div>";
                }
            }
            ?>
            
            <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h4>Testar API Google Drive</h4>
                <table class="form-table">
                    <tr>
                        <th><label for="api_key">API Key:</label></th>
                        <td>
                            <input type="text" name="api_key" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" style="width: 400px;" />
                            <p class="description">Deixe vazio para usar a configuração global</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="folder_id">Folder ID:</label></th>
                        <td><input type="text" name="folder_id" placeholder="Cole o ID da pasta aqui" style="width: 400px;" /></td>
                    </tr>
                </table>
                <p>
                    <input type="submit" name="testar_api_diag" value="🧪 Testar API" class="button-primary" />
                    <a href="<?php echo admin_url('options-general.php?page=gdhn-settings'); ?>" class="button">⚙️ Configurações</a>
                </p>
            </form>
            
            <?php
            echo "<h2>4. Logs</h2>";
            
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/gdhn-debug.log';
            
            if (file_exists($log_file)) {
                echo "<p style='color: green;'>✅ Arquivo de log: <code>$log_file</code></p>";
                
                if (isset($_POST['ver_log'])) {
                    $log_content = file_get_contents($log_file);
                    $lines = explode("\n", trim($log_content));
                    $recent_lines = array_slice(array_reverse($lines), 0, 30);
                    
                    echo "<h4>Últimas 30 entradas:</h4>";
                    echo "<div style='background: #000; color: #fff; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;'>";
                    foreach (array_reverse($recent_lines) as $line) {
                        if (!empty(trim($line))) {
                            echo esc_html($line) . "<br>";
                        }
                    }
                    echo "</div>";
                }
                
                echo "<form method='post' style='margin: 10px 0;'>";
                echo "<input type='submit' name='ver_log' value='👁️ Ver Log' class='button' /> ";
                echo "<input type='submit' name='limpar_log' value='🗑️ Limpar Log' class='button' />";
                echo "</form>";
                
                if (isset($_POST['limpar_log'])) {
                    unlink($log_file);
                    echo "<p style='color: green;'>✅ Log limpo!</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ Nenhum log encontrado. Ative WP_DEBUG para gerar logs.</p>";
            }
            ?>
            
            <div style="background: #fff; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">
                <h3>📋 Exemplo de Uso</h3>
                <p><strong>Com API Key global configurada:</strong></p>
                <code>[gdrive_navigator folder_id="SEU_FOLDER_ID"]</code>
                
                <p><strong>Shortcode completo:</strong></p>
                <code>[gdrive_navigator folder_id="SEU_FOLDER_ID" api_key="SUA_API_KEY" levels="2"]</code>
            </div>
        </div>
        <?php
    }
    
    /**
     * Teste detalhado da API
     */
    private function test_api_detailed($api_key, $folder_id) {
        // Teste de pastas
        $url_folders = add_query_arg(array(
            'q' => "'{$folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
            'key' => $api_key,
            'fields' => 'files(id,name)',
            'pageSize' => 5
        ), 'https://www.googleapis.com/drive/v3/files');
        
        echo "<p><strong>Testando pastas...</strong></p>";
        $response = wp_remote_get($url_folders, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            echo "<p style='color: red;'>❌ Erro: " . esc_html($response->get_error_message()) . "</p>";
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            echo "<p><strong>Status:</strong> $status_code</p>";
            
            if ($status_code === 200 && isset($data['files'])) {
                echo "<p style='color: green;'>✅ Encontradas " . count($data['files']) . " pasta(s)</p>";
                if (!empty($data['files'])) {
                    echo "<ul>";
                    foreach ($data['files'] as $file) {
                        echo "<li>📁 " . esc_html($file['name']) . "</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "<p style='color: red;'>❌ Erro na API</p>";
                if (isset($data['error']['message'])) {
                    echo "<p style='color: red;'>Detalhes: " . esc_html($data['error']['message']) . "</p>";
                }
            }
        }
        
        // Teste de arquivos
        echo "<hr><p><strong>Testando arquivos...</strong></p>";
        $url_files = add_query_arg(array(
            'q' => "'{$folder_id}' in parents and mimeType!='application/vnd.google-apps.folder' and trashed=false",
            'key' => $api_key,
            'fields' => 'files(id,name,mimeType)',
            'pageSize' => 5
        ), 'https://www.googleapis.com/drive/v3/files');
        
        $response = wp_remote_get($url_files, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            echo "<p style='color: red;'>❌ Erro: " . esc_html($response->get_error_message()) . "</p>";
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($status_code === 200 && isset($data['files'])) {
                echo "<p style='color: green;'>✅ Encontrados " . count($data['files']) . " arquivo(s)</p>";
                if (!empty($data['files'])) {
                    echo "<ul>";
                    foreach ($data['files'] as $file) {
                        $icon = $this->get_file_icon($file['mimeType']);
                        echo "<li>$icon " . esc_html($file['name']) . "</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "<p style='color: red;'>❌ Erro na API</p>";
            }
        }
    }
    
    /**
     * Página de estatísticas
     */
    public function stats_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        // Obter estatísticas gerais
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_hits = $wpdb->get_var("SELECT SUM(hits) FROM $table_name");
        $avg_hits = $total_files > 0 ? round($total_hits / $total_files, 2) : 0;
        
        // Obter top 10 arquivos mais visualizados
        $top_files = $wpdb->get_results(
            "SELECT file_id, file_name, folder_id, folder_name, hits, last_hit 
             FROM $table_name 
             ORDER BY hits DESC, last_hit DESC 
             LIMIT 10"
        );
        
        // Obter estatísticas por pasta
        $folder_stats = $wpdb->get_results(
            "SELECT folder_id, 
                    COALESCE(folder_name, CONCAT(SUBSTRING(folder_id, 1, 20), '...')) as display_name,
                    COUNT(*) as file_count, 
                    SUM(hits) as total_hits 
             FROM $table_name 
             GROUP BY folder_id, folder_name 
             ORDER BY total_hits DESC 
             LIMIT 10"
        );
        
        // Obter estatísticas por página
        $page_stats = $wpdb->get_results(
            "SELECT page_url, 
                    COALESCE(page_title, 'Página sem título') as page_title,
                    COUNT(DISTINCT file_id) as unique_files,
                    SUM(hits) as total_hits 
             FROM $table_name 
             WHERE page_url IS NOT NULL AND page_url != ''
             GROUP BY page_url, page_title 
             ORDER BY total_hits DESC 
             LIMIT 10"
        );
        
        // Obter atividade recente (últimos 7 dias)
        $recent_activity = $wpdb->get_results(
            "SELECT file_id, file_name, hits, last_hit 
             FROM $table_name 
             WHERE last_hit >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             ORDER BY last_hit DESC 
             LIMIT 15"
        );
        
        // Obter lista de páginas com atividade
        $pages_with_activity = $wpdb->get_results(
            "SELECT DISTINCT page_url, page_title 
             FROM $table_name 
             WHERE page_url IS NOT NULL AND page_url != '' AND page_title IS NOT NULL
             ORDER BY page_title ASC"
        );
        
        // Estatísticas filtradas por página (se selecionada)
        $selected_page = isset($_GET['filter_page']) ? $_GET['filter_page'] : '';
        $page_files_stats = array();
        if (!empty($selected_page)) {
            $page_files_stats = $wpdb->get_results($wpdb->prepare(
                "SELECT file_id, file_name, folder_id, folder_name, SUM(hits) as total_hits, MAX(last_hit) as last_hit
                 FROM $table_name 
                 WHERE page_url = %s
                 GROUP BY file_id, file_name, folder_id, folder_name
                 ORDER BY total_hits DESC, last_hit DESC
                 LIMIT 20",
                $selected_page
            ));
        }
        
        ?>
        <div class="wrap">
            <h1>📊 Google Drive Navigator - Estatísticas <small style="color: #666; font-size: 14px; font-weight: normal;">v<?php echo GDHN_VERSION; ?></small></h1>
            
            <div class="gdhn-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                
                <!-- Estatísticas Gerais -->
                <div class="gdhn-stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">📈 Estatísticas Gerais</h2>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; text-align: center;">
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #4285f4;"><?php echo number_format($total_files); ?></div>
                            <div style="color: #666; font-size: 14px;">Arquivos Únicos</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #34a853;"><?php echo number_format($total_hits); ?></div>
                            <div style="color: #666; font-size: 14px;">Total de Visualizações</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: bold; color: #ea4335;"><?php echo $avg_hits; ?></div>
                            <div style="color: #666; font-size: 14px;">Média por Arquivo</div>
                        </div>
                    </div>
                </div>
                
                <!-- Top 10 Arquivos -->
                <div class="gdhn-stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">🏆 Top 10 Arquivos Mais Visualizados</h2>
                    <?php if (!empty($top_files)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($top_files as $index => $file): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 500; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($file->file_name); ?>">
                                            <?php if (!empty($file->file_id)): ?>
                                                <a href="https://drive.google.com/file/d/<?php echo esc_attr($file->file_id); ?>/view" target="_blank" style="text-decoration: none; color: inherit;" title="Ver arquivo no Google Drive">
                                                    <?php echo ($index + 1) . '. ' . esc_html($file->file_name); ?> 🔗
                                                </a>
                                            <?php else: ?>
                                                <?php echo ($index + 1) . '. ' . esc_html($file->file_name); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php if (!empty($file->folder_id)): ?>
                                                📁 <a href="https://drive.google.com/drive/folders/<?php echo esc_attr($file->folder_id); ?>" target="_blank" style="color: #4285f4; text-decoration: none;" title="Abrir pasta no Google Drive">
                                                    <?php echo !empty($file->folder_name) ? esc_html($file->folder_name) : 'Pasta'; ?>
                                                </a> •
                                            <?php endif; ?>
                                            Última visualização: <?php echo date('d/m/Y H:i', strtotime($file->last_hit)); ?>
                                        </div>
                                    </div>
                                    <div style="background: #4285f4; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 10px;">
                                        <?php echo number_format($file->hits); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 20px;">Nenhum arquivo visualizado ainda.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Estatísticas por Pasta -->
                <div class="gdhn-stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">📁 Top 10 Pastas Mais Ativas</h2>
                    <?php if (!empty($folder_stats)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($folder_stats as $index => $folder): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 500; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($folder->folder_id); ?>">
                                            <a href="https://drive.google.com/drive/folders/<?php echo esc_attr($folder->folder_id); ?>" target="_blank" style="text-decoration: none; color: inherit;" title="Ver pasta no Google Drive">
                                                <?php echo ($index + 1) . '. ' . esc_html($folder->display_name); ?> 📁
                                            </a>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo number_format($folder->file_count); ?> arquivo(s)
                                        </div>
                                    </div>
                                    <div style="background: #34a853; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 10px;">
                                        <?php echo number_format($folder->total_hits); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 20px;">Nenhuma pasta com atividade ainda.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Estatísticas por Página -->
                <div class="gdhn-stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">📄 Top 10 Páginas Mais Ativas</h2>
                    <?php if (!empty($page_stats)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($page_stats as $index => $page): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 500; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <a href="<?php echo esc_url($page->page_url); ?>" target="_blank" style="text-decoration: none; color: inherit;">
                                                <?php echo ($index + 1) . '. ' . esc_html($page->page_title); ?>
                                            </a>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo number_format($page->unique_files); ?> arquivo(s) únicos
                                        </div>
                                    </div>
                                    <div style="background: #ea4335; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 10px;">
                                        <?php echo number_format($page->total_hits); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 20px;">Nenhuma página com atividade ainda.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Atividade Recente -->
                <div class="gdhn-stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: #4285f4;">🕒 Atividade Recente (7 dias)</h2>
                    <?php if (!empty($recent_activity)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 500; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($activity->file_name); ?>">
                                            <?php if (!empty($activity->file_id)): ?>
                                                <a href="https://drive.google.com/file/d/<?php echo esc_attr($activity->file_id); ?>/view" target="_blank" style="text-decoration: none; color: inherit;" title="Ver no Google Drive">
                                                    <?php echo esc_html($activity->file_name); ?> 🔗
                                                </a>
                                            <?php else: ?>
                                                <?php echo esc_html($activity->file_name); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo date('d/m/Y H:i', strtotime($activity->last_hit)); ?>
                                        </div>
                                    </div>
                                    <div style="background: #ea4335; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 10px;">
                                        <?php echo number_format($activity->hits); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 20px;">Nenhuma atividade recente.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Filtro por Página -->
                <div class="gdhn-stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); grid-column: 1 / -1;">
                    <h2 style="margin-top: 0; color: #4285f4;">📄 Estatísticas por Página</h2>
                    
                    <?php if (!empty($pages_with_activity)): ?>
                        <form method="get" action="" style="margin-bottom: 20px;">
                            <input type="hidden" name="page" value="gdhn-stats">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <label for="filter_page" style="font-weight: 500;">Selecione uma página:</label>
                                <select name="filter_page" id="filter_page" style="flex: 1; max-width: 500px; padding: 8px;">
                                    <option value="">-- Escolha uma página --</option>
                                    <?php foreach ($pages_with_activity as $page): ?>
                                        <option value="<?php echo esc_attr($page->page_url); ?>" <?php selected($selected_page, $page->page_url); ?>>
                                            <?php echo esc_html($page->page_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button button-primary">🔍 Filtrar</button>
                                <?php if (!empty($selected_page)): ?>
                                    <a href="?page=gdhn-stats" class="button">✖ Limpar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (!empty($selected_page) && !empty($page_files_stats)): ?>
                            <div style="border-top: 1px solid #eee; padding-top: 20px;">
                                <h3>Arquivos Visualizados nesta Página:</h3>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($page_files_stats as $index => $file_stat): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-weight: 500; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php if (!empty($file_stat->file_id)): ?>
                                                        <a href="https://drive.google.com/file/d/<?php echo esc_attr($file_stat->file_id); ?>/view" target="_blank" style="text-decoration: none; color: inherit;" title="Ver arquivo no Google Drive">
                                                            <?php echo ($index + 1) . '. ' . esc_html($file_stat->file_name); ?> 🔗
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo ($index + 1) . '. ' . esc_html($file_stat->file_name); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 12px; color: #666;">
                                                    <?php if (!empty($file_stat->folder_id)): ?>
                                                        📁 <a href="https://drive.google.com/drive/folders/<?php echo esc_attr($file_stat->folder_id); ?>" target="_blank" style="color: #4285f4; text-decoration: none;" title="Abrir pasta no Google Drive">
                                                            <?php echo !empty($file_stat->folder_name) ? esc_html($file_stat->folder_name) : 'Pasta'; ?>
                                                        </a> •
                                                    <?php endif; ?>
                                                    Última visualização: <?php echo date('d/m/Y H:i', strtotime($file_stat->last_hit)); ?>
                                                </div>
                                            </div>
                                            <div style="background: #fbbc04; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-left: 10px;">
                                                <?php echo number_format($file_stat->total_hits); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (!empty($selected_page)): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">Nenhum arquivo visualizado nesta página ainda.</p>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 20px;">Selecione uma página para ver as estatísticas.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">Nenhuma página com atividade ainda.</p>
                    <?php endif; ?>
                </div>
                
            </div>
            
            <div style="background: #f0f8ff; border: 1px solid #4285f4; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #4285f4;">💡 Dicas</h3>
                <ul style="margin: 0; color: #666;">
                    <li>As visualizações são contadas quando os utilizadores clicam nos nomes dos arquivos</li>
                    <li>Use o parâmetro <code>show_hits="false"</code> no shortcode para ocultar a coluna de visualizações</li>
                    <li>Os dados são armazenados permanentemente na base de dados do WordPress</li>
                    <li>Esta funcionalidade ajuda a identificar quais arquivos são mais populares</li>
                </ul>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Página de debug para hits (temporária)
     */
    public function debug_hits_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        // Processar ações
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create_table') {
                $this->create_hits_table();
                echo '<div class="notice notice-success"><p>Tabela criada/atualizada!</p></div>';
            } elseif ($_POST['action'] === 'update_folder_names') {
                $this->update_all_folder_names();
                echo '<div class="notice notice-success"><p>Processo de atualização de nomes das pastas iniciado!</p></div>';
            } elseif ($_POST['action'] === 'test_insert') {
                $test_result = $wpdb->insert(
                    $table_name,
                    array(
                        'file_id' => 'test_' . time(),
                        'file_name' => 'Arquivo de Teste',
                        'folder_id' => 'test_folder',
                        'hits' => 1,
                        'first_hit' => current_time('mysql'),
                        'last_hit' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%d', '%s', '%s')
                );
                
                if ($test_result) {
                    echo '<div class="notice notice-success"><p>Teste de inserção bem-sucedido!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erro no teste: ' . $wpdb->last_error . '</p></div>';
                }
            }
        }
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $total_records = 0;
        $recent_records = array();
        
        if ($table_exists) {
            $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $recent_records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_hit DESC LIMIT 10");
            
            // Verificar estrutura da tabela
            $table_structure = $wpdb->get_results("DESCRIBE $table_name");
        }
        
        ?>
        <div class="wrap">
            <h1>🔧 Debug - Sistema de Hits <small style="color: #666; font-size: 14px; font-weight: normal;">v<?php echo GDHN_VERSION; ?></small></h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>Status da Tabela</h2>
                <p><strong>Nome da tabela:</strong> <code><?php echo $table_name; ?></code></p>
                <p><strong>Tabela existe:</strong> 
                    <span style="color: <?php echo $table_exists ? 'green' : 'red'; ?>; font-weight: bold;">
                        <?php echo $table_exists ? '✅ SIM' : '❌ NÃO'; ?>
                    </span>
                </p>
                <?php if ($table_exists): ?>
                    <p><strong>Total de registos:</strong> <?php echo number_format($total_records); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>Ações de Debug</h2>
                <form method="post" style="margin-bottom: 10px;">
                    <input type="hidden" name="action" value="create_table">
                    <button type="submit" class="button button-primary">Criar/Atualizar Tabela</button>
                </form>
                
                <?php if ($table_exists): ?>
                <form method="post" style="margin-bottom: 10px;">
                    <input type="hidden" name="action" value="test_insert">
                    <button type="submit" class="button">Testar Inserção</button>
                </form>
                
                <form method="post">
                    <input type="hidden" name="action" value="update_folder_names">
                    <button type="submit" class="button button-secondary">Atualizar Nomes das Pastas</button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if ($table_exists): ?>
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>Estrutura da Tabela</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Tipo</th>
                            <th>Null</th>
                            <th>Key</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_structure as $column): ?>
                        <tr>
                            <td><strong><?php echo $column->Field; ?></strong></td>
                            <td><?php echo $column->Type; ?></td>
                            <td><?php echo $column->Null; ?></td>
                            <td><?php echo $column->Key; ?></td>
                            <td><?php echo $column->Default; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if ($table_exists && !empty($recent_records)): ?>
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>Registos Recentes</h2>
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>File ID</th>
                                <th>Nome do Arquivo</th>
                                <th>Folder ID</th>
                                <th>Nome da Pasta</th>
                                <th>URL da Página</th>
                                <th>Título da Página</th>
                                <th>Hits</th>
                                <th>Último Hit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): ?>
                            <tr>
                                <td><?php echo $record->id; ?></td>
                                <td><code><?php echo esc_html(substr($record->file_id, 0, 15)) . '...'; ?></code></td>
                                <td><?php echo esc_html($record->file_name); ?></td>
                                <td><code><?php echo esc_html(substr($record->folder_id, 0, 15)) . '...'; ?></code></td>
                                <td><?php echo $record->folder_name ? esc_html($record->folder_name) : '<em>Não definido</em>'; ?></td>
                                <td>
                                    <?php if (!empty($record->page_url)): ?>
                                        <a href="<?php echo esc_url($record->page_url); ?>" target="_blank" title="<?php echo esc_attr($record->page_url); ?>">
                                            <?php echo esc_html(substr($record->page_url, 0, 30)) . '...'; ?>
                                        </a>
                                    <?php else: ?>
                                        <em>Não definido</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $record->page_title ? esc_html($record->page_title) : '<em>Não definido</em>'; ?></td>
                                <td><strong><?php echo $record->hits; ?></strong></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($record->last_hit)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="background: #f0f8ff; border: 1px solid #4285f4; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #4285f4;">💡 Instruções</h3>
                <ol style="margin: 0; color: #666;">
                    <li>Se a tabela não existe, clique em "Criar/Atualizar Tabela"</li>
                    <li>Teste a inserção para verificar se a BD está funcional</li>
                    <li>Verifique os logs do WordPress em <code>wp-content/debug.log</code></li>
                    <li>Teste clicando num arquivo no frontend para ver se os hits são registados</li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Criar tabela para armazenar hits dos arquivos
     */
    public function create_hits_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDHN: Tentando criar tabela $table_name");
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            file_id varchar(255) NOT NULL,
            file_name varchar(500) NOT NULL,
            folder_id varchar(255) NOT NULL,
            folder_name varchar(500) DEFAULT NULL,
            page_url varchar(1000) DEFAULT NULL,
            page_title varchar(500) DEFAULT NULL,
            hits int(11) DEFAULT 0,
            first_hit datetime DEFAULT CURRENT_TIMESTAMP,
            last_hit datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY file_id (file_id),
            KEY folder_id (folder_id),
            KEY page_url (page_url(255)),
            KEY hits (hits)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Log do resultado
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDHN: Resultado dbDelta: " . print_r($result, true));
            
            // Verificar se a tabela foi criada
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            error_log("GDHN: Tabela existe: " . ($table_exists ? 'SIM' : 'NÃO'));
        }
    }
    
    /**
     * AJAX handler para tracking de hits
     */
    public function ajax_track_hit() {
        // Log para debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDHN: ajax_track_hit chamado");
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gdhn_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Nonce inválido");
            }
            wp_die('Nonce inválido');
        }
        
        $file_id = sanitize_text_field($_POST['file_id']);
        $file_name = sanitize_text_field($_POST['file_name']);
        $folder_id = sanitize_text_field($_POST['folder_id']);
        $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
        
        // Se o folder_name estiver vazio, tentar buscar da API
        if (empty($folder_name) && !empty($folder_id)) {
            $folder_name = $this->get_folder_name_from_api($folder_id);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDHN: Dados recebidos - file_id: $file_id, file_name: $file_name, folder_id: $folder_id, folder_name: $folder_name, page_url: $page_url, page_title: $page_title");
        }
        
        if (empty($file_id) || empty($file_name) || empty($folder_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Dados inválidos");
            }
            wp_send_json_error('Dados inválidos');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Tabela $table_name não existe, criando...");
            }
            $this->create_hits_table();
        }
        
        // Verificar se o arquivo já existe na tabela
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, hits FROM $table_name WHERE file_id = %s",
            $file_id
        ));
        
        if ($existing) {
            // Incrementar hits
            $new_hits = $existing->hits + 1;
            $result = $wpdb->update(
                $table_name,
                array(
                    'hits' => $new_hits,
                    'last_hit' => current_time('mysql')
                ),
                array('file_id' => $file_id),
                array('%d', '%s'),
                array('%s')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Update resultado: $result, novos hits: $new_hits");
                if ($wpdb->last_error) {
                    error_log("GDHN: Erro no update: " . $wpdb->last_error);
                }
            }
        } else {
            // Inserir novo registro
            $result = $wpdb->insert(
                $table_name,
                array(
                    'file_id' => $file_id,
                    'file_name' => $file_name,
                    'folder_id' => $folder_id,
                    'folder_name' => $folder_name,
                    'page_url' => $page_url,
                    'page_title' => $page_title,
                    'hits' => 1,
                    'first_hit' => current_time('mysql'),
                    'last_hit' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            $new_hits = 1;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Insert resultado: $result, novos hits: $new_hits");
                if ($wpdb->last_error) {
                    error_log("GDHN: Erro no insert: " . $wpdb->last_error);
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDHN: Enviando resposta com hits: $new_hits");
        }
        
        wp_send_json_success(array('hits' => $new_hits));
    }
    
    /**
     * AJAX handler para obter hits de um arquivo
     */
    public function ajax_get_file_hits() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gdhn_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $file_id = sanitize_text_field($_POST['file_id']);
        
        if (empty($file_id)) {
            wp_send_json_error('File ID inválido');
        }
        
        $hits = $this->get_file_hits($file_id);
        
        wp_send_json_success(array('hits' => $hits));
    }
    
    /**
     * Obter hits de um arquivo específico
     */
    public function get_file_hits($file_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        $hits = $wpdb->get_var($wpdb->prepare(
            "SELECT hits FROM $table_name WHERE file_id = %s",
            $file_id
        ));
        
        return $hits ? intval($hits) : 0;
    }
    
    /**
     * Obter estatísticas de hits por pasta
     */
    public function get_folder_stats($folder_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT file_id, file_name, hits, last_hit 
             FROM $table_name 
             WHERE folder_id = %s 
             ORDER BY hits DESC, last_hit DESC",
            $folder_id
        ));
        
        return $stats;
    }
    
    /**
     * Atualizar nome da pasta usando a API do Google Drive
     */
    private function update_folder_name($file_id, $folder_id) {
        // Executar em background para não atrasar a resposta
        wp_schedule_single_event(time(), 'gdhn_update_folder_name', array($file_id, $folder_id));
    }
    
    /**
     * Atualizar nomes de todas as pastas sem nome
     */
    public function update_all_folder_names() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        // Obter todas as pastas únicas sem nome
        $folders = $wpdb->get_results(
            "SELECT DISTINCT folder_id 
             FROM $table_name 
             WHERE folder_name IS NULL OR folder_name = ''"
        );
        
        foreach ($folders as $folder) {
            // Agendar atualização para cada pasta
            wp_schedule_single_event(time() + rand(1, 10), 'gdhn_update_folder_name', array('', $folder->folder_id));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDHN: Agendadas atualizações para " . count($folders) . " pastas");
        }
    }
    
    /**
     * Função para buscar nome da pasta na API do Google Drive
     */
    /**
     * Buscar nome da pasta pela API (retorna o nome)
     */
    private function get_folder_name_from_api($folder_id) {
        // Obter API key das configurações
        $settings = get_option('gdhn_settings', array());
        $api_key = !empty($settings['api_key']) ? $settings['api_key'] : '';
        
        if (empty($api_key)) {
            return null;
        }
        
        // Buscar informações da pasta na API
        $url = add_query_arg(array(
            'key' => $api_key,
            'fields' => 'name'
        ), "https://www.googleapis.com/drive/v3/files/{$folder_id}");
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($status_code === 200 && isset($data['name'])) {
                return sanitize_text_field($data['name']);
            }
        }
        
        return null;
    }
    
    /**
     * Buscar e atualizar nome da pasta pela API (para background jobs)
     */
    public function fetch_folder_name_from_api($file_id, $folder_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gdhn_file_hits';
        
        $folder_name = $this->get_folder_name_from_api($folder_id);
        
        if ($folder_name) {
            // Atualizar todos os registos com este folder_id
            $wpdb->update(
                $table_name,
                array('folder_name' => $folder_name),
                array('folder_id' => $folder_id),
                array('%s'),
                array('%s')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDHN: Nome da pasta atualizado: $folder_id -> $folder_name");
            }
        }
    }
    
    /**
     * Página de Gestão de Cache
     */
    public function cache_manager_page() {
        // Processar ações
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'clear_all_cache') {
                $this->clear_all_cache();
                echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Cache limpa com sucesso!</strong> Todos os dados em cache foram removidos.</p></div>';
            } elseif ($_POST['action'] === 'clear_folders_cache') {
                $this->clear_cache_by_type('folders');
                echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Cache de pastas limpa!</strong></p></div>';
            } elseif ($_POST['action'] === 'clear_files_cache') {
                $this->clear_cache_by_type('files');
                echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Cache de arquivos limpa!</strong></p></div>';
            }
        }
        
        // Obter estatísticas de cache
        $cache_stats = $this->get_cache_stats();
        
        ?>
        <div class="wrap">
            <h1>🗑️ Gestão de Cache - GDrive Navigator <small style="color: #666; font-size: 14px; font-weight: normal;">v<?php echo GDHN_VERSION; ?></small></h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>📊 Estatísticas de Cache</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #4285f4;">
                        <div style="font-size: 32px; font-weight: bold; color: #4285f4;"><?php echo $cache_stats['total_items']; ?></div>
                        <div style="color: #666;">Total de Itens em Cache</div>
                    </div>
                    <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #34a853;">
                        <div style="font-size: 32px; font-weight: bold; color: #34a853;"><?php echo $cache_stats['folders_count']; ?></div>
                        <div style="color: #666;">Pastas em Cache</div>
                    </div>
                    <div style="background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 4px solid #fbbc04;">
                        <div style="font-size: 32px; font-weight: bold; color: #fbbc04;"><?php echo $cache_stats['files_count']; ?></div>
                        <div style="color: #666;">Arquivos em Cache</div>
                    </div>
                </div>
                
                <p style="color: #666; margin-top: 20px;">
                    <strong>ℹ️ Sobre a Cache:</strong> A cache armazena temporariamente dados do Google Drive para melhorar a performance e reduzir chamadas à API. 
                    O tempo de cache é configurável nas <a href="<?php echo admin_url('admin.php?page=gdhn-settings'); ?>">Configurações</a>.
                </p>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>🧹 Ações de Limpeza</h2>
                
                <div style="display: grid; gap: 15px;">
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h3 style="margin-top: 0;">🗑️ Limpar Toda a Cache</h3>
                        <p>Remove todos os dados em cache (pastas e arquivos). Use quando quiser forçar atualização completa.</p>
                        <form method="post" onsubmit="return confirm('Tem certeza que deseja limpar toda a cache?');">
                            <input type="hidden" name="action" value="clear_all_cache">
                            <button type="submit" class="button button-primary">🗑️ Limpar Toda a Cache</button>
                        </form>
                    </div>
                    
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h3 style="margin-top: 0;">📁 Limpar Apenas Cache de Pastas</h3>
                        <p>Remove apenas a cache de pastas. Útil quando adiciona ou remove pastas no Google Drive.</p>
                        <form method="post" onsubmit="return confirm('Deseja limpar a cache de pastas?');">
                            <input type="hidden" name="action" value="clear_folders_cache">
                            <button type="submit" class="button">📁 Limpar Cache de Pastas</button>
                        </form>
                    </div>
                    
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <h3 style="margin-top: 0;">📄 Limpar Apenas Cache de Arquivos</h3>
                        <p>Remove apenas a cache de arquivos. Útil quando adiciona ou atualiza arquivos no Google Drive.</p>
                        <form method="post" onsubmit="return confirm('Deseja limpar a cache de arquivos?');">
                            <input type="hidden" name="action" value="clear_files_cache">
                            <button type="submit" class="button">📄 Limpar Cache de Arquivos</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0; color: #856404;">⚠️ Quando Limpar a Cache?</h3>
                <ul style="margin: 0; color: #856404;">
                    <li><strong>Conteúdo novo não aparece:</strong> Se adicionou pastas ou arquivos no Google Drive e não aparecem no site</li>
                    <li><strong>Arquivos foram movidos:</strong> Se reorganizou a estrutura de pastas</li>
                    <li><strong>Nomes foram alterados:</strong> Se renomeou pastas ou arquivos</li>
                    <li><strong>Teste de funcionalidades:</strong> Quando está a testar o plugin com diferentes configurações</li>
                </ul>
                <p style="color: #856404; margin-bottom: 0;">
                    <strong>Nota:</strong> A cache é limpa automaticamente após o tempo configurado. Limpar manualmente só é necessário quando precisa de atualização imediata.
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Limpar toda a cache
     */
    private function clear_all_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_gdhn_%' 
             OR option_name LIKE '_transient_timeout_gdhn_%'"
        );
    }
    
    /**
     * Limpar cache por tipo (folders ou files)
     */
    private function clear_cache_by_type($type) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_gdhn_' . $type . '_%',
            '_transient_timeout_gdhn_' . $type . '_%'
        ));
    }
    
    /**
     * Obter estatísticas de cache
     */
    private function get_cache_stats() {
        global $wpdb;
        
        $total = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_gdhn_%'"
        );
        
        $folders = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_gdhn_folders_%'"
        );
        
        $files = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_gdhn_files_%'"
        );
        
        return array(
            'total_items' => intval($total),
            'folders_count' => intval($folders),
            'files_count' => intval($files)
        );
    }
}

// Inicializar o plugin
new GDriveHierarchyNavigator();

// Hook para atualizar nomes das pastas em background
add_action('gdhn_update_folder_name', function($file_id, $folder_id) {
    $plugin = new GDriveHierarchyNavigator();
    $plugin->fetch_folder_name_from_api($file_id, $folder_id);
});
