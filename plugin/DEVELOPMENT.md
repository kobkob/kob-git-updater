# Development Guide

This guide covers the complete development workflow for the Kob Git Updater WordPress plugin, including local development, Docker containerization, and automated testing.

## Development Options

### Option 1: Local Development (Traditional)

**Requirements:**
- PHP 8.1+
- Composer
- Local WordPress installation

**Setup:**
```bash
# Install dependencies
make install

# Run tests
make test

# Create builds
make build
```

### Option 2: Docker Development (Recommended)

**Requirements:**
- Docker 20.10.0+
- Docker Compose 2.0.0+

**Setup:**
```bash
# Start complete WordPress environment
make docker-dev

# Access WordPress at http://localhost:8080
# Access phpMyAdmin at http://localhost:8081
# Access MailCatcher at http://localhost:1080
```

## Quick Start

### For New Contributors

```bash
# Clone and setup
git clone <repository-url>
cd kob-git-updater

# Option A: Docker (Recommended)
make docker-dev

# Option B: Local
make install
```

### For Daily Development

```bash
# Check status
make status

# Run tests before committing
make test

# Create development build for testing
make build-dev

# Deploy when ready
make deploy
```

## Makefile Commands Reference

The Makefile provides a unified interface for all development tasks:

### Development Commands
| Command | Description | Equivalent |
|---------|-------------|------------|
| `make help` | Show all available commands | - |
| `make install` | Install dependencies and setup | `composer install && ../scripts/setup-dev.sh` |
| `make status` | Show project status | `../scripts/dev.sh status` |
| `make version` | Display current version | `grep Version kob-git-updater-new.php` |

### Testing Commands
| Command | Description | Equivalent |
|---------|-------------|------------|
| `make test` | Run comprehensive test suite | `../scripts/test.sh` |
| `make test-unit` | Run PHPUnit tests only | `composer run test` |
| `make test-lint` | Run PHP CodeSniffer | `composer run lint` |
| `make test-analyze` | Run PHPStan analysis | `composer run analyze` |
| `make test-watch` | Watch files and auto-test | `inotifywait` + `make test-unit` |

### Building Commands
| Command | Description | Equivalent |
|---------|-------------|------------|
| `make build` | Create production build | `../scripts/build.sh` |
| `make build-dev` | Create development build | `../scripts/quick-build.sh` |

### Docker Commands
| Command | Description | Docker Compose |
|---------|-------------|----------------|
| `make docker-dev` | Start development stack | `docker-compose up -d` |
| `make docker-stop` | Stop all services | `docker-compose down` |
| `make docker-shell` | Access WordPress container | `docker-compose exec wordpress bash` |
| `make docker-mysql` | Access MySQL shell | `docker-compose exec db mysql -u wordpress -p` |
| `make docker-logs` | View container logs | `docker-compose logs -f` |

### Maintenance Commands
| Command | Description | What it does |
|---------|-------------|--------------|
| `make clean` | Clean build artifacts | Remove `vendor/`, `build/`, `dist/` |
| `make reset` | Reset environment | `make clean` + `make install` |
| `make validate` | Validate project structure | Check required files exist |

## Docker Development Stack

### Services Overview

```yaml
# Core Services (always running)
wordpress:    # WordPress + Plugin (Port 8080)
db:           # MySQL 8.0 (Port 3306) 
phpmyadmin:   # Database admin (Port 8081)
mailcatcher:  # Email testing (Port 1080)
redis:        # Caching layer (Port 6379)

# Development Services (profiles)
tools:        # CLI tools container
test:         # Test runner environment
```

### Development Workflow with Docker

1. **Start Environment**
   ```bash
   make docker-dev
   ```

2. **Setup WordPress** (one-time)
   - Visit http://localhost:8080
   - Complete WordPress setup
   - Plugin is auto-activated

3. **Develop** (live reload enabled)
   ```bash
   # Edit files locally - changes appear immediately
   vim src/Core/Plugin.php
   
   # Run tests
   make test
   
   # View logs
   make docker-logs
   ```

