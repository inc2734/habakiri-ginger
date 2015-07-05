<?php
include_once( get_stylesheet_directory() .'/inc/class.github-updater.php' );
new habakiri_Theme_GitHub_Updater( get_stylesheet(), 'inc2734' );

function habakiri_ginger_theme_setup() {

	/**
	 * Name       : Habakiri_Base_Functions
	 * Version    : 1.0.0
	 * Author     : Takashi Kitajima
	 * Author URI : http://2inc.org
	 * Created    : June 27, 2015
	 * Modified   : 
	 * License    : GPLv2
	 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
	 */
	class Habakiri extends Habakiri_Base_Functions {

		public function __construct() {
			parent::__construct();

			add_filter(
				'habakiri_theme_mods_defaults',
				array( $this, 'habakiri_theme_mods_defaults' )
			);

			add_filter(
				'mce_css',
				array( $this, 'mce_css' )
			);

			add_action(
				'customize_register',
				array( $this, 'customize_register' ),
				99999
			);
		}

		public function wp_enqueue_scripts() {
			wp_enqueue_style(
				get_template(),
				get_template_directory_uri() . '/style.min.css',
				null
			);
			parent::wp_enqueue_scripts();
		}

		public function habakiri_theme_mods_defaults( $args ) {
			return shortcode_atts( $args, array(
				'header'                 => 'header-center',
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
		 * @param WP_Customizer $wp_customize
		 */
		public function customize_register( $wp_customize ) {
			$wp_customize->remove_control( 'header' );
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
		 * サムネイルを表示
		 */
		public static function the_post_thumbnail() {
			$classes = array();
			if ( !has_post_thumbnail() ) {
				$classes[] = 'no-thumbnail';
			}
			?>
			<a href="<?php the_permalink(); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php
					the_post_thumbnail( 'medium', array(
						'class' => '',
					) );
					?>
				<?php else : ?>
					<span class="no-thumbnail-text"><?php the_time( 'd' ); ?></span>
				<?php endif; ?>
			</a>
			<?php
		}
	}
}
add_action( 'after_setup_theme', 'habakiri_ginger_theme_setup' );
