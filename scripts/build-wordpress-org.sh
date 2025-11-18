#!/bin/bash

# WordPress.org Clean Build Script
# Creates a clean distribution package suitable for WordPress.org submission

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🏗️  WordPress.org Clean Build Process${NC}"
echo "=================================================="

# Get the script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_DIR="$PROJECT_ROOT/plugin"
WP_ORG_DIR="$PROJECT_ROOT/wordpress-org"
BUILD_DIR="$WP_ORG_DIR/kob-git-updater"
DIST_DIR="$PROJECT_ROOT/dist"

echo -e "${YELLOW}📂 Directories:${NC}"
echo "   Project Root: $PROJECT_ROOT"
echo "   Plugin Source: $PLUGIN_DIR"
echo "   WordPress.org: $WP_ORG_DIR"
echo "   Build Target: $BUILD_DIR"

# Check if plugin directory exists
if [ ! -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}❌ Plugin directory not found: $PLUGIN_DIR${NC}"
    exit 1
fi

echo -e "${YELLOW}🧹 Cleaning previous builds...${NC}"
rm -rf "$WP_ORG_DIR"
mkdir -p "$WP_ORG_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

echo -e "${YELLOW}📋 Including WordPress.org required files...${NC}"

# Core plugin files (WordPress.org essentials)
INCLUDE_FILES=(
    "kob-git-updater.php"
    "readme.txt"
    "uninstall.php"
)

# Core directories (WordPress.org appropriate)
INCLUDE_DIRS=(
    "src"
    "assets"
    "languages"
)

# Copy main plugin files
for file in "${INCLUDE_FILES[@]}"; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        echo "   ✅ Including: $file"
        cp "$PLUGIN_DIR/$file" "$BUILD_DIR/"
    else
        echo -e "   ${RED}❌ Missing required file: $file${NC}"
        exit 1
    fi
done

# Copy main plugin directories
for dir in "${INCLUDE_DIRS[@]}"; do
    if [ -d "$PLUGIN_DIR/$dir" ]; then
        echo "   ✅ Including directory: $dir"
        cp -r "$PLUGIN_DIR/$dir" "$BUILD_DIR/"
    else
        echo -e "   ${YELLOW}⚠️  Optional directory not found: $dir${NC}"
    fi
done

# Copy Composer production dependencies (vendor folder without dev dependencies)
echo -e "${YELLOW}📦 Installing production dependencies...${NC}"
cd "$PLUGIN_DIR"

# Create temporary composer.json for production build
cat > "$BUILD_DIR/composer.json" << 'EOF'
{
    "name": "kobkob/kob-git-updater",
    "description": "WordPress plugin for GitHub repository updates",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "KobGitUpdater\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "classmap-authoritative": true,
        "apcu-autoloader": true
    }
}
EOF

cd "$BUILD_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
rm composer.json composer.lock

echo -e "${YELLOW}🧹 Cleaning WordPress.org package...${NC}"

# Remove files/directories that shouldn't be in WordPress.org submission
EXCLUDE_PATTERNS=(
    # Development files
    ".git*"
    ".env*"
    ".docker*"
    "*.swp"
    "*.tmp"
    "*.log"
    "*.cache"
    ".phpunit*"
    "phpunit.xml"
    "phpcs.xml*"
    "phpstan.neon"
    
    # Development directories
    "tests"
    "docker"
    "scripts"
    ".github"
    "node_modules"
    
    # Documentation (not required for WordPress.org)
    "DEVELOPMENT.md"
    "DOCKER.md"
    "CHANGELOG.md"
    "CONTRIBUTING.md"
    "README.md"  # We have readme.txt instead
    
    # Build artifacts
    "dist"
    "build"
    ".nyc_output"
    "coverage"
    
    # IDE files
    ".vscode"
    ".idea"
    "*.sublime-*"
    
    # OS files
    ".DS_Store"
    "Thumbs.db"
    
    # Composer dev files in vendor
    "vendor/*/tests"
    "vendor/*/test"
    "vendor/*/.git"
    "vendor/*/*/.git"
    "vendor/composer/installed.dev.json"
)

