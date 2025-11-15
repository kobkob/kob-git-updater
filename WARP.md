# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Architecture Overview

This is a **modular WordPress plugin** that provides GitHub-based installation and automatic updates for WordPress plugins and themes. The plugin has been refactored from a single-file monolith into a modern, object-oriented architecture with comprehensive testing and professional development workflows.

### Current Version: 1.3.0
- **Modular OOP Architecture**: Clean separation of concerns with dependency injection
- **Comprehensive Testing**: PHPUnit test suite with 38 tests and 90 assertions
- **Professional Development Environment**: Docker, Makefile, and CI/CD integration
- **GitHub API Integration**: Cached API client with rate limit handling
- **WordPress Integration**: Clean hooks into WordPress update system

### Core Components

- **Container System**: Dependency injection container for service management
- **Plugin Core**: Main plugin orchestration and WordPress integration
- **GitHub API Client**: Handles authentication, release fetching, and API caching
- **Repository Manager**: Manages plugin/theme installations and updates
- **Repository Model**: Data model for GitHub repository representation
- **Settings Interface**: Modern admin interface with Tailwind CSS styling
- **Testing Framework**: Comprehensive PHPUnit tests with WordPress mocking

## Project Structure

**Important**: The repository structure has been reorganized. Always work from the repository root:

- **Repository Root**: `/home/filipo/storage/storage/projetos/kobkob/code/Wordpress/plugins/KobGitUpdater/`
- **Plugin Source**: `./plugin/` (contains the WordPress plugin)
- **Build Scripts**: `./scripts/` (automation and deployment)
- **Build Artifacts**: `./dist/` (generated releases)
- **Development Tools**: `./Makefile` (unified development interface)

```
KobGitUpdater/                     # Repository root (Git repository here)
├── .git/                          # Git repository
├── .gitignore                     # Repository-wide ignore rules
├── Makefile                       # Unified development interface (54 commands)
├── README.md                      # Complete project documentation
├── LICENSE                        # GPL-2.0-or-later
├── CONTRIBUTING.md                # Contribution guidelines
├── CHANGELOG.md                   # Version history
├── WARP.md                        # This file (WARP guidance)
│
├── .github/                       # GitHub-specific configuration
│   ├── README.md                  # GitHub CLI usage guide
│   └── gh-config.yml             # GitHub CLI configuration
│
├── plugin/                        # WordPress plugin source
│   ├── src/                       # Modular PHP classes (PSR-4)
│   │   ├── Core/                  # Core functionality
│   │   │   ├── Container.php      # Dependency injection (111 lines)
│   │   │   └── Plugin.php         # Main plugin class (237 lines)
│   │   ├── Admin/                 # Admin interface
│   │   │   └── SettingsPage.php   # Settings UI (601 lines)
│   │   ├── GitHub/                # GitHub integration
│   │   │   └── GitHubApiClient.php # API client with caching (301 lines)
│   │   ├── Repository/            # Repository management
│   │   │   ├── RepositoryManager.php # Install/update logic (415 lines)
│   │   │   └── Repository.php     # Repository model (316 lines)
│   │   └── Utils/                 # Utility classes
│   │       └── Logger.php         # Logging utilities
│   ├── tests/                     # PHPUnit test suite
│   │   ├── Core/                  # Core component tests
│   │   ├── GitHub/                # GitHub API tests
│   │   ├── Repository/            # Repository tests
│   │   └── bootstrap.php          # Test bootstrap
│   ├── assets/                    # Plugin assets
│   │   ├── css/                   # Compiled Tailwind CSS
│   │   ├── js/                    # JavaScript files
│   │   └── img/                   # Images and logos
│   ├── docker/                    # Docker development environment
│   │   ├── Dockerfile             # Development container
│   │   ├── docker-compose.yml     # Multi-service stack
│   │   └── wordpress/             # WordPress configuration
│   ├── vendor/                    # Composer dependencies (gitignored)
│   ├── composer.json              # PHP dependencies
│   ├── phpunit.xml               # PHPUnit configuration
│   └── kob-git-updater.php       # Main plugin file (bootstrapper)
│
├── scripts/                       # Build and deployment automation
│   ├── build.sh                  # Production build process
│   ├── quick-build.sh            # Development build
│   ├── test.sh                   # Comprehensive testing
│   ├── deploy.sh                 # Release management
│   ├── setup-dev.sh              # Development environment setup
│   ├── setup-gh.sh               # GitHub CLI configuration
│   └── dev.sh                    # Developer utilities
│
└── dist/                          # Build artifacts
    ├── kob-git-updater-1.3.0-dev.zip  # Development build (9.2MB)
    └── kob-git-updater-latest.zip     # Production symlink
```

## Development Workflow

### Quick Start

