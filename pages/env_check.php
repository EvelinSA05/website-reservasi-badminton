<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'Loaded php.ini: ' . php_ini_loaded_file() . PHP_EOL;
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'ON' : 'OFF') . PHP_EOL;
echo 'mysqli: ' . (extension_loaded('mysqli') ? 'ON' : 'OFF') . PHP_EOL;
?>
