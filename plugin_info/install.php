<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function nhc_install() {
    // Installation des dépendances Python
    log::add('nhc', 'info', 'Installation des dépendances Python...');
    
    // Vérifier si Python3 est disponible
    $python_check = shell_exec('which python3 2>/dev/null');
    if (empty(trim($python_check))) {
        log::add('nhc', 'error', 'Python3 non trouvé. Installation nécessaire.');
        // Essayer d'installer Python3
        $install_python = shell_exec('apt update && apt install -y python3 python3-pip 2>&1');
        log::add('nhc', 'info', 'Installation Python3: ' . $install_python);
        
        // Vérifier à nouveau
        $python_check = shell_exec('which python3 2>/dev/null');
        if (empty(trim($python_check))) {
            log::add('nhc', 'error', 'Impossible d\'installer Python3');
            return false;
        }
    }
    
    log::add('nhc', 'info', 'Python3 trouvé: ' . trim($python_check));
    
    $modules = array(
        'paho.mqtt.client' => 'python3-paho-mqtt',
        'requests' => 'python3-requests'
    );
    
    $install_success = true;
    
    foreach ($modules as $pip_name => $apt_name) {
        // Vérifier si le module est déjà installé
        $module_import = str_replace('-', '.', $pip_name);
        $check_cmd = "python3 -c \"import $module_import\" 2>/dev/null";
        exec($check_cmd, $output, $return_var);
        
        if ($return_var === 0) {
            log::add('nhc', 'info', "Module $pip_name déjà installé");
            continue;
        }
        
        log::add('nhc', 'info', "Installation du module $pip_name...");
        
        // Essayer d'installer via apt
        log::add('nhc', 'info', "Tentative d'installation de $apt_name via apt...");
        $cmd = "apt update && apt install -y $apt_name 2>&1";
        $apt_output = shell_exec($cmd);
        
        // Vérifier si l'installation apt a réussi
        exec($check_cmd, $output, $return_var);
        if ($return_var === 0) {
            log::add('nhc', 'info', "Module $pip_name installé avec succès via apt");
            continue;
        }
        
        // Si apt échoue, essayer pip avec --break-system-packages
        log::add('nhc', 'warning', "Installation via apt échouée pour $apt_name, essai avec pip...");
        $pip_cmd = "pip3 install --break-system-packages $pip_name 2>&1";
        $pip_output = shell_exec($pip_cmd);
        
        // Vérifier si l'installation pip a réussi
        exec($check_cmd, $output, $return_var);
        if ($return_var === 0) {
            log::add('nhc', 'info', "Module $pip_name installé avec succès via pip");
        } else {
            log::add('nhc', 'error', "Erreur lors de l'installation de $pip_name");
            log::add('nhc', 'error', "Sortie apt: " . $apt_output);
            log::add('nhc', 'error', "Sortie pip: " . $pip_output);
            $install_success = false;
        }
    }
    
    if ($install_success) {
        log::add('nhc', 'info', 'Installation des dépendances terminée avec succès');
    } else {
        log::add('nhc', 'error', 'Installation des dépendances incomplète');
    }
    
    return $install_success;
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function nhc_update() {
    // Appeler la fonction d'installation pour s'assurer que les dépendances sont à jour
    nhc_install();
}

// Fonction exécutée automatiquement après la suppression du plugin
function nhc_remove() {
    // Arrêt du démon avant suppression
    if (class_exists('nhc')) {
        nhc::deamon_stop();
    }
    log::add('nhc', 'info', 'Plugin nhc supprimé');
}
