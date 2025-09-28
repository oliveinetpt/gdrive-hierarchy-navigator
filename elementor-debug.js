/**
 * Script de Debug para Elementor
 * Cole este código no console do navegador (F12) para diagnosticar problemas
 */

console.log('=== GDHN Elementor Debug ===');

// Verificar se jQuery está carregado
if (typeof jQuery !== 'undefined') {
    console.log('✅ jQuery carregado:', jQuery.fn.jquery);
} else {
    console.log('❌ jQuery não encontrado');
}

// Verificar se o script do plugin foi carregado
if (typeof gdhn_ajax !== 'undefined') {
    console.log('✅ GDHN AJAX configurado:', gdhn_ajax);
} else {
    console.log('❌ GDHN AJAX não encontrado - script não carregado');
}

// Verificar containers
const containers = document.querySelectorAll('.gdhn-container');
console.log(`📦 Containers encontrados: ${containers.length}`);

containers.forEach((container, index) => {
    console.log(`Container ${index + 1}:`, {
        id: container.id,
        initialized: jQuery(container).data('gdhn-initialized'),
        folderId: container.dataset.folderId,
        apiKey: container.dataset.apiKey ? 'Configurada' : 'Não configurada',
        display: window.getComputedStyle(container).display,
        visibility: window.getComputedStyle(container).visibility
    });
});

// Verificar se Elementor está ativo
if (typeof elementorFrontend !== 'undefined') {
    console.log('✅ Elementor Frontend detectado');
    console.log('Elementor config:', elementorFrontend.config);
} else {
    console.log('⚠️ Elementor Frontend não detectado');
}

// Verificar erros JavaScript
const originalError = console.error;
console.error = function(...args) {
    if (args.some(arg => typeof arg === 'string' && arg.includes('gdhn'))) {
        console.log('🚨 Erro GDHN detectado:', args);
    }
    originalError.apply(console, args);
};

// Função para forçar reinicialização
window.gdhnForceInit = function() {
    console.log('🔄 Forçando reinicialização...');
    if (typeof jQuery !== 'undefined') {
        jQuery('.gdhn-container').removeData('gdhn-initialized');
        jQuery(document).trigger('gdhn:reinit');
        console.log('✅ Reinicialização disparada');
    }
};

// Função para testar AJAX manualmente
window.gdhnTestAjax = function(folderId, apiKey) {
    if (typeof jQuery === 'undefined' || typeof gdhn_ajax === 'undefined') {
        console.log('❌ jQuery ou gdhn_ajax não disponível');
        return;
    }
    
    console.log('🧪 Testando AJAX...');
    
    jQuery.ajax({
        url: gdhn_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'gdhn_load_folder',
            action_type: 'folders',
            folder_id: folderId,
            api_key: apiKey,
            cache_minutes: 15,
            nonce: gdhn_ajax.nonce
        },
        success: function(response) {
            console.log('✅ AJAX Sucesso:', response);
        },
        error: function(xhr, status, error) {
            console.log('❌ AJAX Erro:', {xhr, status, error});
        }
    });
};

console.log('=== Comandos Disponíveis ===');
console.log('gdhnForceInit() - Forçar reinicialização');
console.log('gdhnTestAjax("folder_id", "api_key") - Testar AJAX');
console.log('=============================');
