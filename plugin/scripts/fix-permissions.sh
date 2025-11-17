#!/bin/bash

# File Permission Management Script for Kob Git Updater Plugin
# Ensures proper ownership while preserving Docker container functionality

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOST_USER="${HOST_USER:-$(whoami)}"
HOST_UID="${HOST_UID:-$(id -u)}"
HOST_GID="${HOST_GID:-$(id -g)}"

echo "🔧 Kob Git Updater - Permission Management"
echo "Plugin Directory: $PLUGIN_DIR"
echo "Host User: $HOST_USER (UID: $HOST_UID, GID: $HOST_GID)"

# Function to fix permissions for development files
fix_host_permissions() {
    echo "📝 Fixing host file permissions..."
    
    # Set correct ownership for plugin source files
    sudo chown -R "$HOST_UID:$HOST_GID" \
        "$PLUGIN_DIR/src" \
        "$PLUGIN_DIR/assets" \
        "$PLUGIN_DIR/vendor" \
        "$PLUGIN_DIR/composer.json" \
        "$PLUGIN_DIR/composer.lock" \
        "$PLUGIN_DIR"/*.php \
        "$PLUGIN_DIR/tests" \
        "$PLUGIN_DIR/scripts" \
        2>/dev/null || true
    
    # Set proper file permissions
    find "$PLUGIN_DIR" -type f -name "*.php" -exec chmod 644 {} + 2>/dev/null || true
    find "$PLUGIN_DIR" -type f -name "*.sh" -exec chmod +x {} + 2>/dev/null || true
    find "$PLUGIN_DIR" -type d -exec chmod 755 {} + 2>/dev/null || true
    
    echo "✅ Host permissions fixed"
}

# Function to update .env with current user IDs
update_env_file() {
    echo "⚙️  Updating .env file..."
    
    ENV_FILE="$PLUGIN_DIR/.env"
    
    # Update HOST_UID and HOST_GID in .env file
    if [ -f "$ENV_FILE" ]; then
        sed -i "s/^HOST_UID=.*/HOST_UID=$HOST_UID/" "$ENV_FILE"
        sed -i "s/^HOST_GID=.*/HOST_GID=$HOST_GID/" "$ENV_FILE"
    else
        echo "⚠️  .env file not found, creating with current user IDs"
        cat > "$ENV_FILE" << EOF
# Docker Environment Configuration
HOST_UID=$HOST_UID
HOST_GID=$HOST_GID

# WordPress Configuration
WORDPRESS_DEBUG=true
WORDPRESS_DEBUG_LOG=true
WORDPRESS_DEBUG_DISPLAY=false

# Database Configuration
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress
EOF
    fi
    
    echo "✅ .env file updated"
}

# Function to restart Docker containers with new permissions
restart_containers() {
    echo "🐳 Restarting Docker containers..."
    
    cd "$PLUGIN_DIR"
    
    # Stop containers
    sudo docker-compose down 2>/dev/null || true
    
    # Rebuild WordPress container with new user IDs
    sudo docker-compose build --no-cache wordpress
    
    # Start containers
    sudo docker-compose up -d
    
    echo "✅ Containers restarted with proper permissions"
}

# Main execution
case "${1:-auto}" in
    "fix")
        fix_host_permissions
        ;;
    "env")
        update_env_file
        ;;
    "restart")
        restart_containers
        ;;
    "auto"|"")
        echo "🚀 Running automatic permission fix..."
        fix_host_permissions
        update_env_file
        echo ""
        echo "📋 Next steps:"
        echo "  1. Run 'bash scripts/fix-permissions.sh restart' to rebuild containers"
        echo "  2. Or manually run: 'make docker-dev' or 'sudo docker-compose up -d'"
        ;;
    "help")
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  fix     - Fix file permissions for host development"
        echo "  env     - Update .env file with current user IDs"
        echo "  restart - Rebuild and restart Docker containers"
        echo "  auto    - Fix permissions and update env (default)"
        echo "  help    - Show this help message"
        ;;
    *)
        echo "❌ Unknown command: $1"
        echo "Run '$0 help' for usage information"
        exit 1
        ;;
esac

echo "✅ Permission management completed"