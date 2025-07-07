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
    logging.info("Démarrage du client MQTT...")
    
    # Création d'un client MQTT
    # Le client_id doit être unique, on en génère un aléatoire.
    client = mqtt.Client(client_id="jeedom_nhc_plugin_" + str(os.getpid()))

    # Assignation des fonctions de callback
    client.on_connect = on_connect
    client.on_message = on_message

    # Configuration de l'authentification
    # Le mot de passe est le jeton JWT. [cite_start]Le nom d'utilisateur est fourni par Niko[cite: 137, 138].
    logging.info("Configuration de l'authentification avec l'utilisateur 'hobby'")
    client.username_pw_set("hobby", password=_niko_jwt)
    
    # Configuration de la connexion sécurisée (SSL/TLS)
    # La passerelle Niko utilise un port sécurisé.
    logging.info("Configuration de la connexion sécurisée (TLS)")
    # [cite_start]Note : pour une sécurité maximale, il faudrait utiliser le certificat CA fourni par Niko [cite: 93]
    # Pour commencer, nous ignorons la vérification du certificat, ce qui est plus simple.
    client.tls_set(cert_reqs=ssl.CERT_NONE)
    client.tls_insecure_set(True)

    try:
        logging.info("Tentative de connexion à %s sur le port 8884...", _niko_ip)
        client.connect(_niko_ip, 8884, 60)
        # client.loop_forever() est bloquant, il garde le script en vie
        # et gère la reconnexion automatiquement.
        client.loop_forever()
    except Exception as e:
        logging.error("Erreur fatale lors de la connexion ou dans la boucle MQTT: %s", e)
        shutdown()

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

# Vérification des paramètres essentiels
if not _niko_ip or not _niko_jwt:
    logging.error("L'adresse IP de la passerelle Niko ou le Jeton JWT ne sont pas configurés. Arrêt du démon.")
    exit()
    
logging.info('Démarrage du démon nhc')
logging.info('Log level : %s', _log_level)
logging.info('PID file : %s', _pidfile)
logging.info('IP Passerelle : %s', _niko_ip)

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