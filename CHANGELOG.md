# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-11-13

### Added
- Initial release of Kob Git Updater
- GitHub API integration for repository management
- Support for installing plugins and themes from GitHub releases
- Automatic updates via WordPress update system
- Settings page with GitHub token management
- Repository management interface (add/remove/install)
- Support for both public and private repositories via GitHub tokens
- Fallback to default branch when no releases are available
- Version comparison and update notifications
- Security features: nonce verification, capability checks
- GPL-2.0-or-later license

### Features
- Install from latest GitHub Release with fallback to main branch
- Auto-update integration with WordPress core update system
- Simple admin interface under Settings → GitHub Installer
- Support for both plugins and themes
- Private repository access via GitHub personal access tokens
- Version management using GitHub release tags

[1.0.0]: https://github.com/kobkob/kob-git-updater/releases/tag/v1.0.0