# Como Ativar WP_DEBUG

## 📂 Localizar o arquivo wp-config.php

O arquivo está na **raiz do WordPress**, no mesmo nível das pastas `wp-content`, `wp-admin`, `wp-includes`.

## ✏️ Editar wp-config.php

Procure por estas linhas (geralmente perto do final do arquivo):

```php
define('WP_DEBUG', false);
```

**OU** se não existir, adicione antes da linha `/* That's all, stop editing! */`

## 🔄 Substituir por:

```php
// Ativar debug completo
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// Log de erros JavaScript (opcional)
ini_set('log_errors', 1);
ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
```

## 📍 Exemplo completo:

```php
/**
 * For developers: WordPress debugging mode.
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

/* That's all, stop editing! Happy publishing. */
```

## 💾 Salvar e Testar

1. Salve o arquivo
2. Teste o shortcode numa página
3. Verifique os logs em:
   - `/wp-content/debug.log`
   - `/wp-content/uploads/gdhn-debug.log`

## 🔍 Ver Logs via Admin

Após ativar, vá em **Ferramentas > GDrive Navigator** para ver os logs na interface.
