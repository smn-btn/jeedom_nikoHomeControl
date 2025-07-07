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
    log::add('nhc', 'debug', '=== DÉBUT deamon_info() ===');
    
    $return = array();
    $return['log'] = 'nhc';
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder('nhc') . '/demond.pid';
    log::add('nhc', 'debug', 'Fichier PID: ' . $pid_file);
    
    if (file_exists($pid_file)) {
      log::add('nhc', 'debug', 'Fichier PID trouvé');
      if (posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
        log::add('nhc', 'debug', 'Démon actif');
      } else {
        log::add('nhc', 'debug', 'Démon inactif, suppression fichier PID');
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
      }
    } else {
      log::add('nhc', 'debug', 'Fichier PID non trouvé');
    }
    
    // Vérifier si le démon peut être lancé (configuration)
    $return['launchable'] = 'ok';
    $return['launchable_message'] = '';
    
    // Vérifier configuration Niko Home Control
    $niko_ip = config::byKey('niko_ip', 'nhc', '');
    $niko_jwt = config::byKey('niko_jwt', 'nhc', '');
    
    if (empty($niko_ip)) {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = 'L\'adresse IP Niko Home Control n\'est pas configurée';
      log::add('nhc', 'error', 'ÉCHEC: ' . $return['launchable_message']);
    } elseif (empty($niko_jwt)) {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = 'Le token JWT Niko Home Control n\'est pas configuré';
      log::add('nhc', 'error', 'ÉCHEC: ' . $return['launchable_message']);
    }
    
    if ($return['launchable'] == 'ok') {
      log::add('nhc', 'info', 'SUCCÈS: Configuration OK, démon peut être lancé');
    }
    
    log::add('nhc', 'debug', '=== FIN deamon_info() ===');
    
    return $return;
  }

  /*
  * Permet de lancer le démon
  */
  public static function deamon_start() {
    log::add('nhc', 'debug', '=== DÉBUT deamon_start() ===');
    
    log::add('nhc', 'debug', 'Arrêt du démon existant...');
    self::deamon_stop();
    
    log::add('nhc', 'debug', 'Vérification des dépendances...');
    $deamon_info = self::deamon_info();
    log::add('nhc', 'debug', 'Résultat vérification: launchable=' . $deamon_info['launchable'] . ', message=' . $deamon_info['launchable_message']);
    
    if ($deamon_info['launchable'] != 'ok') {
      log::add('nhc', 'error', 'ÉCHEC démarrage: dépendances non satisfaites');
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    
    $path = realpath(dirname(__FILE__) . '/../../resources/demond');
    log::add('nhc', 'debug', 'Répertoire démon: ' . $path);
    
    $python_venv_path = dirname(__FILE__) . '/../../resources/venv/bin/python3';
    $python_venv = file_exists($python_venv_path) ? $python_venv_path : false;
    log::add('nhc', 'debug', 'Chemin venv: ' . $python_venv_path);
    log::add('nhc', 'debug', 'Venv existe: ' . ($python_venv ? 'OUI' : 'NON'));
    
    if ($python_venv) {
      $python_venv_real = realpath($python_venv_path);
      log::add('nhc', 'debug', 'Venv pointe vers: ' . ($python_venv_real ? $python_venv_real : 'ERREUR'));
    }
    
    // Utiliser l'environnement virtuel Python si disponible, sinon python3 système
    $python_cmd = $python_venv ? $python_venv : 'python3';
    log::add('nhc', 'debug', 'Python choisi: ' . $python_cmd . ($python_venv ? ' (venv)' : ' (système)'));
    
    $cmd = 'cd ' . $path . ' && ' . $python_cmd . ' demond.py';
    log::add('nhc', 'debug', 'Commande de base: ' . $cmd);
    
    $loglevel = log::convertLogLevel(log::getLogLevel('nhc'));
    $cmd .= ' --loglevel ' . $loglevel;
    log::add('nhc', 'debug', 'Log level: ' . $loglevel);
    
    $socketport = config::byKey('socketport', 'nhc', '55001');
    $cmd .= ' --socketport ' . $socketport;
    log::add('nhc', 'debug', 'Socket port: ' . $socketport);
    
    $callback = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/nhc/core/php/jeedomCallback.php';
    $cmd .= ' --callback ' . $callback;
    log::add('nhc', 'debug', 'Callback: ' . $callback);
    
    $apikey = jeedom::getApiKey('nhc');
    $cmd .= ' --apikey ' . $apikey;
    log::add('nhc', 'debug', 'API key: ' . substr($apikey, 0, 10) . '...');
    
    $pid_file = jeedom::getTmpFolder('nhc') . '/demond.pid';
    $cmd .= ' --pid ' . $pid_file;
    log::add('nhc', 'debug', 'PID file: ' . $pid_file);
    
    // Paramètres spécifiques à Niko Home Control
    $niko_ip = config::byKey('niko_ip', 'nhc', '');
    $cmd .= ' --niko_ip ' . $niko_ip;
    log::add('nhc', 'debug', 'Niko IP: ' . ($niko_ip ? $niko_ip : 'NON DÉFINI'));
    
    $niko_jwt = config::byKey('niko_jwt', 'nhc', '');
    $cmd .= ' --niko_jwt ' . $niko_jwt;
    log::add('nhc', 'debug', 'Niko JWT: ' . ($niko_jwt ? substr($niko_jwt, 0, 20) . '...' : 'NON DÉFINI'));
    
    log::add('nhc', 'info', 'Lancement démon nhc : ' . $cmd);
    
    $full_cmd = $cmd . ' >> ' . log::getPathToLog('nhc') . ' 2>&1 &';
    log::add('nhc', 'debug', 'Exécution de la commande...');
    exec($full_cmd);
    
    log::add('nhc', 'debug', 'Attente démarrage démon (max 30s)...');
    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      log::add('nhc', 'debug', 'Tentative ' . ($i + 1) . '/30 - État: ' . $deamon_info['state']);
      
      if ($deamon_info['state'] == 'ok') {
        log::add('nhc', 'info', 'Démon démarré avec succès après ' . ($i + 1) . ' secondes');
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('nhc', 'error', __('Impossible de lancer le démon nhc, vérifiez le log', __FILE__), 'unableStartDeamon');
      log::add('nhc', 'error', 'ÉCHEC: Démon non démarré après 30 secondes');
      return false;
    }
    message::removeAll('nhc', 'unableStartDeamon');
    log::add('nhc', 'debug', '=== FIN deamon_start() - SUCCÈS ===');
    return true;
  }

  /*
  * Permet d'arrêter le démon
  */
  public static function deamon_stop() {
    log::add('nhc', 'debug', '=== DÉBUT deamon_stop() ===');
    
    $pid_file = jeedom::getTmpFolder('nhc') . '/demond.pid';
    log::add('nhc', 'debug', 'Fichier PID: ' . $pid_file);
    
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      log::add('nhc', 'debug', 'PID trouvé: ' . $pid . ', arrêt...');
      system::kill($pid);
    } else {
      log::add('nhc', 'debug', 'Fichier PID non trouvé');
    }
    
    log::add('nhc', 'debug', 'Arrêt de tous les processus demond.py...');
    system::kill('demond.py');
    
    $socketport = config::byKey('socketport', 'nhc', '55001');
    log::add('nhc', 'debug', 'Libération du port ' . $socketport . '...');
    system::fuserk($socketport);
    
    log::add('nhc', 'debug', 'Attente 1 seconde...');
    sleep(1);
    
    log::add('nhc', 'debug', '=== FIN deamon_stop() ===');
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

  /*
  * Permet de récupérer les informations sur les dépendances
  */
  public static function dependancy_info() {
    log::add('nhc', 'debug', '=== DÉBUT dependancy_info() ===');
    
    $return = array();
    $return['log'] = log::getPathToLog('nhc_update');
    $return['progress_file'] = jeedom::getTmpFolder('nhc') . '/dependance';
    
    // Vérifier si l'installation est en cours
    if (file_exists($return['progress_file'])) {
      log::add('nhc', 'debug', 'Installation en cours');
      $return['state'] = 'in_progress';
      log::add('nhc', 'debug', '=== FIN dependancy_info() - EN COURS ===');
      return $return;
    }
    
    // Vérifier si les dépendances Python sont installées
    log::add('nhc', 'debug', 'Vérification dépendances Python...');
    
    // Déterminer quel Python utiliser (environnement virtuel ou système)
    $python_venv_path = dirname(__FILE__) . '/../../resources/venv/bin/python3';
    $python_venv = file_exists($python_venv_path) ? $python_venv_path : false;
    log::add('nhc', 'debug', 'Chemin venv: ' . $python_venv_path);
    log::add('nhc', 'debug', 'Venv existe: ' . ($python_venv ? 'OUI' : 'NON'));
    
    if ($python_venv) {
      $python_venv_real = realpath($python_venv_path);
      log::add('nhc', 'debug', 'Venv pointe vers: ' . ($python_venv_real ? $python_venv_real : 'ERREUR'));
    }
    
    $python_cmd = $python_venv ? $python_venv : 'python3';
    log::add('nhc', 'debug', 'Python choisi: ' . $python_cmd);
    
    // Vérifier la présence de Python
    if ($python_cmd === 'python3') {
      log::add('nhc', 'debug', 'Utilisation Python système');
      $python_check = shell_exec('which python3 2>/dev/null');
      log::add('nhc', 'debug', 'which python3: ' . ($python_check ? trim($python_check) : 'VIDE'));
      
      if (empty(trim($python_check))) {
        log::add('nhc', 'error', 'ÉCHEC: Python3 non trouvé sur le système');
        $return['state'] = 'nok';
        log::add('nhc', 'debug', '=== FIN dependancy_info() - ÉCHEC ===');
        return $return;
      }
    } else {
      log::add('nhc', 'debug', 'Utilisation environnement virtuel');
      if (!file_exists($python_cmd)) {
        log::add('nhc', 'error', 'ÉCHEC: Environnement virtuel Python non trouvé: ' . $python_cmd);
        $return['state'] = 'nok';
        log::add('nhc', 'debug', '=== FIN dependancy_info() - ÉCHEC ===');
        return $return;
      }
      log::add('nhc', 'debug', 'Environnement virtuel trouvé');
    }
    
    // Vérifier version Python
    $python_version = shell_exec($python_cmd . ' --version 2>&1');
    log::add('nhc', 'debug', 'Version Python: ' . trim($python_version));
    
    // Vérifier la présence du module paho-mqtt
    log::add('nhc', 'debug', 'Test des modules Python avec script de validation...');
    $validation_script = dirname(__FILE__) . '/../../resources/validate_dependencies.sh';
    $validation_result = shell_exec($validation_script . ' 2>&1');
    log::add('nhc', 'debug', 'Résultat validation: ' . trim($validation_result));
    
    if (trim($validation_result) !== 'OK') {
      log::add('nhc', 'error', 'ÉCHEC: Dépendances Python manquantes ou incorrectes');
      $return['state'] = 'nok';
      log::add('nhc', 'debug', '=== FIN dependancy_info() - ÉCHEC ===');
      return $return;
    }
    
    $return['state'] = 'ok';
    log::add('nhc', 'info', 'SUCCÈS: Toutes les dépendances sont OK');
    log::add('nhc', 'debug', '=== FIN dependancy_info() - RÉSULTAT: OK ===');
    
    return $return;
  }

  /*
  * Permet d'installer les dépendances
  */
  public static function dependancy_install() {
    log::remove('nhc_update');
    return array(
      'script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('nhc') . '/dependance', 
      'log' => log::getPathToLog('nhc_update')
    );
  }
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
