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
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
    En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
    En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */
    ajax::init();

    if (init('action') == 'testConnection') {
        $niko_ip = init('niko_ip');
        $niko_jwt = init('niko_jwt');
        
        if (empty($niko_ip)) {
            throw new Exception(__('Adresse IP manquante', __FILE__));
        }
        
        if (empty($niko_jwt)) {
            throw new Exception(__('Token JWT manquant', __FILE__));
        }
        
        // Test de connexion à la passerelle Niko
        $test_result = nhc::testNikoConnection($niko_ip, $niko_jwt);
        ajax::success($test_result);
    }

    if (init('action') == 'discoverDevices') {
        // Déclencher la découverte d'équipements via le démon
        $result = nhc::discoverDevices();
        ajax::success($result);
    }

    if (init('action') == 'getDaemonInfo') {
        $info = nhc::deamon_info();
        ajax::success($info);
    }

    if (init('action') == 'sendCommand') {
        $device_id = init('device_id');
        $command = init('command');
        $value = init('value');
        
        $result = nhc::sendCommand($device_id, $command, $value);
        ajax::success($result);
    }



    throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
    /*     * *********Catch exeption*************** */
}
catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
