<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "config: ";
var_dump(file_exists(dirname(__DIR__) . '/config.php'));

echo "supabase: ";
var_dump(file_exists(dirname(__DIR__) . '/supabase.php'));

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/supabase.php';

echo "OK - arquivos carregados";