"""Tests pour la logique de reconnexion MQTT du démon NHC."""

import sys
import os
import time
import unittest
from unittest.mock import MagicMock, patch

# Ajouter le répertoire du démon au path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'resources', 'demond'))

import demond

# Initialiser les variables globales du module (normalement faites par argparse dans __main__)
demond._niko_ip = "192.168.1.100"
demond._niko_jwt = "test_jwt_token_1234567890_abcdef"
demond._apikey = "test_api_key"
demond._callback = "http://localhost/callback"
demond._socketport = 55099
demond._pidfile = "/tmp/test_nhc.pid"


class TestEnsureMqttConnected(unittest.TestCase):
    """Tests pour la fonction ensure_mqtt_connected()."""

    def setUp(self):
        self._orig_client = demond.mqtt_client_instance
        self._orig_last_attempt = demond._last_mqtt_reconnect_attempt
        self._orig_ip = demond._niko_ip
        self._orig_jwt = demond._niko_jwt
        demond._niko_ip = "192.168.1.100"
        demond._niko_jwt = "test_jwt_token_1234567890"
        demond._last_mqtt_reconnect_attempt = 0

    def tearDown(self):
        demond.mqtt_client_instance = self._orig_client
        demond._last_mqtt_reconnect_attempt = self._orig_last_attempt
        demond._niko_ip = self._orig_ip
        demond._niko_jwt = self._orig_jwt

    def test_returns_true_when_already_connected(self):
        """Si le client MQTT est déjà connecté, retourne True sans rien faire."""
        mock_client = MagicMock()
        mock_client.is_connected.return_value = True
        demond.mqtt_client_instance = mock_client

        result = demond.ensure_mqtt_connected()

        self.assertTrue(result)

    def test_returns_false_when_no_config(self):
        """Si la config Niko est absente, retourne False."""
        demond.mqtt_client_instance = None
        demond._niko_ip = ""
        demond._niko_jwt = ""

        result = demond.ensure_mqtt_connected()

        self.assertFalse(result)

    def test_respects_cooldown(self):
        """Ne tente pas de reconnexion si le cooldown n'est pas écoulé."""
        demond.mqtt_client_instance = None
        demond._last_mqtt_reconnect_attempt = time.time()  # Juste maintenant

        result = demond.ensure_mqtt_connected()

        self.assertFalse(result)

    @patch('demond.start_mqtt_client')
    @patch('demond.time')
    def test_attempts_reconnection_after_cooldown(self, mock_time, mock_start):
        """Tente une reconnexion si le cooldown est écoulé."""
        demond.mqtt_client_instance = None
        demond._last_mqtt_reconnect_attempt = 0

        now = time.time()
        mock_time.time.return_value = now
        mock_time.sleep = MagicMock()

        def fake_start():
            mock_client = MagicMock()
            mock_client.is_connected.return_value = True
            demond.mqtt_client_instance = mock_client

        mock_start.side_effect = fake_start

        result = demond.ensure_mqtt_connected()

        mock_start.assert_called_once()
        self.assertTrue(result)

    @patch('demond.start_mqtt_client')
    @patch('demond.time')
    def test_returns_false_when_reconnection_fails(self, mock_time, mock_start):
        """Retourne False si la reconnexion échoue."""
        demond.mqtt_client_instance = None
        demond._last_mqtt_reconnect_attempt = 0

        mock_time.time.return_value = time.time()
        mock_time.sleep = MagicMock()

        mock_start.side_effect = lambda: None

        result = demond.ensure_mqtt_connected()

        mock_start.assert_called_once()
        self.assertFalse(result)

    @patch('demond.start_mqtt_client')
    @patch('demond.time')
    def test_cleans_up_old_client_before_reconnection(self, mock_time, mock_start):
        """Nettoie l'ancien client MQTT avant de tenter la reconnexion."""
        old_client = MagicMock()
        old_client.is_connected.return_value = False
        demond.mqtt_client_instance = old_client
        demond._last_mqtt_reconnect_attempt = 0

        mock_time.time.return_value = time.time()
        mock_time.sleep = MagicMock()

        demond.ensure_mqtt_connected()

        old_client.loop_stop.assert_called_once()
        old_client.disconnect.assert_called_once()

    @patch('demond.start_mqtt_client')
    @patch('demond.time')
    def test_updates_last_attempt_timestamp(self, mock_time, mock_start):
        """Met à jour le timestamp de dernière tentative."""
        demond.mqtt_client_instance = None
        demond._last_mqtt_reconnect_attempt = 0

        now = 1000000.0
        mock_time.time.return_value = now
        mock_time.sleep = MagicMock()

        demond.ensure_mqtt_connected()

        self.assertEqual(demond._last_mqtt_reconnect_attempt, now)


