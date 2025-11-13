# Contributing to Kob Git Updater

Thank you for your interest in contributing to Kob Git Updater! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Create a new branch for your feature or bugfix
4. Make your changes
5. Test your changes thoroughly
6. Submit a pull request

## Development Environment

- PHP 8.1+
- WordPress 6.0+
- Local WordPress development environment

## Code Standards

- Follow WordPress coding standards for PHP
- Use proper escaping for output (`esc_html()`, `esc_attr()`, etc.)
- Include nonce verification for forms
- Check user capabilities before allowing actions
- Document your code with appropriate comments

## Testing

Before submitting a pull request:

1. Test the plugin with a fresh WordPress installation
2. Verify functionality with both public and private GitHub repositories
3. Test with different GitHub token types (classic and fine-grained)
4. Ensure the plugin works correctly with WordPress updates system

## Security

- Never commit GitHub tokens or sensitive information
- Always validate and sanitize user input
- Use WordPress nonces for form submissions
- Check user capabilities before performing actions

## Submitting Changes

1. Ensure your code follows the project's coding standards
2. Write clear, descriptive commit messages
3. Update the CHANGELOG.md if your changes affect functionality
4. Update documentation if needed
5. Submit a pull request with a clear description of your changes

## Reporting Issues

When reporting issues, please include:

- WordPress version
- PHP version
- Plugin version
- Steps to reproduce the issue
- Expected vs actual behavior
- Any relevant error messages

## License

By contributing to this project, you agree that your contributions will be licensed under the GPL-2.0-or-later license.