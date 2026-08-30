<?php
/**
 * Auto-prepend bootstrap for performance tooling against the disposable test database.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = idlm_integration_config(true);
idlm_integration_boot_wordpress($config, false);
idlm_integration_load_plugin($config);
