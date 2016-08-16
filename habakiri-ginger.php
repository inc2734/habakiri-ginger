<?php
/**
 * Plugin Name: Ginger - Habakiri design skin
 * Plugin URI: https://github.com/inc2734/habakiri-ginger
 * Description: Ginger is a design skin of Habakiri. This plugin needs Habakiri 2.0.0 or later.
 * Version: 2.1.0
 * Author: Takashi Kitajima
 * Author URI: http://2inc.org
 * Created : July 5, 2015
 * Modified: August 16, 2016
 * Text Domain: habakiri-ginger
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( defined( 'HABAKIRI_DESIGN_SKIN' ) && get_template() === 'habakiri' ) {
	return;
}

define( 'HABAKIRI_DESIGN_SKIN', true );

include_once( plugin_dir_path( __FILE__ ) . 'classes/class.config.php' );

if ( ! class_exists( 'Habakiri_Plugin_GitHub_Updater' ) ) {
	include_once( plugin_dir_path( __FILE__ ) . 'classes/class.github-updater.php' );
}
new Habakiri_Plugin_GitHub_Updater( 'habakiri-ginger', __FILE__, 'inc2734' );

class Habakiri_Ginger {

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * 言語ファイルの読み込み
	 */
	public function plugins_loaded() {
		load_plugin_textdomain(
			Habakiri_Ginger_Config::NAME,
			false,
			basename( dirname( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * 初期化処理
	 */
	public function init() {
		add_filter(
			'habakiri_theme_mods_defaults',
			array( $this, 'habakiri_theme_mods_defaults' )
		);

		add_filter(
			'mce_css',
			array( $this, 'mce_css' )
		);

		add_filter(
			'theme_mod_header',
			array( $this, 'theme_mod_header' )
		);

		add_filter(
			'theme_mod_header_fixed',
			array( $this, 'theme_mod_header_fixed' )
		);

		add_filter(
			'habakiri_post_thumbnail_size',
			array( $this, 'habakiri_post_thumbnail_size' )
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'wp_enqueue_scripts' )
		);

		add_action(
			'customize_register',
			array( $this, 'customize_register' ),
			99999
		);
	}

	/**
	 * CSS の読み込み
	 */
	public function wp_enqueue_scripts() {
		$url = plugins_url( Habakiri_Ginger_Config::NAME );
		wp_enqueue_style(
			Habakiri_Ginger_Config::NAME,
			$url . '/style.min.css',
			array( 'habakiri' )
		);
	}

	/**
	 * エディタに CSS を適用
	 *
	 * @param string $mce_css CSS のURL
	 * @return string
	 */
	public function mce_css( $mce_css ) {
		if ( ! empty( $mce_css ) ) {
			$mce_css .= ',';
		}
		$mce_css .= get_stylesheet_directory_uri() . '/editor-style.min.css';
		return $mce_css;
	}

	/**
	 * ヘッダーのレイアウトを指定
	 *
	 * @param string $mod
	 * @return string
	 */
	public function theme_mod_header( $mod ) {
		return 'header--center';
	}

	/**
	 * ヘッダーを固定するかどうか
	 *
	 * @param string $mod
	 * @return string
	 */
	public function theme_mod_header_fixed( $mod ) {
		return 'false';
	}

	/**
	 * サムネイルサイズ
	 *
	 * @param string $size
	 * @return string
	 */
	public function habakiri_post_thumbnail_size( $size ) {
		return 'medium';
	}

	/**
	 * デフォルトのテーマオプションを定義して返す
	 *
	 * @param array $args
	 * @return array
	 */
	public function habakiri_theme_mods_defaults( $args ) {
		return shortcode_atts( $args, array(
			'page_header_bg_color'   => '#fff',
			'page_header_text_color' => '#333',
			'link_color'             => '#ffac97',
			'link_hover_color'       => '#FDA38C',
			'gnav_link_hover_color'  => '#ffac97',
			'footer_bg_color'        => '#ffe9e4',
			'footer_text_color'      => '#333',
			'footer_link_color'      => '#333',
		) );
	}

	/**
	 * Customizer の設定
	 *
	 * @param WP_Customizer $wp_customize
	 */
	public function customize_register( $wp_customize ) {
		$wp_customize->remove_control( 'header' );
		$wp_customize->remove_control( 'header_fixed' );
	}
}

$Habakiri_Ginger = new Habakiri_Ginger();
