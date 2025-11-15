#!/bin/bash

# GitHub CLI Setup Script for Kob Git Updater
# This script configures GitHub CLI for the repository

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 Setting up GitHub CLI for Kob Git Updater${NC}"
echo "=================================================="

# Check if gh is installed
if ! command -v gh &> /dev/null; then
    echo -e "${RED}❌ GitHub CLI (gh) is not installed${NC}"
    echo "Install it with: sudo apt install gh"
    exit 1
fi

echo -e "${GREEN}✅ GitHub CLI found: $(gh --version | head -n1)${NC}"

# Check authentication status
echo -e "\n${BLUE}Checking authentication status...${NC}"
if gh auth status &> /dev/null; then
    echo -e "${GREEN}✅ Already authenticated with GitHub${NC}"
    gh auth status
else
    echo -e "${YELLOW}⚠️  Not authenticated with GitHub${NC}"
    echo -e "${BLUE}Choose authentication method:${NC}"
    echo "1. Personal Access Token (recommended for command line)"
    echo "2. Web Browser"
    echo "3. SSH Key"
    echo ""
    read -p "Enter choice (1-3): " auth_choice
    
    case $auth_choice in
        1)
            echo -e "\n${BLUE}Personal Access Token Authentication${NC}"
            echo "You need a GitHub Personal Access Token with appropriate permissions."
            echo ""
            echo -e "${YELLOW}Step 1: Create Personal Access Token${NC}"
            echo "1. Go to: https://github.com/settings/tokens"
            echo "2. Click 'Generate new token' → 'Generate new token (classic)'"
            echo "3. Set expiration (recommend: 90 days or No expiration for development)"
            echo "4. Select these scopes:"
            echo "   ✓ repo (Full control of private repositories)"
            echo "   ✓ workflow (Update GitHub Action workflows)"
            echo "   ✓ admin:org (Full control of orgs and teams) [if org repo]"
            echo "5. Click 'Generate token' and copy it"
            echo ""
            echo -e "${BLUE}Opening GitHub token creation page...${NC}"
            if command -v xdg-open >/dev/null 2>&1; then
                xdg-open "https://github.com/settings/tokens/new?scopes=repo,workflow,admin:org&description=Kob%20Git%20Updater%20CLI" >/dev/null 2>&1 &
            elif command -v open >/dev/null 2>&1; then
                open "https://github.com/settings/tokens/new?scopes=repo,workflow,admin:org&description=Kob%20Git%20Updater%20CLI" >/dev/null 2>&1 &
            else
                echo "Please manually visit: https://github.com/settings/tokens/new"
            fi
            echo ""
            echo -e "${YELLOW}Step 2: Enter your token below${NC}"
            read -p "Enter your Personal Access Token: " -s token
            echo ""
            
            if [[ -n "$token" ]]; then
                echo "$token" | gh auth login --with-token
                if [ $? -eq 0 ]; then
                    echo -e "${GREEN}✅ Authentication successful${NC}"
                else
                    echo -e "${RED}❌ Authentication failed${NC}"
                    exit 1
                fi
            else
                echo -e "${RED}❌ No token provided${NC}"
                exit 1
            fi
            ;;
        2)
            echo -e "\n${BLUE}Web Browser Authentication${NC}"
            gh auth login --web
            ;;
        3)
            echo -e "\n${BLUE}SSH Key Authentication${NC}"
            gh auth login --git-protocol ssh
            ;;
        *)
            echo -e "${RED}Invalid choice${NC}"
            exit 1
            ;;
    esac
fi

# Verify repository connection
echo -e "\n${BLUE}Checking repository configuration...${NC}"
REPO_URL=$(git remote get-url origin)
echo "Repository URL: $REPO_URL"

# Extract owner/repo from URL
if [[ $REPO_URL =~ github\.com[/:]([^/]+)/([^/.]+) ]]; then
    OWNER="${BASH_REMATCH[1]}"
    REPO="${BASH_REMATCH[2]}"
    echo "Repository: $OWNER/$REPO"
