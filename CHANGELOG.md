# Changelog

## [1.3.1] - 2025-11-15

### Added
- **GitHub CLI Integration**: Complete GitHub CLI support with 8 new Makefile commands
- **Automated Release Process**: `make gh-release` command for streamlined releases
- **Enhanced Documentation**: Updated WARP.md with comprehensive GitHub CLI guidance
- **Repository Management**: GitHub CLI commands for PRs, issues, and repository status
- **Token-based Authentication**: Command-line GitHub authentication with Personal Access Tokens

### Improved
- **Makefile Commands**: Expanded from 46 to 54 total commands
- **Development Workflow**: Professional GitHub CLI integration for contributors
- **Project Structure**: Added `.github/` directory with CLI configuration and documentation
- **Release Automation**: Build artifacts automatically uploaded to GitHub releases

### Fixed
- **Repository Structure**: Properly organized Git repository at project root
- **Build Process**: Enhanced production and development build workflows
- **Documentation**: Complete project documentation and contribution guidelines

### Technical Details
- **GitHub CLI Commands**: gh-setup, gh-status, gh-release, gh-pr, gh-releases, gh-issues, gh-workflows, gh-runs
- **Authentication Methods**: Token, web browser, and SSH key support
- **Release Features**: Semantic versioning, automated artifact upload, changelog integration
- **Rate Limits**: 5,000 authenticated requests/hour vs 60 unauthenticated

---

## Previous Releases

- docs: update CHANGELOG for v1.2.0 technical improvements
- feat: Implement technical best practices and improvements
- docs: update WARP.md with correct project structure and build process
- docs: update CHANGELOG for v1.1.0 release
- bump version to 1.1.0
- feat: Enhanced UI with Tailwind CSS and comprehensive documentation
- Add assets directory
- feat: support authenticated installs + flash notices
- Initial commit: Kob Git Updater v1.0.0
