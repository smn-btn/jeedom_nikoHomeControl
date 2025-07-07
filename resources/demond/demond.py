# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import logging
import sys
import os
import time
import signal
import json
import argparse
import paho.mqtt.client as mqtt
import ssl

# Cette partie charge les outils Jeedom pour Python
from jeedom.jeedom import jeedom_socket, jeedom_utils, jeedom_com, JEEDOM_SOCKET_MESSAGE

# Variables globales pour la communication
jeedom_socket_instance = None
jeedom_com_instance = None


# --- FONCTIONS DE GESTION MQTT ---

# Cette fonction est appelée quand le client MQTT réussit à se connecter
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        logging.info("Connexion au broker MQTT de Niko Home Control réussie !")
        # Une fois connecté, on s'abonne aux sujets qui nous intéressent
        # Le topic 'hobby/control/devices/evt' nous donnera les changements d'état en temps réel
        logging.info("Abonnement au topic: hobby/control/devices/evt/#")
        client.subscribe("hobby/control/devices/evt/#")
        # Le topic 'hobby/control/devices/rsp' nous donnera les réponses à nos commandes
        logging.info("Abonnement au topic: hobby/control/devices/rsp/#")
        client.subscribe("hobby/control/devices/rsp/#")
    else:
        logging.error("Échec de la connexion MQTT, code de retour : %s", rc)

# Cette fonction est appelée chaque fois qu'un message est reçu sur un sujet auquel on est abonné
def on_message(client, userdata, msg):
    logging.info("Message reçu sur le topic [%s]: %s", msg.topic, msg.payload.decode())
    # TODO: Ici, nous traiterons plus tard les messages pour mettre à jour Jeedom

# --- FONCTION PRINCIPALE DU DÉMON ---

def listen():
    logging.info("Démarrage du démon nhc...")
    
    # Initialisation de la communication avec Jeedom
    global jeedom_socket_instance, jeedom_com_instance
    
    try:
        # Démarrage du socket Jeedom pour recevoir les commandes
        jeedom_socket_instance = jeedom_socket(port=_socketport, address='localhost')
        jeedom_socket_instance.open()
        
        # Démarrage de la communication avec Jeedom
        jeedom_com_instance = jeedom_com(apikey=_apikey, url=_callback, cycle=0.5)
        if not jeedom_com_instance.test():
            logging.error('Problème de communication réseau. Veuillez vérifier votre configuration réseau Jeedom.')
            shutdown()
            return
            
        logging.info("Communication avec Jeedom initialisée")
        
        # Démarrage du client MQTT si configuration disponible
        if _niko_ip and _niko_jwt:
            start_mqtt_client()
        else:
            logging.warning("Configuration Niko manquante, fonctionnement en mode dégradé")
            
        # Boucle principale du démon
        while True:
            time.sleep(0.5)
            read_socket()
            
    except KeyboardInterrupt:
        logging.info("Arrêt demandé par l'utilisateur")
        shutdown()
    except Exception as e:
        logging.error("Erreur fatale: %s", e)
        shutdown()

def start_mqtt_client():
    """Démarre le client MQTT en arrière-plan"""
    logging.info("Démarrage du client MQTT...")
    
    # Création d'un client MQTT
    client = mqtt.Client(client_id="jeedom_nhc_plugin_" + str(os.getpid()))

    # Assignation des fonctions de callback
    client.on_connect = on_connect
    client.on_message = on_message

    # Configuration de l'authentification
    logging.info("Configuration de l'authentification avec l'utilisateur 'hobby'")
    client.username_pw_set("hobby", password=_niko_jwt)
    
    # Configuration de la connexion sécurisée (SSL/TLS)
    logging.info("Configuration de la connexion sécurisée (TLS)")
    client.tls_set(cert_reqs=ssl.CERT_NONE)
    client.tls_insecure_set(True)

    try:
        logging.info("Tentative de connexion à %s sur le port 8884...", _niko_ip)
        client.connect(_niko_ip, 8884, 60)
        # Utilisation du mode non-bloquant
        client.loop_start()
        logging.info("Client MQTT démarré en arrière-plan")
    except Exception as e:
        logging.error("Erreur lors de la connexion MQTT: %s", e)
        logging.warning("Continuons en mode dégradé sans MQTT")

