#!/bin/bash
#
# Docker Desktop WSL Permission Fix
# This script fixes Docker socket permissions in WSL when using Docker Desktop
#

set -e

echo "🔧 Fixing Docker Desktop permissions for WSL..."

# Check if Docker Desktop socket exists
DOCKER_SOCK="/mnt/wsl/docker-desktop/shared-sockets/guest-services/docker.proxy.sock"

if [ ! -S "$DOCKER_SOCK" ]; then
    echo "❌ Docker Desktop socket not found at $DOCKER_SOCK"
    echo "💡 Make sure Docker Desktop is running on Windows and WSL integration is enabled"
    exit 1
fi

# Fix socket permissions
echo "🔐 Fixing socket permissions..."
sudo chmod 666 "$DOCKER_SOCK"

# Test Docker connection
if docker info >/dev/null 2>&1; then
    echo "✅ Docker is now accessible!"
    echo "🐋 Docker version: $(docker version --format '{{.Client.Version}}')"
    echo "🖥️  Server: $(docker info --format '{{.ServerVersion}}')"
else
    echo "❌ Docker is still not accessible"
    exit 1
fi

echo "🎉 Docker Desktop WSL fix completed successfully!"
echo ""
echo "📝 Note: You may need to run this script again if you restart Docker Desktop or WSL"
echo "💡 Tip: Add this to your .bashrc or .zshrc to run automatically:"
echo "   alias fix-docker='$0'"