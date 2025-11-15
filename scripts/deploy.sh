#!/bin/bash

# Deployment script for Kob Git Updater WordPress Plugin
# Tags releases, creates changelog, and prepares for distribution

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

echo -e "${BLUE}Kob Git Updater Deployment Script${NC}"
echo "================================="

cd "$PLUGIN_DIR"

# Get current version
if [ -f "kob-git-updater-new.php" ]; then
    VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "kob-git-updater-new.php" | head -1)
else
    VERSION=$(grep -oP "Version:\s*\K[\d\.]+" "kob-git-updater.php" | head -1)
fi

echo -e "Current version: ${YELLOW}$VERSION${NC}"

# Check if working directory is clean
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED}Error: Working directory is not clean${NC}"
    echo "Please commit or stash your changes before deploying."
    exit 1
fi

# Check if we're on main/master branch
CURRENT_BRANCH=$(git branch --show-current)
if [[ "$CURRENT_BRANCH" != "main" && "$CURRENT_BRANCH" != "master" ]]; then
    echo -e "${YELLOW}Warning: You are not on the main/master branch (current: $CURRENT_BRANCH)${NC}"
    read -p "Do you want to continue? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Deployment cancelled."
        exit 1
    fi
fi

# Check if tag already exists
if git tag -l | grep -q "^v$VERSION$"; then
    echo -e "${RED}Error: Tag v$VERSION already exists${NC}"
    echo "Please update the version number in the plugin file."
    exit 1
fi

# Run tests before deployment
echo -e "\n${BLUE}Running pre-deployment tests...${NC}"
if ! "$SCRIPT_DIR/test.sh"; then
    echo -e "${RED}Tests failed. Deployment cancelled.${NC}"
    exit 1
fi

# Build the plugin
echo -e "\n${BLUE}Building plugin for release...${NC}"
"$SCRIPT_DIR/build.sh"

# Create/update CHANGELOG.md
echo -e "\n${BLUE}Updating changelog...${NC}"
CHANGELOG_FILE="$PLUGIN_DIR/CHANGELOG.md"

# Function to get commits since last tag
get_commits_since_last_tag() {
    local last_tag=$(git describe --tags --abbrev=0 2>/dev/null || echo "")
    if [ -z "$last_tag" ]; then
        # No previous tags, get all commits
        git log --oneline --reverse
    else
        # Get commits since last tag
        git log --oneline --reverse "${last_tag}..HEAD"
    fi
}

# Create changelog entry
create_changelog_entry() {
    local version="$1"
    local date=$(date +"%Y-%m-%d")
    
    echo "## [$version] - $date"
    echo ""
    
    # Get commits and categorize them
    local commits=$(get_commits_since_last_tag)
    
    if [ -n "$commits" ]; then
        echo "### Changed"
        echo "$commits" | sed 's/^[a-f0-9]* /- /'
        echo ""
    fi
    
    echo "### Technical"
    echo "- Updated to modular architecture v1.3.0"
    echo "- Added comprehensive test suite with PHPUnit"
    echo "- Implemented dependency injection container"
    echo "- Enhanced GitHub API client with caching and rate limiting"
    echo "- Added CI/CD pipeline with GitHub Actions"
    echo "- Improved admin interface with Tailwind CSS"
    echo ""
}

# Update or create changelog
if [ ! -f "$CHANGELOG_FILE" ]; then
    echo "# Changelog" > "$CHANGELOG_FILE"
    echo "" >> "$CHANGELOG_FILE"
    echo "All notable changes to this project will be documented in this file." >> "$CHANGELOG_FILE"
    echo "" >> "$CHANGELOG_FILE"
fi

# Prepend new version to changelog
{
    create_changelog_entry "$VERSION"
    cat "$CHANGELOG_FILE"
} > "$CHANGELOG_FILE.tmp" && mv "$CHANGELOG_FILE.tmp" "$CHANGELOG_FILE"

echo -e "${GREEN}✓ Changelog updated${NC}"

# Commit changelog changes
if ! git diff --quiet "$CHANGELOG_FILE"; then
    echo -e "${YELLOW}Committing changelog changes...${NC}"
    git add "$CHANGELOG_FILE"
    git commit -m "Update changelog for v$VERSION"
fi

# Create git tag
echo -e "\n${BLUE}Creating git tag v$VERSION...${NC}"
git tag -a "v$VERSION" -m "Release v$VERSION

$(create_changelog_entry "$VERSION" | head -20)"

echo -e "${GREEN}✓ Tag v$VERSION created${NC}"

# Show deployment summary
echo -e "\n${BLUE}Deployment Summary${NC}"
echo "=================="
echo "Version: $VERSION"
echo "Tag: v$VERSION"
echo "Branch: $CURRENT_BRANCH"
echo "Build: dist/kob-git-updater-$VERSION.zip"

# Ask about pushing
echo ""
read -p "Push tag and changes to remote repository? (y/n): " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Pushing to remote repository...${NC}"
    git push origin "$CURRENT_BRANCH"
    git push origin "v$VERSION"
    echo -e "${GREEN}✓ Changes pushed to remote repository${NC}"
    
    # Show GitHub release URL if it's a GitHub repo
    REMOTE_URL=$(git config --get remote.origin.url)
    if [[ $REMOTE_URL == *"github.com"* ]]; then
        # Extract owner/repo from URL
        if [[ $REMOTE_URL == *".git" ]]; then
            REMOTE_URL=${REMOTE_URL%.git}
        fi
        GITHUB_REPO=$(echo "$REMOTE_URL" | sed -E 's|.*github\.com[:/]([^/]+/[^/]+).*|\1|')
        echo ""
        echo -e "${BLUE}Create GitHub release:${NC}"
        echo "https://github.com/$GITHUB_REPO/releases/new?tag=v$VERSION"
    fi
else
    echo -e "${YELLOW}Changes not pushed. Don't forget to push when ready:${NC}"
    echo "  git push origin $CURRENT_BRANCH"
    echo "  git push origin v$VERSION"
fi

# Show next steps
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "1. Upload dist/kob-git-updater-$VERSION.zip to WordPress.org (if applicable)"
echo "2. Create GitHub release with release notes"
echo "3. Update any external documentation"
echo "4. Announce the release"

echo ""
echo -e "${GREEN}Deployment completed successfully!${NC}"