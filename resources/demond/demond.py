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
import requests
from urllib3.exceptions import InsecureRequestWarning

# Désactiver les warnings SSL pour les certificats auto-signés
requests.packages.urllib3.disable_warnings(InsecureRequestWarning)

# Cette partie charge les outils Jeedom pour Python
from jeedom.jeedom import jeedom_socket, jeedom_utils, jeedom_com, JEEDOM_SOCKET_MESSAGE

# Variables globales pour la communication
jeedom_socket_instance = None
jeedom_com_instance = None
mqtt_client_instance = None

# Codes de retour MQTT pour diagnostic
MQTT_ERROR_CODES = {
    0: "Connexion réussie",
    1: "Version de protocole incorrecte",
    2: "Identifiant client invalide",
    3: "Serveur indisponible",
    4: "Nom d'utilisateur ou mot de passe incorrect",
    5: "Non autorisé"
}


# --- FONCTIONS DE GESTION MQTT ---

def on_connect(client, userdata, flags, rc):
    """Callback appelé lors de la connexion MQTT"""
    error_message = MQTT_ERROR_CODES.get(rc, f"Erreur inconnue ({rc})")
    
    if rc == 0:
        logging.info("✅ Connexion MQTT réussie à Niko Home Control !")
        logging.info("Flags de connexion: %s", flags)
        
        # Abonnement aux topics Niko Home Control
        logging.info("📡 Abonnement aux topics Niko...")
        
        # Events des appareils (changements d'état)
        result1 = client.subscribe("hobby/control/devices/evt/#")
        logging.info("Topic events: hobby/control/devices/evt/# - Résultat: %s", result1)
        
        # Réponses aux commandes
        result2 = client.subscribe("hobby/control/devices/rsp/#")
        logging.info("Topic responses: hobby/control/devices/rsp/# - Résultat: %s", result2)
        
        # Envoyer confirmation à Jeedom
        if jeedom_com_instance:
            try:
                jeedom_com_instance.send_change_immediate({
                    'action': 'mqtt_connected',
                    'status': 'success',
                    'message': 'Connexion MQTT établie'
                })
            except Exception as e:
                logging.warning("Impossible d'envoyer la confirmation MQTT à Jeedom: %s", e)
                
    else:
        logging.error("❌ Échec connexion MQTT: %s", error_message)
        
        # Envoyer l'erreur à Jeedom
        if jeedom_com_instance:
            try:
                jeedom_com_instance.send_change_immediate({
                    'action': 'mqtt_error',
                    'status': 'error',
                    'code': rc,
                    'message': error_message
                })
            except Exception as e:
                logging.warning("Impossible d'envoyer l'erreur MQTT à Jeedom: %s", e)

def on_disconnect(client, userdata, rc):
    """Callback appelé lors de la déconnexion MQTT"""
    if rc != 0:
        logging.warning("🔌 Déconnexion MQTT inattendue (code: %s)", rc)
    else:
        logging.info("🔌 Déconnexion MQTT normale")

def on_subscribe(client, userdata, mid, granted_qos):
    """Callback appelé lors de l'abonnement à un topic"""
    logging.debug("📋 Abonnement confirmé - ID: %s, QoS: %s", mid, granted_qos)

def on_message(client, userdata, msg):
    """Callback appelé lors de la réception d'un message MQTT"""
    try:
        topic = msg.topic
        payload = msg.payload.decode('utf-8')
        logging.info("📨 Message MQTT reçu sur [%s]: %s", topic, payload)
        
        # Parser le JSON si possible
        try:
            message_data = json.loads(payload)
            logging.debug("📊 Données parsées: %s", message_data)
            
            # Traiter selon le type de topic
            if '/evt/' in topic:
                # Événement de changement d'état
                handle_device_event(topic, message_data)
            elif '/rsp/' in topic:
                # Réponse à une commande
                handle_command_response(topic, message_data)
            else:
                # Autre type de message
                logging.debug("🔄 Message non traité: %s", topic)
            
            # Envoyer le message brut à Jeedom pour traitement complémentaire
            if jeedom_com_instance:
                jeedom_com_instance.send_change_immediate({
                    'action': 'mqtt_message',
                    'topic': topic,
                    'data': message_data
                })
                
        except json.JSONDecodeError:
            logging.debug("📄 Message non-JSON reçu: %s", payload)
            
    except Exception as e:
        logging.error("❌ Erreur traitement message MQTT: %s", e)

