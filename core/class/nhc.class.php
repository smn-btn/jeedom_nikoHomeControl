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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class nhc extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */

  /*
  * Permet de récupérer les informations sur le démon
  */
  public static function deamon_info() {
    $return = array();
    $return['log'] = 'nhc';
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder('nhc') . '/demond.pid';
    if (file_exists($pid_file)) {
      if (posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
      }
    }
    
    // Vérifier si les dépendances Python sont installées
    $return['launchable'] = 'nok';
    $return['launchable_message'] = '';
    
    // Vérifier la présence de Python3
    $python_check = shell_exec('which python3 2>/dev/null');
    if (empty(trim($python_check))) {
      $return['launchable_message'] = 'Python3 non trouvé';
      return $return;
    }
    
    // Vérifier la présence du module paho-mqtt
    $mqtt_check = shell_exec('python3 -c "import paho.mqtt.client" 2>&1');
    if (!empty(trim($mqtt_check))) {
      $return['launchable_message'] = 'Module paho-mqtt manquant. Installez avec: sudo apt install python3-paho-mqtt';
      return $return;
    }
    
    // Vérifier la présence du module requests
    $requests_check = shell_exec('python3 -c "import requests" 2>&1');
    if (!empty(trim($requests_check))) {
      $return['launchable_message'] = 'Module requests manquant. Installez avec: sudo apt install python3-requests';
      return $return;
    }
    
    // Vérifier la présence du fichier démon
    $demond_path = realpath(dirname(__FILE__) . '/../../resources/demond/demond.py');
    if (!file_exists($demond_path)) {
      $return['launchable_message'] = 'Fichier démon manquant: ' . $demond_path;
      return $return;
    }
    
    $return['launchable'] = 'ok';
    
    return $return;
  }

  /*
  * Permet de lancer le démon
  */
  public static function deamon_start() {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    
    $path = realpath(dirname(__FILE__) . '/../../resources/demond');
    $cmd = 'cd ' . $path . ' && python3 demond.py';
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('nhc'));
    $cmd .= ' --socketport ' . config::byKey('socketport', 'nhc', '55001');
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/nhc/core/php/jeedomCallback.php';
    $cmd .= ' --apikey ' . jeedom::getApiKey('nhc');
    $cmd .= ' --pid ' . jeedom::getTmpFolder('nhc') . '/demond.pid';
    log::add('nhc', 'info', 'Lancement démon nhc : ' . $cmd);
    exec($cmd . ' >> ' . log::getPathToLog('nhc') . ' 2>&1 &');
    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('nhc', 'error', __('Impossible de lancer le démon nhc, vérifiez le log', __FILE__), 'unableStartDeamon');
      return false;
    }
    message::removeAll('nhc', 'unableStartDeamon');
    return true;
  }

  /*
  * Permet d'arrêter le démon
  */
  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder('nhc') . '/demond.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      system::kill($pid);
    }
    system::kill('demond.py');
    system::fuserk(config::byKey('socketport', 'nhc', '55001'));
    sleep(1);
  }
  
  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*
   * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
   * lors de la création semi-automatique d'un post sur le forum community
   public static function getConfigForCommunity() {
      // Cette function doit retourner des infos complémentataires sous la forme d'un
      // string contenant les infos formatées en HTML.
      return "les infos essentiel de mon plugin";
   }
   */

  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*     * **********************Getteur Setteur*************************** */
}

class nhcCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
  }

  /*     * **********************Getteur Setteur*************************** */
}
