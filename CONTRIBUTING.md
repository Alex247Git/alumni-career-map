# Contributing

Thanks for your interest! This is a small personal/portfolio project, but useful pull requests are welcome.

## Workflow

1. Create a feature branch from `main` (one feature or fix per branch)
2. Make sure the CI gates pass locally before opening a PR:

   ```bash
   cd api
   composer install
   vendor/bin/phpcs
   vendor/bin/phpstan analyse
   vendor/bin/phpunit
   ```

3. Open a pull request describing what changed and why

## Style

- PSR-12 coding standard (enforced by `api/phpcs.xml`)
- PHPStan level 4 must stay clean
- Run the full stack locally with `docker compose up -d --build` and smoke-test your change against `http://localhost:8081`