cd "$BUILD_DIR"
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    find . -name "$pattern" -type f -delete 2>/dev/null || true
    find . -name "$pattern" -type d -exec rm -rf {} + 2>/dev/null || true
done

echo -e "${YELLOW}✂️  Removing development-only vendor files...${NC}"
# Clean vendor directory more aggressively
find vendor -name "*.md" -delete 2>/dev/null || true
find vendor -name "*.txt" -delete 2>/dev/null || true
find vendor -name "*.yml" -delete 2>/dev/null || true
find vendor -name "*.yaml" -delete 2>/dev/null || true
find vendor -name "*.xml" -delete 2>/dev/null || true
find vendor -name "phpunit*" -delete 2>/dev/null || true
find vendor -name ".git*" -delete 2>/dev/null || true
find vendor -name "test*" -type d -exec rm -rf {} + 2>/dev/null || true
find vendor -name "Test*" -type d -exec rm -rf {} + 2>/dev/null || true
find vendor -name "example*" -type d -exec rm -rf {} + 2>/dev/null || true
find vendor -name "demo*" -type d -exec rm -rf {} + 2>/dev/null || true

echo -e "${YELLOW}🔍 Final package validation...${NC}"

# Validate essential files exist
REQUIRED_FILES=("kob-git-updater.php" "readme.txt" "src" "vendor/autoload.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -e "$BUILD_DIR/$file" ]; then
        echo -e "${RED}❌ Missing required file/directory: $file${NC}"
        exit 1
    fi
done

# Get plugin version for filename
VERSION=$(grep "Version:" "$BUILD_DIR/kob-git-updater.php" | sed 's/.*Version: *//' | tr -d '\r\n ')
FILENAME="kob-git-updater-$VERSION-wp-org.zip"

echo -e "${YELLOW}📦 Creating WordPress.org distribution package...${NC}"

cd "$WP_ORG_DIR"
zip -r "$DIST_DIR/$FILENAME" kob-git-updater -x "*.DS_Store" > /dev/null

# Create symlink for latest
cd "$DIST_DIR"
ln -sf "$FILENAME" "kob-git-updater-wp-org-latest.zip"

echo -e "${GREEN}✅ WordPress.org build completed successfully!${NC}"
echo ""
echo -e "${BLUE}📊 Build Summary:${NC}"
echo "   Version: $VERSION"
echo "   Package: $DIST_DIR/$FILENAME"
echo "   Latest Link: $DIST_DIR/kob-git-updater-wp-org-latest.zip"
echo "   Size: $(du -h "$DIST_DIR/$FILENAME" | cut -f1)"
echo ""

# Show package contents summary
echo -e "${BLUE}📋 Package Contents:${NC}"
cd "$BUILD_DIR"
echo "   Files: $(find . -type f | wc -l)"
echo "   Size: $(du -sh . | cut -f1)"
echo ""

# List main directories
echo -e "${BLUE}📁 Main Directories:${NC}"
for dir in */; do
    if [ -d "$dir" ]; then
        size=$(du -sh "$dir" | cut -f1)
        files=$(find "$dir" -type f | wc -l)
        echo "   $dir ($files files, $size)"
    fi
done

echo ""
echo -e "${GREEN}🚀 Ready for WordPress.org submission!${NC}"
echo "   Upload: $DIST_DIR/$FILENAME"
echo "   Or use: $DIST_DIR/kob-git-updater-wp-org-latest.zip"
echo ""
echo -e "${YELLOW}📝 Next Steps:${NC}"
echo "   1. Upload package to https://wordpress.org/plugins/developers/add/"
echo "   2. Fill out submission form"
echo "   3. Wait for review"
echo ""