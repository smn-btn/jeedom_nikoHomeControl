# Changelog Niko Home Control - beta

# 26/11/2025 - v1.0
- clean des paramêtre non utilisé pour déploiement 

# 24/07/2025 - v0.3
- Mise à jour des équipements existants lors d'un nouveau scan pour refléter les changements (nom, etc.).

# 23/07/2025 - v0.2
- Correction du processus de scan des équipements. Le scan est désormais asynchrone et n'affiche plus de message d'erreur de timeout. Un message informe l'utilisateur que le scan est en cours. 
- Le widget `core::shutter` est maintenant appliqué par défaut à l'état de la position.

# 21/07/2025 - v0.1
- Version initiale du plugin.
- Gestion des dépendances et du démon Python.
- Découverte des équipements via l'API Hobby MQTT.
- Support des volets roulants (`rolldownshutter`).
