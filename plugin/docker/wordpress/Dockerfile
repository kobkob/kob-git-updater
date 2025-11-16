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