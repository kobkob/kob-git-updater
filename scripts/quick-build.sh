#!/bin/bash

# Quick build script for development
# Creates a ZIP with minimal cleanup for faster iteration
# Includes dev dependencies for testing and debugging

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_DIR/plugin"
BUILD_DIR="$PROJECT_DIR/build-dev"
DIST_DIR="$PROJECT_DIR/dist"

PLUGIN_NAME="kob-git-updater"
# Try new plugin file first, fallback to old one
if [ -f "$PLUGIN_DIR/kob-git-updater-new.php" ]; then
    VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "$PLUGIN_DIR/kob-git-updater-new.php" | head -1)
else
    VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "$PLUGIN_DIR/kob-git-updater.php" | head -1)
fi

echo "Quick building $PLUGIN_NAME v$VERSION (development)..."

# Clean previous dev build
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR" "$DIST_DIR"

# Copy plugin files
cp -r "$PLUGIN_DIR" "$BUILD_DIR/$PLUGIN_NAME"
cd "$BUILD_DIR/$PLUGIN_NAME"

# Install Composer dependencies including dev dependencies
if [ -f "composer.json" ]; then
    echo "Installing Composer dependencies (including dev)..."
    composer install --optimize-autoloader --no-interaction --quiet
    
    if [ $? -eq 0 ]; then
        echo "✓ Composer dependencies installed"
    else
        echo "⚠ Warning: Failed to install Composer dependencies"
    fi
fi

# Use new modular main file if it exists
if [ -f "kob-git-updater-new.php" ]; then
    echo "Using new modular architecture..."
    # Keep both files for development comparison
fi

# Create ZIP with development files included
cd "$BUILD_DIR"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}-dev.zip"
zip -r "$DIST_DIR/$ZIP_NAME" "$PLUGIN_NAME" \
    -x "*.git*" \
    -x "*/.DS_Store*" \
    -x "*/node_modules/*" \
    -x "*/build-dev/*"

echo "✓ Created: $DIST_DIR/$ZIP_NAME"
echo "Size: $(du -h "$DIST_DIR/$ZIP_NAME" | cut -f1)"

# Clean up build directory
rm -rf "$BUILD_DIR"

echo "Development build ready for testing!"