```bash
# Clone and setup
cd /home/filipo/storage/storage/projetos/kobkob/code/Wordpress/plugins/KobGitUpdater

# Choose development environment:
make docker-dev        # Full WordPress stack (recommended)
# OR
make install           # Local development setup

# Verify setup
make status            # Check project status
make test             # Run test suite (38 tests)
```

### Available Make Commands

The Makefile provides 54 commands organized into categories:

```bash
make help              # Show all available commands

# Development
make install           # Install dependencies and setup
make docker-dev        # Start complete WordPress development stack
make watch             # Watch files for changes

# Testing
make test             # Run comprehensive test suite
make test-unit        # Run PHPUnit tests only
make test-integration # Run integration tests
make test-coverage    # Generate code coverage report

# Building
make build-dev        # Development build (includes tests)
make build-prod       # Production build (optimized)
make clean            # Clean all build artifacts

# GitHub CLI
make gh-setup         # Setup GitHub CLI authentication
make gh-status        # Show repository status & activity
make gh-release       # Create GitHub release
make gh-pr            # Create pull request
make gh-releases      # List all releases
make gh-issues        # List open issues
make gh-workflows     # Show GitHub Actions workflows
make gh-runs          # Show recent workflow runs

# Quality Assurance
make lint             # Run PHP linting
make format           # Format code (if formatter configured)
make analyze          # Static analysis
```

### Development Environment Requirements

- **PHP 8.1+** (specified in user rules and Docker configuration)
- **WordPress 6.0+** 
- **Composer** for PHP dependency management
- **Docker & Docker Compose** (recommended for development)
- **GitHub CLI** (optional but recommended for repository management)
- GitHub personal access token for testing private repositories

## GitHub CLI Integration

The project includes comprehensive GitHub CLI integration for streamlined repository management and release processes.

### Setup and Authentication

```bash
# One-time setup (interactive authentication)
make gh-setup

# Verify authentication and access
make gh-status
```

The setup script (`scripts/setup-gh.sh`) provides:
- **Interactive Authentication**: Guides through GitHub CLI authentication
- **Repository Connection**: Verifies access to the GitHub repository
- **Feature Testing**: Checks permissions for Issues, PRs, Releases, Actions
- **Configuration**: Sets up GitHub CLI defaults and aliases

### Available GitHub CLI Commands

#### Repository Management
```bash
make gh-status        # Show repository status, PRs, and issues
make gh-releases      # List all GitHub releases
make gh-release       # Create release for current version
```

#### Development Workflow
```bash
make gh-pr            # Create pull request from current branch
make gh-issues        # List open issues
make gh-workflows     # Show GitHub Actions workflows
make gh-runs          # Show recent workflow runs
```

### Automated Release Process

The `make gh-release` command provides automated release management:

1. **Build Verification**: Creates production build if not present
2. **Release Creation**: Creates GitHub release with semantic versioning
3. **Artifact Upload**: Attaches build artifact to release
4. **Release Notes**: Auto-generates from changelog and version
5. **Latest Tag**: Marks as latest release

```bash
# Example release workflow
make test             # Ensure quality
make build-prod       # Create production build
make gh-release       # Create GitHub release with artifact
```

### GitHub CLI Configuration

The project includes:
- **Configuration File**: `.github/gh-config.yml` with project defaults
- **Aliases**: Shortcuts for common operations (e.g., `gh co` for PR checkout)
- **Documentation**: Complete usage guide in `.github/README.md`
- **Integration**: Seamless integration with existing Makefile workflow

### Authentication Methods

1. **Web Browser** (recommended): Most secure and user-friendly
2. **Personal Access Token**: For automated environments
3. **SSH Key**: For SSH-based Git workflows

The setup script guides you through choosing the appropriate method.

### Rate Limits and Permissions

- **Authenticated Requests**: 5,000/hour (vs 60/hour unauthenticated)
- **Repository Access**: Automatically detected and verified
- **Feature Availability**: Issues, PRs, Releases, Actions support
- **Error Handling**: Clear guidance for authentication and permission issues

## Architecture Details

### Modular Design Principles

The plugin follows modern PHP development practices:

1. **PSR-4 Autoloading**: All classes follow PSR-4 namespace conventions
2. **Dependency Injection**: Services are managed through a DI container
3. **Single Responsibility**: Each class has a focused, single purpose
4. **Testability**: All components are unit tested with mocking
5. **WordPress Integration**: Clean hooks with proper capability checks

### Class Responsibilities

#### Core Classes
- **`Core\Container`**: Dependency injection container, service registration
- **`Core\Plugin`**: Main plugin orchestration, WordPress hook management

#### GitHub Integration
- **`GitHub\GitHubApiClient`**: GitHub API communication, token management, caching
- **`Repository\Repository`**: Repository data model and validation
- **`Repository\RepositoryManager`**: Installation and update logic

#### User Interface
- **`Admin\SettingsPage`**: Settings interface, form handling, user experience

### Testing Strategy

The plugin includes comprehensive testing:

