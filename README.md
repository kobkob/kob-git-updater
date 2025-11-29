# Kob Git Updater

🚀 **Professional WordPress plugin** that enables automatic updates for plugins and themes hosted on **GitHub repositories**. Seamlessly integrates with WordPress's native update system, supporting both public and private repositories with a beautiful Bootstrap UI.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0-green.svg)](LICENSE)
[![GitHub release](https://img.shields.io/github/v/release/kobkob/kob-git-updater.svg)](https://github.com/kobkob/kob-git-updater/releases)

---

## ✨ Key Features

### 🎯 **Seamless GitHub Integration**
- **Public & Private Repositories**: Support for any GitHub repository with proper token authentication
- **Multiple Update Sources**: Checks GitHub releases first, falls back to latest branch commits
- **Smart Version Detection**: Automatically detects semantic versioning from tags and plugin headers
- **Rate Limit Management**: Intelligent API caching and authenticated requests (5,000/hour vs 60/hour)

### 🖥️ **Beautiful Admin Interface**
- **Bootstrap 5.3 UI**: Professional, responsive interface that works on all devices
- **Repository Management**: Easy-to-use table with repository status, type badges, and action buttons
- **Force Update Buttons**: Manually trigger update checks for individual repositories
- **Real-time Feedback**: Clear success/error messages and loading states

### ⚡ **WordPress Integration**
- **Native Updates**: Appears in WordPress Admin → Updates alongside core updates
- **Plugin & Theme Support**: Manages both WordPress plugins and themes from GitHub
- **Security First**: Proper nonce verification, capability checks, and sanitized inputs
- **Multisite Compatible**: Works with WordPress multisite installations

### 🛠️ **Developer Experience**
- **Easy Setup**: Simple configuration with GitHub Personal Access Tokens
- **Comprehensive Logging**: Detailed logs for troubleshooting and monitoring
- **Cache Management**: Built-in cache clearing and refresh capabilities
- **GitHub CLI Integration**: Advanced release management for developers

---

## 📸 Screenshots

### Repository Management Interface
Beautiful Bootstrap-powered interface for managing your GitHub repositories:

- **Responsive Table**: Repository list with type badges, status indicators, and action buttons
- **Force Update Buttons**: Instantly trigger update checks for any repository
- **Smart Status Indicators**: Visual feedback for public/private repositories and update availability
- **Mobile Responsive**: Perfect experience on all screen sizes

### GitHub Token Configuration
Secure and user-friendly token management:

- **Token Masking**: Securely displays partial tokens for verification
- **Connection Testing**: Built-in GitHub connectivity testing
- **Rate Limit Information**: Clear guidance on API usage and limits

---

## 🚀 Quick Start

### 1. Installation

**From GitHub Release (Recommended):**
1. Download the latest `kob-git-updater-x.x.x.zip` from [GitHub Releases](https://github.com/kobkob/kob-git-updater/releases)
2. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate the plugin

**From WordPress Admin:**
1. Go to WordPress Admin → Plugins → Add New
2. Search for "Kob Git Updater"
3. Install and activate

### 2. Configuration

1. **Navigate to Settings:**
   Go to WordPress Admin → **Kob Git Updater**

2. **Add GitHub Token (Optional but Recommended):**
   - Create a [GitHub Personal Access Token](https://github.com/settings/personal-access-tokens/new)
   - Required permissions: `Contents: Read` (and `Metadata: Read` for private repos)
   - Enter token in the configuration section
   - Click "Test Connection" to verify

3. **Add Your First Repository:**
   - Click "Add Repository" button
   - Fill in the form:
     - **Owner**: GitHub username or organization (e.g., `kobkob`)
     - **Repository**: Repository name (e.g., `my-awesome-plugin`)
     - **Type**: Choose "Plugin" or "Theme"
     - **WordPress Slug**: The directory name in your WordPress installation
   - Click "Add Repository"

### 3. Managing Updates

**Automatic Updates:**
- Updates appear in WordPress Admin → Updates
- Update just like any other WordPress plugin or theme

**Force Updates:**
- Go to Kob Git Updater settings
- Click the **"Force Update"** button next to any repository
- Confirm the action to clear cache and trigger immediate update check
- Check WordPress → Updates for newly available updates

---

## 📋 System Requirements

| Requirement | Version |
|-------------|---------|  
| **WordPress** | 6.0+ |
| **PHP** | 8.1+ |
| **MySQL** | 5.7+ or MariaDB 10.2+ |
| **GitHub Token** | Optional (recommended for private repos) |

### Browser Support
- ✅ Modern browsers (Chrome, Firefox, Safari, Edge)
- ✅ Mobile devices (iOS Safari, Android Chrome)
- ✅ WordPress admin interface compatibility

---

## 🔧 Advanced Configuration

### GitHub Personal Access Tokens

**Public Repositories:**
- No token required, but recommended for higher rate limits
- **Permissions needed**: None (uses public API)

**Private Repositories:**
- Token required for access
- **Classic Token Permissions**: `repo` (full repository access)
- **Fine-grained Token Permissions**: `Contents: Read`, `Metadata: Read`

### Repository Configuration

| Field | Description | Example |
|-------|-------------|---------|  
| **Owner** | GitHub username or organization | `kobkob` |
| **Repository** | Repository name | `my-awesome-plugin` |
| **Type** | Plugin or Theme | `Plugin` |
| **WordPress Slug** | Directory name in WordPress | `my-awesome-plugin` |

### Update Detection Logic

1. **Check GitHub Releases**: Looks for semantic version tags (v1.0.0, v2.1.0)
2. **Compare Versions**: Uses semantic version comparison
3. **Fallback to Branch**: If no releases, checks latest commit on default branch
4. **Cache Results**: Caches API responses for 1 hour to optimize performance

---

## 🎯 Use Cases

### 🏢 **Agency & Freelancer Workflow**
- Manage custom plugins and themes across multiple client sites
- Deploy updates instantly without manual file transfers
- Keep private repositories secure with token authentication

### 👨‍💻 **Plugin & Theme Developers**
- Distribute updates outside WordPress.org repository
- Beta testing with controlled user groups
- Version control integration with development workflow

### 🏗️ **Enterprise Solutions**
- Private company plugins and themes
- Compliance with internal security policies
- Centralized update management across organizations

### 🚀 **Open Source Projects**
- Alternative distribution channel for WordPress plugins
- Community-driven development with GitHub integration
- Automated update delivery to users

---

## 🛡️ Security Features

### **WordPress Security Standards**
- ✅ Proper nonce verification for all forms
- ✅ Capability checks (`manage_options` required)
- ✅ Input sanitization and output escaping
- ✅ SQL injection protection with prepared statements

### **GitHub Security**
- ✅ Secure token storage (masked in admin interface)
- ✅ HTTPS-only communication with GitHub API
- ✅ Rate limit respect and caching
- ✅ No token exposure in logs or error messages

### **File Security**
- ✅ Validates downloaded packages before installation
- ✅ Secure temporary file handling
- ✅ Permission verification before file operations

---

## 🔧 Troubleshooting

### Common Issues

**❌ "Repository not found or private"**
- Verify repository owner and name are correct
- For private repositories, ensure GitHub token has proper permissions
- Test connection using the "Test Connection" button

**❌ "Updates not showing up"**
- Check that repository has proper version tags (e.g., v1.0.0)
- Verify WordPress slug matches your plugin/theme directory
- Use "Force Update" button to clear cache and re-check
- Ensure your plugin/theme has proper version headers

**❌ "Rate limit exceeded"**
- Add a GitHub Personal Access Token (increases limit from 60/hour to 5,000/hour)
- Use "Clear Cache" button if needed
- Wait for rate limit to reset

### Debug Information

Enable WordPress debugging to see detailed logs:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check debug logs at: `wp-content/debug.log`

---

## 🤝 Support & Community

### 📞 **Getting Help**
- **GitHub Issues**: [Report bugs or request features](https://github.com/kobkob/kob-git-updater/issues)
- **Documentation**: Complete guides in the `/docs` folder
- **Email Support**: filipo@kobkob.org

### 🌟 **Contributing**
We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for:
- Code standards and testing requirements
- Development environment setup
- Pull request process

### 🏷️ **Release Notes**
Stay updated with the latest features and improvements:
- [GitHub Releases](https://github.com/kobkob/kob-git-updater/releases)
- [Changelog](CHANGELOG.md)

---

## 📄 License

**GPL-2.0-or-later** - This plugin is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation.

---

## 🏢 About Kobkob LLC

Professional WordPress development services specializing in custom plugins, themes, and enterprise solutions.

- **Website**: [kobkob.org](https://kobkob.org)
- **GitHub**: [@kobkob](https://github.com/kobkob)
- **Email**: filipo@kobkob.org

---

**Current Version**: 1.4.0 | **WordPress Tested**: 6.4 | **PHP**: 8.1+ | **License**: GPL-2.0+
