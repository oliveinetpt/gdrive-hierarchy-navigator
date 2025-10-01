<?php
/**
 * Sistema de Atualizações Automáticas
 * Google Drive Hierarchy Navigator
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDHN_Plugin_Updater {
    
    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    private $github_token; // Opcional para repos privados
    
    public function __construct($plugin_file, $github_username, $github_repo, $version, $github_token = '') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->github_token = $github_token;
        $this->plugin_path = plugin_basename(dirname($plugin_file));
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }
    
    /**
     * Verificar se há atualizações disponíveis
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Obter informações da versão remota
        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => $this->plugin_path,
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => $this->get_github_repo_url(),
                'package' => $this->get_download_url($remote_version)
            );
        }
        
        return $transient;
    }
    
    /**
     * Obter versão remota do GitHub
     */
    private function get_remote_version() {
        $request = wp_remote_get($this->get_api_url());
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            if (isset($data['tag_name'])) {
                return ltrim($data['tag_name'], 'v'); // Remove 'v' do início se existir
            }
        }
        
        return $this->version;
    }
    
    /**
     * Fornecer informações do plugin para o WordPress
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_path) {
            return $result;
        }
        
        $request = wp_remote_get($this->get_api_url());
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            $result = (object) array(
                'name' => 'Google Drive Hierarchy Navigator',
                'slug' => $this->plugin_path,
                'version' => ltrim($data['tag_name'], 'v'),
                'author' => '<a href="https://oliveinet.pt">Oliveinet</a>',
                'homepage' => $this->get_github_repo_url(),
                'short_description' => 'Plugin WordPress para navegação hierárquica de pastas e arquivos do Google Drive.',
                'sections' => array(
                    'description' => $this->get_description(),
                    'changelog' => $this->get_changelog($data)
                ),
                'download_link' => $this->get_download_url(ltrim($data['tag_name'], 'v')),
                'requires' => '5.0',
                'tested' => '6.4',
                'requires_php' => '7.4',
                'last_updated' => $data['published_at']
            );
        }
        
        return $result;
    }
    
    /**
     * Pós-instalação: renomear pasta se necessário
     */
    public function post_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($this->plugin_path === $hook_extra['plugin']) {
            $wp_filesystem->delete($result['destination_name'], true);
        }
        
        return $result;
    }
    
    /**
     * URL da API do GitHub
     */
    private function get_api_url() {
        return "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
    }
    
    /**
     * URL do repositório GitHub
     */
    private function get_github_repo_url() {
        return "https://github.com/{$this->github_username}/{$this->github_repo}";
    }
    
    /**
     * URL de download do ZIP
     */
    private function get_download_url($version) {
        // Download do asset anexado à release (não do código fonte)
        return "https://github.com/{$this->github_username}/{$this->github_repo}/releases/download/v{$version}/gdrive-hierarchy-navigator.zip";
    }
    
    /**
     * Descrição do plugin
     */
    private function get_description() {
        return '
        <p><strong>Google Drive Hierarchy Navigator</strong> é um plugin WordPress que permite navegar e exibir hierarquicamente pastas e arquivos do Google Drive.</p>
        
        <h4>🚀 Características principais:</h4>
        <ul>
            <li>📁 Navegação hierárquica de pastas</li>
            <li>🎯 Barras de navegação com chips clicáveis</li>
            <li>📊 Tabela de arquivos com filtro</li>
            <li>🎨 Design responsivo e moderno</li>
            <li>⚙️ Configuração via shortcode</li>
            <li>🔧 Painel de administração</li>
        </ul>
        
        <h4>📋 Como usar:</h4>
        <pre>[gdrive_navigator folder_id="SEU_FOLDER_ID" api_key="SUA_API_KEY"]</pre>
        ';
    }
    
    /**
     * Changelog baseado na release do GitHub
     */
    private function get_changelog($release_data) {
        $changelog = '<h4>Versão ' . ltrim($release_data['tag_name'], 'v') . '</h4>';
        
        if (isset($release_data['body']) && !empty($release_data['body'])) {
            $changelog .= '<p>' . nl2br(esc_html($release_data['body'])) . '</p>';
        }
        
        return $changelog;
    }
}

// Inicializar o updater apenas se estivermos no admin
if (is_admin()) {
    new GDHN_Plugin_Updater(
        __FILE__, 
        'oliveinetpt',           // Seu username do GitHub
        'gdrive-hierarchy-navigator', // Nome do repositório
        GDHN_VERSION,          // Versão atual
        ''                     // Token do GitHub (deixe vazio para repos públicos)
    );
}
