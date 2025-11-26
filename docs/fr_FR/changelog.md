# Changelog Niko Home Control

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte.

# 26/11/2025 - v1.0
- clean des paramêtres non utilisés pour déploiement 

# 23/07/2025 - v0.2
- Correction du processus de scan des équipements. Le scan est désormais asynchrone et n'affiche plus de message d'erreur de timeout. Un message informe l'utilisateur que le scan est en cours. 
- Le widget `core::shutter` est maintenant appliqué par défaut à l'état de la position.

# 21/07/2025 - v0.1
- Version initiale du plugin.
- Gestion des dépendances et du démon Python.
- Découverte des équipements via l'API Hobby MQTT.
- Support des volets roulants (`rolldownshutter`).
