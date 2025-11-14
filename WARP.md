# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Architecture Overview

This is a **single-file WordPress plugin** (`kob-git-updater.php`) that provides GitHub-based installation and automatic updates for WordPress plugins and themes. The entire functionality is contained in a single `GIU_Plugin` class with no external dependencies.

### Core Components

- **Settings Interface**: Admin page under Settings → GitHub Installer for managing GitHub tokens and repositories
- **GitHub API Integration**: Handles authentication, release fetching, and repository management using GitHub's REST API
- **WordPress Update System Integration**: Hooks into WordPress core update mechanisms for both plugins and themes
- **Install/Update Engine**: Uses WordPress's built-in upgrader classes with custom authentication and directory handling

## Development Workflow

### Testing the Plugin

Since this is a WordPress plugin with no automated tests, manual testing is required:

```bash
# Install in a WordPress environment by copying to wp-content/plugins/
cp -r . /path/to/wordpress/wp-content/plugins/kob-git-updater/

# Activate via WordPress admin or WP-CLI
wp plugin activate kob-git-updater
```

### Development Environment Requirements

- **PHP 8.1+** (specified in user rules and README)
- **WordPress 6.0+** 
- Local WordPress installation or Docker environment
- GitHub personal access token for testing private repositories

### Common Development Tasks

#### Modify Plugin Functionality
All logic is in the main file:
```bash
# Edit the single plugin file
vim kob-git-updater.php
```

#### Test GitHub API Integration
```bash
# Check WordPress error logs for API issues
tail -f /path/to/wordpress/wp-content/debug.log

# Test with curl (requires token)
curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/owner/repo/releases/latest
```

#### Debug Update System
The plugin integrates with WordPress transients for update checking:
```bash
# Clear WordPress update transients via WP-CLI
wp transient delete update_plugins
wp transient delete update_themes
```

## Key Implementation Details

### Security Model
- All admin actions require `manage_options` capability
- Uses WordPress nonces for form submissions
- Sanitizes all user inputs using WordPress functions
- Stores GitHub tokens in WordPress options (encrypted by WordPress)

### Update Detection Logic
1. **Plugin Updates**: Hooks `pre_set_site_transient_update_plugins`
2. **Theme Updates**: Hooks `pre_set_site_transient_update_themes` 
3. **Version Comparison**: Strips leading 'v' from GitHub release tags
4. **Fallback Strategy**: Falls back to default branch when no releases exist

### Authentication Flow
- Supports both classic and fine-grained GitHub tokens
- Automatically adds Authorization headers for GitHub API requests
- Handles private repository access through token-based authentication

## File Structure

```
kob-git-updater/
├── kob-git-updater.php    # Main plugin file (entire functionality)
├── README.md              # User documentation
├── CHANGELOG.md           # Version history
├── CONTRIBUTING.md        # Development guidelines
├── LICENSE               # GPL-2.0-or-later license
└── assets/
    └── img/
        └── logo_en.jpg    # Plugin logo
```

## WordPress Integration Points

### Admin Hooks
- `admin_menu`: Adds settings page
- `admin_init`: Registers settings and fields  
- `admin_post_*`: Handles form submissions
- `admin_notices`: Displays flash messages

### Update System Hooks
- `pre_set_site_transient_update_plugins`: Injects plugin update data
- `pre_set_site_transient_update_themes`: Injects theme update data
- `upgrader_source_selection`: Enforces correct directory names during installation

### GitHub API Endpoints Used
- `/repos/{owner}/{repo}/releases/latest` - Get latest release
- `/repos/{owner}/{repo}` - Get repository details and default branch
- `/repos/{owner}/{repo}/zipball/{ref}` - Download release or branch archive

## Common Issues & Debugging

### GitHub API Rate Limits
- Authenticated requests: 5,000/hour
- Unauthenticated: 60/hour
- Monitor via response headers: `X-RateLimit-*`

### WordPress Update Cache
WordPress caches update checks in transients. Clear them during development:
```bash
wp transient delete --all
```

### Directory Name Conflicts
The plugin enforces WordPress naming conventions:
- **Plugins**: Directory must match first part of plugin basename
- **Themes**: Directory name must match theme slug

## Version Management

This plugin follows semantic versioning aligned with GitHub releases:
- Plugin header version should match latest GitHub release tag
- Tags should use format `v1.2.3` (v prefix is stripped automatically)
- Update CHANGELOG.md for all releases