def handle_device_event(topic, data):
    """Traite un événement de changement d'état d'équipement"""
    logging.info("🔄 Événement équipement: %s", data)
    
    try:
        # Extraire l'ID de l'équipement du topic ou des données
        device_id = extract_device_id(topic, data)
        
        if device_id:
            # Extraire les nouvelles propriétés
            properties = data.get('Properties') or data.get('properties') or []
            
            for prop in properties:
                if isinstance(prop, dict):
                    status = prop.get('Status')
                    if status is not None:
                        # Envoyer la mise à jour à Jeedom
                        jeedom_com_instance.send_change_immediate({
                            'action': 'device_status_changed',
                            'device_id': device_id,
                            'status': status,
                            'timestamp': time.time(),
                            'raw_data': data
                        })
                        logging.info("📊 État mis à jour - %s: %s", device_id, status)
                        
    except Exception as e:
        logging.error("❌ Erreur traitement événement: %s", e)

def handle_command_response(topic, data):
    """Traite une réponse à une commande"""
    logging.info("📋 Réponse commande: %s", data)
    
    try:
        # Vérifier si la commande a réussi
        if 'Error' in data or 'error' in data:
            error_msg = data.get('Error') or data.get('error')
            logging.warning("⚠️  Erreur commande: %s", error_msg)
            
            jeedom_com_instance.send_change_immediate({
                'action': 'command_response',
                'status': 'error',
                'error': error_msg,
                'raw_data': data
            })
        else:
            logging.info("✅ Commande exécutée avec succès")
            
            jeedom_com_instance.send_change_immediate({
                'action': 'command_response',
                'status': 'success',
                'raw_data': data
            })
            
    except Exception as e:
        logging.error("❌ Erreur traitement réponse: %s", e)

def extract_device_id(topic, data):
    """Extrait l'ID de l'équipement du topic MQTT ou des données"""
    
    # Essayer d'extraire du topic
    # Format typique: hobby/control/devices/evt/{device_id}
    topic_parts = topic.split('/')
    if len(topic_parts) >= 5:
        potential_id = topic_parts[4]
        if potential_id and potential_id != '#':
            return potential_id
    
    # Essayer d'extraire des données
    if isinstance(data, dict):
        # Vérifier plusieurs formats possibles
        for key in ['Uuid', 'uuid', 'Id', 'id', 'DeviceId', 'device_id']:
            if key in data:
                return data[key]
        
        # Vérifier dans les devices imbriqués
        devices = data.get('Devices') or data.get('devices') or []
        if devices and len(devices) > 0:
            first_device = devices[0]
            if isinstance(first_device, dict):
                for key in ['Uuid', 'uuid', 'Id', 'id']:
                    if key in first_device:
                        return first_device[key]
    
    return None

# --- FONCTION PRINCIPALE DU DÉMON ---

