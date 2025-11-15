# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-11-15

### Added
- Comprehensive logging system with debug support (WP_DEBUG_LOG integration)
- WordPress transient caching for GitHub API responses (1-hour default cache)
- Advanced input validation and sanitization for all form inputs
- Plugin lifecycle management with proper activation, deactivation, and uninstall hooks
- WordPress action and filter hooks for extensibility:
  - `giu_before_install`, `giu_after_install` - Installation lifecycle hooks
  - `giu_github_release_url`, `giu_github_release_data` - GitHub API filtering
  - `giu_plugin_activated`, `giu_plugin_deactivated`, `giu_plugin_uninstalled` - Plugin lifecycle hooks
- Repository duplicate detection to prevent managing the same repo twice
- User-friendly error messages with internationalization support
- Enhanced security with improved capability checks and CSRF protection
- Safe option saving with comprehensive structure validation
- Detailed form validation with specific error messages for different input types

### Enhanced
- Error handling system with proper logging and user-friendly messages
- GitHub API integration with rate limiting protection through caching
- Security practices following WordPress standards
- Database handling with proper WordPress APIs and validation
- Input sanitization using WordPress core functions
- Form processing with enhanced validation and error reporting

### Technical Improvements
- Added proper WordPress coding standards compliance
- Implemented comprehensive error logging for debugging
- Enhanced plugin architecture with better separation of concerns
- Added proper option validation and sanitization
- Improved performance through intelligent caching
- Better handling of GitHub API rate limits
- Enhanced plugin cleanup on deactivation and uninstall

### Security
- Enhanced nonce validation and CSRF protection
- Improved capability checks for all admin actions
- Better input validation with regex patterns for repository names and slugs
- Secure handling of GitHub tokens and sensitive data
- Protection against malformed input and injection attempts

### Developer Experience
- Added comprehensive logging for debugging and troubleshooting
- Extensible architecture with action and filter hooks
- Better error reporting for development and production environments
- Improved plugin lifecycle management for clean installations and removals

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