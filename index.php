<?php
/**
 * Impedir acesso direto ao diretório do plugin
 * 
 * Este arquivo protege o diretório do plugin contra navegação direta
 * e garante que só pode ser acessado através do WordPress
 */

// Silenciar - não mostrar nada se acessado diretamente
// Redirecionar para a página inicial do site se acessado diretamente
if (!defined('ABSPATH')) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $home_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        header('Location: ' . $home_url);
        exit;
    }
    exit;
}
