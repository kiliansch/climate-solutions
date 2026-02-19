# climate-solutions

A dockerized Symfony 7 development application using PHP 8.3, PostgreSQL 16, and Nginx with SSL.

## Requirements

- Docker
- Docker Compose

## Setup

1. Clone the repository
2. Copy the environment file and adjust values if needed:
   ```bash
   cp .env .env.local
   ```
3. Build and start the containers:
   ```bash
   docker compose up -d --build
   ```

The application will be available at https://localhost.

## Services

| Service    | URL / Port         | Description             |
|------------|--------------------|-------------------------|
| nginx      | https://localhost  | Web server (SSL)        |
| php        | -                  | PHP 8.3-FPM             |
| database   | localhost:5432     | PostgreSQL 16           |

## Useful Commands

```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# View logs
docker compose logs -f

# Run Symfony console commands
docker compose exec php php bin/console <command>

# Access PHP container shell
docker compose exec php bash
```

Or use the provided `Makefile`:

```bash
make up       # Start containers
make down     # Stop containers
make logs     # View logs
make shell    # Access PHP container
make console  # Run Symfony console commands
```

## Environment Variables

The main configuration is in `.env`. For local overrides, create a `.env.local` file (not committed to git).

| Variable            | Default         | Description          |
|---------------------|-----------------|----------------------|
| `APP_ENV`           | `dev`           | Symfony environment  |
| `APP_SECRET`        | (set in .env)   | Symfony app secret   |
| `DATABASE_URL`      | (set in .env)   | PostgreSQL URL       |
| `POSTGRES_DB`       | `app`           | Database name        |
| `POSTGRES_USER`     | `app`           | Database user        |
| `POSTGRES_PASSWORD` | `!ChangeMe!`    | Database password    |

## SSL

Self-signed SSL certificates are included in `docker/nginx/ssl/` for development purposes.
The browser will show a security warning which you can safely accept for local development.
