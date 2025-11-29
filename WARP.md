# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Architecture Overview

This is a **modular WordPress plugin** that provides GitHub-based installation and automatic updates for WordPress plugins and themes. The plugin has been refactored from a single-file monolith into a modern, object-oriented architecture with comprehensive testing and professional development workflows.

### Current Version: 1.4.0
- **Modular OOP Architecture**: Clean separation of concerns with dependency injection
- **Bootstrap 5.3 UI**: Professional responsive interface with beautiful components
- **Force Update Functionality**: Manual update triggers for individual repositories
- **Comprehensive Testing**: PHPUnit test suite with 38 tests and 90 assertions
- **Professional Development Environment**: Docker, Makefile, and CI/CD integration
- **GitHub API Integration**: Cached API client with rate limit handling
- **WordPress Integration**: Clean hooks into WordPress update system

### Core Components

- **Container System**: Dependency injection container for service management
- **Plugin Core**: Main plugin orchestration and WordPress integration
- **GitHub API Client**: Handles authentication, release fetching, and API caching
- **Repository Manager**: Manages plugin/theme installations and updates with force update support
- **Repository Model**: Data model for GitHub repository representation
- **Settings Interface**: Modern Bootstrap 5.3 admin interface with responsive design
- **Force Update System**: Individual repository update triggers with cache clearing
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
│   │   ├── Installer/             # Installation utilities
│   │   ├── Updates/               # Update management
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

# WP-CLI Integration
make wp-cli           # Access WP-CLI in WordPress container
make wp-info          # Show WordPress information via WP-CLI
make wp-status        # Show WordPress status
make wp-plugins       # List WordPress plugins
make wp-activate      # Activate the Kob Git Updater plugin
make wp-deactivate    # Deactivate the Kob Git Updater plugin

# Maintenance
make clean            # Clean build artifacts and caches
make clean-all        # Clean everything including Docker
make reset            # Reset environment (clean + install)
make status           # Show development status
make info             # Show detailed project information
make docs             # Generate documentation
make update           # Update Composer dependencies

# Quality Assurance
make test-lint        # Run PHP CodeSniffer only
make test-analyze     # Run PHPStan static analysis only
make test-security    # Run Composer security audit
make validate         # Validate project structure and configuration
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
- WordPress: http://localhost:8082
- phpMyAdmin: http://localhost:8083
- MailCatcher: http://localhost:1082
- Redis: localhost:6380
- MySQL: localhost:3307

### WP-CLI Integration

Direct WordPress CLI access in Docker environment:

```bash
make wp-cli           # Interactive WP-CLI session
make wp-activate      # Activate plugin
make wp-deactivate    # Deactivate plugin
make wp-status        # WordPress version and status
make wp-plugins       # List all plugins
make wp-info          # Complete WordPress environment info
```

## Version Management

The project follows semantic versioning:
- **Current Version**: 1.3.2 (WordPress.org compliant)
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

## Recent Major Enhancements (v1.4.0 - Nov 2025)

### Force Update Functionality

**Goal**: Provide users with manual control over repository update checks without waiting for WordPress's automatic schedule.

**Implementation**:
1. **Force Update Handler**: Added `handle_force_update_repository()` method in `SettingsPage.php`
   - Validates user permissions with `manage_options` capability
   - Verifies nonce security for all requests
   - Clears repository-specific GitHub API cache
   - Deletes WordPress update transients to force immediate re-check

2. **UI Integration**: Added force update buttons to repository table
   - Blue "Force Update" button with update icon for each repository
   - JavaScript confirmation dialog to prevent accidental clicks
   - Responsive design that works on mobile devices
   - Clear user feedback via admin notices

3. **Security & UX**:
   - Proper nonce verification (`force_update_repository_nonce`)
   - User confirmation with descriptive messaging
   - Error handling for invalid repositories
   - Success messages directing users to WordPress Updates page

**Technical Implementation**:
```php
// New admin post hook registration
add_action('admin_post_force_update_repository', [$this, 'handle_force_update_repository']);

// Cache clearing and transient deletion
$this->github_client->clear_cache($repository->get_owner(), $repository->get_repo());
if ($repository->is_plugin()) {
    delete_site_transient('update_plugins');
} else {
    delete_site_transient('update_themes');
}
```

### Bootstrap 5.3 UI Enhancement

**Goal**: Replace custom CSS with professional Bootstrap framework for better user experience and maintainability.

**Implementation**:
1. **Bootstrap Integration**:
   - Downloaded Bootstrap 5.3.3 CSS for local hosting (WordPress.org compliance)
   - Updated CSS enqueue system to load Bootstrap before custom styles
   - Created custom CSS to complement Bootstrap with brand theming

