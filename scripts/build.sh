#!/bin/bash

# Build script for Kob Git Updater WordPress Plugin
# Creates a clean ZIP file ready for WordPress installation with Composer dependencies

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
BUILD_DIR="$PROJECT_DIR/build"
DIST_DIR="$PROJECT_DIR/dist"

# Plugin info
PLUGIN_NAME="kob-git-updater"
# Try new plugin file first, fallback to old one
if [ -f "$PLUGIN_DIR/kob-git-updater-new.php" ]; then
    VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "$PLUGIN_DIR/kob-git-updater-new.php" | head -1)
else
    VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "$PLUGIN_DIR/kob-git-updater.php" | head -1)
fi

echo -e "${BLUE}Building Kob Git Updater v${VERSION}${NC}"
echo "=================================="

# Clean previous builds
echo -e "${YELLOW}Cleaning previous builds...${NC}"
rm -rf "$BUILD_DIR" "$DIST_DIR"
mkdir -p "$BUILD_DIR" "$DIST_DIR"

# Copy plugin files to build directory
echo -e "${YELLOW}Copying plugin files...${NC}"
cp -r "$PLUGIN_DIR" "$BUILD_DIR/$PLUGIN_NAME"

# Check if we need to install Composer dependencies
echo -e "${YELLOW}Installing Composer dependencies for production...${NC}"
cd "$BUILD_DIR/$PLUGIN_NAME"

if [ -f "composer.json" ]; then
    # Install production dependencies only
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Composer dependencies installed${NC}"
    else
        echo -e "${RED}✗ Failed to install Composer dependencies${NC}"
        echo "Make sure Composer is installed and accessible"
        exit 1
    fi
else
    echo -e "${YELLOW}⚠ No composer.json found, skipping dependency installation${NC}"
fi

# Replace main plugin file if new modular version exists
if [ -f "kob-git-updater-new.php" ]; then
    echo -e "${YELLOW}Switching to new modular main plugin file...${NC}"
    rm -f "kob-git-updater.php"
    mv "kob-git-updater-new.php" "kob-git-updater.php"
    echo -e "${GREEN}✓ Using modular architecture${NC}"
fi

# Remove development and build files from build
echo -e "${YELLOW}Cleaning development files...${NC}"

# Remove git files
rm -rf .git .gitignore .github

# Remove development-specific Composer files but keep vendor/
rm -f composer.json composer.lock
rm -f package.json package-lock.json yarn.lock
rm -rf node_modules .vscode .idea

# Remove test files and development configs
rm -rf tests/ phpunit.xml phpstan.neon .phpcs.xml
rm -f kob-git-updater-new.php  # Remove if it still exists

# Remove OS files
find . -name ".DS_Store" -delete
find . -name "Thumbs.db" -delete
find . -name "*.tmp" -delete
find . -name "*.bak" -delete

# Create ZIP file
echo -e "${YELLOW}Creating ZIP package...${NC}"
cd "$BUILD_DIR"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
zip -r "$DIST_DIR/$ZIP_NAME" "$PLUGIN_NAME" -x "*.git*" "*.DS_Store*"

# Create latest version symlink
cd "$DIST_DIR"
ln -sf "$ZIP_NAME" "${PLUGIN_NAME}-latest.zip"

# Display results
echo ""
echo -e "${GREEN}✓ Build completed successfully!${NC}"
echo ""
echo -e "${BLUE}Package details:${NC}"
echo "  Plugin: $PLUGIN_NAME"
echo "  Version: $VERSION"
echo "  File: $ZIP_NAME"
echo "  Size: $(du -h "$ZIP_NAME" | cut -f1)"
echo "  Location: $DIST_DIR"
echo ""
echo -e "${GREEN}Ready for WordPress installation!${NC}"
echo "Upload $ZIP_NAME to WordPress Admin > Plugins > Add New > Upload Plugin"

# Clean build directory
rm -rf "$BUILD_DIR"

echo ""
echo -e "${BLUE}Available packages:${NC}"
ls -lh "$DIST_DIR"