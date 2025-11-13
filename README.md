# Kob Git Updater

Install and auto-update WordPress plugins and themes from GitHub releases (with fallback to default branch). Provides a simple settings page to store a GitHub token and manage repos to install/update.

## Features
- Install from latest GitHub Release, or fallback to default branch
- Auto-update via WordPress update system (plugins and themes)
- Private repo support via personal access token
- Minimal UI in Settings → GitHub Installer

## Requirements
- WordPress 6.0+
- PHP 8.1+
- A GitHub personal access token (fine‑grained or classic) with read access to target repositories

## Installation
1. Copy the `kob-git-updater` directory into `wp-content/plugins/`
2. Activate “Kob Git Updater”
3. Go to Settings → GitHub Installer
4. Paste a GitHub token and save
5. Add repositories:
   - Type: Plugin or Theme
   - Owner/Repo: e.g., `kobkob/special-rate-shipping`
   - WP Slug:
     - Plugins: `plugin_folder/plugin_file.php` (plugin_basename)
     - Themes: `theme-directory-name`
   - Optional: “Install Now”

## Usage
- When a managed repository publishes a Release with a semantic tag (e.g., `v1.2.3`), WordPress will offer an update. The plugin strips a leading `v` when comparing versions.
- If there are no releases, the installer falls back to the default branch `main` for initial install. Automatic updates require Releases.

## Security Notes
- Tokens are stored in WordPress options; ensure only trusted admins have access.
- Consider scoping fine‑grained tokens to specific repositories.

## Roadmap
- Validate package contents and directory name on install
- Per-repo branch selection when no releases are available
- Bulk update/install actions

## License
GPL-2.0-or-later. See `LICENSE`.