2. **Component Overhaul**:
   - **Repository Table**: Converted to Bootstrap responsive table with hover effects
   - **Forms**: Implemented Bootstrap form controls with proper validation styling
   - **Cards**: Professional card layouts with headers and footers
   - **Buttons**: Button groups with proper sizing and responsive behavior
   - **Alerts**: Bootstrap alert components for better user notifications

3. **Responsive Design**:
   - Mobile-first approach with Bootstrap's grid system
   - Responsive table that adapts to small screens
   - Button groups that stack on mobile devices
   - Optimized spacing and typography for all screen sizes

**UI Improvements**:
- Professional visual hierarchy with consistent spacing
- Better color scheme with semantic colors for status indicators
- Improved accessibility with proper ARIA labels and focus states
- Enhanced user feedback with loading states and confirmation dialogs

### Previous Bug Fixes (v1.3.x)

#### WordPress.org Directory Compliance
- Fixed 4,979 WordPress Coding Standard violations
- Implemented snake_case method naming for interfaces
- Added required WordPress.org files (readme.txt, uninstall.php)
- Enhanced security with proper input sanitization

#### Fatal Error Fix - Method Name Consistency
- Fixed `get_repository_info()` to `get_repository()` method calls
- Resolved fatal errors during update checks
- Maintained all 38 PHPUnit tests passing

#### False Positive Update Detection Fix
- Prevented unnecessary updates from stable versions to development versions
- Smart detection logic for repositories without GitHub releases
- Eliminated false "Update" notifications for stable installations

## Common Issues & Troubleshooting

### Update Detection Issues

#### False Positive Updates (Resolved)
**Symptoms**: WordPress shows "Update" button for themes/plugins that are already up-to-date
**Cause**: Repository has stable version (e.g., "0.1.0") but no GitHub releases
**Status**: ✅ **Fixed** in post-v1.3.1 patch (see above)
**Solution**: The fix is already applied. If you're still seeing this, restart your WordPress container.

#### No Updates Showing
**Symptoms**: Expected updates don't appear in WordPress admin
**Debugging Steps**:
1. Check repository has proper version tags (e.g., v1.0.0, v2.1.0)
2. Verify GitHub token has correct permissions
3. Clear plugin cache: `make clear-cache` or admin interface
4. Check logs for GitHub API errors
5. Test GitHub connection via admin interface

#### GitHub API Authentication Issues
**Symptoms**: "Not Found" or "Repository not found or private" errors
**Solutions**:
1. **Private Repositories**: Ensure GitHub token has `Contents: Read` permission
2. **Rate Limits**: Add token to increase limit from 60/hour to 5,000/hour
3. **Token Validation**: Use "Test Connection" button in admin interface
4. **Repository Access**: Verify the repository owner/name is correct

### Development Environment Issues

#### Docker Container Issues
**Symptoms**: Container won't start or shows permission errors
**Solutions**:
```bash
# Reset Docker environment
make clean-all
make docker-dev

# Fix permission issues (RECOMMENDED)
make fix-permissions          # Fix host file ownership
make fix-docker-permissions   # Rebuild containers with user mapping

# Manual permission fix (alternative)
sudo chown -R $USER:$USER /path/to/plugin

# Clear PHP OPcache after code changes
sudo docker-compose restart wordpress
```

#### File Ownership Issues (Resolved)
**Symptoms**: Need sudo to edit files after Docker container restarts
**Root Cause**: Docker containers running as different user ID than host
**Status**: ✅ **Permanently Fixed**
**Solution Applied**:
- WordPress container now maps `www-data` user to host UID/GID (1000:1000)
- Permission management script: `plugin/scripts/fix-permissions.sh`
- Makefile integration: `make fix-permissions`, `make fix-docker-permissions`
- Environment file: `plugin/.env` with `HOST_UID` and `HOST_GID` settings
- Container startup script only manages WordPress-generated directories
- Host source files remain owned by development user

#### Test Failures
**Symptoms**: PHPUnit tests failing after changes
**Solutions**:
```bash
# Run specific test suites
make test-unit          # Core functionality only
make test-integration   # WordPress integration

# Check syntax
php -l src/Path/To/File.php

# Verify all dependencies
composer install
```

### Performance & Caching

#### Slow Update Checks
**Symptoms**: WordPress admin slow when checking for updates
**Solutions**:
1. GitHub API responses are cached for 1 hour by default
2. Use authenticated requests (token) for higher rate limits
3. Consider reducing number of monitored repositories
4. Check network connectivity to GitHub API

#### Debug Logging
**Symptoms**: Need to troubleshoot plugin behavior
**Enable Logging**:
```bash
# Check WordPress debug logs
tail -f /path/to/wp-content/debug.log

# Or in Docker environment
sudo docker logs plugin-wordpress-1 --follow
```
