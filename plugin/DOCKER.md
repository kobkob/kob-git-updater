# Docker Development Environment

This document provides instructions for setting up and using the Docker-based development environment for the Kob Git Updater WordPress plugin.

## Quick Start

### Prerequisites
- Docker (20.10.0 or newer)
- Docker Compose (2.0.0 or newer)  
- Make (optional, for convenience commands)

### Start Development Environment

```bash
# Using Make (recommended)
make docker-dev

# Or using Docker Compose directly
docker-compose up -d
```

### Access the Environment

- **WordPress**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **MailCatcher**: http://localhost:1080

## Services Overview

### Core Services

#### WordPress (`wordpress`)
- **Image**: Custom Dockerfile based on WordPress 6.4 + PHP 8.1
- **Port**: 8080
- **Features**:
  - Pre-installed plugin with live reload
  - Xdebug enabled for debugging
  - WP-CLI available
  - Development constants configured
  - Composer dependencies installed

#### MySQL Database (`db`)
- **Image**: mysql:8.0
- **Port**: 3306
- **Credentials**:
  - Database: `wordpress`
  - User: `wordpress`
  - Password: `wordpress`
  - Root Password: `rootpassword`

#### phpMyAdmin (`phpmyadmin`)
- **Image**: phpmyadmin/phpmyadmin:latest
- **Port**: 8081
- **Pre-configured** to connect to the MySQL database

### Development Services

#### MailCatcher (`mailcatcher`)
- **Image**: sj26/mailcatcher:latest
- **Ports**: 1080 (web), 1025 (SMTP)
- **Purpose**: Catch and display emails sent by WordPress

#### Redis (`redis`)
- **Image**: redis:7-alpine
- **Port**: 6379
- **Purpose**: Optional caching layer

#### Test Runner (`test`)
- **Profile**: `test` (not started by default)
- **Purpose**: Run PHPUnit tests in isolated environment

## Development Workflow

### 1. Environment Setup

```bash
# Start all services
make docker-dev

# Check service status
docker-compose ps

# View logs
make docker-logs
```

### 2. Plugin Development

The plugin source code is mounted as a live volume:
- **Host**: `./` (current directory)
- **Container**: `/opt/plugin-dev` and `/var/www/html/wp-content/plugins/kob-git-updater`

Changes to your local files are immediately reflected in the WordPress container.

### 3. WordPress Setup

1. Navigate to http://localhost:8080
2. Complete WordPress installation
3. The plugin will be automatically activated
4. Access plugin settings at: **Admin → Git Updater**

### 4. Database Management

Access phpMyAdmin at http://localhost:8081:
- **Server**: db
- **Username**: wordpress  
- **Password**: wordpress

### 5. Email Testing

All emails sent by WordPress are captured by MailCatcher:
- **Web Interface**: http://localhost:1080
- **SMTP**: localhost:1025

### 6. Testing

```bash
# Run tests in Docker environment
docker-compose --profile test run test

# Or run tests using Make
make test

# Run specific test types
make test-unit
make test-lint
make test-analyze
```

## Make Commands

### Development
- `make docker-build` - Build Docker image
- `make docker-dev` - Start development environment
- `make docker-stop` - Stop environment
- `make docker-clean` - Clean Docker resources
- `make docker-logs` - View container logs

### Container Access
- `make docker-shell` - Access WordPress container shell
- `make docker-mysql` - Access MySQL container shell

### Testing
- `make test` - Run comprehensive test suite
- `make build` - Create production build
- `make clean` - Clean build artifacts

## File Structure

```
kob-git-updater/
├── docker/
│   ├── mysql/
│   │   └── init.sql              # MySQL initialization
│   ├── wordpress/
│   │   └── wp-config-custom.php  # WordPress dev config
│   └── logs/                     # Container logs
├── Dockerfile                    # WordPress dev environment
├── docker-compose.yml           # Multi-service stack
├── .dockerignore                # Docker build exclusions
└── DOCKER.md                    # This file
```

## Configuration

### WordPress Development Settings

The development environment includes:
- `WP_DEBUG=true`
- `WP_DEBUG_LOG=true` 
- `SCRIPT_DEBUG=true`
- `WP_ENVIRONMENT_TYPE=development`
- Custom error logging to `/var/log/wordpress/`

### Xdebug Configuration

Xdebug is pre-configured for debugging:
- **Mode**: debug,coverage
- **Client Host**: host.docker.internal
- **Client Port**: 9003
- **Start with Request**: yes

### PHP Settings

Development-optimized PHP configuration:
- Memory limit: 512M
- Upload max filesize: 64M
- Max execution time: 300s
- Error display: On
- Error logging: On

## Troubleshooting

### Container Issues

```bash
# Restart services
docker-compose restart

# Rebuild containers
make docker-clean
make docker-build
make docker-dev

# View container logs
docker-compose logs wordpress
docker-compose logs db
```

### Database Issues

```bash
# Reset database
docker-compose down -v
make docker-dev

# Access MySQL directly
make docker-mysql
```

### Plugin Issues

```bash
# Access WordPress container
make docker-shell

# Check plugin status
wp plugin list --path=/var/www/html --allow-root

# Activate plugin
wp plugin activate kob-git-updater --path=/var/www/html --allow-root
```

### File Permission Issues

```bash
# Fix WordPress file permissions
docker-compose exec wordpress chown -R www-data:www-data /var/www/html
```

## Performance Optimization

### For Development

The default configuration prioritizes development convenience over performance:
- All debugging enabled
- No caching
- Verbose logging
- Live file mounting

### For Testing/Staging

Modify `docker-compose.yml` to disable debug features:
```yaml
environment:
  WORDPRESS_DEBUG: 0
  WORDPRESS_CONFIG_EXTRA: |
    define('WP_DEBUG', false);
    define('WP_CACHE', true);
```

## Security Considerations

This Docker environment is designed for development only:
- Default passwords are used
- Debug mode is enabled
- File editing is disabled
- Database is accessible externally

**Never use this configuration in production!**

## Advanced Usage

### Custom Database

```bash
# Use different database
docker-compose exec db mysql -u root -p
CREATE DATABASE my_custom_db;
```

### Additional Services

```bash
# Start with tools container
docker-compose --profile tools up -d

# Use tools container for CLI operations
docker-compose exec tools bash
```

### Backup and Restore

```bash
# Backup database
docker-compose exec db mysqldump -u wordpress -p wordpress > backup.sql

# Restore database  
docker-compose exec -T db mysql -u wordpress -p wordpress < backup.sql
```