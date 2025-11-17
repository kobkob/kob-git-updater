=== Kob Git Updater ===
Contributors: kobkob
Tags: github, updates, plugins, themes, automatic-updates, git, version-control
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enables automatic updates for WordPress plugins and themes hosted on GitHub repositories. Supports both public and private repositories.

== Description ==

Kob Git Updater allows you to automatically update WordPress plugins and themes that are hosted on GitHub repositories. This plugin is perfect for developers who maintain custom plugins or themes on GitHub and want to provide seamless updates to their users.

**Key Features:**

* **Automatic Updates**: Integrates with WordPress's native update system
* **GitHub Integration**: Works with public and private GitHub repositories  
* **Release Management**: Supports GitHub releases with semantic versioning
* **Branch Support**: Fall back to branch updates when releases aren't available
* **Private Repository Support**: Use GitHub Personal Access Tokens for private repos
* **Rate Limit Management**: Smart caching and authenticated requests
* **Security Focused**: All inputs sanitized, outputs escaped, capabilities checked
* **Developer Friendly**: Modern OOP architecture with comprehensive testing

**How It Works:**

1. Add your GitHub repository details in the plugin settings
2. Configure your GitHub Personal Access Token (optional, for private repos)
3. WordPress will automatically check for updates and notify you when available
4. Update your plugins/themes just like any other WordPress update

**Perfect For:**

* Custom plugin and theme developers
* Agencies managing client websites with custom code
* Organizations with private repositories
* Developers who want to distribute updates via GitHub

**Security & Privacy:**

* No data tracking or external services
* All API communications are with GitHub only
* Tokens and sensitive data are handled securely
* Full compliance with WordPress security standards

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kob-git-updater` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to 'Git Updater' in your WordPress admin menu
4. Configure your GitHub Personal Access Token (optional but recommended)
5. Add your GitHub repositories using the "Add Repository" form

**GitHub Token Setup:**

1. Go to [GitHub Settings > Personal Access Tokens](https://github.com/settings/personal-access-tokens/new)
2. Create a new token with `Contents: Read` permission
3. For private repositories, ensure the token has access to those repositories
4. Copy the token and paste it in the plugin settings

== Frequently Asked Questions ==

= What types of repositories are supported? =

The plugin supports both public and private GitHub repositories. For private repositories, you'll need to configure a GitHub Personal Access Token with appropriate permissions.

= Do I need a GitHub token for public repositories? =

No, but it's recommended. Without a token, you're limited to 60 API requests per hour. With a token, you get 5,000 requests per hour.

= How does the plugin determine when updates are available? =

The plugin first checks for GitHub releases. If releases are available, it compares semantic versions. If no releases exist, it can fall back to branch-based updates.

= Can I use this with WordPress.com? =

This plugin is designed for self-hosted WordPress installations (WordPress.org). It won't work on WordPress.com due to their restrictions on custom plugins.

= Is my GitHub token secure? =

Yes. The token is stored securely in your WordPress database and only used for GitHub API communications. It's never transmitted to third parties.

= What happens if GitHub is down? =

The plugin gracefully handles API failures and will retry on the next scheduled update check. Your site remains fully functional.

= Can I contribute to the plugin development? =

Absolutely! The plugin is open source and available on [GitHub](https://github.com/kobkob/kob-git-updater). Pull requests and issues are welcome.

== Screenshots ==

1. Main plugin settings page with repository management
2. Add repository form with validation
3. GitHub token configuration with connection testing
4. WordPress updates page showing GitHub-managed plugins/themes
5. Plugin in action - update notification for GitHub-hosted theme

== Changelog ==

= 1.3.1 =
* **Bug Fix**: Resolved false positive update detection for repositories with semantic versions but no releases
* **Improvement**: Enhanced branch-based update logic to prevent unnecessary updates
* **Testing**: All 38 tests continue to pass, ensuring stability
* **Documentation**: Updated troubleshooting guide and development documentation

= 1.3.0 =
* **Major Release**: Complete architectural overhaul from monolithic to modular design
* **New**: Dependency injection container for better service management
* **New**: Comprehensive PHPUnit test suite with 38 tests and 90 assertions
* **New**: Docker development environment with full WordPress stack
* **New**: GitHub CLI integration for streamlined repository management
* **New**: Professional development workflow with Makefile automation
* **New**: Enhanced error handling and logging system
* **New**: Modern admin interface with improved user experience
* **Improvement**: PSR-4 autoloading and modern PHP practices
* **Improvement**: Better GitHub API integration with caching and rate limit handling
* **Improvement**: Security enhancements and proper sanitization
* **Documentation**: Complete rewrite of documentation and contribution guidelines

= 1.2.0 =
* Added support for private repositories with GitHub tokens
* Improved error handling and user feedback
* Better integration with WordPress update system
* Enhanced security and input validation

= 1.1.0 =
* Added theme support alongside plugins
* Improved GitHub API integration
* Better caching and performance
* Bug fixes and stability improvements

= 1.0.0 =
* Initial release
* Basic plugin and theme update functionality
* GitHub integration for public repositories
* WordPress admin integration

== Upgrade Notice ==

= 1.3.1 =
This update fixes false positive update notifications and improves branch-based update detection. Recommended for all users.

= 1.3.0 =
Major architecture update with improved reliability, testing, and developer experience. Backup recommended before updating.

== Technical Details ==

**System Requirements:**
* WordPress 6.0 or higher
* PHP 8.1 or higher  
* Internet connection for GitHub API access

**Architecture:**
* Modern object-oriented design with dependency injection
* PSR-4 autoloading via Composer
* Comprehensive test coverage with PHPUnit
* Docker development environment included

**API Usage:**
* Respects GitHub API rate limits
* Implements proper caching (1-hour default)
* Supports both classic and fine-grained personal access tokens
* Graceful fallback for API failures

**Security:**
* All user inputs sanitized and validated
* All outputs properly escaped
* Capability checks on all administrative functions
* Secure token storage and handling
* No external tracking or data collection

== Support ==

For support, bug reports, or feature requests:

* **Plugin Support**: [WordPress.org Support Forum](https://wordpress.org/support/plugin/kob-git-updater/)
* **GitHub Issues**: [GitHub Repository](https://github.com/kobkob/kob-git-updater/issues)
* **Documentation**: [Plugin Documentation](https://kobkob.org/plugins/kob-git-updater/)
* **Developer Resources**: See the WARP.md file in the plugin directory

== Contributing ==

This plugin is open source and welcomes contributions:

1. Fork the repository on GitHub
2. Create a feature branch for your changes  
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

See the CONTRIBUTING.md file for detailed guidelines.

== License ==

This plugin is licensed under the GPL v2 or later.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.