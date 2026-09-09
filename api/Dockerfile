# Alumni Career Map — API runtime image
# Slim 4 (PHP 8.3) + PDO MySQL, packs composer deps at build time.

FROM php:8.5-alpine

# composer + PDO MySQL driver + curl (health checks)
RUN apk add --no-cache composer curl \
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

# Install production deps first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --no-scripts --prefer-dist --no-autoloader

# Copy the rest of the API source
COPY . .

# Regenerate the classmap AFTER source is present
# (an authoritative classmap built too early would miss App\* classes)
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

# Listen on the built-in PHP dev server (fastest for a small stateless API)
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "public/router.php"]