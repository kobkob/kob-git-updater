# Dockerfile for Kob Git Updater WordPress Plugin Development
FROM wordpress:6.4-php8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # Development tools
    git \
    vim \
    curl \
    wget \
    unzip \
    # PHP extensions and tools
    php-xdebug \
    php-zip \
    php-mbstring \
    php-xml \
    php-curl \
    # Database tools
    default-mysql-client \
    # Build tools
    make \
    # File watching tools
    inotify-tools \
    # Cleanup
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Xdebug for development
RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Configure PHP for development
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "display_errors=On" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "log_errors=On" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "error_log=/var/log/php_errors.log" >> /usr/local/etc/php/conf.d/dev.ini

# Enable Apache mod_rewrite for pretty permalinks
RUN a2enmod rewrite

# Create development directories
RUN mkdir -p /var/www/html/wp-content/plugins/kob-git-updater \
    && mkdir -p /opt/plugin-dev \
    && mkdir -p /var/log/kob-git-updater

# Set working directory for plugin development
WORKDIR /opt/plugin-dev

# Copy plugin source code
COPY . /opt/plugin-dev/

# Install Composer dependencies if composer.json exists
RUN if [ -f composer.json ]; then \
        composer install --no-interaction --optimize-autoloader; \
    fi

# Create symlink to WordPress plugins directory
RUN ln -sf /opt/plugin-dev /var/www/html/wp-content/plugins/kob-git-updater

# Create development script
RUN cat > /usr/local/bin/dev-setup.sh << 'EOF' && chmod +x /usr/local/bin/dev-setup.sh
#!/bin/bash
# Development setup script for WordPress container

echo "Setting up Kob Git Updater development environment..."

# Wait for WordPress to be ready
until wp core is-installed --path=/var/www/html --allow-root 2>/dev/null; do
    echo "Waiting for WordPress to be ready..."
    sleep 5
done

# Activate the plugin
wp plugin activate kob-git-updater --path=/var/www/html --allow-root

# Set development constants
wp config set WP_DEBUG true --raw --path=/var/www/html --allow-root
wp config set WP_DEBUG_LOG true --raw --path=/var/www/html --allow-root
wp config set WP_DEBUG_DISPLAY false --raw --path=/var/www/html --allow-root
wp config set SCRIPT_DEBUG true --raw --path=/var/www/html --allow-root

echo "Development environment ready!"
echo "Plugin installed at: /var/www/html/wp-content/plugins/kob-git-updater"
echo "Access WordPress at: http://localhost:8080"
echo "Admin credentials will be displayed during first setup"

EOF

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Create entrypoint script
RUN cat > /usr/local/bin/docker-entrypoint-dev.sh << 'EOF' && chmod +x /usr/local/bin/docker-entrypoint-dev.sh
#!/bin/bash
set -e

# Run the original WordPress entrypoint
exec docker-entrypoint.sh "$@" &
WORDPRESS_PID=$!

# Wait a bit for WordPress to start
sleep 10

# Run development setup in background
/usr/local/bin/dev-setup.sh &

# Wait for WordPress process
wait $WORDPRESS_PID
EOF

# Expose port 80 for Apache
EXPOSE 80

# Add labels for documentation
LABEL maintainer="Kobkob LLC <filipo@kobkob.org>"
LABEL description="WordPress development environment for Kob Git Updater plugin"
LABEL version="1.3.0"

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-dev.sh"]
CMD ["apache2-foreground"]