#!/bin/bash

# Developer convenience script for Kob Git Updater
# Provides quick access to common development tasks

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
PLUGIN_DIR="$PROJECT_DIR/kob-git-updater"

cd "$PLUGIN_DIR"

# Display help
show_help() {
    echo -e "${BLUE}Kob Git Updater Development Helper${NC}"
    echo "=================================="
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Available commands:"
    echo ""
    echo -e "${GREEN}Development:${NC}"
    echo "  setup       Set up development environment"
    echo "  test        Run comprehensive test suite"
    echo "  lint        Run PHP CodeSniffer only"
    echo "  analyze     Run PHPStan static analysis only"
    echo "  unit        Run PHPUnit tests only"
    echo ""
    echo -e "${GREEN}Building:${NC}"
    echo "  build       Create production build"
    echo "  dev-build   Create development build"
    echo ""
    echo -e "${GREEN}Release:${NC}"
    echo "  deploy      Deploy new version"
    echo "  version     Show current version"
    echo ""
    echo -e "${GREEN}Maintenance:${NC}"
    echo "  clean       Clean build artifacts and caches"
    echo "  deps        Update Composer dependencies"
    echo ""
    echo "Examples:"
    echo "  $0 test                 # Run all tests"
    echo "  $0 build                # Create production build"
    echo "  $0 version              # Show current version"
}

# Get current version
get_version() {
    if [ -f "kob-git-updater-new.php" ]; then
        VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "kob-git-updater-new.php" | head -1)
    else
        VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "kob-git-updater.php" | head -1)
    fi
    echo "$VERSION"
}

# Main command processing
case "${1:-help}" in
    "setup")
        echo -e "${BLUE}Setting up development environment...${NC}"
        "$SCRIPT_DIR/setup-dev.sh"
        ;;
    
    "test")
        echo -e "${BLUE}Running comprehensive test suite...${NC}"
        "$SCRIPT_DIR/test.sh"
        ;;
    
    "lint")
        echo -e "${BLUE}Running PHP CodeSniffer...${NC}"
        composer run lint
        ;;
    
    "analyze")
        echo -e "${BLUE}Running PHPStan static analysis...${NC}"
        composer run analyze
        ;;
    
    "unit")
        echo -e "${BLUE}Running PHPUnit tests...${NC}"
        composer run test
        ;;
    
    "build")
        echo -e "${BLUE}Creating production build...${NC}"
        "$SCRIPT_DIR/build.sh"
        ;;
    
    "dev-build"|"quick")
        echo -e "${BLUE}Creating development build...${NC}"
        "$SCRIPT_DIR/quick-build.sh"
        ;;
    
    "deploy")
        echo -e "${BLUE}Deploying new version...${NC}"
        "$SCRIPT_DIR/deploy.sh"
        ;;
    
    "version")
        VERSION=$(get_version)
        echo -e "Current version: ${GREEN}$VERSION${NC}"
        ;;
    
    "clean")
        echo -e "${YELLOW}Cleaning build artifacts and caches...${NC}"
        rm -rf "$PROJECT_DIR/dist" "$PROJECT_DIR/build" "$PROJECT_DIR/build-dev"
        rm -rf vendor/
        rm -f .phpunit.result.cache
        echo -e "${GREEN}✓ Cleaned build artifacts${NC}"
        
        echo "Reinstalling Composer dependencies..."
        composer install --no-interaction
        echo -e "${GREEN}✓ Dependencies reinstalled${NC}"
        ;;
    
    "deps")
        echo -e "${BLUE}Updating Composer dependencies...${NC}"
        composer update --no-interaction
        echo -e "${GREEN}✓ Dependencies updated${NC}"
        ;;
    
    "status")
        echo -e "${BLUE}Development Status${NC}"
        echo "=================="
        VERSION=$(get_version)
        echo "Version: $VERSION"
        
        # Check Git status
        if git status --porcelain > /dev/null 2>&1; then
            if [[ -n $(git status --porcelain) ]]; then
                echo -e "Git status: ${YELLOW}Modified files${NC}"
            else
                echo -e "Git status: ${GREEN}Clean${NC}"
            fi
            
            BRANCH=$(git branch --show-current 2>/dev/null || echo "unknown")
            echo "Branch: $BRANCH"
        fi
        
        # Check dependencies
        if [ -d "vendor" ]; then
            echo -e "Dependencies: ${GREEN}Installed${NC}"
        else
            echo -e "Dependencies: ${RED}Missing${NC}"
        fi
        
        # Check recent builds
        if [ -d "$PROJECT_DIR/dist" ]; then
            echo ""
            echo "Recent builds:"
            ls -la "$PROJECT_DIR/dist" 2>/dev/null | tail -5
        fi
        ;;
    
    "watch")
        echo -e "${BLUE}Watching for changes and running tests...${NC}"
        echo "Press Ctrl+C to stop"
        
        # Simple file watcher using inotifywait if available
        if command -v inotifywait &> /dev/null; then
            while true; do
                inotifywait -r -e modify,create,delete src/ tests/ --exclude '.*\.swp$' -q
                echo -e "\n${YELLOW}Changes detected, running tests...${NC}"
                if composer run test --quiet 2>/dev/null; then
                    echo -e "${GREEN}✓ Tests passed${NC}"
                else
                    echo -e "${RED}✗ Tests failed${NC}"
                fi
                echo -e "${BLUE}Waiting for changes...${NC}"
            done
        else
            echo -e "${YELLOW}inotifywait not found. Install inotify-tools for file watching.${NC}"
            echo "Alternative: run 'watch -n 5 composer run test' in another terminal"
        fi
        ;;
    
    "help"|"-h"|"--help")
        show_help
        ;;
    
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac