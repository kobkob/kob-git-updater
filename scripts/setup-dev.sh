#!/bin/bash

# Development setup script for Kob Git Updater
# Sets up development environment for new contributors

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_DIR/plugin"

echo -e "${BLUE}Kob Git Updater Development Setup${NC}"
echo "================================="
echo ""

cd "$PLUGIN_DIR"

# Check system requirements
echo -e "${BLUE}Checking system requirements...${NC}"

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null || echo "not found")
if [[ "$PHP_VERSION" == "not found" ]]; then
    echo -e "${RED}✗ PHP not found${NC}"
    echo "Please install PHP 8.1 or higher"
    exit 1
elif [[ $(echo "$PHP_VERSION" | cut -d. -f1-2) < "8.1" ]]; then
    echo -e "${RED}✗ PHP version $PHP_VERSION is too old${NC}"
    echo "Please install PHP 8.1 or higher"
    exit 1
else
    echo -e "${GREEN}✓ PHP $PHP_VERSION${NC}"
fi

# Check Composer
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version --no-interaction 2>/dev/null | head -1)
    echo -e "${GREEN}✓ $COMPOSER_VERSION${NC}"
else
    echo -e "${RED}✗ Composer not found${NC}"
    echo "Please install Composer: https://getcomposer.org/download/"
    exit 1
fi

# Check Git
if command -v git &> /dev/null; then
    GIT_VERSION=$(git --version)
    echo -e "${GREEN}✓ $GIT_VERSION${NC}"
else
    echo -e "${RED}✗ Git not found${NC}"
    echo "Please install Git"
    exit 1
fi

# Check Node.js (optional)
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    echo -e "${GREEN}✓ Node.js $NODE_VERSION${NC}"
else
    echo -e "${YELLOW}⚠ Node.js not found (optional for frontend development)${NC}"
fi

echo ""

# Install PHP dependencies
echo -e "${BLUE}Installing PHP dependencies...${NC}"
if [ -f "composer.json" ]; then
    composer install --no-interaction
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${RED}✗ composer.json not found${NC}"
    exit 1
fi

# Set up Git hooks (if .git exists)
if [ -d "$PROJECT_DIR/.git" ]; then
    echo -e "\n${BLUE}Setting up Git hooks...${NC}"
    
    # Create pre-commit hook
    PRE_COMMIT_HOOK="$PROJECT_DIR/.git/hooks/pre-commit"
    cat > "$PRE_COMMIT_HOOK" << 'EOF'
#!/bin/bash
# Pre-commit hook for Kob Git Updater

echo "Running pre-commit checks..."

# Move to plugin directory for PHP operations
cd "$PLUGIN_DIR"

# Run PHP syntax check on staged files
php_files=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' | grep '^plugin/' | sed 's|^plugin/||' || true)
if [ -n "$php_files" ]; then
    echo "Checking PHP syntax..."
    for file in $php_files; do
        if [ -f "$file" ]; then
            php -l "$file" > /dev/null
            if [ $? -ne 0 ]; then
                echo "PHP syntax error in $file"
                exit 1
            fi
        fi
    done
    echo "✓ PHP syntax check passed"
fi

# Run PHPUnit tests
if [ -f "composer.json" ]; then
    echo "Running tests..."
    composer run test --quiet
    if [ $? -ne 0 ]; then
        echo "Tests failed. Commit aborted."
        exit 1
    fi
    echo "✓ Tests passed"
fi

echo "✓ Pre-commit checks completed successfully"
EOF
    
    chmod +x "$PRE_COMMIT_HOOK"
    echo -e "${GREEN}✓ Git pre-commit hook installed${NC}"
else
    echo -e "${YELLOW}⚠ Not a Git repository, skipping Git hooks${NC}"
fi

# Create local development config
echo -e "\n${BLUE}Creating development configuration...${NC}"

# Create local WordPress constants file for testing
cat > "wp-config-local.php" << 'EOF'
<?php
/**
 * Local WordPress configuration for development
 * This file is ignored by Git and safe for local settings
 */

// Development constants
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);

// Database (update these with your local values)
define('DB_NAME', 'wordpress_dev');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');

// Disable caching during development
define('WP_CACHE', false);

// Enable WordPress automatic updates
define('WP_AUTO_UPDATE_CORE', true);
EOF

echo -e "${GREEN}✓ Local development config created${NC}"

# Run initial tests
echo -e "\n${BLUE}Running initial test suite...${NC}"
composer run test
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed${NC}"
else
    echo -e "${YELLOW}⚠ Some tests failed (this is normal for initial setup)${NC}"
fi

# Create useful development aliases
echo -e "\n${BLUE}Development commands available:${NC}"
echo "================================"
echo "• composer run test           - Run PHPUnit tests"
echo "• composer run lint           - Run PHP CodeSniffer"
echo "• composer run analyze        - Run PHPStan static analysis"
echo "• composer run test:coverage  - Run tests with coverage"
echo ""
echo "• ./scripts/test.sh           - Run full test suite"
echo "• ./scripts/quick-build.sh    - Create development build"
echo "• ./scripts/build.sh          - Create production build"
echo "• ./scripts/deploy.sh         - Deploy new version"

# Suggest next steps
echo -e "\n${BLUE}Next steps for development:${NC}"
echo "=========================="
echo "1. Update wp-config-local.php with your database settings"
echo "2. Set up a local WordPress installation"
echo "3. Symlink the plugin to your WordPress plugins directory:"
echo "   ln -s $(pwd) /path/to/wordpress/wp-content/plugins/kob-git-updater"
echo "4. Activate the plugin in WordPress admin"
echo "5. Run ./scripts/test.sh to ensure everything works"
echo ""
echo "For more information, see README.md"

# Create .gitignore additions for development
if [ ! -f ".gitignore" ]; then
    echo -e "\n${BLUE}Creating .gitignore...${NC}"
    cat > ".gitignore" << 'EOF'
# Development files
wp-config-local.php
.env
.env.local

# Composer
/vendor/

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db

# Test coverage
/coverage/
clover.xml

# Build files
/build/
/build-dev/
/dist/

# Logs
*.log
error_log

# Temporary files
*.tmp
*.bak
EOF
    echo -e "${GREEN}✓ .gitignore created${NC}"
fi

echo ""
echo -e "${GREEN}Development environment setup completed successfully!${NC}"
echo -e "${YELLOW}Happy coding! 🚀${NC}"