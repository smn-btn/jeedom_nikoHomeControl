#!/usr/bin/env php
<?php
/**
 * Tests unitaires pour la vérification d'expiration du token JWT
 * Usage: php tests/test_jwt_expiration.php
 */

$passed = 0;
$failed = 0;

function base64url_encode($data) {
    return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($data));
}

function createTestJwt($payload) {
    $header = base64url_encode(json_encode(array('alg' => 'HS256', 'typ' => 'JWT')));
    $body = base64url_encode(json_encode($payload));
    $signature = base64url_encode('test_signature');
    return $header . '.' . $body . '.' . $signature;
}

/**
 * Reproduit la logique de checkJwtExpiration pour tester sans Jeedom
 * @return array ['status' => 'expired'|'expiring'|'valid'|'error', 'days' => int|null, 'message' => string]
 */
function checkJwtExpirationLogic($niko_jwt, $alertDays = 30) {
    if (empty($niko_jwt)) {
        return array('status' => 'error', 'days' => null, 'message' => 'Token vide');
    }

    $parts = explode('.', $niko_jwt);
    if (count($parts) !== 3) {
        return array('status' => 'error', 'days' => null, 'message' => 'Format JWT invalide (3 parties attendues, ' . count($parts) . ' trouvées)');
    }

    $payload = $parts[1];
    $payload = str_replace(array('-', '_'), array('+', '/'), $payload);
    $decoded = base64_decode($payload, true);
    if ($decoded === false) {
        return array('status' => 'error', 'days' => null, 'message' => 'Impossible de décoder le payload');
    }

    $data = json_decode($decoded, true);
    if (!is_array($data) || !isset($data['exp'])) {
        return array('status' => 'error', 'days' => null, 'message' => 'Champ "exp" absent du payload');
    }

    $expTimestamp = intval($data['exp']);
    $now = time();
    $daysRemaining = ($expTimestamp - $now) / 86400;

    if ($daysRemaining <= 0) {
        return array('status' => 'expired', 'days' => intval(floor($daysRemaining)), 'message' => 'Token expiré le ' . date('d/m/Y H:i', $expTimestamp));
    } elseif ($daysRemaining <= $alertDays) {
        return array('status' => 'expiring', 'days' => intval(ceil($daysRemaining)), 'message' => 'Token expire dans ' . intval(ceil($daysRemaining)) . ' jour(s)');
    } else {
        return array('status' => 'valid', 'days' => intval(floor($daysRemaining)), 'message' => 'Token valide encore ' . intval(floor($daysRemaining)) . ' jour(s)');
    }
}

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ $name\n";
        $passed++;
    } else {
        echo "  ❌ $name\n";
        $failed++;
    }
}

// === Tests ===

echo "=== Test vérification expiration JWT ===\n\n";

// --- Token vide ---
echo "1. Token vide\n";
$result = checkJwtExpirationLogic('');
test('Status = error', $result['status'] === 'error');
test('Message contient "vide"', strpos($result['message'], 'vide') !== false);

// --- Format invalide ---
echo "\n2. Format invalide (pas 3 parties)\n";
$result = checkJwtExpirationLogic('not.a.valid.jwt.token');
test('Status = error', $result['status'] === 'error');
test('Message contient "Format"', strpos($result['message'], 'Format') !== false);

$result2 = checkJwtExpirationLogic('seulement_une_partie');
test('Une seule partie = error', $result2['status'] === 'error');

// --- Payload non décodable ---
echo "\n3. Payload non décodable (base64 invalide)\n";
$result = checkJwtExpirationLogic('aaa.!!!invalid!!!.ccc');
test('Status = error', $result['status'] === 'error');

// --- Pas de champ exp ---
echo "\n4. JWT valide sans champ exp\n";
$jwt = createTestJwt(array('sub' => 'hobby', 'iat' => time()));
$result = checkJwtExpirationLogic($jwt);
test('Status = error', $result['status'] === 'error');
test('Message contient "exp"', strpos($result['message'], 'exp') !== false);

// --- Token expiré (il y a 10 jours) ---
echo "\n5. Token expiré (il y a 10 jours)\n";
$jwt = createTestJwt(array('exp' => time() - 10 * 86400));
$result = checkJwtExpirationLogic($jwt);
test('Status = expired', $result['status'] === 'expired');
test('Days <= -10', $result['days'] <= -10);
test('Message contient "expiré"', strpos($result['message'], 'expir') !== false);

// --- Token expire dans 5 jours ---
echo "\n6. Token expire dans 5 jours (< 30 jours = alerte)\n";
$jwt = createTestJwt(array('exp' => time() + 5 * 86400));
$result = checkJwtExpirationLogic($jwt);
test('Status = expiring', $result['status'] === 'expiring');
test('Days = 5', $result['days'] === 5);
test('Message contient "expire dans"', strpos($result['message'], 'expire dans') !== false);

// --- Token expire dans 29 jours ---
echo "\n7. Token expire dans 29 jours (< 30 jours = alerte)\n";
$jwt = createTestJwt(array('exp' => time() + 29 * 86400));
$result = checkJwtExpirationLogic($jwt);
test('Status = expiring', $result['status'] === 'expiring');
test('Days = 29', $result['days'] === 29);

// --- Token expire dans 31 jours ---
echo "\n8. Token expire dans 31 jours (> 30 jours = OK)\n";
$jwt = createTestJwt(array('exp' => time() + 31 * 86400));
$result = checkJwtExpirationLogic($jwt);
test('Status = valid', $result['status'] === 'valid');
test('Days = 31', $result['days'] === 31);
test('Message contient "valide"', strpos($result['message'], 'valide') !== false);

// --- Token expire dans 365 jours ---
echo "\n9. Token expire dans 365 jours (typique Niko)\n";
$jwt = createTestJwt(array('exp' => time() + 365 * 86400));
$result = checkJwtExpirationLogic($jwt);
test('Status = valid', $result['status'] === 'valid');
test('Days ~ 365', $result['days'] >= 364 && $result['days'] <= 365);

// --- Seuil personnalisé (7 jours) ---
echo "\n10. Seuil personnalisé (alertDays = 7)\n";
$jwt = createTestJwt(array('exp' => time() + 10 * 86400));
$result = checkJwtExpirationLogic($jwt, 7);
test('10 jours restants, seuil 7 = valid', $result['status'] === 'valid');

$jwt = createTestJwt(array('exp' => time() + 5 * 86400));
$result = checkJwtExpirationLogic($jwt, 7);
test('5 jours restants, seuil 7 = expiring', $result['status'] === 'expiring');

// --- Token expiré exactement maintenant ---
echo "\n11. Token expire exactement maintenant\n";
$jwt = createTestJwt(array('exp' => time()));
$result = checkJwtExpirationLogic($jwt);
test('Status = expired', $result['status'] === 'expired');

// === Résultat ===
echo "\n=== Résultat: $passed réussi(s), $failed échoué(s) ===\n";
exit($failed > 0 ? 1 : 0);
