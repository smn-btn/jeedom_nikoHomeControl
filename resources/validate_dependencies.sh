#!/bin/bash

# Script de validation des dépendances pour le plugin Niko Home Control

BASEDIR=$(cd "$(dirname "$0")/.." && pwd)
VENV_DIR="$BASEDIR/resources/venv"

# Vérification de l'environnement virtuel
if [ ! -d "$VENV_DIR" ]; then
    echo "NOK: Environnement virtuel non trouvé"
    exit 1
fi

# Activation de l'environnement virtuel
source "$VENV_DIR/bin/activate"

# Vérification des modules Python
python -c "import paho.mqtt.client as mqtt; import requests; print('OK')" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "OK"
    exit 0
else
    echo "NOK: Modules Python manquants"
    exit 1
fi
