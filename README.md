# Alumni Career Map

A platform to visualise where graduates end up. Map of alumni worldwide (Leaflet/OpenStreetMap), job analytics by country, searchable directory, and a full REST API.

> Built as an individual project during my MSc studies.
> Seed data is 100% synthetic (fake names, `@example.com` emails, fictional companies).

## Features

- **Interactive map** — every alumnus' current job pinned with real-world coordinates (Leaflet + OSM)
- **Analytics** — bar chart of graduate distribution by country, alumni count
- **Search** — multi-criteria (name, enrollment year, graduation year, country) with pagination; JSON **and** XML
- **Auth** — JWT login/registration, ownership authorization (only the owner edits/deletes their jobs)
- **REST API** — Slim Framework 4 (PHP), layered architecture (Controllers / Domain / Infrastructure / Middleware)
- **Stack**: PHP (Slim 4), MySQL, vanilla JS + Bootstrap 5 frontend, Docker

## Repository layout

```
api/                 Slim 4 REST API (PHP)
  app/settings.php   config (reads DB/JWT from env vars)
  sql/schema.sql     database schema
  sql/seed.sql       20 synthetic graduates + jobs
  sql/update_schema.sql  adds password column + API user
  .env.example       env template (copy to .env)
frontend/            vanilla JS + Bootstrap 5 single-page app
.htaccess            routes /api/* to Slim, else serves frontend
```

## Quick start

1. **Database**
   ```
   mysql -u root -p < api/sql/schema.sql
   mysql -u root -p < api/sql/seed.sql
   mysql -u root -p < api/sql/update_schema.sql
   ```
   Then override the passwords: edit `api/.env` (copy from `api/.env.example`) and update the `GRANT`/`CREATE USER` in `update_schema.sql` to match.
2. **API** (from `api/`)
   ```
   composer install
   export $(cat .env | xargs)   # load DB_PASS / JWT_SECRET etc.
   php -S 0.0.0.0:8081 -t public
   ```
   (or `docker-compose up` from `api/`)
3. **Frontend** — point a static server at `frontend/` and set `API_BASE` in `frontend/js/api.js` to your API origin.

## API endpoints

| Method | Path | Auth |
|---|---|---|
| POST | `/api/v1/auth/login` | – (returns JWT) |
| POST | `/api/v1/alumni` | – (register) |
| GET | `/api/v1/alumni/count` | JWT |
| GET | `/api/v1/alumni` | JWT |
| GET | `/api/v1/alumni/search?name=&enrollment_year=&graduation_year=&country=&page=&format=json\|xml` | JWT |
| GET | `/api/v1/alumni/{id}/jobs` | JWT |
| PUT | `/api/v1/alumni/{id}/jobs/{jobId}` | JWT + ownership |
| DELETE | `/api/v1/alumni/{id}/jobs/{jobId}` | JWT + ownership |

## Roadmap

- React + MUI frontend (leaflet-react, Recharts, dark theme)
- OpenAPI/Swagger docs
- GitHub Actions CI (phpunit + phpstan already configured in `api/`)

## License

[MIT](LICENSE)