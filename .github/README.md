# GitHub CLI Integration

This directory contains configuration and documentation for GitHub CLI integration with the Kob Git Updater project.

## Quick Setup

```bash
# 1. Setup GitHub CLI for this repository
make gh-setup

# 2. Verify authentication and access
make gh-status
```

## Available Commands

### Repository Management
```bash
make gh-status      # Show repository status, PRs, and issues
make gh-releases    # List all releases
make gh-release     # Create release for current version
```

### Development Workflow
```bash
make gh-pr          # Create pull request from current branch
make gh-issues      # List open issues
make gh-workflows   # Show GitHub Actions workflows
make gh-runs        # Show recent workflow runs
```

### Direct GitHub CLI Commands

Once authenticated, you can use these `gh` commands directly:

#### Repository Operations
```bash
gh repo view                    # View repository information
gh repo clone owner/repo        # Clone repository
gh repo fork                    # Fork repository
```

#### Pull Requests
```bash
gh pr list                      # List pull requests
gh pr create                    # Create new pull request
gh pr checkout 123              # Checkout PR #123
gh pr merge 123                 # Merge PR #123
gh pr review 123                # Review PR #123
```

#### Issues
```bash
gh issue list                   # List issues
gh issue create                 # Create new issue
gh issue close 123              # Close issue #123
gh issue reopen 123             # Reopen issue #123
```

#### Releases
```bash
gh release list                 # List releases
gh release view v1.3.0          # View specific release
gh release create v1.3.1        # Create new release
gh release upload v1.3.1 *.zip  # Upload assets to release
```

#### GitHub Actions
```bash
gh workflow list                # List workflows
gh workflow run ci.yml          # Run specific workflow
gh run list                     # List recent runs
gh run view 123456              # View specific run
gh run logs 123456              # Download run logs
```

## Configuration

### Aliases
The following aliases are configured in `gh-config.yml`:

- `gh co` → `gh pr checkout` - Checkout pull request
- `gh pv` → `gh pr view` - View pull request
- `gh rv` → `gh repo view` - View repository
- `gh rl` → `gh release list` - List releases
- `gh rc` → `gh release create` - Create release
- `gh il` → `gh issue list` - List issues
- `gh ic` → `gh issue create` - Create issue
- `gh wl` → `gh workflow list` - List workflows
- `gh wr` → `gh run list` - List workflow runs

### Authentication

GitHub CLI supports multiple authentication methods:

1. **Personal Access Token** (recommended for command line):
   ```bash
   make gh-setup
   # Choose option 1 and follow the prompts
   ```
   
   **Creating a Personal Access Token:**
   - Go to: https://github.com/settings/tokens
   - Click "Generate new token" → "Generate new token (classic)"
   - Select scopes:
     - `repo` (full repository access)
     - `workflow` (GitHub Actions)
     - `admin:org` (if organization repository)
   - Copy the token and use it in the setup script

2. **Web Browser**:
   ```bash
   gh auth login --web
   ```

3. **SSH Key**:
   ```bash
   gh auth login --git-protocol ssh
   ```

## Integration with Release Process

The GitHub CLI is integrated with the project's release process:

```bash
# Standard release workflow
make version       # Update version if needed
make test         # Run all tests
make build-prod   # Create production build
make gh-release   # Create GitHub release with build artifact
```

## Troubleshooting

### Authentication Issues
```bash
# Check authentication status
gh auth status

# Re-authenticate if needed
gh auth logout
make gh-setup
```

### Permission Issues
```bash
# Verify repository access
gh repo view

# Check if you have write access
gh api repos/:owner/:repo --jq .permissions
```

### API Rate Limits
```bash
# Check rate limit status
gh api rate_limit
```

GitHub CLI uses authenticated requests (5,000/hour) vs unauthenticated (60/hour), so authentication is strongly recommended.

## Further Reading

- [GitHub CLI Manual](https://cli.github.com/manual/)
- [GitHub CLI Authentication](https://cli.github.com/manual/gh_auth)
- [GitHub API Documentation](https://docs.github.com/en/rest)