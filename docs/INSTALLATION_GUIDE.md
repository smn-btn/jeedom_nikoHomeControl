# Guide d'installation des dépendances pour le plugin NHC Jeedom

## Problème rencontré

Le plugin NHC nécessite des modules Python (`paho-mqtt` et `requests`) qui ne sont pas installés par défaut.

**Note importante** : Les modules `serial` et `pyudev` présents dans le framework Jeedom générique ne sont PAS nécessaires pour le plugin NHC car il utilise exclusivement MQTT.

## Solutions

### Solution 1 : Installation automatique via le script
Exécutez le script d'installation fourni :
```bash
cd /home/simon-bertin/Documents/workspace/perso/jeedom_nikoHomeControl/resources
chmod +x install_dependencies.sh
sudo ./install_dependencies.sh
```

### Solution 2 : Installation manuelle via apt (recommandé)
```bash
sudo apt update
sudo apt install -y python3-paho-mqtt python3-requests
```

### Solution 3 : Installation via pip (si apt ne fonctionne pas)
```bash
pip3 install --break-system-packages paho-mqtt requests
```

## Vérification
Pour vérifier que tout fonctionne :
```bash
python3 -c "import paho.mqtt.client; import requests; print('Tous les modules sont installés!')"
```

## Erreurs résolues
1. **deamon_info() non statique** : ✅ Corrigé - méthode maintenant statique
2. **Module paho manquant** : ✅ Corrigé - ajout de l'installation automatique
3. **Module requests manquant** : ✅ Corrigé - ajout de l'installation automatique

## Notes
- Le plugin vérifie maintenant automatiquement les dépendances avant de démarrer le démon
- L'installation se fait automatiquement lors de l'installation/mise à jour du plugin
- Un message d'erreur explicite est affiché si les dépendances manquent