4. **Debug** (Xdebug enabled)
   - Set breakpoints in your IDE
   - Configure IDE to connect to localhost:9003
   - Debug requests in real-time

5. **Test Email** (MailCatcher)
   - WordPress emails → http://localhost:1080
   - SMTP server: localhost:1025

6. **Database** (phpMyAdmin)  
   - Access: http://localhost:8081
   - Credentials: wordpress/wordpress

### File Synchronization

```
Host (your machine)     →    Docker Container
./                      →    /opt/plugin-dev
./                      →    /var/www/html/wp-content/plugins/kob-git-updater
```

Changes to local files are immediately reflected in WordPress.

## Integration with Scripts

The Makefile intelligently uses existing scripts when available:

```bash
# Makefile checks for scripts and uses them
if [ -x "../scripts/test.sh" ]; then
    ../scripts/test.sh          # Use enhanced script
else
    make test-unit && make test-lint  # Fallback to basic
fi
```

### Script Fallbacks

| Make Target | Primary Method | Fallback Method |
|-------------|---------------|-----------------|
| `make test` | `../scripts/test.sh` | `test-unit` + `test-lint` + `test-analyze` |
| `make build` | `../scripts/build.sh` | Manual build process |
| `make deploy` | `../scripts/deploy.sh` | `test` + `build` |

## Environment Variables

### Docker Environment
```bash
# WordPress Database
WORDPRESS_DB_HOST=db:3306
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=wordpress

# Development Mode
WP_DEBUG=true
WP_DEBUG_LOG=true
SCRIPT_DEBUG=true
KGU_DEV_MODE=true
```

### Local Environment
```bash
# Create .env file for local overrides
WP_DEBUG=true
WP_DEBUG_LOG=true
PLUGIN_VERSION=1.3.0-dev
```

## IDE Integration

### VS Code

```json
// .vscode/settings.json
{
    "php.validate.executablePath": "/usr/bin/php",
    "phpunit.command": "composer run test",
    "xdebug.remote_host": "localhost",
    "xdebug.remote_port": 9003
}
```

### PHPStorm

```xml
<!-- Configure Xdebug server -->
<server name="Docker" host="localhost" port="9003" />
```

## Testing Strategies

### Unit Testing
```bash
# Test specific classes
make test-unit

# Test with coverage
make test-coverage

# Continuous testing
make test-watch
```

### Integration Testing
```bash
# Test in Docker environment
docker-compose --profile test run test

# Test with fresh WordPress
make docker-clean && make docker-dev
```

### Manual Testing
```bash
# Development build for testing
make build-dev

# Install in WordPress:
# 1. Upload dist/kob-git-updater-1.3.0-dev.zip
# 2. Activate plugin
# 3. Test functionality
```

## Deployment Workflow

### Development → Staging → Production

```bash
# 1. Development
make test                    # Ensure tests pass
make build-dev              # Create dev build for testing

# 2. Staging  
make build                  # Create production build
# Manual testing in staging environment

# 3. Production
make deploy                 # Full deployment pipeline
```

### Release Process

```bash
# 1. Update version in plugin file
vim kob-git-updater-new.php  # Update Version: 1.4.0

# 2. Run full deployment
make deploy
# This will:
# - Run all tests
# - Create production build  
# - Generate changelog
# - Create Git tag
# - Push to repository
```

## Troubleshooting

### Common Issues

**Docker issues:**
```bash
make docker-clean    # Clean everything
make docker-build    # Rebuild images
make docker-dev      # Start fresh
```

**Dependency issues:**
```bash
make clean           # Clean artifacts
make reset           # Reset + reinstall
```

**Permission issues:**
```bash
# Fix file permissions
sudo chown -R $USER:$USER .
```

### Debug Information

```bash
# Check system status
make status          # Project status
make info           # Detailed information
make validate       # Structure validation

# Check Docker
docker-compose ps    # Service status
make docker-logs    # View logs
```

This integration of Makefile, Docker, and shell scripts provides a comprehensive, professional development environment that scales from simple local development to complex containerized workflows.