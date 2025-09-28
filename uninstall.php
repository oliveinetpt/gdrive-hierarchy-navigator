<?php
/**
 * Arquivo de desinstalação do Google Drive Hierarchy Navigator
 * 
 * Este arquivo é executado quando o plugin é desinstalado via WordPress admin
 * Remove todas as configurações e dados temporários do plugin
 * 
 * @package GDriveHierarchyNavigator
 * @version 1.0.0
 */

// Impedir acesso direto
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Impedir execução se não for chamado pelo WordPress
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Limpar cache e transientes do plugin
 */
function gdhn_cleanup_transients() {
    global $wpdb;
    
    // Buscar todos os transientes do plugin
    $transients = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            '_transient_gdhn_%',
            '_transient_timeout_gdhn_%'
        )
    );
    
    // Deletar cada transiente encontrado
    foreach ($transients as $transient) {
        delete_option($transient->option_name);
    }
}

/**
 * Limpar opções do plugin
 */
function gdhn_cleanup_options() {
    // Lista de opções que podem ter sido criadas pelo plugin
    $options_to_delete = array(
        'gdhn_version',
        'gdhn_settings',
        'gdhn_cache_settings',
        'gdhn_api_settings',
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
}

/**
 * Limpar metadados de posts (se houver)
 */
function gdhn_cleanup_post_meta() {
    global $wpdb;
    
    // Remover qualquer meta relacionado ao plugin
    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => 'gdhn_folder_id'
        )
    );
    
    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => 'gdhn_settings'
        )
    );
}

/**
 * Limpar cache de objetos (se usando cache persistente)
 */
function gdhn_cleanup_object_cache() {
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('gdhn');
    }
}

/**
 * Limpar logs de erro específicos do plugin
 */
function gdhn_cleanup_logs() {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/gdhn-errors.log';
    
    if (file_exists($log_file)) {
        unlink($log_file);
    }
}

/**
 * Executar limpeza completa
 */
function gdhn_uninstall() {
    // Verificar permissões
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Executar funções de limpeza
    gdhn_cleanup_transients();
    gdhn_cleanup_options();
    gdhn_cleanup_post_meta();
    gdhn_cleanup_object_cache();
    gdhn_cleanup_logs();
    
    // Forçar limpeza do cache do WordPress
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Log da desinstalação (opcional, apenas para debug)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Google Drive Hierarchy Navigator: Plugin desinstalado e dados limpos');
    }
}

// Executar desinstalação
gdhn_uninstall();
