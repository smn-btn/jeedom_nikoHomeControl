#!/bin/bash

# Script d'installation des dépendances pour le plugin NHC Jeedom
# Ce script installe le module paho-mqtt nécessaire au démon Python

echo "Installation des dépendances Python pour le plugin NHC..."

# Vérifier si Python3 est installé
if ! command -v python3 &> /dev/null; then
    echo "Erreur: Python3 n'est pas installé"
    exit 1
fi

# Essayer d'installer via apt (recommandé pour les systèmes Debian/Ubuntu)
echo "Tentative d'installation via apt..."

# Vérifier et installer python3-paho-mqtt
if apt list --installed python3-paho-mqtt 2>/dev/null | grep -q python3-paho-mqtt; then
    echo "python3-paho-mqtt est déjà installé via apt"
else
    echo "Installation de python3-paho-mqtt via apt..."
    sudo apt update
    sudo apt install -y python3-paho-mqtt
fi

# Vérifier et installer python3-requests
if apt list --installed python3-requests 2>/dev/null | grep -q python3-requests; then
    echo "python3-requests est déjà installé via apt"
else
    echo "Installation de python3-requests via apt..."
    sudo apt install -y python3-requests
fi

# Vérifier si toutes les dépendances sont installées
missing_modules=()

if ! python3 -c "import paho.mqtt.client" 2>/dev/null; then
    missing_modules+=("paho-mqtt")
fi

if ! python3 -c "import requests" 2>/dev/null; then
    missing_modules+=("requests")
fi

if [ ${#missing_modules[@]} -gt 0 ]; then
    echo "Modules manquants détectés: ${missing_modules[*]}"
    echo "Tentative d'installation via pip avec --break-system-packages..."
    
    for module in "${missing_modules[@]}"; do
        echo "Installation de $module via pip..."
        if ! python3 -m pip install --break-system-packages "$module"; then
            echo "Erreur: Impossible d'installer $module"
            exit 1
        fi
    done
fi

# Vérifier l'installation
echo "Vérification de l'installation..."
success=true

if python3 -c "import paho.mqtt.client; print('✓ Module paho-mqtt disponible')" 2>/dev/null; then
    echo "✓ Module paho-mqtt disponible"
else
    echo "✗ Erreur: le module paho-mqtt n'est pas disponible"
    success=false
fi

if python3 -c "import requests; print('✓ Module requests disponible')" 2>/dev/null; then
    echo "✓ Module requests disponible"
else
    echo "✗ Erreur: le module requests n'est pas disponible"
    success=false
fi

if [ "$success" = true ]; then
    echo "✓ Installation terminée avec succès!"
else
    echo "✗ Erreur: certains modules ne sont pas disponibles"
    exit 1
fi
