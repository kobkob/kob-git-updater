# Build and Deployment Scripts

This directory contains comprehensive build and deployment automation for the Kob Git Updater plugin, supporting the new modular architecture with Composer dependencies.

## Scripts Overview

### Core Build Scripts

#### `build.sh` - Production Build
Creates a clean, production-ready ZIP file with optimized Composer dependencies.

**Features:**
- Installs production-only Composer dependencies (`--no-dev`)
- Switches to modular architecture main file
- Removes all development files and configs
- Optimizes autoloader for production
- Creates versioned ZIP with proper exclusions
- Colorized output with detailed progress

**Usage:**
```bash
./scripts/build.sh
```

**Output:**
- `dist/kob-git-updater-X.X.X.zip` - Production-ready package
- `dist/kob-git-updater-latest.zip` - Symlink to latest version

#### `quick-build.sh` - Development Build
Creates a development ZIP with all dependencies and debugging tools.

**Features:**
- Includes dev dependencies (PHPUnit, PHPStan, etc.)
- Keeps test files and development configs
- Faster iteration for testing
- Uses temporary build directory

**Usage:**
```bash
./scripts/quick-build.sh
```

**Output:**
- `dist/kob-git-updater-X.X.X-dev.zip` - Development package

### Quality Assurance

#### `test.sh` - Comprehensive Test Suite
Runs all quality checks and tests before building or deployment.

**Features:**
- PHP syntax validation
- PHPUnit test suite execution
- PHP CodeSniffer style checks
- PHPStan static analysis
- Composer security audit
- Plugin structure validation
- WordPress compatibility checks

**Usage:**
```bash
./scripts/test.sh
```

### Deployment Automation

#### `deploy.sh` - Release Management
Handles version tagging, changelog generation, and release preparation.

**Features:**
- Validates working directory is clean
- Runs full test suite before deployment
- Auto-generates changelog from Git commits
- Creates and pushes Git tags
- Builds production package
- Provides GitHub release URL

**Usage:**
```bash
./scripts/deploy.sh
```

#### `setup-dev.sh` - Development Environment Setup
Prepares development environment for new contributors.

**Features:**
- Validates system requirements (PHP 8.1+, Composer, Git)
- Installs all dependencies
- Sets up Git pre-commit hooks
- Creates local development configuration
- Provides development workflow guidance

**Usage:**
```bash
./scripts/setup-dev.sh
```

#### `dev.sh` - Developer Convenience Script
Unified interface for common development tasks.

**Features:**
- Single script for all development commands
- Status reporting and project information
- Quick access to testing, building, and deployment
- File watching capabilities (with inotify-tools)
- Dependency management shortcuts

**Usage:**
```bash
./scripts/dev.sh <command>

# Examples:
./scripts/dev.sh test           # Run comprehensive test suite
./scripts/dev.sh build          # Create production build
./scripts/dev.sh status         # Show development status
./scripts/dev.sh version        # Show current version
./scripts/dev.sh clean          # Clean build artifacts
```

## Development Workflow

### For New Contributors
1. Run `./scripts/setup-dev.sh` to set up your development environment
2. Make your changes to the codebase
3. Run `./scripts/test.sh` to ensure all tests pass
4. Commit your changes (pre-commit hooks will run automatically)

### For Maintainers
1. Ensure all changes are committed and pushed
2. Update version number in the main plugin file
3. Run `./scripts/test.sh` to validate all tests pass
4. Run `./scripts/deploy.sh` to create release
5. Upload build to distribution channels

## Architecture Support

The build scripts automatically detect and handle both legacy monolithic and new modular architectures:

- **Legacy**: Uses `kob-git-updater.php` as main file
- **Modular**: Uses `kob-git-updater-new.php` and switches it to main file during build
- **Composer**: Handles production vs development dependencies appropriately
- **Testing**: Supports PHPUnit, PHPStan, and PHPCS workflows

## Installation

After running either build script, install the generated ZIP in WordPress:

1. Go to WordPress Admin > Plugins > Add New
2. Click "Upload Plugin"
3. Select the ZIP file from `dist/` directory
4. Click "Install Now" and activate

## Directory Structure

```
KobGitUpdater/
├── kob-git-updater/          # Plugin source code
│   ├── src/                  # Modular source code
│   ├── tests/                # PHPUnit test suite
│   ├── vendor/               # Composer dependencies
│   ├── composer.json         # PHP dependencies
│   ├── phpunit.xml           # Test configuration
│   ├── kob-git-updater.php   # Legacy main file
│   └── kob-git-updater-new.php # Modular main file
├── scripts/                  # Build and deployment automation
│   ├── build.sh             # Production build
│   ├── quick-build.sh       # Development build  
│   ├── test.sh               # Quality assurance
│   ├── deploy.sh             # Release management
│   ├── setup-dev.sh         # Environment setup
│   └── README.md             # This file
├── dist/                     # Generated packages
├── build/                    # Production build temp
└── build-dev/                # Development build temp
```

## Version Management

Version is automatically extracted from the main plugin file header:
```php
* Version: 1.3.0
```

The scripts check both `kob-git-updater-new.php` (modular) and `kob-git-updater.php` (legacy) for version information.

## CI/CD Integration

The scripts integrate with GitHub Actions workflow (`.github/workflows/tests.yml`):
- Automated testing on PHP 8.1, 8.2, 8.3
- Code quality checks with PHPStan and PHPCS  
- Security vulnerability scanning
- Coverage reporting integration

## Requirements

- **PHP**: 8.1 or higher
- **Composer**: Latest stable version
- **Git**: For version control and deployment
- **Node.js**: Optional, for frontend development
