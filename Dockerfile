# ---- Stage 1: install PHP deps ----
FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ---- Stage 2: runtime ----
FROM php:8.3-apache

# Enable needed Apache modules
RUN a2enmod rewrite headers

# Allow .htaccess overrides in the web root
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Copy vendor from deps stage
COPY --from=deps /app/vendor /var/www/html/vendor

# Copy site files
COPY index.html send-mail.php .htaccess /var/www/html/

# Apache listens on 80 — Dokploy's Traefik handles HTTPS termination
EXPOSE 80

# Default CMD from php:8.3-apache starts Apache in foreground
