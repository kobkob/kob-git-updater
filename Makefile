# Makefile for Kob Git Updater WordPress Plugin
# Provides standardized development workflow automation

.PHONY: help install test build clean deploy docker docs
.DEFAULT_GOAL := help

# Configuration
PLUGIN_NAME := kob-git-updater
PLUGIN_DIR := plugin
PLUGIN_VERSION := $(shell grep -oP "Version:\s*\K[\d\.]+" $(PLUGIN_DIR)/kob-git-updater-new.php 2>/dev/null || grep -oP "Version:\s*\K[\d\.]+" $(PLUGIN_DIR)/kob-git-updater.php 2>/dev/null || echo "unknown")
BUILD_DIR := build
DIST_DIR := dist
DOCKER_IMAGE := kob-git-updater
DOCKER_TAG := $(PLUGIN_VERSION)

# Colors for output
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
BLUE := \033[34m
NC := \033[0m

##@ Development

help: ## Display this help message
	@echo "$(BLUE)Kob Git Updater - Development Makefile$(NC)"
	@echo "========================================"
	@echo ""
	@echo "Version: $(GREEN)$(PLUGIN_VERSION)$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make $(GREEN)<target>$(NC)\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2 } /^##@/ { printf "\n$(BLUE)%s$(NC)\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

install: ## Install dependencies and setup development environment
	@echo "$(BLUE)Installing dependencies...$(NC)"
	@cd $(PLUGIN_DIR) && if [ ! -f "composer.json" ]; then echo "$(RED)Error: composer.json not found$(NC)" && exit 1; fi
	@cd $(PLUGIN_DIR) && composer install --no-interaction
	@if [ -x "scripts/setup-dev.sh" ]; then \
		echo "$(YELLOW)Running development setup...$(NC)"; \
		scripts/setup-dev.sh || true; \
	fi
	@echo "$(GREEN)✓ Dependencies installed and environment configured$(NC)"

update: ## Update Composer dependencies
	@echo "$(BLUE)Updating dependencies...$(NC)"
	@cd $(PLUGIN_DIR) && composer update --no-interaction
	@echo "$(GREEN)✓ Dependencies updated$(NC)"

##@ Testing

test: ## Run comprehensive test suite
	@echo "$(BLUE)Running comprehensive test suite...$(NC)"
	@if [ -x "scripts/test.sh" ]; then \
		cd $(PLUGIN_DIR) && ../scripts/test.sh; \
	else \
		$(MAKE) test-unit && $(MAKE) test-lint && $(MAKE) test-analyze; \
	fi

test-unit: ## Run PHPUnit tests only
	@echo "$(BLUE)Running PHPUnit tests...$(NC)"
	@cd $(PLUGIN_DIR) && composer run test

test-lint: ## Run PHP CodeSniffer only
	@echo "$(BLUE)Running PHP CodeSniffer...$(NC)"
	@cd $(PLUGIN_DIR) && composer run lint

test-analyze: ## Run PHPStan static analysis only
	@echo "$(BLUE)Running PHPStan analysis...$(NC)"
	@cd $(PLUGIN_DIR) && composer run analyze

test-security: ## Run Composer security audit
	@echo "$(BLUE)Running security audit...$(NC)"
	@cd $(PLUGIN_DIR) && composer audit

test-coverage: ## Run tests with coverage report
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	@cd $(PLUGIN_DIR) && composer run test:coverage

test-watch: ## Watch files and run tests on changes
	@echo "$(BLUE)Watching for file changes...$(NC)"
	@if command -v inotifywait >/dev/null 2>&1; then \
		echo "Press Ctrl+C to stop"; \
		cd $(PLUGIN_DIR) && while true; do \
			inotifywait -r -e modify,create,delete src/ tests/ --exclude '.*\.swp$$' -q 2>/dev/null; \
			echo "$(YELLOW)Changes detected, running tests...$(NC)"; \
			if $(MAKE) test-unit >/dev/null 2>&1; then \
				echo "$(GREEN)✓ Tests passed$(NC)"; \
			else \
				echo "$(RED)✗ Tests failed$(NC)"; \
			fi; \
		done; \
	else \
		echo "$(YELLOW)inotifywait not available. Install inotify-tools for file watching.$(NC)"; \
		echo "Alternative: watch -n 5 'make test-unit'"; \
	fi

##@ Building

build: test ## Create production build (runs tests first)
	@echo "$(BLUE)Creating production build...$(NC)"
	@if [ -x "scripts/build.sh" ]; then \
		scripts/build.sh; \
	else \
		$(MAKE) build-manual; \
	fi

build-dev: ## Create development build with all dependencies
	@echo "$(BLUE)Creating development build...$(NC)"
	@if [ -x "scripts/quick-build.sh" ]; then \
		scripts/quick-build.sh; \
	else \
		$(MAKE) build-dev-manual; \
	fi

build-manual: ## Manual production build process
	@mkdir -p $(BUILD_DIR) $(DIST_DIR)
	@echo "$(YELLOW)Copying files...$(NC)"
	@cp -r $(PLUGIN_DIR) $(BUILD_DIR)/$(PLUGIN_NAME)
	@cd $(BUILD_DIR)/$(PLUGIN_NAME) && \
		composer install --no-dev --optimize-autoloader --no-interaction && \
		rm -rf .git .gitignore .github tests/ phpunit.xml composer.json composer.lock && \
		if [ -f "kob-git-updater-new.php" ]; then \
			rm -f kob-git-updater.php && mv kob-git-updater-new.php kob-git-updater.php; \
		fi
	@cd $(BUILD_DIR) && zip -r ../$(DIST_DIR)/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip $(PLUGIN_NAME)
	@cd $(DIST_DIR) && ln -sf $(PLUGIN_NAME)-$(PLUGIN_VERSION).zip $(PLUGIN_NAME)-latest.zip
	@echo "$(GREEN)✓ Build created: $(DIST_DIR)/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip$(NC)"

build-dev-manual: ## Manual development build process
	@mkdir -p $(BUILD_DIR)-dev $(DIST_DIR)
	@echo "$(YELLOW)Copying files for development build...$(NC)"
	@cp -r $(PLUGIN_DIR) $(BUILD_DIR)-dev/$(PLUGIN_NAME)
	@cd $(BUILD_DIR)-dev/$(PLUGIN_NAME) && \
		composer install --optimize-autoloader --no-interaction && \
		rm -rf .git
	@cd $(BUILD_DIR)-dev && zip -r ../$(DIST_DIR)/$(PLUGIN_NAME)-$(PLUGIN_VERSION)-dev.zip $(PLUGIN_NAME)
	@echo "$(GREEN)✓ Development build created: $(DIST_DIR)/$(PLUGIN_NAME)-$(PLUGIN_VERSION)-dev.zip$(NC)"

build-wp-org: ## Create clean WordPress.org submission package
	@echo "$(BLUE)Creating WordPress.org submission package...$(NC)"
	@if [ -x "scripts/build-wordpress-org.sh" ]; then \
		scripts/build-wordpress-org.sh; \
	else \
		echo "$(RED)WordPress.org build script not found$(NC)"; \
		exit 1; \
	fi

##@ Docker

docker-build: ## Build Docker development environment
	@echo "$(BLUE)Building Docker image...$(NC)"
	cd $(PLUGIN_DIR) && docker build -t $(DOCKER_IMAGE):$(DOCKER_TAG) -f Dockerfile .
	docker tag $(DOCKER_IMAGE):$(DOCKER_TAG) $(DOCKER_IMAGE):latest
	@echo "$(GREEN)✓ Docker image built: $(DOCKER_IMAGE):$(DOCKER_TAG)$(NC)"

docker-dev: ## Start Docker development environment
	@echo "$(BLUE)Starting Docker development environment...$(NC)"
	cd $(PLUGIN_DIR) && docker-compose up -d
	@echo "$(GREEN)✓ Development environment started$(NC)"
	@echo "WordPress: http://localhost:8082"
	@echo "phpMyAdmin: http://localhost:8083"
	@echo "MailCatcher: http://localhost:1082"
	@echo "Redis: localhost:6380"
	@echo "MySQL: localhost:3307"

docker-stop: ## Stop Docker development environment
	@echo "$(YELLOW)Stopping Docker development environment...$(NC)"
	cd $(PLUGIN_DIR) && docker-compose down
	@echo "$(GREEN)✓ Development environment stopped$(NC)"

docker-clean: ## Clean Docker images and containers
	@echo "$(YELLOW)Cleaning Docker resources...$(NC)"
	cd $(PLUGIN_DIR) && docker-compose down -v --remove-orphans
	docker rmi $(DOCKER_IMAGE):$(DOCKER_TAG) $(DOCKER_IMAGE):latest 2>/dev/null || true
	@echo "$(GREEN)✓ Docker resources cleaned$(NC)"

docker-logs: ## Show Docker container logs
	cd $(PLUGIN_DIR) && docker-compose logs -f

docker-shell: ## Access WordPress container shell
	cd $(PLUGIN_DIR) && docker-compose exec wordpress bash

docker-mysql: ## Access MySQL container shell
	cd $(PLUGIN_DIR) && docker-compose exec db mysql -u wordpress -p

##@ Permission Management

fix-permissions: ## Fix file ownership and permissions for development
	@echo "$(BLUE)Fixing file permissions...$(NC)"
	@if [ -x "$(PLUGIN_DIR)/scripts/fix-permissions.sh" ]; then \
		cd $(PLUGIN_DIR) && bash scripts/fix-permissions.sh; \
	else \
		echo "$(YELLOW)Permission script not found, using manual fix...$(NC)"; \
		sudo chown -R $$(id -u):$$(id -g) $(PLUGIN_DIR)/src $(PLUGIN_DIR)/assets $(PLUGIN_DIR)/vendor $(PLUGIN_DIR)/*.php $(PLUGIN_DIR)/tests 2>/dev/null || true; \
		chmod -R 755 $(PLUGIN_DIR) 2>/dev/null || true; \
		find $(PLUGIN_DIR) -name "*.php" -exec chmod 644 {} + 2>/dev/null || true; \
	fi
	@echo "$(GREEN)✓ Permissions fixed$(NC)"

fix-docker-permissions: ## Rebuild Docker containers with proper user mapping
	@echo "$(BLUE)Rebuilding Docker containers with user mapping...$(NC)"
	@if [ -x "$(PLUGIN_DIR)/scripts/fix-permissions.sh" ]; then \
		cd $(PLUGIN_DIR) && bash scripts/fix-permissions.sh restart; \
	else \
		echo "$(YELLOW)Using manual Docker rebuild...$(NC)"; \
		cd $(PLUGIN_DIR) && export HOST_UID=$$(id -u) && export HOST_GID=$$(id -g) && \
		docker-compose down && \
		docker-compose build --no-cache wordpress && \
		docker-compose up -d; \
	fi
	@echo "$(GREEN)✓ Docker containers rebuilt with proper permissions$(NC)"

##@ Release Management

deploy: ## Deploy new version (runs full pipeline)
	@echo "$(BLUE)Deploying version $(PLUGIN_VERSION)...$(NC)"
	@if [ -x "scripts/deploy.sh" ]; then \
		cd $(PLUGIN_DIR) && ../scripts/deploy.sh; \
	else \
		echo "$(RED)Deploy script not found. Run tests and build manually.$(NC)"; \
		$(MAKE) test && $(MAKE) build; \
	fi

version: ## Display current version
	@echo "Current version: $(GREEN)$(PLUGIN_VERSION)$(NC)"

changelog: ## Generate changelog from git commits
	@echo "$(BLUE)Generating changelog...$(NC)"
	@if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then \
		echo "# Changelog" > CHANGELOG.md; \
		echo "" >> CHANGELOG.md; \
		echo "## [$(PLUGIN_VERSION)] - $$(date +%Y-%m-%d)" >> CHANGELOG.md; \
		echo "" >> CHANGELOG.md; \
		git log --oneline --no-merges -10 | sed 's/^[a-f0-9]* /- /' >> CHANGELOG.md; \
		echo "$(GREEN)✓ Changelog generated$(NC)"; \
	else \
		echo "$(YELLOW)Not a git repository or git not available$(NC)"; \
	fi

tag: ## Create git tag for current version
	@if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then \
		echo "$(BLUE)Creating tag v$(PLUGIN_VERSION)...$(NC)"; \
		git tag -a v$(PLUGIN_VERSION) -m "Release v$(PLUGIN_VERSION)"; \
		echo "$(GREEN)✓ Tag v$(PLUGIN_VERSION) created$(NC)"; \
		echo "$(YELLOW)Push with: git push origin v$(PLUGIN_VERSION)$(NC)"; \
	else \
		echo "$(RED)Not a git repository$(NC)"; \
	fi

##@ Maintenance

clean: ## Clean build artifacts and caches
	@echo "$(YELLOW)Cleaning build artifacts...$(NC)"
	@rm -rf $(BUILD_DIR) $(BUILD_DIR)-dev $(DIST_DIR)
	@rm -rf vendor/ .phpunit.result.cache
	@find . -name "*.log" -delete 2>/dev/null || true
	@find . -name "*.tmp" -delete 2>/dev/null || true
	@echo "$(GREEN)✓ Build artifacts cleaned$(NC)"

clean-all: clean ## Clean everything including Docker
	@$(MAKE) docker-clean
	@echo "$(GREEN)✓ All artifacts cleaned$(NC)"

reset: clean install ## Reset environment (clean + install)
	@echo "$(GREEN)✓ Environment reset$(NC)"

status: ## Show development status
	@echo "$(BLUE)Development Status$(NC)"
	@echo "=================="
	@echo "Plugin: $(PLUGIN_NAME)"
	@echo "Version: $(GREEN)$(PLUGIN_VERSION)$(NC)"
	@if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then \
		if [ -n "$$(git status --porcelain 2>/dev/null)" ]; then \
			echo "Git status: $(YELLOW)Modified files$(NC)"; \
		else \
			echo "Git status: $(GREEN)Clean$(NC)"; \
		fi; \
		echo "Branch: $$(git branch --show-current 2>/dev/null || echo 'unknown')"; \
	fi
	@if [ -d "vendor" ]; then \
		echo "Dependencies: $(GREEN)Installed$(NC)"; \
	else \
		echo "Dependencies: $(RED)Missing$(NC)"; \
	fi
	@if [ -d "$(DIST_DIR)" ]; then \
		echo "Recent builds:"; \
		ls -la $(DIST_DIR) 2>/dev/null | tail -3 | sed 's/^/  /'; \
	fi

info: ## Show detailed project information
	@echo "$(BLUE)Project Information$(NC)"
	@echo "==================="
	@echo "Plugin Name: $(PLUGIN_NAME)"
	@echo "Version: $(PLUGIN_VERSION)"
	@echo "Build Directory: $(BUILD_DIR)"
	@echo "Distribution Directory: $(DIST_DIR)"
	@echo "Docker Image: $(DOCKER_IMAGE):$(DOCKER_TAG)"
	@echo ""
	@echo "$(BLUE)Available Composer Scripts:$(NC)"
	@composer run-script --list 2>/dev/null | grep -E '^\s+\w+' | sed 's/^/  /' || echo "  No composer scripts found"

##@ Documentation

docs: ## Generate documentation
	@echo "$(BLUE)Generating documentation...$(NC)"
	@if [ -d "src" ]; then \
		echo "Scanning source files..."; \
		find src/ -name "*.php" -exec grep -l "@" {} \; | wc -l | xargs echo "Files with documentation:"; \
	fi
	@echo "$(GREEN)✓ Documentation scan completed$(NC)"

validate: ## Validate project structure and configuration
	@echo "$(BLUE)Validating project structure...$(NC)"
	@errors=0; \
	if [ ! -f "composer.json" ]; then echo "$(RED)✗ composer.json missing$(NC)"; errors=$$((errors+1)); else echo "$(GREEN)✓ composer.json$(NC)"; fi; \
	if [ ! -d "src" ]; then echo "$(RED)✗ src/ directory missing$(NC)"; errors=$$((errors+1)); else echo "$(GREEN)✓ src/ directory$(NC)"; fi; \
	if [ ! -d "tests" ]; then echo "$(RED)✗ tests/ directory missing$(NC)"; errors=$$((errors+1)); else echo "$(GREEN)✓ tests/ directory$(NC)"; fi; \
	if [ ! -f "phpunit.xml" ]; then echo "$(RED)✗ phpunit.xml missing$(NC)"; errors=$$((errors+1)); else echo "$(GREEN)✓ phpunit.xml$(NC)"; fi; \
	if [ $$errors -eq 0 ]; then echo "$(GREEN)✓ Project structure is valid$(NC)"; else echo "$(RED)✗ Project structure has $$errors errors$(NC)"; fi

##@ GitHub CLI

gh-setup: ## Setup GitHub CLI for this repository
	@./scripts/setup-gh.sh

gh-status: ## Show GitHub repository status
	@$(MAKE) _require-gh
	@echo "$(BLUE)GitHub Repository Status$(NC)"
	@echo "========================"
	@echo "Repository: $$(gh repo view --json name --jq .name)"
	@echo "Description: $$(gh repo view --json description --jq '.description // "No description"')"
	@echo "Visibility: $$(gh repo view --json isPrivate --jq 'if .isPrivate then "Private" else "Public" end')"
	@echo "Stars: $$(gh repo view --json stargazerCount --jq .stargazerCount)"
	@echo "Forks: $$(gh repo view --json forkCount --jq .forkCount)"
	@echo ""
	@if gh pr list --limit 1 >/dev/null 2>&1; then \
		echo "$(BLUE)Open Pull Requests:$(NC)"; \
		gh pr list --limit 5 || echo "  None"; \
	fi
	@echo ""
	@if gh issue list --limit 1 >/dev/null 2>&1; then \
		echo "$(BLUE)Open Issues:$(NC)"; \
		gh issue list --limit 5 || echo "  None"; \
	fi

gh-releases: ## List GitHub releases
	@$(MAKE) _require-gh
	@echo "$(BLUE)GitHub Releases$(NC)"
	@echo "==============="
	@gh release list --limit 10 || echo "No releases found"

gh-release: ## Create GitHub release for current version
	@$(MAKE) _require-gh
	@echo "$(BLUE)Creating release v$(PLUGIN_VERSION)...$(NC)"
	@if [ ! -f "$(DIST_DIR)/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip" ]; then \
		echo "$(YELLOW)Build artifact not found, creating production build...$(NC)"; \
		$(MAKE) build-prod; \
	fi
	@echo "$(BLUE)Creating GitHub release...$(NC)"
	@gh release create "v$(PLUGIN_VERSION)" \
		"$(DIST_DIR)/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip" \
		--title "Release v$(PLUGIN_VERSION)" \
		--notes "Release v$(PLUGIN_VERSION) - GitHub CLI Integration and Enhanced Development Workflow\n\n### Major Features Added:\n- Complete GitHub CLI integration with 8 new Makefile commands\n- Automated release process with make gh-release command\n- Token-based command-line authentication\n- Professional development workflow with comprehensive tooling\n\nSee CHANGELOG.md for complete details."
	@echo "$(GREEN)✓ Release v$(PLUGIN_VERSION) created successfully$(NC)"

gh-pr: ## Create pull request for current branch
	@$(MAKE) _require-gh _require-git
	@CURRENT_BRANCH=$$(git branch --show-current); \
	if [ "$$CURRENT_BRANCH" = "main" ] || [ "$$CURRENT_BRANCH" = "master" ]; then \
		echo "$(RED)Cannot create PR from main/master branch$(NC)"; \
		exit 1; \
	fi; \
	echo "$(BLUE)Creating pull request from $$CURRENT_BRANCH...$(NC)"; \
	gh pr create --fill --assignee @me

gh-workflows: ## Show GitHub Actions workflows
	@$(MAKE) _require-gh
	@echo "$(BLUE)GitHub Actions Workflows$(NC)"
	@echo "=========================="
	@gh workflow list || echo "No workflows found"

gh-runs: ## Show recent GitHub Actions runs
	@$(MAKE) _require-gh
	@echo "$(BLUE)Recent GitHub Actions Runs$(NC)"
	@echo "============================"
	@gh run list --limit 10 || echo "No workflow runs found"

gh-issues: ## List GitHub issues
	@$(MAKE) _require-gh
	@echo "$(BLUE)GitHub Issues$(NC)"
	@echo "=============="
	@gh issue list --limit 10 || echo "No issues found"

##@ WP-CLI

wp-cli: ## Access WP-CLI in WordPress container
	@$(MAKE) _require-docker
	@echo "$(BLUE)Starting WP-CLI session in WordPress container...$(NC)"
	@echo "Use 'exit' to return to host shell"
	@cd plugin && sudo docker-compose exec wordpress bash

wp-info: ## Show WordPress information via WP-CLI
	@$(MAKE) _require-docker
	@echo "$(BLUE)WordPress Information$(NC)"
	@echo "===================="
	@cd plugin && sudo docker-compose exec -T wordpress php -r "\$$wp_cli = '/usr/local/bin/wp'; if (file_exists(\$$wp_cli)) { system('php ' . \$$wp_cli . ' --info --allow-root --path=/var/www/html'); } else { echo 'WP-CLI not found'; }"

wp-status: ## Show WordPress status
	@$(MAKE) _require-docker
	@echo "$(BLUE)WordPress Status$(NC)"
	@echo "================="
	@cd plugin && sudo docker-compose exec -T wordpress php -r "\$$wp_cli = '/usr/local/bin/wp'; if (file_exists(\$$wp_cli)) { system('php ' . \$$wp_cli . ' core version --allow-root --path=/var/www/html'); } else { echo 'WP-CLI not found'; }"

wp-plugins: ## List WordPress plugins
	@$(MAKE) _require-docker
	@echo "$(BLUE)WordPress Plugins$(NC)"
	@echo "=================="
	@cd plugin && sudo docker-compose exec -T wordpress php -r "\$$wp_cli = '/usr/local/bin/wp'; if (file_exists(\$$wp_cli)) { system('php ' . \$$wp_cli . ' plugin list --allow-root --path=/var/www/html'); } else { echo 'WP-CLI not found'; }"

wp-activate: ## Activate the Kob Git Updater plugin
	@$(MAKE) _require-docker
	@echo "$(BLUE)Activating Kob Git Updater plugin...$(NC)"
	@cd plugin && sudo docker-compose exec -T wordpress php -r "\$$wp_cli = '/usr/local/bin/wp'; if (file_exists(\$$wp_cli)) { system('php ' . \$$wp_cli . ' plugin activate kob-git-updater --allow-root --path=/var/www/html'); } else { echo 'WP-CLI not found'; }"

wp-deactivate: ## Deactivate the Kob Git Updater plugin
	@$(MAKE) _require-docker
	@echo "$(BLUE)Deactivating Kob Git Updater plugin...$(NC)"
	@cd plugin && sudo docker-compose exec -T wordpress php -r "\$$wp_cli = '/usr/local/bin/wp'; if (file_exists(\$$wp_cli)) { system('php ' . \$$wp_cli . ' plugin deactivate kob-git-updater --allow-root --path=/var/www/html'); } else { echo 'WP-CLI not found'; }"

# Utility targets
.PHONY: _require-composer _require-git _require-docker _require-gh

_require-composer:
	@command -v composer >/dev/null 2>&1 || (echo "$(RED)Composer is required$(NC)" && exit 1)

_require-git:
	@command -v git >/dev/null 2>&1 || (echo "$(RED)Git is required$(NC)" && exit 1)

_require-docker:
	@command -v docker >/dev/null 2>&1 || (echo "$(RED)Docker is required$(NC)" && exit 1)
	@command -v docker-compose >/dev/null 2>&1 || (echo "$(RED)Docker Compose is required$(NC)" && exit 1)

_require-gh:
	@command -v gh >/dev/null 2>&1 || (echo "$(RED)GitHub CLI is required. Install with: sudo apt install gh$(NC)" && exit 1)
	@gh auth status >/dev/null 2>&1 || (echo "$(RED)GitHub CLI not authenticated. Run: make gh-setup$(NC)" && exit 1)