```bash
# Run all tests
make test

# Run specific test suites
./plugin/vendor/bin/phpunit --testsuite=Core
./plugin/vendor/bin/phpunit --testsuite=GitHub
./plugin/vendor/bin/phpunit --testsuite=Repository
```

**Test Coverage:**
- 38 tests with 90 assertions
- WordPress function mocking via Brain Monkey
- GitHub API response mocking
- Service container testing
- UI form handling tests

## Common Development Tasks

### Modifying Plugin Functionality

The modular architecture allows targeted modifications:

```bash
# Edit GitHub API integration
vim plugin/src/GitHub/GitHubApiClient.php

# Modify update logic
vim plugin/src/Repository/RepositoryManager.php

# Update settings interface
vim plugin/src/Admin/SettingsPage.php

# Add new utility functions
vim plugin/src/Utils/
```

### Running Tests

```bash
# From repository root
make test              # Run all tests with coverage
make test-unit         # Quick unit test run

# From plugin directory
cd plugin
composer test          # Run tests via Composer
./vendor/bin/phpunit   # Direct PHPUnit execution
```

### Debugging

```bash
# Enable WordPress debugging (development)
make docker-dev        # Includes WP_DEBUG configuration

# Check plugin logs
docker-compose -f plugin/docker-compose.yml logs wordpress

# View test output
make test-verbose      # Detailed test output
```

### Building and Releasing

```bash
# Development build (includes all files and tests)
make build-dev         # Creates 9.2MB archive

# Production build (optimized for deployment)
make build-prod        # Creates minimal production archive

# Traditional release process
make deploy           # Version bump, build, tag, release

# GitHub CLI release process (recommended)
make gh-release       # Create GitHub release with build artifact
```

#### GitHub CLI Release Workflow

The GitHub CLI integration provides a streamlined release process:

```bash
# Complete release workflow
make test             # Run comprehensive tests
make build-prod       # Create production build  
make gh-release       # Create GitHub release
```

The `gh-release` command automatically:
- Detects current version from plugin header
- Creates production build if not present
- Creates GitHub release with semantic versioning
- Uploads build artifact as release asset
- Generates release notes from changelog
- Marks release as "latest"

## WordPress Integration Points

### Hook Structure

The modular architecture maintains clean WordPress integration:

```php
// Main plugin file bootstraps the system
Plugin::getInstance($container)->init();

// Services are registered in the container
$container->register(GitHubApiClient::class);
$container->register(RepositoryManager::class);
$container->register(SettingsPage::class);
```

### Admin Interface

- **Settings Page**: `Settings → GitHub Installer`
- **Capabilities**: Requires `manage_options`
- **Security**: WordPress nonces and sanitization
- **UI Framework**: Tailwind CSS for modern styling

### Update System Integration

- **Plugin Updates**: `pre_set_site_transient_update_plugins`
- **Theme Updates**: `pre_set_site_transient_update_themes`
- **Directory Management**: `upgrader_source_selection`
- **Caching**: Respects WordPress transient system

### API Usage

The GitHub API client handles:
- Rate limit management (5,000/hour authenticated)
- Token authentication (classic and fine-grained)
- Response caching for performance
- Error handling and retry logic

## Testing Integration

### Local Testing

```bash
# Quick verification
make test-unit

# Full test suite with coverage
make test

# Integration testing with WordPress
make docker-dev       # Starts test environment
make test-integration # Runs integration tests
```

### CI/CD Integration

The project includes GitHub Actions workflow:
- Tests on PHP 8.1, 8.2, 8.3
- WordPress compatibility testing
- Automated builds on pull requests
- Release automation

## Docker Development Environment

Complete WordPress development stack:

```bash
make docker-dev       # Start services

# Services included:
# - WordPress 6.4 + PHP 8.1
# - MySQL 8.0
# - phpMyAdmin (database management)
# - MailCatcher (email testing)  
# - Redis (caching)
# - Test runner container
```

Access points:
- WordPress: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MailCatcher: http://localhost:1080

## Version Management

The project follows semantic versioning:
- **Current Version**: 1.3.0 (modular architecture)
- **Version Detection**: Automatic from plugin headers and Git tags
- **Release Process**: Automated via `make deploy`
- **Changelog**: Maintained in CHANGELOG.md

## Key Improvements in v1.3.0

1. **Modular Architecture**: Split 1600+ line monolith into focused classes
2. **Testing Framework**: 38 comprehensive tests with mocking
3. **Development Environment**: Docker stack with debugging tools
4. **Build Automation**: Professional Makefile with 54 commands
5. **GitHub CLI Integration**: Streamlined repository management and release automation
6. **Code Quality**: PSR-4 autoloading, dependency injection, modern PHP practices
7. **Documentation**: Complete project documentation and contribution guidelines

The plugin maintains backward compatibility while providing a modern, maintainable codebase for future development.
