# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-11-14

### Added
- Modern Tailwind CSS-based admin interface with professional design
- Main menu item "Git Updater" with Configuration and Documentation submenus
- Comprehensive documentation page with setup guides and API reference
- Professional header with Kobkob logo and branding integration
- Card-based layout design for better UX organization
- Visual status indicators with color-coded badges
- Direct GitHub repository links in management table
- Empty state handling with helpful guidance messages
- Responsive design for mobile-friendly layouts
- Enhanced form designs with better input validation
- Navigation breadcrumbs and improved user flow
- WARP.md file for AI development guidance

### Changed
- Complete UI redesign from basic WordPress admin to modern interface
- Moved from Settings submenu to dedicated main menu item
- Enhanced repository management table with professional styling
- Improved configuration forms with Tailwind styling
- Better visual hierarchy and typography throughout
- Updated admin navigation structure

### Improved
- User experience with intuitive design patterns
- Visual feedback for user actions and states
- Form usability with proper labels and descriptions
- Overall plugin accessibility and responsiveness

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