else
    echo -e "${RED}❌ Could not parse GitHub repository from remote URL${NC}"
    exit 1
fi

# Test gh connection to repository
echo -e "\n${BLUE}Testing GitHub CLI connection...${NC}"
if gh repo view "$OWNER/$REPO" --json name,description,visibility > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Successfully connected to GitHub repository${NC}"
    
    # Show repository info
    echo -e "\n${BLUE}Repository Information:${NC}"
    gh repo view "$OWNER/$REPO" --json name,description,visibility,defaultBranch,createdAt,updatedAt | \
        jq -r '
        "  Name: \(.name)",
        "  Description: \(.description // "No description")",
        "  Visibility: \(.visibility)",
        "  Default Branch: \(.defaultBranch)",
        "  Created: \(.createdAt | strptime("%Y-%m-%dT%H:%M:%SZ") | strftime("%Y-%m-%d"))",
        "  Updated: \(.updatedAt | strptime("%Y-%m-%dT%H:%M:%SZ") | strftime("%Y-%m-%d"))"
        '
else
    echo -e "${RED}❌ Could not connect to GitHub repository${NC}"
    echo "This might be due to:"
    echo "  - Authentication issues"
    echo "  - Repository access permissions"
    echo "  - Network connectivity"
    exit 1
fi

# Configure gh for this repository
echo -e "\n${BLUE}Configuring GitHub CLI defaults...${NC}"

# Set default repository
gh config set -h github.com git_protocol https
echo -e "${GREEN}✅ Set Git protocol to HTTPS${NC}"

# Check if we can access repository features
echo -e "\n${BLUE}Testing repository features...${NC}"

# Test issues access
if gh issue list --limit 1 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Issues access: Available${NC}"
else
    echo -e "${YELLOW}⚠️  Issues access: Limited or disabled${NC}"
fi

# Test pull requests access  
if gh pr list --limit 1 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Pull Requests access: Available${NC}"
else
    echo -e "${YELLOW}⚠️  Pull Requests access: Limited or disabled${NC}"
fi

# Test releases access
if gh release list --limit 1 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Releases access: Available${NC}"
    
    # Show latest release info if available
    LATEST_RELEASE=$(gh release list --limit 1 --json tagName,publishedAt,name 2>/dev/null || echo "[]")
    if [[ "$LATEST_RELEASE" != "[]" && "$LATEST_RELEASE" != "" ]]; then
        echo -e "\n${BLUE}Latest Release:${NC}"
        echo "$LATEST_RELEASE" | jq -r '.[0] | "  Tag: \(.tagName)", "  Name: \(.name)", "  Published: \(.publishedAt | strptime("%Y-%m-%dT%H:%M:%SZ") | strftime("%Y-%m-%d"))"'
    fi
else
    echo -e "${YELLOW}⚠️  Releases access: Limited or disabled${NC}"
fi

# Test actions access
if gh run list --limit 1 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ GitHub Actions access: Available${NC}"
else
    echo -e "${YELLOW}⚠️  GitHub Actions access: Limited or disabled${NC}"
fi

echo -e "\n${GREEN}🎉 GitHub CLI setup complete!${NC}"
echo ""
echo -e "${BLUE}Useful commands for this repository:${NC}"
echo "  gh repo view                    # View repository info"
echo "  gh issue list                   # List issues"
echo "  gh pr list                      # List pull requests"
echo "  gh pr create                    # Create pull request"
echo "  gh release list                 # List releases"
echo "  gh release create v1.3.1       # Create new release"
echo "  gh workflow list                # List GitHub Actions workflows"
echo "  gh run list                     # List workflow runs"
echo ""
echo -e "${BLUE}Integration with Makefile:${NC}"
echo "  make gh-status                  # Check GitHub status"
echo "  make gh-release                 # Create release via gh"
echo "  make gh-pr                      # Create pull request"

echo -e "\n${GREEN}Setup completed successfully!${NC}"