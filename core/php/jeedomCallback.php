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
    require_once dirname(__FILE__) . '/nhc.inc.php';

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
    
    // Masquage des champs sensibles avant log
    $result_log = $result;
    foreach(['apikey','token','jwt','niko_jwt'] as $sensitive) {
        if (isset($result_log[$sensitive])) {
            $result_log[$sensitive] = '[masqué]';
        }
    }
    log::add('nhc', 'debug', 'Message reçu du démon: ' . print_r($result_log, true));
    
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
                // Traitement des messages MQTT pour mise à jour temps réel des équipements Jeedom
                if (isset($result['topic']) && isset($result['data'])) {
                    // Délègue le traitement du message MQTT à la classe nhc
                    nhc::handleMqttMessage($result['topic'], $result['data']);
                } else {
                    // Log d'avertissement si le message MQTT est incomplet
                    log::add('nhc', 'warning', 'mqtt_message reçu sans topic ou data');
                }
                break;
                
            case 'discover_devices_response':
                // Réponse à la découverte d'équipements (liste complète)
                $transaction_id = isset($result['transaction_id']) ? $result['transaction_id'] : null;
                $error = isset($result['error']) ? $result['error'] : null;

                if ($error) {
                    log::add('nhc', 'error', 'Erreur de découverte reçue du démon: ' . $error);
                    if ($transaction_id) {
                        $response_data = json_encode(['status' => 'error', 'message' => $error]);
                        cache::set('nhc_discovery_result_' . $transaction_id, $response_data, 60);
                    }
                    break;
                }

                if (isset($result['devices']) && is_array($result['devices'])) {
                    log::add('nhc', 'info', 'Liste des équipements découverts reçue : ' . count($result['devices']) . ' équipements');
                    $created_count = 0;
                    $updated_count = 0;
                    foreach ($result['devices'] as $device) {
                        // Récupération du UUID ou ID de l'équipement
                        $uuid = isset($device['uuid']) ? $device['uuid'] : (isset($device['id']) ? $device['id'] : null);
                        if ($uuid === null) {
                            // Log d'erreur si l'UUID est manquant
                            log::add('nhc', 'error', 'UUID manquant pour un équipement : ' . print_r($device, true));
                            continue;
                        }
                        // Récupération du type d'équipement
                        $type = isset($device['type']) ? $device['type'] : '';
                        // Filtrage des types non supportés
                        if ($type !== 'action') {
                            log::add('nhc', 'debug', 'Type non supporté : ' . $type . ' (UUID: ' . $uuid . ')');
                            continue;
                        }
                        // Création uniquement si le modèle est "rolldownshutter" ou "socket"
                        $model = isset($device['model']) ? $device['model'] : '';
                        if ($model !== 'rolldownshutter' && $model !== 'socket') {
                            log::add('nhc', 'debug', 'Modèle non supporté : ' . $model . ' (UUID: ' . $uuid . ')');
                            continue;
                        }
                        // Recherche de l'équipement existant dans Jeedom
                        $eqLogic = eqLogic::byLogicalId($uuid, 'nhc');
                        if (!is_object($eqLogic)) {
                            // Création d'un nouvel équipement si non existant
                            $eqLogic = new nhc();
                            $eqLogic->setName($device['name']); // Nom affiché dans Jeedom
                            $eqLogic->setLogicalId($uuid); // Identifiant unique
                            $eqLogic->setConfiguration('uuid', $uuid); // Stockage du UUID
                            $eqLogic->setConfiguration('type', $type); // Type d'équipement
                            $eqLogic->setConfiguration('location', isset($device['location']) ? $device['location'] : ''); // Localisation
                            $eqLogic->setConfiguration('raw_data', isset($device['raw_data']) ? $device['raw_data'] : array()); // Données brutes
                            $eqLogic->setConfiguration('lastCommunication',date('Y-m-d H:i:s'));

                            $eqLogic->setEqType_name('nhc'); // Type Jeedom
                            $eqLogic->setIsEnable(1); // Activation
                            $eqLogic->setIsVisible(1); // Visibilité

                            // Si smartmotor, configurer comme volet
                            // if ($model === 'rolldownshutter') {
                            //     $eqLogic->setConfiguration('jeedom_type', 'volet');
                            // }
                            try {
                                // Sauvegarde de l'équipement dans Jeedom
                                $eqLogic->save();
                                log::add('nhc', 'info', 'Création nouvel équipement : ' . $device['name'] . ' (UUID: ' . $uuid . ')');
                                $created_count++;
                                // Ajout des commandes pour les volets si smartmotor ou rolldownshutter
                                if ($type === 'action' && $model === 'rolldownshutter') {
                                    nhc::addVoletCommands($eqLogic);
                                }
                                // TODO : ajouter les commandes pour les prises connectées
                            } catch (Exception $e) {
                                log::add('nhc', 'error', 'Erreur lors de la sauvegarde de l\'équipement : ' . $e->getMessage());
                                continue;
                            }
                        }
                        else{
                            log::add('nhc', 'info', 'Équipement déjà existant : ' . $device['name'] . ' (UUID: ' . $uuid . ')');
                            // TODO : mise à jours de l'équipement si nécessaire
                            $updated_count++;
                        }
                    }
                    if ($transaction_id) {
                        $message = sprintf(
                            __('%s équipements créés, %s équipements déjà existants. La page va maintenant se rafraîchir.', __FILE__),
                            $created_count,
                            $updated_count
                        );
                        $response_data = json_encode(['status' => 'ok', 'message' => $message]);
                        log::add('nhc', 'debug', 'Stockage du résultat de la découverte dans le cache pour ' . $transaction_id);
                        cache::set('nhc_discovery_result_' . $transaction_id, $response_data, 60);
                    }
                } else {
                    log::add('nhc', 'warning', 'discover_devices_response sans liste d\'équipements');
                    if ($transaction_id) {
                        $message = __('Aucun nouvel équipement compatible trouvé.', __FILE__);
                        $response_data = json_encode(['status' => 'ok', 'message' => $message]);
                        cache::set('nhc_discovery_result_' . $transaction_id, $response_data, 60);
                    }
                }
                log::add('nhc', 'debug', 'Fin du traitement discover_devices_response');
                break;
            default:
                log::add('nhc', 'debug', 'Action non gérée: ' . $result['action']);
                break;
        }
    } else {
        log::add('nhc', 'debug', 'Message reçu du démon sans action définie');
    }
    
} catch (Throwable $e) {
    log::add('nhc', 'error', displayException($e));
}
?>
