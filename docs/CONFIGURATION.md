# 🏠 Configuration Niko Home Control pour Jeedom

## 📋 Prérequis

1. **Connected Controller Niko Home Control** installé et configuré
2. **Hobby profile activé** dans votre installation
3. **Accès réseau** à votre passerelle depuis Jeedom

## 🔑 Obtention du token JWT

### Méthode 1 : Via l'interface web du Connected Controller

1. **Connectez-vous à votre Connected Controller**
   - Ouvrez un navigateur web
   - Allez à l'adresse : `https://[IP_DE_VOTRE_PASSERELLE]`
   - Connectez-vous avec vos identifiants

2. **Activez le hobby profile**
   - Allez dans `Paramètres` → `Généralités` → `Hobby`
   - Activez l'option "Hobby profile"
   - Définissez un mot de passe pour le hobby profile
   - Notez ce mot de passe !

3. **Générez le token JWT**
   - Utilisez cette commande curl (remplacez les valeurs) :
   ```bash
   curl -X POST "https://[IP_PASSERELLE]:8884/hobby/authenticate" \
     -H "Content-Type: application/json" \
     -d '{"username": "hobby", "password": "[VOTRE_MOT_DE_PASSE]"}' \
     --insecure
   ```

### Méthode 2 : Via le logiciel de programmation Niko

1. **Ouvrez le logiciel de programmation Niko**
2. **Connectez-vous à votre installation**
3. **Allez dans les paramètres système**
4. **Activez le "hobby profile"**
5. **Générez ou récupérez le token JWT**

## 🔧 Configuration dans Jeedom

1. **Ouvrez la configuration du plugin nhc**
2. **Saisissez l'adresse IP** de votre passerelle Niko
3. **Collez le token JWT** obtenu précédemment
4. **Sauvegardez la configuration**
5. **Testez la connexion** en cliquant sur "Tester la connexion"

## 🔍 Ports réseau utilisés

- **Port 8884** : MQTT over TLS (Communication avec la passerelle Niko)
- **Port 55001** : Socket Jeedom (Communication démon ↔ plugin)

Assurez-vous que ces ports sont accessibles.

## 📡 Topics MQTT

Une fois connecté, le plugin utilise ces topics MQTT :

- **Réception d'événements** : `hobby/control/devices/evt/#`
- **Envoi de commandes** : `hobby/control/devices/cmd`
- **Réception de réponses** : `hobby/control/devices/rsp/#`

## ❗ Dépannage

### Le démon ne démarre pas
- Vérifiez que les dépendances Python sont installées
- Consultez les logs dans `Analyse` → `Logs` → `nhc`

### Connexion impossible à la passerelle
- Vérifiez l'adresse IP de la passerelle
- Testez la connectivité : `ping [IP_PASSERELLE]`
- Vérifiez que le port 8884 est ouvert
- Vérifiez que le hobby profile est activé

### Token JWT invalide
- Régénérez le token via l'interface web ou le logiciel de programmation
- Vérifiez que le mot de passe du hobby profile est correct
- Le token JWT est très long (plusieurs centaines de caractères)

## 📞 Support

En cas de problème :
1. Activez les logs en mode DEBUG
2. Reproduisez le problème
3. Consultez les logs détaillés
4. Contactez le support avec les logs

---
*Plugin développé pour Jeedom - Compatible Niko Home Control Connected Controller*
