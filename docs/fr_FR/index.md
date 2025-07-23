# Plugin Niko Home Control

Ce plugin permet de piloter les équipements de votre installation Niko Home Control

Il utilise l'API locale (Hobby API) fournie par Niko, qui communique via le protocole MQTT.

## Configuration

La configuration du plugin est accessible depuis le menu `Plugins` → `Gestion des plugins` → `Niko Home Control`.

### Prérequis

Avant de configurer le plugin, vous devez :
1.  Posséder une installation Niko Home Control avec un **Connected Controller**.
2.  Activer le **Hobby Profile** sur votre installation et récupérer le **Token d'accès (JWT)**. La procédure est détaillée dans la documentation de Niko Home Control. Vous aurez besoin de l'adresse IP de votre contrôleur et du mot de passe que vous avez défini pour le profil "hobby". [plus d'info ici](https://guide.niko.eu/fr/smnhc2/lv/hobby-api#)
3.  pour éviter de reconfigurer le plugin, vous pouvez configurer l'IP du hub Niko Home Control en IP Statique

### Configuration du plugin

Une fois sur la page de configuration du plugin, vous devrez renseigner les informations suivantes :

-   **Adresse IP du contrôleur Niko** : L'adresse IP locale de votre Connected Controller.
-   **Token d'accès (JWT)** : Le token que vous avez généré via le Hobby Profile.

Après avoir sauvegardé ces informations, vous devrez :
1.  **Installer les dépendances** : Cliquez sur le bouton `Relancer` dans la section `Dépendances`.
2.  **Démarrer le démon** : Une fois les dépendances installées, le démon devrait démarrer automatiquement. Vous pouvez vérifier son statut dans la section `Démon`. S'il n'est pas `OK`, vous pouvez le démarrer manuellement.

## Utilisation

### Découverte des équipements

Une fois le plugin configuré et le démon démarré, vous pouvez lancer la découverte de vos équipements Niko Home Control.

1.  Allez sur la page du plugin (`Plugins` → `Protocole domotique` → `Niko Home Control`).
2.  Cliquez sur le bouton **Scan** en haut
3.  Le plugin va interroger votre installation Niko et créer automatiquement les équipements compatibles trouvés.

Un rafraîchissement de la page peut être nécessaire pour voir les nouveaux équipements.

### Équipements compatibles

Le plugin supporte actuellement les types d'équipements suivants :

#### Volets roulants / Stores (`rolldownshutter`)

Les équipements de type volet sont automatiquement créés avec les commandes suivantes :
-   **Monter** : Ouvre complètement le volet.
-   **Descendre** : Ferme complètement le volet.
-   **Stop** : Arrête le mouvement du volet.
-   **Position** (Action) : Permet de définir une position précise (de 0% à 100%) via un curseur.
-   **État Position** (Info) : Donne la position actuelle du volet.

Grâce à l'utilisation des types génériques, l'équipement est compatible avec les widgets de volet standards de Jeedom et l'application mobile.

## FAQ

**Le démon ne démarre pas**
Vérifiez les points suivants :
- Assurez-vous que les dépendances sont bien installées et à jour (statut `OK`).
- Consultez les logs du plugin (`nhc` et `nhc_update`) depuis le menu `Analyse` → `Logs` pour identifier l'erreur. Souvent, un problème de configuration (IP ou Token) empêche le démon de se lancer.

**La découverte ne trouve aucun équipement**
- Vérifiez que le démon est bien en statut `OK`.
- Assurez-vous que l'adresse IP du contrôleur est correcte et que votre Jeedom peut communiquer avec sur le réseau.
- Vérifiez la validité de votre Token JWT. Il a une durée de vie limitée (généralement 1 an) et doit être renouvelé.

**Les commandes ne fonctionnent pas ou l'état ne se met pas à jour**
- Vérifiez que le démon est toujours en cours d'exécution.
- Consultez les logs `nhc` en mode debug pour voir les commandes envoyées et les messages reçus du contrôleur Niko.


## Prochaines évolutions
-   Prise en charge des prises connectées (`socket`).
-   Ajout d'une alerte pour notifier l'utilisateur avant l'expiration du token d'accès (JWT).
-   mise à jours de l'équipement après un nouveau scan