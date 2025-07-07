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

try {
    require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

    if (!jeedom::apiAccess(init('apikey'), 'nhc')) {
        echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
        die();
    }
    
    if (init('test') != '') {
        echo 'OK';
        die();
    }
    
    $result = json_decode(file_get_contents("php://input"), true);
    if (!is_array($result)) {
        die();
    }
    
    log::add('nhc', 'debug', 'Message reçu du démon: ' . print_r($result, true));
    
    if (isset($result['action'])) {
        switch ($result['action']) {
            case 'device_update':
                // Mise à jour d'un équipement
                if (isset($result['device_id']) && isset($result['state'])) {
                    $eqLogic = eqLogic::byLogicalId($result['device_id'], 'nhc');
                    if (is_object($eqLogic)) {
                        $cmd = $eqLogic->getCmd(null, 'state');
                        if (is_object($cmd)) {
                            $cmd->execCmd(array('value' => $result['state']));
                        }
                    }
                }
                break;
                
            case 'device_discovered':
                // Découverte d'un nouvel équipement
                if (isset($result['device_id']) && isset($result['name'])) {
                    log::add('nhc', 'info', 'Nouvel équipement découvert: ' . $result['name'] . ' (ID: ' . $result['device_id'] . ')');
                    // Ici vous pouvez ajouter la logique pour créer automatiquement l'équipement
                }
                break;
                
            case 'connection_status':
                // Statut de la connexion
                if (isset($result['status'])) {
                    log::add('nhc', 'info', 'Statut de connexion: ' . $result['status']);
                }
                break;
                
            default:
                log::add('nhc', 'debug', 'Action non gérée: ' . $result['action']);
                break;
        }
    } else {
        log::add('nhc', 'debug', 'Message reçu du démon sans action définie');
    }
    
} catch (Exception $e) {
    log::add('nhc', 'error', displayException($e));
}
?>
