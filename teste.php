<?php
echo json_encode([
    "curl"     => extension_loaded('curl'),
    "openssl"  => extension_loaded('openssl'),
    "version"  => phpversion()
]);