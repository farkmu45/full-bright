# Deploy dengan Docker (VPS)

Stack terdiri dari empat container yang didefinisikan di `compose.yaml`:

| Service | Isi |
|---|---|
| `web` | nginx, melayani `public/` dan meneruskan PHP ke `app` |
| `app` | PHP-FPM 8.4; saat start menjalankan `migrate --force` dan `optimize` |
| `ssr` | Node 22 menjalankan `bootstrap/ssr/ssr.js` (Inertia SSR, port 13714) |
| `db` | MySQL 8.4 dengan volume persisten |

## Langkah pertama kali

1. Salin env dan isi nilainya:

    ```bash
    cp .env.docker.example .env.docker
    ```

    Wajib diisi: `APP_KEY`, `APP_URL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`.
    `APP_KEY` bisa dibuat dengan `php artisan key:generate --show`.

2. Build dan jalankan:

    ```bash
    docker compose --env-file .env.docker up -d --build
    ```

    Situs tersedia di port `APP_PORT` (default `8080`). Pasang reverse proxy
    (Caddy/nginx/Traefik) di depannya untuk HTTPS.

## Update

```bash
git pull
docker compose --env-file .env.docker up -d --build
```

Cache HTML landing page otomatis berganti karena kuncinya memakai waktu build
`public/build/manifest.json`.

## Perintah berguna

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f app ssr
docker compose --env-file .env.docker exec app php artisan cache:clear
```
