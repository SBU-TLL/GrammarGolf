# syntax=docker/dockerfile:1
###############################################################################
# Production image — GrammarGolf (grammar practice "golf" game)
#
# Tech stack : PHP + jQuery/JS game. Document root is ./public (index.php is the
#              LTI/Shibboleth launch entry; index.html/scripts are the game UI).
#              No database — user progress is written to JSON files under a
#              data/ directory OUTSIDE the web root.
# Web server : Apache (php:8.3-apache), DocumentRoot -> /var/www/html/public.
#
# Authentication: LTI launch (LMS POSTs lis_person_* -> $_SESSION) OR Shibboleth
#   ($_SERVER mail/givenName/...). Shibboleth is enforced at the ingress /
#   reverse proxy (Ansible-managed). No secrets or DB (see
#   .env.production.example).
#   NOTE: auth.php/index.php contain a dev identity shim gated on the hostname
#   containing "ddev" — inert in production (non-ddev host), but it ships in the
#   app code rather than being fenced under .ddev/. Flagged, not changed.
#
# EXTERNAL INTERNAL-PROJECT DEPENDENCY: the grade-passback posts to
#   /LTI/postLTI.php (public/grading.js) — an absolute path to the separate
#   internal "lti" project, NOT bundled here. In production that path must be
#   served at the same origin (deploy the lti app there / route /LTI/ via the
#   ingress) or grade passback will 404.
#
# Runs non-root (www-data) on unprivileged port 8080.
###############################################################################
FROM php:8.3-apache

# --- Apache modules (headers for parity; no active .htaccess/rewrites) ---
RUN set -eux; \
    a2enmod headers

# --- Serve the app from ./public ---
RUN set -eux; \
    sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!' \
        /etc/apache2/sites-available/000-default.conf

# --- Run as a non-root user on an unprivileged port (8080) ---
RUN set -eux; \
    sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    sed -ri 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf

# --- Security hardening (suppress server tokens/signature, TRACE, ETag) ---
RUN set -eux; \
    { \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo 'TraceEnable Off'; \
      echo 'FileETag None'; \
    } > /etc/apache2/conf-available/zzz-hardening.conf; \
    a2enconf zzz-hardening

# --- Docroot policy: parse .htaccess (AllowOverride All), no dir listing,
#     log to stdout/stderr for container log capture ---
RUN set -eux; \
    { \
      echo '<Directory /var/www/html/public>'; \
      echo '    Options -Indexes +FollowSymLinks'; \
      echo '    AllowOverride All'; \
      echo '    Require all granted'; \
      echo '</Directory>'; \
      echo 'ErrorLog /dev/stderr'; \
      echo 'CustomLog /dev/stdout combined'; \
    } > /etc/apache2/conf-available/zzz-docroot.conf; \
    a2enconf zzz-docroot

# --- Application code. .dockerignore excludes .ddev/, .git/, .env*, Dockerfile,
#     the committed sess_* files, dev scripts (*.py), notes (*.txt/*.md) and
#     OS junk. ---
COPY --chown=www-data:www-data . /var/www/html/

# --- Permissions: read-only app tree owned by www-data, plus a writable data/
#     dir (OUTSIDE the web root) where the app saves per-user progress JSON.
#     Mount a volume here in production for persistence. ---
RUN set -eux; \
    find /var/www/html -type d -exec chmod 0755 {} +; \
    find /var/www/html -type f -exec chmod 0644 {} +; \
    mkdir -p /var/www/html/data; \
    chown -R www-data:www-data /var/www/html/data; \
    chmod 0775 /var/www/html/data; \
    chown -R www-data:www-data /var/run/apache2 /var/log/apache2 /var/lock; \
    chmod -R g=u /var/run/apache2 /var/log/apache2 /var/lock

USER www-data
EXPOSE 8080
VOLUME ["/var/www/html/data"]

# php:apache base CMD = apache2-foreground
