#!/usr/bin/env python3
"""
Tests unitaires pour la logique de vérification d'expiration du token JWT.
Valide la logique implémentée dans nhc.class.php::checkJwtExpiration()
Usage: python3 tests/test_jwt_expiration.py
"""

import base64
import json
import time
import sys


def base64url_encode(data: bytes) -> str:
    """Encode en base64url (sans padding)."""
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode("ascii")


def create_test_jwt(payload: dict) -> str:
    """Crée un JWT de test avec le payload donné."""
    header = base64url_encode(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    body = base64url_encode(json.dumps(payload).encode())
    signature = base64url_encode(b"test_signature")
    return f"{header}.{body}.{signature}"


def check_jwt_expiration_logic(niko_jwt: str, alert_days: int = 30) -> dict:
    """
    Reproduit la logique PHP de checkJwtExpiration() pour la tester.
    Retourne: {'status': 'expired'|'expiring'|'valid'|'error', 'days': int|None, 'message': str}
    """
    if not niko_jwt:
        return {"status": "error", "days": None, "message": "Token vide"}

    parts = niko_jwt.split(".")
    if len(parts) != 3:
        return {
            "status": "error",
            "days": None,
            "message": f"Format JWT invalide (3 parties attendues, {len(parts)} trouvées)",
        }

    # Décodage base64url -> base64 (même logique que le PHP)
    payload = parts[1]
    payload = payload.replace("-", "+").replace("_", "/")
    # Ajout du padding manquant
    padding = 4 - len(payload) % 4
    if padding != 4:
        payload += "=" * padding
    try:
        decoded = base64.b64decode(payload)
    except Exception:
        return {
            "status": "error",
            "days": None,
            "message": "Impossible de décoder le payload",
        }

    try:
        data = json.loads(decoded)
    except (json.JSONDecodeError, UnicodeDecodeError):
        return {
            "status": "error",
            "days": None,
            "message": "Payload JSON invalide",
        }

    if not isinstance(data, dict) or "exp" not in data:
        return {
            "status": "error",
            "days": None,
            "message": 'Champ "exp" absent du payload',
        }

    exp_timestamp = int(data["exp"])
    now = int(time.time())
    days_remaining = (exp_timestamp - now) / 86400

    if days_remaining <= 0:
        return {
            "status": "expired",
            "days": int(days_remaining),
            "message": f"Token expiré",
        }
    elif days_remaining <= alert_days:
        days_int = int(days_remaining) + (1 if days_remaining % 1 > 0 else 0)  # ceil
        return {
            "status": "expiring",
            "days": days_int,
            "message": f"Token expire dans {days_int} jour(s)",
        }
    else:
        days_int = int(days_remaining)
        return {
            "status": "valid",
            "days": days_int,
            "message": f"Token valide encore {days_int} jour(s)",
        }


# === Framework de test ===

passed = 0
failed = 0


def test(name: str, condition: bool):
    global passed, failed
    if condition:
        print(f"  ✅ {name}")
        passed += 1
    else:
        print(f"  ❌ {name}")
        failed += 1


# === Tests ===

print("=== Test vérification expiration JWT ===\n")

# 1. Token vide
print("1. Token vide")
result = check_jwt_expiration_logic("")
test("Status = error", result["status"] == "error")
test('Message contient "vide"', "vide" in result["message"])

# 2. Format invalide
print("\n2. Format invalide (pas 3 parties)")
result = check_jwt_expiration_logic("not.a.valid.jwt.token")
test("Status = error", result["status"] == "error")
test('Message contient "Format"', "Format" in result["message"])

result2 = check_jwt_expiration_logic("seulement_une_partie")
test("Une seule partie = error", result2["status"] == "error")

# 3. Payload non décodable
print("\n3. Payload non décodable")
result = check_jwt_expiration_logic("aaa.!!!invalid!!!.ccc")
test("Status = error", result["status"] == "error")

# 4. JWT valide sans champ exp
print("\n4. JWT valide sans champ exp")
jwt = create_test_jwt({"sub": "hobby", "iat": int(time.time())})
result = check_jwt_expiration_logic(jwt)
test("Status = error", result["status"] == "error")
test('Message contient "exp"', "exp" in result["message"])

# 5. Token expiré (il y a 10 jours)
print("\n5. Token expiré (il y a 10 jours)")
jwt = create_test_jwt({"exp": int(time.time()) - 10 * 86400})
result = check_jwt_expiration_logic(jwt)
test("Status = expired", result["status"] == "expired")
test("Days <= -10", result["days"] <= -10)

# 6. Token expire dans 5 jours (< 30 jours = alerte)
print("\n6. Token expire dans 5 jours (< 30 jours = alerte)")
jwt = create_test_jwt({"exp": int(time.time()) + 5 * 86400})
result = check_jwt_expiration_logic(jwt)
test("Status = expiring", result["status"] == "expiring")
test("Days = 5", result["days"] == 5)

# 7. Token expire dans 29 jours (< 30 jours = alerte)
print("\n7. Token expire dans 29 jours (< 30 jours = alerte)")
jwt = create_test_jwt({"exp": int(time.time()) + 29 * 86400})
result = check_jwt_expiration_logic(jwt)
test("Status = expiring", result["status"] == "expiring")
test("Days = 29", result["days"] == 29)

# 8. Token expire dans 31 jours (> 30 jours = OK)
print("\n8. Token expire dans 31 jours (> 30 jours = OK)")
jwt = create_test_jwt({"exp": int(time.time()) + 31 * 86400})
result = check_jwt_expiration_logic(jwt)
test("Status = valid", result["status"] == "valid")
test("Days = 31", result["days"] == 31)

# 9. Token expire dans 365 jours (typique Niko)
print("\n9. Token expire dans 365 jours (typique Niko)")
jwt = create_test_jwt({"exp": int(time.time()) + 365 * 86400})
result = check_jwt_expiration_logic(jwt)
test("Status = valid", result["status"] == "valid")
test("Days ~ 365", 364 <= result["days"] <= 365)

# 10. Seuil personnalisé (7 jours)
print("\n10. Seuil personnalisé (alertDays = 7)")
jwt = create_test_jwt({"exp": int(time.time()) + 10 * 86400})
result = check_jwt_expiration_logic(jwt, alert_days=7)
test("10 jours restants, seuil 7 = valid", result["status"] == "valid")

jwt = create_test_jwt({"exp": int(time.time()) + 5 * 86400})
result = check_jwt_expiration_logic(jwt, alert_days=7)
test("5 jours restants, seuil 7 = expiring", result["status"] == "expiring")

# 11. Token expire exactement maintenant
print("\n11. Token expire exactement maintenant")
jwt = create_test_jwt({"exp": int(time.time())})
result = check_jwt_expiration_logic(jwt)
test("Status = expired", result["status"] == "expired")

# 12. Vérification du décodage base64url (caractères spéciaux)
print("\n12. Décodage base64url avec caractères spéciaux (+, /, =)")
jwt = create_test_jwt({"exp": int(time.time()) + 100 * 86400, "data": "a+b/c=d"})
result = check_jwt_expiration_logic(jwt)
test("Décodage OK avec caractères spéciaux", result["status"] == "valid")

# === Résultat ===
print(f"\n=== Résultat: {passed} réussi(s), {failed} échoué(s) ===")
sys.exit(1 if failed > 0 else 0)
