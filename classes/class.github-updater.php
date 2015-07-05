<?php
if ( class_exists( 'habakiri_Plugin_GitHub_Updater' ) || !is_admin() ) {
	return;
}

/**
 * habakiri_Plugin_GitHub_Updater
 * Version    : 1.0.0
 * Author     : Takashi Kitajima
 * Created    : June 15, 2015
 * Modified   : 
 * License    : GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class habakiri_Plugin_GitHub_Updater {

	/**
	 * プラグインのディレクトリ名
	 * @var string
	 * @example habakiri-share-buttons
	 */
	protected $slug;

	/**
	 * プラグインデータ
	 * @var array
	 */
	protected $plugin;

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
	 * @param string $slug プラグインのディレクトリ名
	 * @param string $path プラグインメインファイルのフルパス
	 * @param string $user_name GitHub のユーザー名
	 */
	public function __construct( $slug, $path, $user_name ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			delete_site_transient( 'update_plugin' );
		}
		
		if ( !function_exists( 'get_plugin_data' ) ){
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}
		$this->slug      = $slug;
		$this->plugin    = get_plugin_data( $path, false, false );
		$this->user_name = $user_name;


		add_filter(
			'pre_set_site_transient_update_plugins',
			array( $this, 'pre_set_site_transient_update_plugins' )
		);
		add_filter(
			'plugins_api',
			array( $this, 'plugins_api' ),
			10,
			3
		);
		add_filter(
			'upgrader_post_install',
			array( $this, 'upgrader_post_install' ),
			10,
			3
		);
		/*
		add_filter(
			'site_transient_update_plugins',
			array( $this, 'site_transient_update_plugins' )
		);
		*/
	}

	/**
	 * 対象のプラグインについて GitHub に問い合わせ、更新があれば $transient を更新
	 *
	 * @param object $transient
	 * @return object $transient
	 */
	public function pre_set_site_transient_update_plugins( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$slug   = $this->get_relative_plugin_path();
		$github = $this->get_github();

		if ( empty( $github->name ) ) {
			return $transient;
		}

		$do_update = version_compare(
			$github->name,
			$transient->checked[$slug]
		);

		if ( $do_update != 1 ) {
			return $transient;
		}

		$plugin              = new stdClass();
		$plugin->slug        = $this->slug;
		$plugin->new_version = $github->name;
		$plugin->url         = $this->plugin['PluginURI'];
		$plugin->package     = $github->zipball_url;
		$plugin->id          = 0;
		$plugin->plugin      = $slug;

		$transient->response[$slug] = $plugin;
		return $transient;
	}

	/**
	 * プラグイン詳細情報に表示する情報
	 *
	 * @param mixed $result
	 * @param string $action
	 * @param array|object $response
	 * @return mixed
	 */
	public function plugins_api( $result, $action, $response ) {
		$slug = $this->get_relative_plugin_path();

		if ( empty( $response->slug ) || $response->slug != $this->slug ) {
			return $result;
		}

		$github = $this->get_github();

		$new_result                = new stdClass();
		$new_result->last_updated  = $github->last_modified;
		$new_result->slug          = $this->slug;
		$new_result->plugin        = $slug;
		$new_result->name          = $this->plugin['Name'];
		$new_result->plugin_name   = $this->plugin['Name'];
		$new_result->version       = $github->name;
		$new_result->author        = $this->plugin['AuthorName'];
		$new_result->homepage      = $this->plugin['PluginURI'];
		$new_result->download_link = $github->zipball_url;

		$new_result->sections = array(
			'description' => $this->plugin['Description'],
			'changelog'   => sprintf(
				'<a href="%s" target="_blank">See Repository.</a>',
				esc_url( $new_result->homepage )
			),
		);

		return $new_result;
	}

	/**
	 * プラグインの配置と有効化
	 *
	 * @param bool $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public function upgrader_post_install( $response, $hook_extra, $result ) {
		$slug = $this->get_relative_plugin_path();
		
		if ( !isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $slug ) {
			return $response;
		}
		
		$is_activated = is_plugin_active( $slug );

		global $wp_filesystem;
		$plugin_dir_path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->slug;
		$wp_filesystem->move( $result['destination'], $plugin_dir_path );
		if ( $is_activated ) {
			$activate = activate_plugin( $slug );
		}
		return $response;
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
	 * メインファイルの相対パスを取得
	 *
	 * @param string プラグインディレクトリ名
	 * @return string
	 */
	protected function get_relative_plugin_path() {
		return sprintf(
			'%1$s/%1$s.php',
			$this->slug
		);
	}
}
