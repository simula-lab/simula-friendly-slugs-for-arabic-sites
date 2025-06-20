<?php
/**
 * Plugin Name: Simula Friendly Slugs for Arabic Sites
 * Plugin URI: https://github.com/simula-lab/simula-friendly-slugs-for-arabic-sites

 * Description: Automatically generate friendly slugs for Arabic posts/pages via transliteration, 3arabizi or translation.
 * Version: 1.0.0
 * Author: Simula
 * Author URI: https://simulalab.org/
 * License: GPL2
 * Text Domain: simula-friendly-slugs-for-arabic-sites
 * Domain Path: /languages
 *
 */


 // Abort, if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


final class Simula_Friendly_Slugs {
    /** Version */
    const VERSION = '1.0.0';
    /** Plugin slug */
    const PLUGIN_SLUG = 'simula-friendly-slugs';
    /** Option key */
    const OPTION_KEY  = 'simula_friendly_slugs_settings';

    /** Singleton instance */
    private static $instance = null;

    /** Get instance */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }
        return self::$instance;
    }


    /** Setup WordPress hooks */
    private function setup_hooks() {
        // Load textdomain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Admin settings
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Override slug on save
        add_filter( 'wp_unique_post_slug', array( $this, 'filter_custom_slug' ), 10, 6 );
    }

    /** Load plugin textdomain */
    public function load_textdomain() {
        load_plugin_textdomain(
            self::PLUGIN_SLUG,
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }


    /** Register admin settings page */
    public function register_settings_page() {
        add_options_page(
            __( 'Simula Friendly Arabic Slugs', 'simula-friendly-slugs' ),
            __( 'Simula Friendly Arabic Slugs', 'simula-friendly-slugs' ),
            'manage_options',
            self::PLUGIN_SLUG,
            array( $this, 'render_settings_page' )
        );
    }
    
    /** Register settings and fields */
    public function register_settings() {
        register_setting(
            'simula_friendly_slugs_group',
            self::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'simula_friendly_slugs_main',
            __( 'Default Slug Method', 'simula-friendly-slugs' ),
            '__return_false',
            'simula_friendly_slugs'
        );

        add_settings_field(
            'method',
            __( 'Method', 'simula-friendly-slugs' ),
            array( $this, 'field_method_html' ),
            'simula_friendly_slugs',
            'simula_friendly_slugs_main'
        );
    }    
    
    /** Sanitize settings */
    public function sanitize_settings( $input ) {
        $valid = array();
        $methods = array( 'transliteration', 'arabizi', 'translation', 'none' );
        if ( isset( $input['method'] ) && in_array( $input['method'], $methods, true ) ) {
            $valid['method'] = $input['method'];
        }
        return $valid;
    }


    /** Settings field HTML */
    public function field_method_html() {
        $options = get_option( self::OPTION_KEY );
        $current = isset( $options['method'] ) ? $options['method'] : 'transliteration';
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[method]">
            <option value="transliteration" <?php selected( $current, 'transliteration' ); ?>><?php esc_html_e( 'Transliteration', 'simula-friendly-slugs' ); ?></option>
            <option value="arabizi" <?php selected( $current, 'arabizi' ); ?>><?php esc_html_e( '3arabizi', 'simula-friendly-slugs' ); ?></option>
            <option value="translation" <?php selected( $current, 'translation' ); ?>><?php esc_html_e( 'Machine Translation', 'simula-friendly-slugs' ); ?></option>
            <option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( 'No Change', 'simula-friendly-slugs' ); ?></option>
        </select>
        <?php
    }

    /** Render settings page */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Simula Friendly Arabic Slugs Settings', 'simula-friendly-slugs' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'simula_friendly_slugs_group' );
                do_settings_sections( 'simula_friendly_slugs' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /** Filter slug on save */
    public function filter_custom_slug( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
        // Get the post object
        $post = get_post( $post_ID );
        if ( ! $post ) {
            return $slug;
        }
        // Detect Arabic characters in the title; skip if none found
        $title = $post->post_title;
        if ( ! preg_match( '/\p{Arabic}/u', $title ) ) {
            return $slug;
        }
        // Check if method is disabled
        $method = $this->get_method();
        if ( 'none' === $method ) {
            return $slug;
        }
        // Generate new slug via selected converter
        $converter = "convert_{$method}";
        $generated = $this->$converter( $title );
        return sanitize_title_with_dashes( $generated );
    }

    /** Get current method */
    private function get_method() {
        $opt = get_option( self::OPTION_KEY );
        return $opt['method'] ?? 'transliteration';
    }    

        /** Transliteration converter */
    private function convert_transliteration( $text ) {
        // TODO: implement Arabic to Latin transliteration
        return $text;
    }

    /** 3arabizi converter */
    private function convert_arabizi( $text ) {
        // TODO: implement 3arabizi logic
        return $text;
    }

    /** Machine translation converter */
    private function convert_translation( $text ) {
        // TODO: integrate with Google Translate API
        return $text;
    }
}


// Initialize plugin
Simula_Friendly_Slugs::instance();