def listen():
    logging.info("Démarrage du démon nhc...")
    
    # Initialisation de la communication avec Jeedom
    global jeedom_socket_instance, jeedom_com_instance
    
    try:
        # Démarrage du socket Jeedom pour recevoir les commandes
        try:
            jeedom_socket_instance = jeedom_socket(port=_socketport, address='localhost')
            jeedom_socket_instance.open()
            logging.info("Socket Jeedom ouvert sur le port %s", _socketport)
        except Exception as e:
            logging.error("Erreur lors de l'ouverture du socket: %s", e)
            logging.info("Continuons sans socket...")
            jeedom_socket_instance = None
        
        # Démarrage de la communication avec Jeedom
        jeedom_com_instance = jeedom_com(apikey=_apikey, url=_callback, cycle=0.5)
        
        # Test de la communication (non bloquant)
        try:
            if not jeedom_com_instance.test():
                logging.warning('Test de communication avec Jeedom échoué. Continuons quand même...')
            else:
                logging.info("Communication avec Jeedom validée")
        except Exception as e:
            logging.warning('Erreur lors du test de communication: %s. Continuons quand même...', e)
            
        logging.info("Communication avec Jeedom initialisée")
        
        # Test de ping initial vers Jeedom
        try:
            jeedom_com_instance.send_change_immediate({
                'action': 'daemon_started',
                'status': 'ok',
                'message': 'Démon nhc démarré avec succès'
            })
            logging.info("Message de démarrage envoyé à Jeedom")
        except Exception as e:
            logging.warning("Impossible d'envoyer le message de démarrage: %s", e)
        
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
    global mqtt_client_instance
    
    logging.info("🚀 Démarrage du client MQTT...")
    logging.info("🏠 Connexion à la passerelle: %s", _niko_ip)
    logging.info("🔑 Token JWT: %s...%s", _niko_jwt[:20], _niko_jwt[-10:] if len(_niko_jwt) > 30 else "")
    
    try:
        # Création d'un client MQTT avec un ID unique
        client_id = f"jeedom_nhc_{os.getpid()}_{int(time.time())}"
        mqtt_client_instance = mqtt.Client(client_id=client_id)
        logging.info("🆔 Client ID: %s", client_id)

        # Configuration des callbacks
        mqtt_client_instance.on_connect = on_connect
        mqtt_client_instance.on_disconnect = on_disconnect
        mqtt_client_instance.on_message = on_message
        mqtt_client_instance.on_subscribe = on_subscribe

        # Configuration de l'authentification
        logging.info("🔐 Configuration authentification (hobby/JWT)...")
        mqtt_client_instance.username_pw_set("hobby", password=_niko_jwt)
        
        # Configuration SSL/TLS pour Niko Home Control
        logging.info("🔒 Configuration connexion sécurisée (TLS)...")
        context = ssl.create_default_context()
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
        
        mqtt_client_instance.tls_set_context(context)
        # Alternative pour plus de compatibilité
        # mqtt_client_instance.tls_set(cert_reqs=ssl.CERT_NONE)
        # mqtt_client_instance.tls_insecure_set(True)

        # Tentative de connexion
        logging.info("🔗 Connexion à %s:%d...", _niko_ip, 8884)
        result = mqtt_client_instance.connect(_niko_ip, 8884, 60)
        
        if result == mqtt.MQTT_ERR_SUCCESS:
            # Démarrage de la boucle non-bloquante
            mqtt_client_instance.loop_start()
            logging.info("✅ Client MQTT démarré en arrière-plan")
        else:
            logging.error("❌ Échec immédiat de la connexion: %s", result)
            
    except Exception as e:
        logging.error("💥 Erreur lors du démarrage MQTT: %s", e)
        logging.error("🔍 Type d'erreur: %s", type(e).__name__)
        logging.warning("⚠️  Continuons en mode dégradé sans MQTT")

def read_socket():
    """Lit les messages du socket Jeedom"""
    try:
        # Vérifier que le socket existe avant d'essayer de lire
        if jeedom_socket_instance is None:
            return
            
        if not JEEDOM_SOCKET_MESSAGE.empty():
            logging.debug("Message reçu du socket Jeedom")
            raw_message = JEEDOM_SOCKET_MESSAGE.get()
            
            # S'assurer que le message est une chaîne
            if isinstance(raw_message, bytes):
                raw_message = raw_message.decode('utf-8')
            elif not isinstance(raw_message, str):
                raw_message = str(raw_message)
                
            message = json.loads(jeedom_utils.stripped(raw_message))
            
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
        logging.debug('Type de message problématique: %s', type(raw_message) if 'raw_message' in locals() else 'N/A')

def handle_jeedom_command(message):
    """Traite les commandes reçues de Jeedom"""
    action = message.get('action')
    logging.debug("Commande reçue: %s", action)
    
    if action == 'test':
        logging.info("Test de communication reçu")
        # Répondre à Jeedom
        jeedom_com_instance.send_change_immediate({'action': 'test_response', 'status': 'ok'})
    elif action == 'test_niko_connection':
        logging.info("Test de connexion Niko demandé")
        test_niko_connection(message.get('ip'), message.get('jwt'))
    elif action == 'discover_devices':
        logging.info("Découverte d'équipements demandée")
        discover_niko_devices()
    elif action == 'send_command':
        logging.info("Commande à envoyer: %s", message)
        send_niko_command(message.get('device_id'), message.get('command'), message.get('value'))
    elif action == 'refresh':
        logging.info("Demande de rafraîchissement reçue")
        # Ici on pourrait déclencher une synchronisation
    else:
        logging.warning("Action non reconnue: %s", action)

