<?php
/**
 * Plugin Name: Google Drive Hierarchy Navigator
 * Plugin URI: https://github.com/oliveinet/gdrive-hierarchy-navigator
 * Description: Plugin WordPress para navegação hierárquica de pastas e arquivos do Google Drive com barras de navegação e filtros.
 * Version: 1.9.0
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
define('GDHN_VERSION', '1.9.0');
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
        add_shortcode('gdrive_navigator', array($this, 'shortcode_handler'));
        
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function init() {
        // Inicialização do plugin
    }
    
    public function enqueue_scripts() {
        // Só carregar se estivermos numa página que tem o shortcode ou no admin
        if (is_admin() || $this->has_shortcode()) {
            // Enfileirar Font Awesome (só se não estiver já carregado)
            if (!wp_style_is('font-awesome', 'enqueued') && !wp_style_is('fontawesome', 'enqueued')) {
                wp_enqueue_style('gdhn-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
            }
            
            wp_enqueue_script('gdhn-main', GDHN_PLUGIN_URL . 'gdhn-main.js', array('jquery'), GDHN_VERSION, true);
            wp_enqueue_style('gdhn-style', GDHN_PLUGIN_URL . 'gdhn-style.css', array(), GDHN_VERSION);
            
            // Localizar script para AJAX
            wp_localize_script('gdhn-main', 'gdhn_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdhn_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG
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
        $atts['cache_minutes'] = max(1, min(60, intval($atts['cache_minutes'])));
        $atts['max_files'] = max(10, min(500, intval($atts['max_files'])));
        $atts['show_date'] = ($atts['show_date'] === 'true');
        $atts['show_size'] = ($atts['show_size'] === 'true');
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
            
            <!-- Filtro de arquivos -->
            <div class="gdhn-filter-section" style="display: none;">
                <div class="gdhn-filter-container">
                    <input type="text" 
                           class="gdhn-filter-input" 
                           placeholder="<?php echo esc_attr($atts['filter_placeholder']); ?>">
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
                            <th class="gdhn-actions-col">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="gdhn-files-tbody">
                        <!-- Arquivos serão inseridos aqui via JavaScript -->
                    </tbody>
                </table>
                
                <div class="gdhn-no-files" style="display: none;">
                    <p>📁 Nenhum arquivo encontrado nesta pasta.</p>
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
        $settings_link = '<a href="' . admin_url('options-general.php?page=gdhn-settings') . '">⚙️ Configurações</a>';
        $diagnostico_link = '<a href="' . admin_url('tools.php?page=gdhn-diagnostico') . '">🔧 Diagnóstico</a>';
        array_unshift($links, $settings_link, $diagnostico_link);
        return $links;
    }
    
    /**
     * Adicionar páginas no menu admin
     */
    public function add_admin_menu() {
        // Página de configurações
        add_options_page(
            'Google Drive Navigator - Configurações',
            'Google Drive Navigator',
            'manage_options',
            'gdhn-settings',
            array($this, 'settings_page')
        );
        
        // Página de diagnóstico
        add_management_page(
            'Google Drive Navigator - Diagnóstico',
            'GDrive Navigator',
            'manage_options',
            'gdhn-diagnostico',
            array($this, 'diagnostico_page')
        );
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
            $validated['default_cache_minutes'] = max(1, min(60, intval($input['default_cache_minutes'])));
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
        echo '<input type="number" name="gdhn_settings[default_cache_minutes]" value="' . esc_attr($cache) . '" min="1" max="60" />';
        echo '<p class="description">Tempo de cache em minutos (1-60)</p>';
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
            <h1>⚙️ Google Drive Navigator - Configurações</h1>
            
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
                <code>[gdrive_navigator folder_id="SEU_FOLDER_ID" api_key="SUA_API_KEY" levels="2" show_date="true" cache_minutes="20"]</code>
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
            <h1>🔧 Google Drive Navigator - Diagnóstico</h1>
            
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
}

// Inicializar o plugin
new GDriveHierarchyNavigator();
