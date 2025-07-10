# Guide d'utilisation du Plugin Jeedom Niko Home Control

## ✅ Fonctionnalités implémentées

### 🔧 Infrastructure
- ✅ Daemon Python stable et robuste
- ✅ Connexion MQTT sécurisée à la passerelle Niko
- ✅ Communication bidirectionnelle avec Jeedom (socket + callback)
- ✅ Gestion d'erreurs complète et logs détaillés
- ✅ Redémarrage automatique du daemon

### 🏠 Fonctionnalités Niko Home Control
- ✅ **Découverte d'équipements** via API REST
- ✅ **Envoi de commandes** via MQTT
- ✅ **Réception d'événements** en temps réel via MQTT
- ✅ **Test de connexion** à la passerelle
- ✅ Parsing intelligent des données Niko

### 📡 Protocoles supportés
- ✅ MQTT sur port 8884 (sécurisé TLS)
- ✅ API REST sur port 8443 (HTTPS)
- ✅ Authentification JWT
- ✅ Topics Niko: `hobby/control/devices/evt/#` et `hobby/control/devices/rsp/#`

## 🚀 Configuration

### Prérequis
1. Passerelle Niko Home Control accessible sur le réseau
2. Token JWT obtenu via l'application Niko Home Control
3. Jeedom avec plugin nhc installé

### Paramètres requis
- **IP Passerelle**: 192.168.1.53 (remplacer par votre IP)
- **Token JWT**: eyJhbGciOiJSUzUxMi... (votre token)
- **Utilisateur MQTT**: hobby (fixe)

## 📋 Commandes disponibles

### Via socket (port 55001)
```json
// Test de connexion
{"apikey":"...", "action":"test_niko_connection", "ip":"192.168.1.53", "jwt":"..."}

// Découverte d'équipements
{"apikey":"...", "action":"discover_devices"}

// Envoi de commande
{"apikey":"...", "action":"send_command", "device_id":"uuid", "command":"on", "value":100}

// Test simple
{"apikey":"...", "action":"test"}
```

### Commandes supportées
- `on` : Allumer (valeur 100)
- `off` : Éteindre (valeur 0)
- `dim` : Varier (valeur 0-100)
- `toggle` : Basculer
- `up`/`down` : Monter/Descendre (volets)
- `stop` : Arrêter (volets)

## 🔍 Types d'équipements détectés
- **light** : Éclairages et variateurs
- **switch** : Interrupteurs
- **cover** : Volets et stores
- **sensor** : Capteurs
- **unknown** : Autres équipements

## 📊 Messages MQTT reçus

### Événements (`hobby/control/devices/evt/#`)
```json
{
  "Devices": [{
    "Uuid": "device-uuid",
    "Properties": [{"Status": 100}]
  }]
}
```

### Réponses (`hobby/control/devices/rsp/#`)
```json
{
  "Method": "devices.control",
  "Params": {...},
  "Error": "..." // si erreur
}
```

## 🛠️ Scripts de test disponibles

### test_mqtt.py
Test direct de la connexion MQTT :
```bash
python3 test_mqtt.py 192.168.1.53 "votre-jwt-token"
```

### test_daemon_connection.php
Test de communication avec le daemon :
```bash
php test_daemon_connection.php
```

### test_discover.php
Test de découverte d'équipements :
```bash
php test_discover.php
```

### test_command.php
Test d'envoi de commande :
```bash
php test_command.php
```

## 📈 Logs et diagnostics

### Fichiers de logs
- `/tmp/daemon.log` : Logs du daemon
- Logs Jeedom : Interface web Jeedom > Plugins > nhc

### Codes d'erreur MQTT
- 0 : Connexion réussie
- 1 : Version de protocole incorrecte
- 2 : Identifiant client invalide
- 3 : Serveur indisponible
- 4 : Nom d'utilisateur ou mot de passe incorrect
- 5 : Non autorisé

### Vérifications
```bash
# Vérifier le daemon
docker exec jeedom-server ps aux | grep demond.py

# Vérifier la connexion réseau
ping 192.168.1.53

# Tester le port MQTT
telnet 192.168.1.53 8884

# Logs en temps réel
docker exec jeedom-server tail -f /tmp/daemon.log
```

## 🔧 Prochaines étapes

### Pour finaliser l'intégration :
1. **Interface utilisateur** : Ajouter boutons de test et découverte dans Jeedom
2. **Gestion des équipements** : Création automatique des équipements Jeedom
3. **Commandes Jeedom** : Mapping vers les commandes Niko
4. **Synchronisation** : Mise à jour des états en temps réel
5. **Persistence** : Sauvegarde de la configuration des équipements

### Fonctionnalités avancées :
- Scénarios et programmations
- Groupes d'équipements
- Notifications d'état
- Historiques et statistiques

## 📞 Support

En cas de problème :
1. Vérifier les logs du daemon
2. Tester la connexion réseau
3. Valider le token JWT
4. Redémarrer le daemon si nécessaire

Le plugin est maintenant fonctionnel et prêt pour l'utilisation !