def test_niko_connection(ip, jwt):
    """Teste la connexion à la passerelle Niko"""
    logging.info("Test de connexion à %s", ip)
    
    try:
        # Test de connexion MQTT
        test_client = mqtt.Client(client_id="jeedom_nhc_test_" + str(os.getpid()))
        test_client.username_pw_set("hobby", password=jwt)
        test_client.tls_set(cert_reqs=ssl.CERT_NONE)
        test_client.tls_insecure_set(True)
        
        # Tenter la connexion avec timeout
        result = test_client.connect(ip, 8884, 10)
        if result == 0:
            logging.info("Connexion MQTT réussie")
            test_client.disconnect()
            jeedom_com_instance.send_change_immediate({
                'action': 'connection_test_result', 
                'status': 'success',
                'message': 'Connexion MQTT réussie'
            })
        else:
            logging.error("Échec connexion MQTT: code %s", result)
            jeedom_com_instance.send_change_immediate({
                'action': 'connection_test_result',
                'status': 'error', 
                'message': f'Échec connexion MQTT: code {result}'
            })
            
    except Exception as e:
        logging.error("Erreur test connexion: %s", e)
        jeedom_com_instance.send_change_immediate({
            'action': 'connection_test_result',
            'status': 'error',
            'message': str(e)
        })

