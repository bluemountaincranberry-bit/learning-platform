# Phone access to the local app

Use local HTTPS when testing microphone capture on iPhone or Android. Browser microphone APIs require a secure context, and the development app also loads JavaScript and CSS from Vite, so both the app and Vite need HTTPS.

## Start or refresh HTTPS

From the repository root:

```bash
make mobile-https
```

The command detects the computer's LAN IPv4 address, creates a local CA and a server certificate, updates the ignored `config/docker.env` Vite origin, then starts the HTTPS reverse proxy. Keep the computer and phone on the same Wi-Fi. Open the printed `https://<LAN-IP>:8443` URL on the phone.

If the computer is on a VPN or the detected address is wrong, choose its Wi-Fi IPv4 address explicitly:

```bash
MOBILE_HTTPS_HOST=192.168.1.64 make mobile-https
```

Run `bash scripts/setup-mobile-https-wizard.sh` for the guided one-time certificate installation steps.

## Trust the local CA

The setup creates phone-safe files under `runtime/mobile-https/public/`: `rootCA.crt` for Android and `rootCA.mobileconfig` for iPhone. That directory contains no private key. Keep `runtime/mobile-https/rootCA.key` on the development computer and never transfer it.

To download the files over your local Wi-Fi, start this temporary server from the project root and leave it running during installation:

```bash
python3 -m http.server 8765 --bind 0.0.0.0 --directory runtime/mobile-https/public
```

Open `http://<LAN-IP>:8765/rootCA.crt` on Android or `http://<LAN-IP>:8765/rootCA.mobileconfig` in Safari on iPhone. Stop the file server with Ctrl-C when both phones have downloaded their file.

On Android, use Settings → Security & privacy → More security settings → Encryption & credentials → Install a certificate → CA certificate. Android menu labels vary by device. Google documents certificate installation in [Manage advanced network settings](https://support.google.com/android/answer/9654714).

On iPhone, after downloading the `.mobileconfig`, go to Settings → Profile Downloaded and install “Learning App Local Development CA”. Then go to Settings → General → About → Certificate Trust Settings and enable full trust for the certificate. Apple documents [installing configuration profiles](https://support.apple.com/en-us/102400) and [manually trusting their certificates](https://support.apple.com/en-us/102390).

After trust is enabled, visit the app URL and grant microphone permission. If the computer's LAN IP changes, run `make mobile-https` again; the server certificate will be refreshed for that address. The root CA remains the same.

## Stop HTTPS

```bash
docker compose --env-file config/docker.env --profile mobile-https stop mobile-https
```

The default app ports remain unchanged. The HTTPS proxy uses host ports 8443 and 5443 (for Vite HMR); change `MOBILE_HTTPS_PORT` or `MOBILE_VITE_HTTPS_PORT` in `config/docker.env` if those are occupied.