class TestSendNikoCommandReconnect(unittest.TestCase):
    """Tests pour la reconnexion dans send_niko_command()."""

    def setUp(self):
        self._orig_client = demond.mqtt_client_instance
        self._orig_com = demond.jeedom_com_instance
        demond.jeedom_com_instance = MagicMock()

    def tearDown(self):
        demond.mqtt_client_instance = self._orig_client
        demond.jeedom_com_instance = self._orig_com

    @patch('demond.ensure_mqtt_connected')
    def test_attempts_reconnect_when_disconnected(self, mock_ensure):
        """send_niko_command tente une reconnexion quand le client est déconnecté."""
        demond.mqtt_client_instance = None
        mock_ensure.return_value = False

        demond.send_niko_command("device-123", "Action", "100")

        mock_ensure.assert_called_once()
        demond.jeedom_com_instance.send_change_immediate.assert_called_once()
        call_args = demond.jeedom_com_instance.send_change_immediate.call_args[0][0]
        self.assertEqual(call_args['error'], 'mqtt_not_connected')
        self.assertEqual(call_args['device_id'], 'device-123')

    @patch('demond.ensure_mqtt_connected')
    @patch('demond.build_niko_command_message')
    def test_sends_command_after_successful_reconnect(self, mock_build, mock_ensure):
        """send_niko_command envoie la commande après une reconnexion réussie."""
        mock_client = MagicMock()
        mock_client.is_connected.return_value = False
        demond.mqtt_client_instance = mock_client

        def fake_ensure():
            mock_client.is_connected.return_value = True
            return True

        mock_ensure.side_effect = fake_ensure
        mock_build.return_value = {"Method": "devices.control"}

        mock_result = MagicMock()
        mock_result.rc = 0
        mock_client.publish.return_value = mock_result

        demond.send_niko_command("device-123", "Action", "100")

        mock_ensure.assert_called_once()
        mock_client.publish.assert_called_once()

    def test_no_reconnect_if_already_connected(self):
        """send_niko_command n'appelle pas ensure_mqtt_connected si déjà connecté."""
        mock_client = MagicMock()
        mock_client.is_connected.return_value = True
        demond.mqtt_client_instance = mock_client

        mock_result = MagicMock()
        mock_result.rc = 0
        mock_client.publish.return_value = mock_result

        with patch('demond.ensure_mqtt_connected') as mock_ensure:
            demond.send_niko_command("device-123", "Action", "100")
            mock_ensure.assert_not_called()


class TestDiscoverReconnect(unittest.TestCase):
    """Tests pour la reconnexion dans discover_niko_devices_mqtt()."""

    def setUp(self):
        self._orig_client = demond.mqtt_client_instance
        self._orig_com = demond.jeedom_com_instance
        demond.jeedom_com_instance = MagicMock()

    def tearDown(self):
        demond.mqtt_client_instance = self._orig_client
        demond.jeedom_com_instance = self._orig_com

    @patch('demond.ensure_mqtt_connected')
    def test_attempts_reconnect_for_discovery(self, mock_ensure):
        """discover_niko_devices_mqtt tente une reconnexion quand déconnecté."""
        demond.mqtt_client_instance = None
        mock_ensure.return_value = False

        result = demond.discover_niko_devices_mqtt()

        mock_ensure.assert_called_once()
        self.assertEqual(result, [])

    @patch('demond.request_all_device_status')
    @patch('demond.ensure_mqtt_connected')
    def test_proceeds_after_successful_reconnect(self, mock_ensure, mock_request):
        """discover_niko_devices_mqtt continue après reconnexion réussie."""
        mock_client = MagicMock()
        mock_client.is_connected.return_value = False
        demond.mqtt_client_instance = mock_client

        def fake_ensure():
            mock_client.is_connected.return_value = True
            return True

        mock_ensure.side_effect = fake_ensure

        result = demond.discover_niko_devices_mqtt()

        mock_ensure.assert_called_once()
        self.assertIsInstance(result, list)


if __name__ == '__main__':
    unittest.main()
