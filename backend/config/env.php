<?php

declare(strict_types=1);

/**
 * Lit d'abord les variables du serveur, puis un fichier local non versionné.
 * Ce dernier permet les hébergeurs gratuits sans variables d'environnement.
 */
function appConfig(string $name): string|false
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    static $local = null;
    if ($local === null) {
        $path = __DIR__ . '/local.php';
        $loaded = is_file($path) ? require $path : [];
        if (!is_array($loaded)) {
            throw new RuntimeException('Configuration locale invalide.');
        }
        $local = $loaded;
    }

    if (!array_key_exists($name, $local)) {
        return false;
    }
    if (!is_string($local[$name]) && !is_numeric($local[$name])) {
        throw new RuntimeException('Valeur de configuration locale invalide.');
    }
    return (string) $local[$name];
}
