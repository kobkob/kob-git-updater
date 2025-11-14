<?php
/**
 * Plugin Name: Kob Git Updater
 * Description: Install and auto-update plugins & themes from GitHub releases (or branches). Adds a settings page for a GitHub token and managed repos.
 * Version: 1.0.0
 * Author: Monsenhor Filipo
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class GIU_Plugin {
	const OPTION = 'giu_options';
	const VERSION = '1.0.0';
	private const NOTICE_KEY_PREFIX = 'giu_flash_';
	private ?array $current_install = null;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_giu_add_repo', [ $this, 'handle_add_repo' ] );
		add_action( 'admin_post_giu_remove_repo', [ $this, 'handle_remove_repo' ] );
		add_action( 'admin_post_giu_install_repo', [ $this, 'handle_install_repo' ] );
		add_action( 'admin_notices', [ $this, 'render_flash_notices' ] );

		// Inject update metadata for plugins
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'filter_plugin_updates' ] );
		// Inject update metadata for themes
		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'filter_theme_updates' ] );
	}

	public static function get_options() : array {
		$defaults = [
			'token' => '',
			'repos' => [] // keyed by unique id
		];
		$opts = get_option( self::OPTION, [] );
		return wp_parse_args( is_array( $opts ) ? $opts : [], $defaults );
	}

	public static function update_options( array $opts ) : void {
		update_option( self::OPTION, $opts );
	}

	public function add_menu() {
		add_options_page(
			'GitHub Installer & Updater',
			'GitHub Installer',
			'manage_options',
			'giu-settings',
			[ $this, 'render_settings' ]
		);
	}

	public function register_settings() {
		register_setting( 'giu', self::OPTION, [ $this, 'sanitize_options' ] );

		add_settings_section( 'giu_main', 'GitHub API', function(){
			echo '<p>Provide a <strong>GitHub personal access token</strong> with permission to read the repositories you manage here. Tokens are stored in WordPress options.</p>';
		}, 'giu' );

		add_settings_field( 'giu_token', 'Token', function() {
			$opts = self::get_options();
			echo '<input type="password" style="width:480px" name="' . esc_attr( self::OPTION ) . '[token]" value="' . esc_attr( $opts['token'] ) . '" placeholder="ghp_xxx or fine-grained token">';
			if ( ! empty( $opts['token'] ) ) {
				echo '<p><em>Token is set.</em></p>';
			}
		}, 'giu', 'giu_main' );
	}

	public function sanitize_options( $input ) {
		$current = self::get_options();
		$next = [
			'token' => $current['token'],
			'repos' => $current['repos'],
		];
		if ( isset( $input['token'] ) ) {
			$next['token'] = trim( (string) $input['token'] );
		}
		if ( isset( $input['repos'] ) && is_array( $input['repos'] ) ) {
			$next['repos'] = $this->sanitize_repos( $input['repos'] );
		}
		return $next;
	}

	private function sanitize_repos( array $repos ) : array {
		$sanitized = [];
		foreach ( $repos as $id => $repo ) {
			if ( ! is_array( $repo ) ) continue;
			$key = sanitize_title( (string) $id );
			if ( $key === '' ) continue;
			$type = isset( $repo['type'] ) && $repo['type'] === 'theme' ? 'theme' : 'plugin';
			$owner = isset( $repo['owner'] ) ? sanitize_text_field( (string) $repo['owner'] ) : '';
			$name = isset( $repo['repo'] ) ? sanitize_text_field( (string) $repo['repo'] ) : '';
			$slug = isset( $repo['slug'] ) ? sanitize_text_field( (string) $repo['slug'] ) : '';
			if ( $owner === '' || $name === '' || $slug === '' ) continue;
			$latest = isset( $repo['latest'] ) ? sanitize_text_field( (string) $repo['latest'] ) : '-';
			if ( $latest === '' ) $latest = '-';
			$sanitized[ $key ] = [
				'type' => $type,
				'owner' => $owner,
				'repo' => $name,
				'slug' => $slug,
				'latest' => $latest,
			];
		}
		return $sanitized;
	}

	private function enqueue_notice( string $type, string $message ) : void {
		$user_id = get_current_user_id();
		if ( ! $user_id || $message === '' ) {
			return;
		}
		$allowed = [
			'success',
			'error',
			'info',
		];
		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'info';
		}
		$key = self::NOTICE_KEY_PREFIX . $user_id;
		$existing = get_transient( $key );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing[] = [
			'type'    => $type,
			'message' => $message,
		];
		set_transient( $key, $existing, 2 * MINUTE_IN_SECONDS );
	}

	public function render_flash_notices() : void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$key = self::NOTICE_KEY_PREFIX . $user_id;
		$queue = get_transient( $key );
		if ( ! is_array( $queue ) || $queue === [] ) {
			return;
		}
		delete_transient( $key );
		$classes = [
			'success' => 'notice notice-success',
			'error'   => 'notice notice-error',
			'info'    => 'notice notice-info',
		];
		foreach ( $queue as $notice ) {
			if ( empty( $notice['message'] ) ) {
				continue;
			}
			$type = isset( $notice['type'], $classes[ $notice['type'] ] ) ? $notice['type'] : 'info';
			printf(
				'<div class="%s"><p>%s</p></div>',
				esc_attr( $classes[ $type ] ),
				esc_html( $notice['message'] )
			);
		}
	}

	private function api_get( string $url ) {
		$opts = self::get_options();
		$args = [
			'headers' => [
				'Accept'        => 'application/vnd.github+json',
				'User-Agent'    => 'giu-wordpress/' . self::VERSION,
			],
			'timeout' => 30,
		];
		if ( ! empty( $opts['token'] ) ) {
			$args['headers']['Authorization'] = 'token ' . $opts['token'];
		}
		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) return $res;
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'giu_http',
				'GitHub API error: HTTP ' . $code . ' – ' . substr( $body, 0, 200 ),
				[
					'status' => $code,
				]
			);
		}
		$json = json_decode( $body, true );
		if ( null === $json ) {
			return new WP_Error( 'giu_json', 'Invalid JSON from GitHub' );
		}
		return $json;
	}

	private function latest_release( string $owner, string $repo ) {
		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $owner ), rawurlencode( $repo ) );
		return $this->api_get( $url );
	}

	private function repo_default_branch_zip( string $owner, string $repo, string $branch = 'main' ) : string {
		return sprintf( 'https://api.github.com/repos/%s/%s/zipball/%s', rawurlencode( $owner ), rawurlencode( $repo ), rawurlencode( $branch ) );
	}

	private function release_zipball( $release ) : ?string {
		if ( is_wp_error( $release ) ) return null;
		return isset( $release['zipball_url'] ) ? $release['zipball_url'] : null;
	}

	private function get_error_status( WP_Error $error ) : ?int {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];
			return $status > 0 ? $status : null;
		}
		if ( is_int( $data ) && $data > 0 ) {
			return $data;
		}
		return null;
	}

	private function get_default_branch( string $owner, string $repo ) {
		$details = $this->api_get(
			sprintf(
				'https://api.github.com/repos/%s/%s',
				rawurlencode( $owner ),
				rawurlencode( $repo )
			)
		);
		if ( is_wp_error( $details ) ) return $details;
		if ( isset( $details['default_branch'] ) && $details['default_branch'] !== '' ) {
			return (string) $details['default_branch'];
		}
		return null;
	}

	private function fallback_branch_zip( string $owner, string $repo ) {
		$branch = $this->get_default_branch( $owner, $repo );
		if ( is_wp_error( $branch ) ) {
			return $branch;
		}
		$branch = $branch ?: 'main';
		return $this->repo_default_branch_zip( $owner, $repo, $branch );
	}

	private function with_github_download_auth( string $download_url, callable $callback ) {
		$opts = self::get_options();
		$token = isset( $opts['token'] ) ? trim( (string) $opts['token'] ) : '';
		$filter = null;
		if ( $token !== '' && str_starts_with( $download_url, 'https://api.github.com/' ) ) {
			$filter = static function( $args, $url ) use ( $download_url, $token ) {
				if ( $url !== $download_url ) return $args;
				$args['headers']['Authorization'] = 'token ' . $token;
				if ( empty( $args['headers']['User-Agent'] ) ) {
					$args['headers']['User-Agent'] = 'giu-wordpress/' . self::VERSION;
				}
				return $args;
			};
			add_filter( 'http_request_args', $filter, 10, 2 );
		}
		try {
			return $callback();
		} finally {
			if ( $filter ) {
				remove_filter( 'http_request_args', $filter, 10 );
			}
		}
	}

	private function with_install_source_override( string $type, string $slug, callable $callback ) {
		$expected = $type === 'plugin' ? trim( dirname( $slug ), '.' . DIRECTORY_SEPARATOR ) : $slug;
		if ( $expected === '' ) {
			return $callback();
		}
		$this->current_install = [
			'type'     => $type,
			'expected' => $expected,
		];
		add_filter( 'upgrader_source_selection', [ $this, 'enforce_source_directory' ], 10, 4 );
		try {
			return $callback();
		} finally {
			remove_filter( 'upgrader_source_selection', [ $this, 'enforce_source_directory' ], 10 );
			$this->current_install = null;
		}
	}

	public function enforce_source_directory( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $this->current_install ) || ! is_string( $source ) ) {
			return $source;
		}
		if ( empty( $hook_extra['type'] ) || $hook_extra['type'] !== $this->current_install['type'] ) {
			return $source;
		}
		$current = basename( $source );
		$expected = $this->current_install['expected'];
		if ( $current === $expected ) {
			return $source;
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'giu_fs', 'Unable to initialize filesystem.' );
		}
		$target = trailingslashit( dirname( $source ) ) . $expected;
		if ( $wp_filesystem->exists( $target ) ) {
			$wp_filesystem->delete( $target, true );
		}
		if ( ! $wp_filesystem->move( $source, $target, true ) ) {
			return new WP_Error( 'giu_move', 'Unable to prepare package directory.' );
		}
		return trailingslashit( $target );
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$opts = self::get_options();
		?>
		<div class="wrap">
			<h1>GitHub Installer & Updater</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'giu' ); do_settings_sections( 'giu' ); submit_button( 'Save Token' ); ?>
			</form>

			<hr />
			<h2>Manage Repositories</h2>
			<p>Add a repo to manage and optionally install it now. Updates will be supplied from GitHub Releases.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'giu_add_repo' ); ?>
				<input type="hidden" name="action" value="giu_add_repo" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Type</th>
						<td>
							<select name="type">
								<option value="plugin">Plugin</option>
								<option value="theme">Theme</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Owner / Repo</th>
						<td><input type="text" name="owner_repo" placeholder="owner/repo" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row">WP Slug</th>
						<td>
							<input type="text" name="slug" class="regular-text" required placeholder="plugins: folder/file.php — themes: directory name">
							<p class="description">For plugins, enter the <code>plugin_basename</code> like <code>my-plugin/my-plugin.php</code>. For themes, enter the directory name.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Install Now?</th>
						<td><label><input type="checkbox" name="install_now" value="1"> Download & install immediately</label></td>
					</tr>
				</table>
				<?php submit_button( 'Add Repo' ); ?>
			</form>

			<?php $repos = $opts['repos']; ?>
			<h2>Managed Repos</h2>
			<?php if ( empty( $repos ) ) : ?>
				<p>No repos added yet.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>Type</th><th>Owner/Repo</th><th>WP Slug</th><th>Latest Release</th><th>Actions</th></tr></thead>
					<tbody>
						<?php foreach ( $repos as $id => $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['type'] ); ?></td>
							<td><?php echo esc_html( $r['owner'] . '/' . $r['repo'] ); ?></td>
							<td><code><?php echo esc_html( $r['slug'] ); ?></code></td>
							<td><?php echo esc_html( $r['latest'] ?? '-' ); ?></td>
							<td>
								<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'giu_install_repo' ); ?>
									<input type="hidden" name="action" value="giu_install_repo">
									<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
									<?php submit_button( 'Install/Update', 'secondary small', 'submit', false ); ?>
								</form>
								<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remove this repo from management?');">
									<?php wp_nonce_field( 'giu_remove_repo' ); ?>
									<input type="hidden" name="action" value="giu_remove_repo">
									<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">
									<?php submit_button( 'Remove', 'delete small', 'submit', false ); ?>
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_add_repo() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'giu_add_repo' );
		$type = isset($_POST['type']) && $_POST['type'] === 'theme' ? 'theme' : 'plugin';
		$owner_repo = isset($_POST['owner_repo']) ? trim( wp_unslash( $_POST['owner_repo'] ) ) : '';
		$slug = isset($_POST['slug']) ? trim( wp_unslash( $_POST['slug'] ) ) : '';
		if ( ! $owner_repo || ! $slug ) {
			wp_redirect( add_query_arg( 'giu_notice', 'missing', admin_url( 'options-general.php?page=giu-settings' ) ) );
			exit;
		}
		list( $owner, $repo ) = array_pad( array_map( 'trim', explode( '/', $owner_repo, 2 ) ), 2, '' );
		if ( ! $owner || ! $repo ) {
			wp_redirect( add_query_arg( 'giu_notice', 'bador', admin_url( 'options-general.php?page=giu-settings' ) ) );
			exit;
		}
		$opts = self::get_options();
		$id = sanitize_title( $type . '-' . $owner . '-' . $repo . '-' . $slug );
		$opts['repos'][ $id ] = [
			'type' => $type,
			'owner' => $owner,
			'repo' => $repo,
			'slug' => $slug,
			'latest' => '-',
		];
		self::update_options( $opts );

		if ( ! empty( $_POST['install_now'] ) ) {
			$this->do_install_by_id( $id );
		}
		wp_redirect( admin_url( 'options-general.php?page=giu-settings' ) );
		exit;
	}

	public function handle_remove_repo() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'giu_remove_repo' );
		$id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$opts = self::get_options();
		unset( $opts['repos'][ $id ] );
		self::update_options( $opts );
		wp_redirect( admin_url( 'options-general.php?page=giu-settings' ) );
		exit;
	}

	public function handle_install_repo() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'giu_install_repo' );
		$id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$this->do_install_by_id( $id );
		wp_redirect( admin_url( 'options-general.php?page=giu-settings' ) );
		exit;
	}

	private function do_install_by_id( string $id ) : void {
		$opts = self::get_options();
		if ( empty( $opts['repos'][ $id ] ) ) return;
		$r = $opts['repos'][ $id ];
		$download_url = $this->get_download_url( $r['owner'], $r['repo'] );
		if ( is_wp_error( $download_url ) ) {
			$this->enqueue_notice( 'error', $download_url->get_error_message() );
			return;
		}
		$ok = $this->install_package( $r['type'], $download_url, $r['slug'] );
		if ( is_wp_error( $ok ) ) {
			$this->enqueue_notice( 'error', 'Install failed: ' . $ok->get_error_message() );
			return;
		}
		$release = $this->latest_release( $r['owner'], $r['repo'] );
		if ( ! is_wp_error( $release ) && isset( $release['tag_name'] ) ) {
			$opts['repos'][ $id ]['latest'] = $release['tag_name'];
			self::update_options( $opts );
		}
		$this->enqueue_notice(
			'success',
			sprintf(
				'Installed/updated %s from %s/%s.',
				$r['type'],
				$r['owner'],
				$r['repo']
			)
		);
	}

	private function get_download_url( string $owner, string $repo ) {
		$release = $this->latest_release( $owner, $repo );
		if ( is_wp_error( $release ) ) {
			$status = $this->get_error_status( $release );
			if ( $status === 404 ) {
				return $this->fallback_branch_zip( $owner, $repo );
			}
			return $release;
		}
		$zip = $this->release_zipball( $release );
		if ( $zip ) {
			return $zip;
		}
		return $this->fallback_branch_zip( $owner, $repo );
	}

	private function install_package( string $type, string $download_url, string $slug ) {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$skin = new Automatic_Upgrader_Skin();
		$result = $this->with_github_download_auth(
			$download_url,
			function() use ( $type, $download_url, $skin, $slug ) {
				return $this->with_install_source_override(
					$type,
					$slug,
					function() use ( $type, $download_url, $skin ) {
						if ( $type === 'theme' ) {
							$upgrader = new Theme_Upgrader( $skin );
							return $upgrader->install(
								$download_url,
								[
									'overwrite_package' => true,
								]
							);
						}
						$upgrader = new Plugin_Upgrader( $skin );
						return $upgrader->install(
							$download_url,
							[
								'overwrite_package' => true,
							]
						);
					}
				);
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'giu_install', 'Upgrader reported failure.' );
		}
		return true;
	}

	public function filter_plugin_updates( $transient ) {
		if ( empty( $transient ) || ! isset( $transient->checked ) ) return $transient;
		$opts = self::get_options();
		foreach ( $opts['repos'] as $r ) {
			if ( $r['type'] !== 'plugin' ) continue;
			$plugin_basename = $r['slug']; // e.g., my-plugin/my-plugin.php
			if ( ! isset( $transient->checked[ $plugin_basename ] ) ) continue; // not installed
			$installed_version = $transient->checked[ $plugin_basename ];
			$release = $this->latest_release( $r['owner'], $r['repo'] );
			if ( is_wp_error( $release ) || empty( $release['tag_name'] ) ) continue;
			$new_version = ltrim( (string) $release['tag_name'], 'v' );
			if ( version_compare( $new_version, $installed_version, '>' ) ) {
				$transient->response[ $plugin_basename ] = (object) [
					'slug'        => dirname( $plugin_basename ),
					'plugin'      => $plugin_basename,
					'new_version' => $new_version,
					'url'         => sprintf( 'https://github.com/%s/%s', $r['owner'], $r['repo'] ),
					'package'     => $this->get_download_url( $r['owner'], $r['repo'] ),
				];
			}
		}
		return $transient;
	}

	public function filter_theme_updates( $transient ) {
		if ( empty( $transient ) || ! isset( $transient->checked ) ) return $transient;
		$opts = self::get_options();
		foreach ( $opts['repos'] as $r ) {
			if ( $r['type'] !== 'theme' ) continue;
			$slug = $r['slug']; // theme directory name
			if ( ! isset( $transient->checked[ $slug ] ) ) continue; // not installed
			$installed_version = $transient->checked[ $slug ];
			$release = $this->latest_release( $r['owner'], $r['repo'] );
			if ( is_wp_error( $release ) || empty( $release['tag_name'] ) ) continue;
			$new_version = ltrim( (string) $release['tag_name'], 'v' );
			if ( version_compare( $new_version, $installed_version, '>' ) ) {
				$transient->response[ $slug ] = [
					'theme'       => $slug,
					'new_version' => $new_version,
					'url'         => sprintf( 'https://github.com/%s/%s', $r['owner'], $r['repo'] ),
					'package'     => $this->get_download_url( $r['owner'], $r['repo'] ),
				];
			}
		}
		return $transient;
	}
}

add_action( 'plugins_loaded', function(){ new GIU_Plugin(); } );

/*
USAGE
-----
1) Install this plugin like any other.
2) Go to Settings → GitHub Installer:
   - Paste a GitHub personal access token (fine‑grained is recommended; read access to the repos).
   - Add a repo: choose Plugin or Theme, enter "owner/repo" and the WordPress slug:
       * Plugins: plugin_basename, e.g. my-plugin/my-plugin.php
       * Themes: directory name, e.g. twentytwentyfive
   - Optionally tick "Install Now" to download and install from the latest Release.
3) Updates: When the repo publishes a new Release (tag), WordPress will see it on the next update check and offer to update. The tag name (e.g. v1.2.3) is used as version (v is stripped). Ensure your plugin/theme header Version matches your latest tag after the first install.

Notes
-----
• If a repo has no releases, the installer falls back to the default branch (main). Updates are only automatically offered for Releases.
• Private repos require the token.
• GitHub API rate limits apply; token raises limits.
*/
