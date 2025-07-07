<?php
// Script de diagnostic des dépendances

echo "=== DIAGNOSTIC DES DÉPENDANCES NHC ===\n\n";

// Simuler le chemin d'execution depuis le plugin
$plugin_path = __DIR__;
echo "Chemin du plugin: $plugin_path\n\n";

// Tester les chemins comme dans le plugin
$python_venv_path = $plugin_path . '/resources/python_venv/bin/python3';
echo "Chemin venv testé: $python_venv_path\n";
echo "Venv existe: " . (file_exists($python_venv_path) ? 'OUI' : 'NON') . "\n";

if (file_exists($python_venv_path)) {
    $python_venv_real = realpath($python_venv_path);
    echo "Venv pointe vers: " . ($python_venv_real ? $python_venv_real : 'ERREUR') . "\n";
}

$python_cmd = file_exists($python_venv_path) ? $python_venv_path : 'python3';
echo "Python choisi: $python_cmd\n\n";

// Tester Python
echo "=== TEST PYTHON ===\n";
if ($python_cmd === 'python3') {
    echo "Utilisation Python système\n";
    $python_check = shell_exec('which python3 2>/dev/null');
    echo "which python3: " . ($python_check ? trim($python_check) : 'VIDE') . "\n";
} else {
    echo "Utilisation environnement virtuel\n";
    echo "Fichier existe: " . (file_exists($python_cmd) ? 'OUI' : 'NON') . "\n";
}

// Tester version Python
echo "\n=== VERSION PYTHON ===\n";
$python_version = shell_exec($python_cmd . ' --version 2>&1');
echo "Version: " . trim($python_version) . "\n";

// Tester modules Python
echo "\n=== TEST MODULES PYTHON ===\n";

echo "Test paho-mqtt:\n";
$mqtt_check = shell_exec($python_cmd . ' -c "import paho.mqtt.client; print(\'OK\')" 2>&1');
echo "Résultat: " . trim($mqtt_check) . "\n";
echo "Est vide: " . (empty(trim($mqtt_check)) ? 'OUI' : 'NON') . "\n";

echo "\nTest requests:\n";
$requests_check = shell_exec($python_cmd . ' -c "import requests; print(\'OK\')" 2>&1');
echo "Résultat: " . trim($requests_check) . "\n";
echo "Est vide: " . (empty(trim($requests_check)) ? 'OUI' : 'NON') . "\n";

// Tester fichier démon
echo "\n=== TEST FICHIER DÉMON ===\n";
$demond_path = realpath($plugin_path . '/resources/demond/demond.py');
echo "Chemin démon: " . ($demond_path ? $demond_path : 'NON TROUVÉ') . "\n";
echo "Fichier existe: " . (file_exists($demond_path) ? 'OUI' : 'NON') . "\n";

// Simuler la logique du plugin
echo "\n=== SIMULATION LOGIQUE PLUGIN ===\n";
$launchable = 'ok';
$launchable_message = '';

// Test paho-mqtt
$mqtt_check = shell_exec($python_cmd . ' -c "import paho.mqtt.client" 2>&1');
if (!empty(trim($mqtt_check))) {
    $launchable = 'nok';
    $launchable_message = 'Module paho-mqtt manquant';
    echo "ÉCHEC: $launchable_message\n";
    echo "Détail erreur: " . trim($mqtt_check) . "\n";
} else {
    echo "paho-mqtt: OK\n";
}

// Test requests
$requests_check = shell_exec($python_cmd . ' -c "import requests" 2>&1');
if (!empty(trim($requests_check))) {
    $launchable = 'nok';
    $launchable_message = 'Module requests manquant';
    echo "ÉCHEC: $launchable_message\n";
    echo "Détail erreur: " . trim($requests_check) . "\n";
} else {
    echo "requests: OK\n";
}

// Test fichier démon
if (!file_exists($demond_path)) {
    $launchable = 'nok';
    $launchable_message = 'Fichier démon manquant';
    echo "ÉCHEC: $launchable_message\n";
} else {
    echo "Fichier démon: OK\n";
}

echo "\n=== RÉSULTAT FINAL ===\n";
echo "Launchable: $launchable\n";
echo "Message: $launchable_message\n";

// Tester la liste des modules installés
echo "\n=== MODULES INSTALLÉS ===\n";
$pip_list = shell_exec($python_cmd . ' -m pip list 2>&1');
echo "Modules pip:\n$pip_list\n";

?>
