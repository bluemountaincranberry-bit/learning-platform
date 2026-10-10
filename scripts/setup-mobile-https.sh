#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/config/docker.env"
CERT_DIR="$ROOT_DIR/runtime/mobile-https"
PUBLIC_CERT_DIR="$CERT_DIR/public"

if [[ ! -f "$ENV_FILE" ]]; then
  printf 'Missing %s. Copy config/docker.env.example first.\n' "$ENV_FILE" >&2
  exit 1
fi
command -v openssl >/dev/null || { printf 'openssl is required.\n' >&2; exit 1; }
command -v docker >/dev/null || { printf 'Docker is required.\n' >&2; exit 1; }

host="${MOBILE_HTTPS_HOST:-${1:-}}"
if [[ -z "$host" ]]; then
  host="$(ip -o -4 addr show scope global 2>/dev/null \
    | awk '$2 !~ /^(docker|br-|veth|wginno|tun|wg|tailscale)/ { split($4, address, "/"); print address[1]; exit }')"
fi
if [[ ! "$host" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
  printf 'Could not determine a LAN IPv4 address. Run with MOBILE_HTTPS_HOST=192.168.x.x.\n' >&2
  exit 1
fi
python3 - "$host" <<'PY'
import ipaddress, sys
ipaddress.IPv4Address(sys.argv[1])
PY

mkdir -p "$CERT_DIR"
chmod 700 "$CERT_DIR"
if [[ -e "$CERT_DIR/rootCA.key" || -e "$CERT_DIR/rootCA.crt" ]]; then
  if [[ ! -s "$CERT_DIR/rootCA.key" || ! -s "$CERT_DIR/rootCA.crt" ]]; then
    rm -f "$CERT_DIR/rootCA.key" "$CERT_DIR/rootCA.crt"
  fi
fi
if [[ ! -s "$CERT_DIR/rootCA.key" || ! -s "$CERT_DIR/rootCA.crt" ]]; then
  openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
    -keyout "$CERT_DIR/rootCA.key" -out "$CERT_DIR/rootCA.crt" \
    -subj "/CN=Learning App Local Development CA" \
    -addext "basicConstraints=critical,CA:TRUE" \
    -addext "keyUsage=critical,keyCertSign,cRLSign" >/dev/null 2>&1
  chmod 600 "$CERT_DIR/rootCA.key"
fi

openssl req -new -newkey rsa:2048 -nodes -sha256 \
  -keyout "$CERT_DIR/server.key" -out "$CERT_DIR/server.csr" \
  -subj "/CN=$host" >/dev/null 2>&1
cat > "$CERT_DIR/server.ext" <<EOF
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=IP:$host,IP:127.0.0.1,DNS:localhost
EOF
openssl x509 -req -sha256 -days 397 -in "$CERT_DIR/server.csr" \
  -CA "$CERT_DIR/rootCA.crt" -CAkey "$CERT_DIR/rootCA.key" \
  -CAcreateserial -out "$CERT_DIR/server.crt" -extfile "$CERT_DIR/server.ext" >/dev/null 2>&1
chmod 600 "$CERT_DIR/server.key"
rm -f "$CERT_DIR/server.csr" "$CERT_DIR/server.ext" "$CERT_DIR/rootCA.srl"

mkdir -p "$PUBLIC_CERT_DIR"
cp "$CERT_DIR/rootCA.crt" "$PUBLIC_CERT_DIR/rootCA.crt"
openssl x509 -in "$CERT_DIR/rootCA.crt" -outform DER -out "$CERT_DIR/rootCA.der"
python3 - "$CERT_DIR/rootCA.der" "$PUBLIC_CERT_DIR/rootCA.mobileconfig" <<'PY'
from pathlib import Path
import plistlib, sys, uuid

certificate = Path(sys.argv[1]).read_bytes()
profile_uuid = str(uuid.uuid4()).upper()
certificate_uuid = str(uuid.uuid4()).upper()
profile = {
    "PayloadContent": [{
        "PayloadContent": certificate,
        "PayloadDescription": "Installs the local CA used to access the Learning App dev server.",
        "PayloadDisplayName": "Learning App Local Development CA",
        "PayloadEnabled": True,
        "PayloadIdentifier": "com.learningapp.local-development-ca.certificate",
        "PayloadType": "com.apple.security.root",
        "PayloadUUID": certificate_uuid,
        "PayloadVersion": 1,
    }],
    "PayloadDescription": "Trust the local development certificate for the Learning App.",
    "PayloadDisplayName": "Learning App Local Development CA",
    "PayloadIdentifier": "com.learningapp.local-development-ca",
    "PayloadOrganization": "Learning App local development",
    "PayloadRemovalDisallowed": False,
    "PayloadScope": "User",
    "PayloadType": "Configuration",
    "PayloadUUID": profile_uuid,
    "PayloadVersion": 1,
}
Path(sys.argv[2]).write_bytes(plistlib.dumps(profile, fmt=plistlib.FMT_XML))
PY
chmod 644 "$PUBLIC_CERT_DIR/rootCA.crt" "$PUBLIC_CERT_DIR/rootCA.mobileconfig"
rm -f "$CERT_DIR/rootCA.der"

python3 - "$ENV_FILE" "https://$host:5443" <<'PY'
from pathlib import Path
import sys
path, value = Path(sys.argv[1]), sys.argv[2]
lines = path.read_text().splitlines()
lines = [line for line in lines if not line.startswith("VITE_DEV_SERVER_ORIGIN=")]
lines.append(f"VITE_DEV_SERVER_ORIGIN={value}")
path.write_text("\n".join(lines) + "\n")
PY

cd "$ROOT_DIR"
MOBILE_HTTPS_HOST="$host" docker compose --env-file "$ENV_FILE" --profile mobile-https up -d --force-recreate mobile-https
printf '\nMobile HTTPS is ready: https://%s:8443\n' "$host"
printf 'Vite HMR: https://%s:5443\n' "$host"
printf 'Install this public certificate on each phone: %s/rootCA.crt\n' "$CERT_DIR"
printf 'iPhone profile: %s/rootCA.mobileconfig\n' "$PUBLIC_CERT_DIR"
printf 'To download phone-safe files over Wi-Fi, run: python3 -m http.server 8765 --bind 0.0.0.0 --directory %s\n' "$PUBLIC_CERT_DIR"
printf 'Keep rootCA.key on this computer; do not transfer it.\n'
