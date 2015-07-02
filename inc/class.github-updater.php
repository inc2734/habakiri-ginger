<?php
if ( class_exists( 'habakiri_Theme_GitHub_Updater' ) || !is_admin() ) {
	return;
}

/**
 * habakiri_Theme_GitHub_Updater
 * Version    : 1.0.0
 * Author     : Takashi Kitajima
 * Created    : July 1, 2015
 * Modified   : 
 * License    : GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class habakiri_Theme_GitHub_Updater {

	/**
	 * テーマのディレクトリ名
	 * @var string
	 * @example habakiri-ginger
	 */
	protected $slug;

	/**
	 * テーマデータ
	 * @var array
	 */
	protected $theme;

	/**
	 * GitHub リポジトリのユーザー名
	 */
	protected $user_name;

	/**
	 * GutHub API の response body
	 * @var object
	 */
	protected $github;
	
	/**
	 * @param string $slug テーマのディレクトリ名
	 * @param string $user_name GitHub のユーザー名
	 */
	public function __construct( $slug, $user_name ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			delete_site_transient( 'update_theme' );
		}
		
		if ( !function_exists( 'wp_get_theme' ) ){
			require_once( ABSPATH . '/wp-includes/theme.php' );
		}
		$this->slug      = $slug;
		$this->theme     = wp_get_theme( $slug );
		$this->user_name = $user_name;

		add_filter(
			'pre_set_site_transient_update_themes',
			array( $this, 'pre_set_site_transient_update_themes' )
		);
		add_filter(
			'upgrader_post_install',
			array( $this, 'upgrader_post_install' ),
			9,
			3
		);
		add_filter(
			'wp_prepare_themes_for_js',
			array( $this, 'wp_prepare_themes_for_js' )
		);
	}

	/**
	 * 対象のテーマについて GitHub に問い合わせ、更新があれば $transient を更新
	 *
	 * @param object $transient
	 * @return object $transient
	 */
	public function pre_set_site_transient_update_themes( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		if ( empty( $transient->checked[$this->slug] ) ) {
			return $transient;
		}

		$github = $this->get_github();

		if ( empty( $github->name ) ) {
			return $transient;
		}

		$do_update = version_compare(
			$github->name,
			$transient->checked[$this->slug]
		);

		if ( $do_update != 1 ) {
			return $transient;
		}

		$theme = array(
			'theme'       => $this->slug,
			'new_version' => $github->name,
			'url'         => $this->theme->get( 'ThemeURI' ),
			'package'     => $github->zipball_url
		);

		$transient->response[$this->slug] = $theme;
		return $transient;
	}

	/**
	 * テーマの配置と有効化
	 *
	 * @param bool $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public function upgrader_post_install( $response, $hook_extra, $result ) {
		if ( !isset( $hook_extra['theme'] ) || $hook_extra['theme'] !== $this->slug ) {
			return $response;
		}

		global $wp_filesystem;
		$theme_dir_path = get_theme_root() . DIRECTORY_SEPARATOR . $this->slug;
		$wp_filesystem->move( $result['destination'], $theme_dir_path );

		if ( $is_activated ) {
			add_action( 'switch_theme', array( $this, 'switch_theme' ) );
		}

		return $response;
	}

	/**
	 * 現在のテーマが本テーマでないときは強制的に切り替える
	 *
	 * @param string $new_name 現在のテーマ名
	 */
	public function switch_theme( $new_name ) {
		if ( $new_name !== $this->slug ) {
			switch_theme( $this->slug );
		}
		remove_action( 'switch_theme', array( $this, 'switch_theme' ) );
	}

	/**
	 * GitHub API へのリクエスト
	 *
	 * @return object
	 */
	protected function get_github() {
		if ( !empty( $this->github ) ) {
			return $this->github;
		}

		$url = "https://api.github.com/repos/{$this->user_name}/{$this->slug}/tags";
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept-Encoding' => '',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code === 200 ) {
			$json_decoded_body = json_decode( $body );
			if ( !empty( $json_decoded_body[0] ) ) {
				$json_decoded_body[0]->last_modified = $response['headers']['last-modified'];
				$this->github = $json_decoded_body[0];
				return $this->github;
			}
		}

		return new WP_Error(
			'GitHub updater error',
			'GitHub API error. HTTP status: ' . $code
		);
	}

	/**
	 * アップデートメッセージをカスタマイズ
	 *
	 * @param $prepared_themes
	 * @return mixed
	 */
	public function wp_prepare_themes_for_js( $prepared_themes ) {
		if ( empty( $prepared_themes[$this->slug] ) ) {
			return $prepared_themes;
		}
		if ( $prepared_themes[$this->slug]['hasUpdate'] ) {
			$prepared_themes[$this->slug]['update'] = sprintf(
				'<br />
				<strong>There is a new version of %s available now. <a href="%s">update now</a>.</strong>',
				esc_html( $this->theme->get( 'Name') ),
				wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' ) . urlencode( $this->slug ), 'upgrade-theme_' . $this->slug )
			);
		}
		return $prepared_themes;
	}
}
