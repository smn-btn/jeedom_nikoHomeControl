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
                
            case 'mqtt_message':
                // Traitement des messages MQTT pour mise à jour temps réel
                if (isset($result['topic']) && isset($result['data'])) {
                    require_once dirname(__FILE__) . '/../class/nhc.class.php';
                    nhc::handleMqttMessage($result['topic'], $result['data']);
                } else {
                    log::add('nhc', 'warning', 'mqtt_message reçu sans topic ou data');
                }
                break;
                
            case 'discover_devices_response':
                // Réponse à la découverte d'équipements (liste complète)
                if (isset($result['devices']) && is_array($result['devices'])) {
                    log::add('nhc', 'info', 'Liste des équipements découverts reçue : ' . count($result['devices']) . ' équipements');
                    require_once dirname(__FILE__) . '/../class/nhc.class.php';
                    foreach ($result['devices'] as $device) {
                        $uuid = isset($device['uuid']) ? $device['uuid'] : (isset($device['id']) ? $device['id'] : null);
                        if ($uuid === null) {
                            log::add('nhc', 'error', 'UUID manquant pour un équipement : ' . print_r($device, true));
                            continue;
                        }
                        $type = isset($device['type']) ? $device['type'] : '';
                        if (!in_array($type, array('smartmotor', 'energyhome'))) {
                            log::add('nhc', 'debug', 'Type non supporté : ' . $type . ' (UUID: ' . $uuid . ')');
                            continue;
                        }
                        // Correction de la méthode (typo possible)
                        if (method_exists('eqLogic', 'byTypeAndSearchConfiguration')) {
                            $eqLogic = eqLogic::byTypeAndSearchConfiguration('nhc', 'uuid', $uuid, '1');
                        } elseif (method_exists('eqLogic', 'byTypeAndSearhConfiguration')) { // fallback typo
                            $eqLogic = eqLogic::byTypeAndSearhConfiguration('nhc', 'uuid', $uuid, '1');
                        } else {
                            log::add('nhc', 'error', 'Méthode eqLogic::byTypeAndSearchConfiguration introuvable');
                            continue;
                        }
                        if (is_array($eqLogic) && count($eqLogic) > 0 && is_object($eqLogic[0])) {
                            $eqLogic = $eqLogic[0];
                            log::add('nhc', 'info', 'Mise à jour équipement existant : ' . $device['name'] . ' (UUID: ' . $uuid . ')');
                        } else {
                            if (!class_exists('nhc')) {
                                log::add('nhc', 'error', 'Classe nhc non trouvée');
                                continue;
                            }
                            $eqLogic = new nhc();
                            $eqLogic->setName($device['name']);
                            $eqLogic->setLogicalId($uuid);
                            $eqLogic->setConfiguration('uuid', $uuid);
                            $eqLogic->setConfiguration('type', $type);
                            $eqLogic->setConfiguration('location', isset($device['location']) ? $device['location'] : '');
                            $eqLogic->setConfiguration('raw_data', isset($device['raw_data']) ? $device['raw_data'] : array());
                            $eqLogic->setEqType_name('nhc');
                            $eqLogic->setIsEnable(1);
                            $eqLogic->setIsVisible(1);
                            try {
                                $eqLogic->save();
                                log::add('nhc', 'info', 'Création nouvel équipement : ' . $device['name'] . ' (UUID: ' . $uuid . ')');
                            } catch (Exception $e) {
                                log::add('nhc', 'error', 'Erreur lors de la sauvegarde de l\'équipement : ' . $e->getMessage());
                                continue;
                            }
                        }
                        // Mise à jour des propriétés spécifiques
                        if ($type == 'energyhome' && isset($device['properties']) && is_array($device['properties'])) {
                            foreach ($device['properties'] as $prop) {
                                foreach ($prop as $key => $value) {
                                    if (!class_exists('nhcCmd')) {
                                        log::add('nhc', 'error', 'Classe nhcCmd non trouvée');
                                        continue;
                                    }
                                    $cmd = $eqLogic->getCmd(null, strtolower($key));
                                    if (!is_object($cmd)) {
                                        $cmd = new nhcCmd();
                                        $cmd->setName($key);
                                        $cmd->setEqLogic_id($eqLogic->getId());
                                        $cmd->setLogicalId(strtolower($key));
                                        $cmd->setType('info');
                                        $cmd->setSubType('numeric');
                                        $cmd->setIsVisible(1);
                                        $cmd->setEventOnly(1);
                                        try {
                                            $cmd->save();
                                        } catch (Exception $e) {
                                            log::add('nhc', 'error', 'Erreur lors de la sauvegarde de la commande : ' . $e->getMessage());
                                            continue;
                                        }
                                    }
                                    try {
                                        $cmd->event($value);
                                    } catch (Exception $e) {
                                        log::add('nhc', 'error', 'Erreur lors de l\'enregistrement de la valeur de commande : ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }
                } else {
                    log::add('nhc', 'warning', 'discover_devices_response sans liste d\'équipements');
                }
                log::add('nhc', 'debug', 'Fin du traitement discover_devices_response');
                // Fin du traitement : renvoyer une réponse JSON
                // Correction : renvoyer la structure attendue par Jeedom

                echo json_encode([
                    'action' => 'discover_devices_response',
                    'devices' => isset($result['devices']) ? $result['devices'] : [],
                    'count' => isset($result['count']) ? $result['count'] : (isset($result['devices']) ? count($result['devices']) : 0)
                ]);
                exit;
                
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