def read_socket():
    """Lit les messages du socket Jeedom"""
    try:
        if not JEEDOM_SOCKET_MESSAGE.empty():
            logging.debug("Message reçu du socket Jeedom")
            message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
            if message['apikey'] != _apikey:
                logging.error("Clé API invalide reçue du socket: %s", message)
                return
            
            # Traitement du message
            if 'action' in message:
                handle_jeedom_command(message)
            else:
                logging.debug("Message sans action: %s", message)
                
    except Exception as e:
        logging.error('Erreur lors de la lecture du socket: %s', e)

def handle_jeedom_command(message):
    """Traite les commandes reçues de Jeedom"""
    action = message.get('action')
    logging.debug("Commande reçue: %s", action)
    
    if action == 'test':
        logging.info("Test de communication reçu")
        # Répondre à Jeedom
        jeedom_com_instance.send_change_immediate({'action': 'test_response', 'status': 'ok'})
    elif action == 'refresh':
        logging.info("Demande de rafraîchissement reçue")
        # Ici on pourrait déclencher une synchronisation
    else:
        logging.warning("Action non reconnue: %s", action)

# --- GESTION DU DÉMON (Code standard Jeedom) ---

def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting...", int(signum))
    shutdown()

def shutdown():
    logging.info("Arrêt du démon...")
    logging.debug("Removing PID file %s", _pidfile)
    try:
        os.remove(_pidfile)
    except Exception as e:
        logging.warning('Error removing PID file: %s', e)
        
    # Fermeture du socket Jeedom
    try:
        if jeedom_socket_instance:
            jeedom_socket_instance.close()
    except Exception as e:
        logging.warning('Error closing socket: %s', e)
        
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)

# Parsing des arguments passés par Jeedom au démon
parser = argparse.ArgumentParser(description='Démon pour le plugin Niko Home Control')
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
parser.add_argument("--socketport", help="Socket Port", type=int)
parser.add_argument("--niko_ip", help="Niko Gateway IP", type=str)
parser.add_argument("--niko_jwt", help="Niko JWT Token", type=str)
args = parser.parse_args()

# Configuration initiale
_pidfile = '/tmp/nhc_demond.pid'
if args.pid:
    _pidfile = args.pid

_log_level = "error"
if args.loglevel:
    _log_level = args.loglevel

_apikey = ""
if args.apikey:
    _apikey = args.apikey
    
_callback = ""
if args.callback:
    _callback = args.callback

_socketport = 55001
if args.socketport:
    _socketport = args.socketport

# On récupère l'IP et le Jeton passés en argument
_niko_ip = ""
if args.niko_ip:
    _niko_ip = args.niko_ip

_niko_jwt = ""
if args.niko_jwt:
    _niko_jwt = args.niko_jwt

# Configuration des logs
jeedom_utils.set_log_level(_log_level)

logging.info('Démarrage du démon nhc')
logging.info('Log level : %s', _log_level)
logging.info('PID file : %s', _pidfile)
logging.info('Socket port : %s', _socketport)
logging.info('Callback : %s', _callback)
logging.info('IP Passerelle : %s', _niko_ip if _niko_ip else 'Non configurée')

# Vérification des paramètres essentiels pour Jeedom
if not _apikey:
    logging.error("Clé API manquante. Arrêt du démon.")
    sys.exit(1)

if not _callback:
    logging.error("URL de callback manquante. Arrêt du démon.")
    sys.exit(1)

# Avertissement si la configuration Niko n'est pas complète
if not _niko_ip or not _niko_jwt:
    logging.warning("Configuration Niko incomplète. Le démon fonctionnera en mode dégradé.")

# Gestion des signaux d'arrêt
signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

# Lancement du démon
try:
    jeedom_utils.write_pid(str(_pidfile))
    listen() # On lance notre fonction principale
except Exception as e:
    logging.error('Fatal error: %s', e)
    shutdown()