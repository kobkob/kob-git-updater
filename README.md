# Kob Git Updater

A professional WordPress plugin that enables automatic updates for plugins and themes hosted on GitHub repositories, supporting both public and private repositories.

## 🚀 Quick Start

### For New Contributors

```bash
# Clone the repository
git clone <repository-url>
cd KobGitUpdater

# Option A: Docker Development (Recommended)
make docker-dev
# Access WordPress at http://localhost:8080

# Option B: Local Development
make install
make test
```

### For Daily Development

```bash
make status          # Check project status  
make test           # Run comprehensive tests
make build-dev      # Create development build
make deploy         # Full release pipeline
```

## 📁 Repository Structure

```
KobGitUpdater/                 # Repository root
├── Makefile                   # Development workflow automation
├── README.md                  # This file
├── .gitignore                # Git ignore rules
│
├── plugin/                    # WordPress plugin source code
│   ├── src/                   # Modular PHP source code
│   ├── tests/                 # PHPUnit test suite
│   ├── assets/                # CSS, JS, images
│   ├── docker/                # Docker development environment
│   ├── vendor/                # Composer dependencies (gitignored)
│   ├── composer.json          # PHP dependencies
│   ├── phpunit.xml           # Test configuration
│   ├── Dockerfile            # Docker development image
│   ├── docker-compose.yml    # Docker stack
│   └── kob-git-updater.php   # Main plugin file
│
├── scripts/                   # Build and deployment automation
│   ├── build.sh              # Production build
│   ├── quick-build.sh        # Development build
│   ├── test.sh               # Comprehensive testing
│   ├── deploy.sh             # Release management
│   ├── setup-dev.sh          # Environment setup
│   └── dev.sh                # Developer utilities
│
├── dist/                      # Generated build artifacts (gitignored)
│   ├── kob-git-updater-1.3.0.zip
│   └── kob-git-updater-latest.zip
│
└── build/                     # Temporary build directories (gitignored)
```

## 🛠️ Development Workflows

### Make Commands (Unified Interface)

| Category | Command | Description |
|----------|---------|-------------|
| **Setup** | `make help` | Show all available commands |
| | `make install` | Install dependencies & setup environment |
| | `make status` | Show development status |
| **Testing** | `make test` | Run comprehensive test suite |
| | `make test-unit` | PHPUnit tests only |
| | `make test-lint` | PHP CodeSniffer only |
| | `make test-watch` | Watch files and auto-test |
| **Building** | `make build` | Production build |
| | `make build-dev` | Development build |
| **Docker** | `make docker-dev` | Start WordPress stack |
| | `make docker-stop` | Stop Docker services |
| | `make docker-logs` | View container logs |
| **Release** | `make deploy` | Full deployment pipeline |
|| | `make version` | Show current version |
|| **GitHub CLI** | `make gh-setup` | Setup GitHub CLI authentication |
|| | `make gh-status` | Show repository status & activity |
|| | `make gh-release` | Create GitHub release |
|| | `make gh-pr` | Create pull request |

### Docker Development Stack

- **WordPress 6.4 + PHP 8.1**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081  
- **MailCatcher**: http://localhost:1080
- **MySQL 8.0**: localhost:3306
- **Redis Cache**: localhost:6379

## ✨ Features

### Plugin Features
- **GitHub Integration**: Supports public and private repositories
- **Automatic Updates**: Seamless WordPress update integration
- **Release Management**: GitHub releases and branch-based updates
- **Admin Interface**: Beautiful Tailwind CSS interface
- **Caching**: Intelligent API caching with WordPress transients
- **Security**: GitHub token authentication and rate limiting

### Development Features
- **Modular Architecture**: Clean, testable OOP structure
- **Comprehensive Testing**: PHPUnit, PHPStan, PHPCS integration
- **Docker Environment**: Complete WordPress development stack
- **CI/CD Pipeline**: GitHub Actions with multi-PHP testing
- **Professional Tooling**: Make, Composer, automated builds

## 🧪 Testing

### Running Tests

```bash
make test              # All tests (recommended)
make test-unit         # PHPUnit only
make test-lint         # Code style only  
make test-analyze      # Static analysis only
make test-watch        # Continuous testing
```

### Test Coverage

- **Unit Tests**: Core functionality with mocked dependencies
- **Integration Tests**: WordPress integration testing
- **Code Quality**: PSR-12 standards with WPCS
- **Static Analysis**: PHPStan level 8 analysis
- **Security**: Composer vulnerability scanning

## 🏗️ Building

### Development Build
```bash
make build-dev         # Includes dev tools (9MB)
```

### Production Build  
```bash
make build             # Optimized for WordPress (136KB)
```

Builds are created in `dist/` directory and ready for WordPress installation.

## 🚢 Deployment

### Release Process

```bash
# 1. Update version in plugin/kob-git-updater-new.php
vim plugin/kob-git-updater-new.php

# 2. Run full deployment pipeline
make deploy
```

The deployment pipeline:
1. ✅ Runs comprehensive test suite
2. 🏗️ Creates production build
3. 📝 Generates changelog from Git commits  
4. 🏷️ Creates and pushes Git tag
5. 📦 Prepares release artifacts

### GitHub CLI Integration

Streamlined GitHub operations with GitHub CLI:

```bash
# Setup GitHub CLI (one-time)
make gh-setup

# Repository management
make gh-status         # Repository status and activity
make gh-releases       # List all releases
make gh-release        # Create release for current version

# Development workflow
make gh-pr             # Create pull request from current branch
make gh-issues         # List open issues
make gh-workflows      # Show GitHub Actions workflows
make gh-runs           # Show recent workflow runs
```

The `gh-release` command automatically:
- Creates production build if needed
- Creates GitHub release with build artifact
- Uses semantic versioning from plugin header
- Generates release notes from changelog

## 🐳 Docker Development

### Quick Start with Docker

```bash
make docker-dev        # Start complete WordPress environment
```

### Services

- **WordPress**: Pre-configured with plugin activated
- **Database**: MySQL 8.0 with development optimizations
- **Admin Tools**: phpMyAdmin for database management
- **Email Testing**: MailCatcher for WordPress emails
- **Caching**: Redis for development caching
- **Debugging**: Xdebug enabled (port 9003)

### Development Workflow

1. Start environment: `make docker-dev`
2. Access WordPress: http://localhost:8080
3. Make code changes (live reload enabled)
4. Run tests: `make test`
5. View logs: `make docker-logs`

## 📚 Documentation

- **[DEVELOPMENT.md](plugin/DEVELOPMENT.md)**: Complete development guide
- **[DOCKER.md](plugin/DOCKER.md)**: Docker environment details
- **[Scripts README](scripts/README.md)**: Build automation guide

## 🔧 Requirements

### Local Development
- PHP 8.1+
- Composer
- Make (optional but recommended)

### Docker Development  
- Docker 20.10.0+
- Docker Compose 2.0.0+
- Make (optional but recommended)

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Setup environment: `make install`
4. Make changes and test: `make test`
5. Create development build: `make build-dev`
6. Commit changes: `git commit -m 'Add amazing feature'`
7. Push branch: `git push origin feature/amazing-feature`
8. Open Pull Request

## 📄 License

GPL-2.0-or-later - see [LICENSE](plugin/LICENSE) file.

## 🆘 Support

- **Issues**: [GitHub Issues](../../issues)
- **Documentation**: See `docs/` directory
- **Email**: filipo@kobkob.org

## 🏷️ Version

Current version: **1.3.0** (Modular Architecture)

---

**Kobkob LLC** - Professional WordPress Development