<?php
echo "=== JIT Status Check ===\n";
echo "OPcache enabled: " . (extension_loaded('Zend OPcache') ? 'Yes' : 'No') . "\n";
echo "JIT enabled: " . (ini_get('opcache.jit') ? 'Yes (' . ini_get('opcache.jit') . ')' : 'No') . "\n";
echo "JIT buffer size: " . ini_get('opcache.jit_buffer_size') . "\n";

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status && isset($status['jit'])) {
        echo "JIT compiled functions: " . $status['jit']['compiled_functions'] . "\n";
        echo "JIT hits: " . $status['jit']['hits'] . "\n";
        echo "JIT memory used: " . round($status['jit']['buffer_size'] / 1024 / 1024, 2) . "MB\n";
    }
}

// Тест производительности
$start = microtime(true);
for ($i = 0; $i < 1000000; $i++) {
    $result = $i * 2 + 1;
}
$time = microtime(true) - $start;
echo "Performance test: " . round($time * 1000, 2) . "ms\n";
?>