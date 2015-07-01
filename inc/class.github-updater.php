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

		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'pre_set_site_transient_update_themes' ) );
		add_filter( 'themes_api'                          , array( $this, 'themes_api' ), 10, 3 );
		add_filter( 'upgrader_post_install'                , array( $this, 'upgrader_post_install' ), 10, 3 );
		add_filter( 'site_transient_update_themes'        , array( $this, 'site_transient_update_themes' ) );
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

		$theme              = new stdClass();
		$theme->theme       = $this->slug;
		$theme->new_version = $github->name;
		$theme->url         = $this->theme->get( 'ThemeURI' );
		$theme->package     = $github->zipball_url;

		$transient->response[$slug] = $theme;
		return $transient;
	}

	/**
	 * テーマ詳細情報に表示する情報
	 *
	 * @param mixed $result
	 * @param string $action
	 * @param array|object $response
	 * @return mixed
	 */
	public function themes_api( $result, $action, $response ) {
		if ( $action !== 'theme_information' ) {
			return $result;
		}
		if ( empty( $response->slug ) || $response->slug != $this->slug ) {
			return $result;
		}

		$github = $this->get_github();

		$new_result                = new stdClass();
		$new_result->slug          = $this->slug;
		$new_result->name          = $this->theme->get( 'Name' );
		$new_result->homepage      = $this->theme->get( 'AuthorURI' );
		$new_result->version       = $github->name;
		$new_result->description   = $this->theme->get( 'Description' );
		$new_result->author        = $this->theme->get( 'Author' );
		$new_result->preview_url   = $this->theme->get( 'ThemeURI' );
		$new_result->last_updated  = $github->last_modified;
		$new_result->download_link = $github->zipball_url;

		$new_result->sections = array(
			'description' => $this->theme->get( 'Description' ),
			'changelog'   => sprintf(
				'<a href="%s" target="_blank">See Repository.</a>',
				esc_url( $new_result->homepage )
			),
		);

		return $new_result;
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
			return $result;
		}
		$is_activated = ( get_stylesheet() === $this->slug );

		global $wp_filesystem;
		$theme_dir_path = get_theme_root() . DIRECTORY_SEPARATOR . $this->slug;
		$wp_filesystem->move( $result['destination'], $theme_dir_path );
		$result['destination'] = $theme_dir_path;
		if ( $is_activated ) {
			$activate = switch_theme( $this->slug );
		}
		return $result;
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
	 * @param object $updates
	 * @return object $updates
	 */
	public function site_transient_update_themes( $updates ) {
		if ( !is_array( $updates ) ) {
			return $updates;
		}

		foreach ( $updates as $key => $update ) {
			if ( $key === 'response' || $key === 'no_update' ) {
				if ( !empty( $update[$slug] ) ) {
					/*
					$update[$slug]->id     = 0;
					$update[$slug]->plugin = $slug;
					$update[$slug]->slug   = $this->slug;
					*/
					var_dump( $update[$slug] );
				}
				$updates->$key = $update;
			}
		}
		return $updates;
	}
}
