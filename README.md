# climate-solutions

A dockerized Symfony development environment using PHP-FPM, Nginx (HTTPS), and PostgreSQL.

## Stack

| Service    | Technology               |
|------------|--------------------------|
| Application | Symfony (latest skeleton) |
| PHP        | PHP 8.3 FPM              |
| Web server  | Nginx 1.27 + TLS         |
| Database    | PostgreSQL 16            |

## Requirements

- [Docker](https://docs.docker.com/get-docker/) + [Docker Compose v2](https://docs.docker.com/compose/)
- `make`
- `openssl` (for SSL certificate generation)

## First-time setup

```bash
# 1. Clone and enter the project
git clone <repo-url> climate-solutions
cd climate-solutions

# 2. Copy the environment file and adjust values if needed
cp .env .env.local

# 3. Generate SSL cert, build images, and start containers
#    Symfony is installed automatically on first start
make install
```

> The first `make install` takes a few minutes — it builds the PHP image,
> generates a self-signed certificate, and installs the Symfony skeleton via Composer.

## Daily workflow

```bash
make up          # start containers
make down        # stop containers
make logs        # follow all logs
make bash        # shell into the PHP container
make cc          # clear Symfony cache
make migrate     # run Doctrine migrations
make console CMD="debug:router"   # any bin/console command
make composer CMD="require foo/bar"
```

Run `make help` to see all available targets.

## Access

| URL                      | Service       |
|--------------------------|---------------|
| https://localhost        | Symfony app   |
| http://localhost         | → redirects to HTTPS |
| localhost:5432           | PostgreSQL    |

The self-signed certificate will trigger a browser warning — accept it or
import `docker/nginx/ssl/cert.pem` into your system/browser trust store.

## Environment variables

Copy `.env` to `.env.local` and override any values:

| Variable            | Default | Description                       |
|---------------------|---------|-----------------------------------|
| `APP_ENV`           | `dev`   | Symfony environment               |
| `APP_SECRET`        | —       | **Change this** for any real use  |
| `POSTGRES_DB`       | `app`   | Database name                     |
| `POSTGRES_USER`     | `app`   | Database user                     |
| `POSTGRES_PASSWORD` | `app`   | Database password                 |

## Project structure

```
.
├── docker/
│   ├── nginx/
│   │   ├── default.conf       # Nginx virtual host (HTTP → HTTPS, PHP-FPM)
│   │   ├── generate-ssl.sh    # Self-signed cert generator
│   │   └── ssl/               # Generated certs (gitignored)
│   └── php/
│       ├── Dockerfile         # PHP 8.3-FPM image
│       ├── entrypoint.sh      # Auto-installs Symfony on first run
│       └── php.ini            # PHP settings
├── docker-compose.yml
├── Makefile
├── .env                       # Shared defaults (commit this)
└── .env.local                 # Local overrides (gitignored)
```