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
            self::$instance->init_hooks();
        }
        return self::$instance;
    }


    /** Setup WordPress hooks */
    private function init_hooks() {
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
            self::TEXT_DOMAIN,
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }


    /** Add settings page under Settings menu */
    public function register_settings_page() {
        add_options_page(
            __( 'Arabic Slugs', self::TEXT_DOMAIN ),
            __( 'Arabic Slugs', self::TEXT_DOMAIN ),
            'manage_options',
            self::TEXT_DOMAIN,
            array( $this, 'render_settings_page' )
        );
    }
    
    /** Register settings and fields */
    public function register_settings() {
        register_setting(
            self::TEXT_DOMAIN,
            self::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'simula_friendly_slugs_main',
            __( 'Default Slug Method', self::TEXT_DOMAIN ),
            '__return_false',
            self::TEXT_DOMAIN
        );

        add_settings_field(
            'method',
            __( 'Method', self::TEXT_DOMAIN ),
            array( $this, 'field_method_html' ),
            self::TEXT_DOMAIN,
            'simula_friendly_slugs_section'
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
        $options = get_option( self::OPTION_KEY, [] );
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
            <h1><?php esc_html_e( 'Simula Friendly Arabic Slugs Settings',  self::TEXT_DOMAIN ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( self::TEXT_DOMAIN );
                do_settings_sections( self::TEXT_DOMAIN );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /** Filter slug on save */
    public function filter_custom_slug( $override_slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
        // Get the post object
        $post = get_post( $post_ID );
        if ( ! $post || 'auto-draft' === $post->post_status ) {
            return $override_slug;
        }
        // Detect Arabic characters in the title; skip if none found
        $title = $post->post_title;
        if ( ! preg_match( '/\p{Arabic}/u', $title ) ) {
            return $override_slug;
        }
        // Check if method is disabled
        $method = $this->get_method();
        if ( 'none' === $method ) {
            return $override_slug;
        }
        // Generate new slug via selected converter
        $converter = "convert_{$method}";
        if ( is_callable( [ $this, $converter ] ) ) {
            $new_slug = $this->$converter( $post->post_title );
            return sanitize_title( $new_slug, $original_slug, 'save' );
        }
        return $override_slug;
    }

    /** Get current method */
    private function get_method() {
        $opt = get_option( self::OPTION_KEY, [] );
        return $opt['method'] ?? 'transliteration';
    }    

    /** Transliteration converter */
    private function convert_transliteration( $text ) {
        // Remove diacritics
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $text);
        // Mapping table
        $map = [
            'ا'=>'a','أ'=>'a','إ'=>'i','آ'=>'a','ء'=>'','ؤ'=>'u','ئ'=>'i','ب'=>'b','ت'=>'t',
            'ث'=>'th','ج'=>'j','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'dh','ر'=>'r','ز'=>'z',
            'س'=>'s','ش'=>'sh','ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z','ع'=>'','غ'=>'gh',
            'ف'=>'f','ق'=>'q','ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','و'=>'w',
            'ي'=>'y','ى'=>'a','ة'=>'h','ﻻ'=>'la',' ':' ','-'=>'-'
        ];
        // Split into characters and transliterate
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach ( $chars as $ch ) {
            if ( isset( $map[$ch] ) ) {
                $out .= $map[$ch];
            } elseif ( preg_match('/[A-Za-z0-9]/', $ch) ) {
                $out .= $ch;
            } else {
                $out .= ' ';
            }
        }
        // Normalize spaces
        $out = preg_replace('/\s+/', ' ', $out);
        return trim( strtolower( $out ) );
    }

    /** 3arabizi converter */
    private function convert_arabizi( $text ) {
        // Remove diacritics
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $text);
        // 3arabizi mapping table
        $map = [
            'ا'=>'a','أ'=>'a','إ'=>'i','آ'=>'a','ء'=>'2','ؤ'=>'u','ئ'=>'i','ب'=>'b','ت'=>'t',
            'ث'=>'4','ج'=>'j','ح'=>'7','خ'=>'5','د'=>'d','ذ'=>'dh','ر'=>'r','ز'=>'z',
            'س'=>'s','ش'=>'sh','ص'=>'9','ض'=>'9','ط'=>'6','ظ'=>'z','ع'=>'3','غ'=>'gh',
            'ف'=>'f','ق'=>'8','ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','و'=>'w',
            'ي'=>'y','ى'=>'a','ة'=>'h','ﻻ'=>'la',' '=>' ','-'=>'-'
        ];
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach ( $chars as $ch ) {
            if ( isset( $map[$ch] ) ) {
                $out .= $map[$ch];
            } elseif ( preg_match('/[A-Za-z0-9]/', $ch) ) {
                $out .= $ch;
            } else {
                $out .= ' ';
            }
        }
        $out = preg_replace('/\s+/', ' ', $out);
        return trim( strtolower( $out ) );
    }

    /** Machine translation converter */
    private function convert_translation( $text ) {
        // TODO: integrate with Google Translate API
        return $text;
    }
}


// Initialize plugin
Simula_Friendly_Slugs::instance();