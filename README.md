# Codenames

A Laravel web app for playing Codenames around a table, with a separate view for the spymasters and for the players.

- **Spymaster view** – the full key (red / blue / neutral / assassin). Tap a card twice to reveal it, end the turn, finish the game, or deal a new board in any word list. Both spymasters open the same spymaster link on their own devices.
- **Player view** – unrevealed cards are shown in a neutral color; only revealed cards show their team color. Open it on as many devices as you like (e.g. a TV plus phones).

Every device polls the server (every 1.5 s by default), so a reveal on one spymaster device shows up everywhere. The spymaster link contains a secret token, so players can't open the key by guessing the URL.

## Word lists

Word lists live in [`resources/words`](resources/words), one word per line (`#` starts a comment):

- `en.txt` – English
- `hu.txt` – Magyar

To add a language, drop in a new `{code}.txt` file (at least 25 words) and optionally give it a display name in [`config/codenames.php`](config/codenames.php).

## Local development

Requires PHP 8.3+ and Composer.

```bash
composer setup
php artisan serve
```

Run the tests with `php artisan test`.

## Docker / TrueNAS

Every push to `main` builds and publishes `ghcr.io/feaxr/codenames:latest` via GitHub Actions (version tags `v1.2.3` also publish `1.2.3` and `1.2`). The image runs Nginx + PHP-FPM on port **8080** and stores its SQLite database in `/data`; migrations run automatically on start.

1. Generate an app key: `echo "base64:$(openssl rand -base64 32)"`
2. In TrueNAS SCALE, go to **Apps → Discover Apps → Custom App → Install via YAML** and paste [`compose.yaml`](compose.yaml), filling in `APP_KEY` and `APP_URL` (the address players will use; it is used for the share links and QR codes).
3. If you use a host path instead of the named volume for `/data`, make it owned by UID/GID `33` (`www-data`).

If the GitHub package is private, either make it public under **Package settings** or add ghcr.io credentials to TrueNAS.

| Variable | Default | Description |
| --- | --- | --- |
| `APP_KEY` | – | Required. Laravel encryption key. |
| `APP_URL` | – | Public URL of the app. |
| `CODENAMES_DEFAULT_LANGUAGE` | `en` | Word list preselected on the home page. |
| `CODENAMES_POLL_INTERVAL` | `1500` | How often devices check for updates (ms). |