def discover_niko_devices():
    """Découvre les équipements Niko via l'API REST"""
    logging.info("🔍 Découverte des équipements Niko via API REST...")
    
    if not _niko_ip or not _niko_jwt:
        logging.error("❌ Configuration Niko manquante pour la découverte")
        return
    
    try:
        # URL de l'API REST pour lister les équipements
        url = f"https://{_niko_ip}:8443/hobby/control/devices"
        
        # Headers avec authentification JWT
        headers = {
            'Authorization': f'Bearer {_niko_jwt}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
        
        logging.info("🌐 Requête API: GET %s", url)
        
        # Requête avec vérification SSL désactivée (comme pour MQTT)
        response = requests.get(url, headers=headers, verify=False, timeout=10)
        
        logging.info("📡 Réponse API: Status=%d", response.status_code)
        
        if response.status_code == 200:
            devices_data = response.json()
            logging.info("📊 Données reçues: %s", devices_data)
            
            # Parser la réponse et extraire les équipements
            discovered_devices = parse_niko_devices(devices_data)
            
            # Envoyer chaque équipement à Jeedom
            for device in discovered_devices:
                jeedom_com_instance.send_change_immediate({
                    'action': 'device_discovered',
                    'device_id': device['id'],
                    'name': device['name'],
                    'type': device['type'],
                    'uuid': device.get('uuid'),
                    'location': device.get('location'),
                    'properties': device.get('properties', [])
                })
                logging.debug("📤 Équipement envoyé: %s", device['name'])
                
            logging.info("✅ Découverte terminée: %d équipements trouvés", len(discovered_devices))
            
        elif response.status_code == 401:
            logging.error("❌ Erreur d'authentification: JWT token invalide ou expiré")
            jeedom_com_instance.send_change_immediate({
                'action': 'discovery_error',
                'error': 'authentication_failed',
                'message': 'Token JWT invalide ou expiré'
            })
        else:
            logging.error("❌ Erreur API: %d - %s", response.status_code, response.text)
            jeedom_com_instance.send_change_immediate({
                'action': 'discovery_error',
                'error': 'api_error',
                'message': f'Erreur API: {response.status_code}'
            })
            
    except requests.exceptions.Timeout:
        logging.error("❌ Timeout lors de la requête API")
        jeedom_com_instance.send_change_immediate({
            'action': 'discovery_error',
            'error': 'timeout',
            'message': 'Timeout de connexion à l\'API'
        })
    except requests.exceptions.ConnectionError as e:
        logging.error("❌ Erreur de connexion API: %s", e)
        jeedom_com_instance.send_change_immediate({
            'action': 'discovery_error',
            'error': 'connection_error',
            'message': str(e)
        })
    except Exception as e:
        logging.error("❌ Erreur inattendue lors de la découverte: %s", e)
        jeedom_com_instance.send_change_immediate({
            'action': 'discovery_error',
            'error': 'unknown',
            'message': str(e)
        })

def parse_niko_devices(api_response):
    """Parse la réponse de l'API Niko et extrait les équipements"""
    devices = []
    
    try:
        # La structure peut varier selon la version de l'API Niko
        # Essayons plusieurs formats possibles
        
        if isinstance(api_response, dict):
            # Format: {"Devices": [...]}
            if 'Devices' in api_response:
                device_list = api_response['Devices']
            # Format: {"devices": [...]}
            elif 'devices' in api_response:
                device_list = api_response['devices']
            # Format direct: [...]
            else:
                device_list = [api_response]
        elif isinstance(api_response, list):
            device_list = api_response
        else:
            logging.warning("⚠️  Format de réponse API inattendu: %s", type(api_response))
            return devices
        
        for device_data in device_list:
            if not isinstance(device_data, dict):
                continue
                
            # Extraction des informations de l'équipement
            device_id = device_data.get('Uuid') or device_data.get('uuid') or device_data.get('Id')
            device_name = device_data.get('Name') or device_data.get('name') or f"Équipement {device_id}"
            
            # Déterminer le type d'équipement
            device_type = determine_device_type(device_data)
            
            # Localisation
            location = device_data.get('Location') or device_data.get('location') or 'Non défini'
            
            # Propriétés
            properties = device_data.get('Properties') or device_data.get('properties') or []
            
            if device_id:
                device = {
                    'id': device_id,
                    'name': device_name,
                    'type': device_type,
                    'uuid': device_id,
                    'location': location,
                    'properties': properties,
                    'raw_data': device_data  # Garder les données brutes pour debug
                }
                devices.append(device)
                logging.debug("🔧 Équipement parsé: %s (%s)", device_name, device_type)
            
    except Exception as e:
        logging.error("❌ Erreur lors du parsing des équipements: %s", e)
    
    return devices

def determine_device_type(device_data):
    """Détermine le type d'équipement à partir des données Niko"""
    
    # Vérifier le type explicite s'il existe
    device_type = device_data.get('Type') or device_data.get('type')
    if device_type:
        return device_type.lower()
    
    # Vérifier les propriétés pour deviner le type
    properties = device_data.get('Properties') or device_data.get('properties') or []
    
    for prop in properties:
        if isinstance(prop, dict):
            prop_type = prop.get('Type') or prop.get('type') or ''
            prop_type_lower = prop_type.lower()
            
            if 'light' in prop_type_lower or 'dimmer' in prop_type_lower:
                return 'light'
            elif 'switch' in prop_type_lower:
                return 'switch'
            elif 'motor' in prop_type_lower:
                return 'cover'
            elif 'sensor' in prop_type_lower:
                return 'sensor'
    
    # Vérifier le nom pour deviner le type
    device_name = (device_data.get('Name') or device_data.get('name') or '').lower()
    
    if any(word in device_name for word in ['light', 'lumière', 'éclairage', 'lampe']):
        return 'light'
    elif any(word in device_name for word in ['switch', 'interrupteur', 'bouton']):
        return 'switch'
    elif any(word in device_name for word in ['volet', 'store', 'cover', 'blind']):
        return 'cover'
    elif any(word in device_name for word in ['sensor', 'capteur', 'détecteur']):
        return 'sensor'
    
    # Type par défaut
    return 'unknown'

def send_niko_command(device_id, command, value=None):
    """Envoie une commande à un équipement Niko via MQTT"""
    logging.info("📤 Envoi commande %s à %s (valeur: %s)", command, device_id, value)
    
    if not mqtt_client_instance or not mqtt_client_instance.is_connected():
        logging.error("❌ Client MQTT non connecté")
        jeedom_com_instance.send_change_immediate({
            'action': 'command_error',
            'device_id': device_id,
            'command': command,
            'error': 'mqtt_not_connected'
        })
        return
    
    try:
        # Construire le message selon le protocole Niko Home Control
        mqtt_message = build_niko_command_message(device_id, command, value)
        
        # Topic pour les commandes Niko
        topic = "hobby/control/devices/cmd"
        
        # Envoyer la commande via MQTT
        logging.info("📡 Publication MQTT sur [%s]: %s", topic, mqtt_message)
        
        result = mqtt_client_instance.publish(topic, json.dumps(mqtt_message))
        
        if result.rc == mqtt.MQTT_ERR_SUCCESS:
            logging.info("✅ Commande MQTT envoyée avec succès")
            jeedom_com_instance.send_change_immediate({
                'action': 'command_sent',
                'device_id': device_id,
                'command': command,
                'value': value,
                'status': 'success'
            })
        else:
            logging.error("❌ Échec envoi MQTT: %s", result.rc)
            jeedom_com_instance.send_change_immediate({
                'action': 'command_error',
                'device_id': device_id,
                'command': command,
                'error': f'mqtt_error_{result.rc}'
            })
            
    except Exception as e:
        logging.error("❌ Erreur lors de l'envoi de commande: %s", e)
        jeedom_com_instance.send_change_immediate({
            'action': 'command_error',
            'device_id': device_id,
            'command': command,
            'error': str(e)
        })

def build_niko_command_message(device_id, command, value=None):
    """Construit le message de commande selon le protocole Niko Home Control"""
    
    # Mapper les commandes génériques vers les valeurs Niko
    command_mapping = {
        'on': 100,
        'off': 0,
        'toggle': None,  # Sera géré spécialement
        'dim': value if value is not None else 50,
        'up': 100,
        'down': 0,
        'stop': None,  # Pour les volets
    }
    
    # Déterminer la valeur à envoyer
    if command in command_mapping:
        if command == 'toggle':
            # Pour toggle, on devrait d'abord récupérer l'état actuel
            # Pour l'instant, on utilise une valeur par défaut
            status_value = 100  # On supposera "allumer" par défaut
        elif command_mapping[command] is not None:
            status_value = command_mapping[command]
        else:
            status_value = value if value is not None else 0
    else:
        # Commande personnalisée avec valeur directe
        status_value = value if value is not None else 0
    
    # Format du message selon la documentation Niko Home Control
    message = {
        "Method": "devices.control",
        "Params": {
            "Devices": [{
                "Uuid": device_id,
                "Properties": [{
                    "Status": status_value
                }]
            }]
        }
    }
    
    logging.debug("🔧 Message construit: %s", message)
    return message

def get_device_status(device_id):
    """Récupère l'état actuel d'un équipement via l'API REST"""
    logging.info("📊 Récupération état de %s", device_id)
    
    try:
        url = f"https://{_niko_ip}:8443/hobby/control/devices/{device_id}"
        headers = {
            'Authorization': f'Bearer {_niko_jwt}',
            'Content-Type': 'application/json'
        }
        
        response = requests.get(url, headers=headers, verify=False, timeout=5)
        
        if response.status_code == 200:
            device_data = response.json()
            logging.debug("📊 État récupéré: %s", device_data)
            return device_data
        else:
            logging.warning("⚠️  Impossible de récupérer l'état: %d", response.status_code)
            return None
            
    except Exception as e:
        logging.error("❌ Erreur récupération état: %s", e)
        return None

# --- GESTION DU DÉMON (Code standard Jeedom) ---

def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting...", int(signum))
    shutdown()

def shutdown():
    logging.info("🛑 Arrêt du démon...")
    
    # Fermeture du client MQTT
    try:
        if mqtt_client_instance:
            logging.info("🔌 Fermeture connexion MQTT...")
            mqtt_client_instance.loop_stop()
            mqtt_client_instance.disconnect()
    except Exception as e:
        logging.warning('Erreur fermeture MQTT: %s', e)
    
    # Suppression du fichier PID
    logging.debug("🗑️  Suppression fichier PID: %s", _pidfile)
    try:
        os.remove(_pidfile)
    except Exception as e:
        logging.warning('Erreur suppression PID: %s', e)
        
    # Fermeture du socket Jeedom
    try:
        if jeedom_socket_instance:
            logging.info("🔌 Fermeture socket Jeedom...")
            jeedom_socket_instance.close()
    except Exception as e:
        logging.warning('Erreur fermeture socket: %s', e)
        
    logging.info("✅ Arrêt terminé")
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