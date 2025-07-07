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
    
    $modules = array(
        'paho-mqtt' => 'python3-paho-mqtt',
        'requests' => 'python3-requests'
    );
    
    foreach ($modules as $pip_name => $apt_name) {
        // Vérifier si le module est déjà installé
        $check_cmd = "python3 -c \"import " . str_replace('-', '.', $pip_name) . "\" 2>/dev/null";
        exec($check_cmd, $output, $return_var);
        
        if ($return_var === 0) {
            log::add('nhc', 'info', "Module $pip_name déjà installé");
            continue;
        }
        
        // Essayer d'installer via apt
        log::add('nhc', 'info', "Installation de $apt_name via apt...");
        $cmd = "sudo apt update && sudo apt install -y $apt_name";
        exec($cmd . ' 2>&1', $output, $return_var);
        
        if ($return_var !== 0) {
            // Si apt échoue, essayer pip avec --break-system-packages
            log::add('nhc', 'warning', "Installation via apt échouée pour $apt_name, essai avec pip...");
            $cmd = "pip3 install --break-system-packages $pip_name";
            exec($cmd . ' 2>&1', $output, $return_var);
            
            if ($return_var !== 0) {
                log::add('nhc', 'error', "Erreur lors de l'installation de $pip_name: " . implode('\n', $output));
            } else {
                log::add('nhc', 'info', "Module $pip_name installé avec succès via pip");
            }
        } else {
            log::add('nhc', 'info', "Module $apt_name installé avec succès via apt");
        }
    }
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
