#!/bin/bash

# Quick GitHub CLI authentication with token
# Usage: ./scripts/gh-auth-token.sh [TOKEN]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔐 Quick GitHub CLI Token Authentication${NC}"
echo "=========================================="

# Check if gh is installed
if ! command -v gh &> /dev/null; then
    echo -e "${RED}❌ GitHub CLI (gh) is not installed${NC}"
    echo "Install it with: sudo apt install gh"
    exit 1
fi

# Check if token is provided as argument
TOKEN="$1"

if [[ -z "$TOKEN" ]]; then
    echo -e "${YELLOW}No token provided as argument${NC}"
    echo -e "${BLUE}Enter your Personal Access Token:${NC}"
    read -s TOKEN
    echo ""
fi

if [[ -z "$TOKEN" ]]; then
    echo -e "${RED}❌ No token provided${NC}"
    echo "Usage: $0 [TOKEN]"
    echo "   or: $0 (and enter token when prompted)"
    exit 1
fi

# Authenticate with the token
echo -e "${BLUE}Authenticating with GitHub CLI...${NC}"
echo "$TOKEN" | gh auth login --with-token

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Authentication successful!${NC}"
    
    # Verify authentication
    echo -e "\n${BLUE}Verification:${NC}"
    gh auth status
    
    # Test repository access
    echo -e "\n${BLUE}Testing repository access...${NC}"
    REPO_URL=$(git remote get-url origin 2>/dev/null || echo "")
    if [[ $REPO_URL =~ github\.com[/:]([^/]+)/([^/.]+) ]]; then
        OWNER="${BASH_REMATCH[1]}"
        REPO="${BASH_REMATCH[2]}"
        
        if gh repo view "$OWNER/$REPO" --json name >/dev/null 2>&1; then
            echo -e "${GREEN}✅ Repository access confirmed${NC}"
        else
            echo -e "${YELLOW}⚠️  Repository access limited (may be private)${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  Not in a GitHub repository directory${NC}"
    fi
    
    echo -e "\n${GREEN}🎉 GitHub CLI is ready to use!${NC}"
    echo -e "${BLUE}Try: make gh-status${NC}"
    
else
    echo -e "${RED}❌ Authentication failed${NC}"
    echo "Please check your token and try again."
    exit 1
fi