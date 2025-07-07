#!/bin/bash

# Script d'installation des dépendances pour le plugin Niko Home Control
# Conforme aux conventions Jeedom pour les plugins avec daemon

PROGRESS_FILE=/tmp/jeedom_main/nhc/dependance

if [ ! -z $1 ]; then
    PROGRESS_FILE=$1
fi

# Créer le répertoire si nécessaire
mkdir -p "$(dirname "$PROGRESS_FILE")"

touch ${PROGRESS_FILE}

PLUGIN_DIR=$(dirname "$0")
BASEDIR=$(cd "$PLUGIN_DIR/.." && pwd)

echo "$(date '+%Y-%m-%d %H:%M:%S') - Début de l'installation des dépendances pour le plugin nhc"

# Nettoyage du fichier de progression
echo 0 > ${PROGRESS_FILE}

# Vérification que Python3 est disponible
if ! command -v python3 &> /dev/null; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERREUR: Python3 n'est pas installé"
    echo 100 > ${PROGRESS_FILE}
    exit 1
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - Python3 trouvé: $(python3 --version)"
echo 20 > ${PROGRESS_FILE}

# Création de l'environnement virtuel Python
VENV_DIR="$BASEDIR/resources/venv"
if [ ! -d "$VENV_DIR" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Création de l'environnement virtuel Python"
    python3 -m venv "$VENV_DIR"
    if [ $? -ne 0 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - ERREUR: Impossible de créer l'environnement virtuel"
        echo 100 > ${PROGRESS_FILE}
        exit 1
    fi
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Environnement virtuel déjà présent"
fi

echo 40 > ${PROGRESS_FILE}

# Activation de l'environnement virtuel
source "$VENV_DIR/bin/activate"
echo "$(date '+%Y-%m-%d %H:%M:%S') - Environnement virtuel activé"

# Mise à jour de pip
echo "$(date '+%Y-%m-%d %H:%M:%S') - Mise à jour de pip"
python -m pip install --upgrade pip
if [ $? -ne 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERREUR: Impossible de mettre à jour pip"
    echo 100 > ${PROGRESS_FILE}
    exit 1
fi

echo 60 > ${PROGRESS_FILE}

# Installation des dépendances Python
echo "$(date '+%Y-%m-%d %H:%M:%S') - Installation des dépendances Python"
if [ -f "$BASEDIR/resources/requirements.txt" ]; then
    pip install -r "$BASEDIR/resources/requirements.txt"
    if [ $? -ne 0 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - ERREUR: Impossible d'installer les dépendances depuis requirements.txt"
        echo 100 > ${PROGRESS_FILE}
        exit 1
    fi
else
    # Installation directe des modules requis
    pip install paho-mqtt requests
    if [ $? -ne 0 ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - ERREUR: Impossible d'installer les modules Python"
        echo 100 > ${PROGRESS_FILE}
        exit 1
    fi
fi

echo 80 > ${PROGRESS_FILE}

# Vérification de l'installation
echo "$(date '+%Y-%m-%d %H:%M:%S') - Vérification de l'installation des modules"
python -c "import paho.mqtt.client as mqtt; import requests; print('Modules installés avec succès')"
if [ $? -ne 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERREUR: Les modules ne sont pas correctement installés"
    echo 100 > ${PROGRESS_FILE}
    exit 1
fi

echo 100 > ${PROGRESS_FILE}
echo "$(date '+%Y-%m-%d %H:%M:%S') - Installation des dépendances terminée avec succès"

# Supprimer le fichier de progression pour indiquer la fin
rm -f ${PROGRESS_FILE}
