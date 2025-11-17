FROM wordpress:6.4-php8.1-apache

# Install WP-CLI
RUN curl -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/wp-cli/v2.9.0/phar/wp-cli.phar && \
    php /tmp/wp-cli.phar --info --allow-root && \
    chmod +x /tmp/wp-cli.phar && \
    mv /tmp/wp-cli.phar /usr/local/bin/wp && \
    echo '#!/bin/bash' > /usr/local/bin/wp-cli && \
    echo 'php /usr/local/bin/wp "$@"' >> /usr/local/bin/wp-cli && \
    chmod +x /usr/local/bin/wp-cli

# Install additional utilities
RUN apt-get update && \
    apt-get install -y \
        less \
        vim \
        nano \
        default-mysql-client \
        git \
        unzip \
        curl \
        wget \
    && rm -rf /var/lib/apt/lists/*

# Set proper ownership for wp-cli cache
RUN mkdir -p /var/www/.wp-cli/cache && \
    chown -R www-data:www-data /var/www/.wp-cli

# Create wp-cli.yml config file
RUN echo "path: /var/www/html" > /var/www/wp-cli.yml && \
    echo "user: www-data" >> /var/www/wp-cli.yml && \
    chown www-data:www-data /var/www/wp-cli.yml

# Add host user to match mounted volume ownership
ARG HOST_UID=1000
ARG HOST_GID=1000

# Modify www-data user to match host user ID
RUN usermod -u ${HOST_UID} www-data && \
    groupmod -g ${HOST_GID} www-data

# Create startup script for proper permissions
RUN echo '#!/bin/bash' > /usr/local/bin/fix-permissions.sh && \
    echo '# Fix ownership of WordPress-generated directories only' >> /usr/local/bin/fix-permissions.sh && \
    echo 'mkdir -p /var/www/html/wp-content/uploads /var/www/html/wp-content/cache' >> /usr/local/bin/fix-permissions.sh && \
    echo 'chown -R www-data:www-data /var/www/html/wp-content/uploads || true' >> /usr/local/bin/fix-permissions.sh && \
    echo 'chown -R www-data:www-data /var/www/html/wp-content/cache || true' >> /usr/local/bin/fix-permissions.sh && \
    echo '# Fix wp-content base permissions but preserve mounted files' >> /usr/local/bin/fix-permissions.sh && \
    echo 'find /var/www/html/wp-content -type d -exec chmod 755 {} + 2>/dev/null || true' >> /usr/local/bin/fix-permissions.sh && \
    echo 'find /var/www/html/wp-content -name "*.php" -exec chmod 644 {} + 2>/dev/null || true' >> /usr/local/bin/fix-permissions.sh && \
    echo '# Do NOT change ownership of mounted plugin source files' >> /usr/local/bin/fix-permissions.sh && \
    echo 'exec "$@"' >> /usr/local/bin/fix-permissions.sh && \
    chmod +x /usr/local/bin/fix-permissions.sh

ENTRYPOINT ["/usr/local/bin/fix-permissions.sh"]
CMD ["apache2-foreground"]
