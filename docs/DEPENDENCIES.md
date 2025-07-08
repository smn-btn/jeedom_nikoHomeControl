# Dépendances du plugin NHC Jeedom

## Dépendances REQUISES

### Python (système)
- **python3** : Version 3.6 ou supérieure
- **python3-paho-mqtt** : Client MQTT pour communiquer avec Niko Home Control
- **python3-requests** : Client HTTP pour communiquer avec Jeedom

### Installation recommandée (via apt)
```bash
sudo apt update
sudo apt install -y python3-paho-mqtt python3-requests
```

### Installation alternative (via pip)
```bash
pip3 install --break-system-packages paho-mqtt requests
```

## Dépendances NON REQUISES

### Modules présents dans jeedom.py mais NON utilisés par le plugin NHC
- **serial/pyserial** : Communication série (RS232/USB) - NON utilisé car NHC utilise MQTT
- **pyudev** : Détection des périphériques USB - NON utilisé car NHC utilise MQTT

### Gestion des modules optionnels
Les modules `serial` et `pyudev` sont importés de manière optionnelle dans `jeedom.py` :
- Si ces modules ne sont pas installés, un avertissement est affiché dans les logs
- Les fonctionnalités série et USB sont désactivées mais le démon continue de fonctionner
- Aucune erreur n'est générée si ces modules sont absents

### Pourquoi ces modules sont-ils présents ?
Le fichier `jeedom.py` est un framework générique Jeedom qui fournit des classes utilitaires pour différents types de plugins :
- `jeedom_serial` : Pour les plugins utilisant la communication série
- `jeedom_utils.find_tty_usb()` : Pour détecter les périphériques USB série
- `jeedom_com` : Pour la communication HTTP avec Jeedom
- `jeedom_socket` : Pour la communication socket

Le plugin NHC utilise uniquement :
- `jeedom_com` : Communication avec Jeedom
- `jeedom_socket` : Communication socket
- `jeedom_utils` : Utilitaires généraux (logging, etc.)

## Résumé

**Pour le plugin NHC, seuls 2 modules Python sont nécessaires :**
1. `paho-mqtt` - Communication MQTT avec Niko Home Control
2. `requests` - Communication HTTP avec Jeedom

Les modules `serial` et `pyudev` peuvent être ignorés car ils ne sont pas utilisés par ce plugin spécifique.
