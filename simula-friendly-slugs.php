<?php
/**
 * Plugin Name: Simula Friendly Slugs for Arabic Sites
 * Plugin URI: https://github.com/simula-lab/simula-friendly-slugs-for-arabic-sites

 * Description: Automatically generate friendly slugs for Arabic posts/pages via transliteration, 3arabizi or translation.
 * Version: 0.4.0
 * Author: Simula
 * Author URI: https://simulalab.org/
 * License: GPL2
 * Text Domain: simula-friendly-slugs-for-arabic-sites
 * Domain Path: /languages
 *
 */

// Abort, if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Apply filters to HTTP args for requests.
 *
 * @param array  $args        HTTP request arguments.
 * @param string $provider    Provider key (e.g., 'google', 'yandex', 'custom').
 * @param string $endpoint    Request URL endpoint.
 * @param string $text        Text being translated.
 * @return array Modified HTTP args.
 */
function simula_friendly_slugs_http_args( $args, $provider, $endpoint, $text ) {
    return apply_filters( 'simula_friendly_slugs_http_args', $args, $provider, $endpoint, $text );
}


// Provider interface
interface Simula_Friendly_Slugs_Provider_Interface {
    /**
     * Translate a given text title to the target language.
     * @param string $text
     * @return string
     */
    public function translate( $text );
}

// Google provider
class Simula_Friendly_Slugs_Provider_Google implements Simula_Friendly_Slugs_Provider_Interface {
    private $api_key;
    public function __construct( $key ) {
        $this->api_key = $key;
    }
    public function translate( $text ) {
        if ( empty( $this->api_key ) ) {
            return $text;
        }
        $endpoint = 'https://translation.googleapis.com/language/translate/v2';
        // $args     = [ 'timeout' => 5, 'body' => [ 'q' => $text, 'target' => get_bloginfo( 'language' ), 'format' => 'text', 'key' => $this->api_key ] ];
        $args     = [
            'timeout'   => 5,
            'sslverify' => true,
            'body'      => [
                'q'      => $text,
                'target' => get_bloginfo( 'language' ),
                'format' => 'text',
                'key'    => $this->api_key,
            ],
        ];
        $args = simula_friendly_slugs_http_args( $args, 'google', $endpoint, $text );        
        $response = wp_remote_post( $endpoint, $args );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return $text;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data']['translations'][0]['translatedText'] ?? $text;
    }
}

// // Yandex provider
// class Simula_Friendly_Slugs_Provider_Yandex implements Simula_Friendly_Slugs_Provider_Interface {
//     private $api_key;
//     public function __construct( $key ) {
//         $this->api_key = $key;
//     }
//     public function translate( $text ) {
//         if ( empty( $this->api_key ) ) {
//             return $text;
//         }
//         $endpoint = 'https://translate.yandex.net/api/v1.5/tr.json/translate';
//         $args     = [
//             'timeout'   => 5,
//             'sslverify' => true,
//             'body'      => [
//                 'key'  => $this->api_key,
//                 'text' => $text,
//                 'lang' => 'ar-en',
//             ],
//         ];
//         $args = simula_friendly_slugs_http_args( $args, 'yandex', $endpoint, $text );
//         $response = wp_remote_post( $endpoint, $args );
//         if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
//             return $text;
//         }
//         $data = json_decode( wp_remote_retrieve_body( $response ), true );
//         return $data['text'][0] ?? $text;
//     }
// }

// Custom provider
class Simula_Friendly_Slugs_Provider_Custom implements Simula_Friendly_Slugs_Provider_Interface {
    private $endpoint;
    private $api_key;
    public function __construct( $endpoint, $key ) {
        $this->endpoint = esc_url_raw( $endpoint );
        $this->api_key  = $key;
    }
    public function translate( $text ) {
        if ( empty( $this->endpoint ) || empty( $this->api_key ) ) {
            return $text;
        }
        $args     = [
            'timeout'   => 5,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Bearer ' . sanitize_text_field( $this->api_key ),
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode( [ 'text' => $text ] ),            
        ];
        $args     = simula_friendly_slugs_http_args( $args, 'custom', $this->endpoint, $text );
        $response = wp_remote_post( $this->endpoint, $args );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return $text;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return apply_filters( 'simula_friendly_slugs_custom_parse_response', $text, $data );
    }
}

class Simula_Friendly_Slugs {
    const TEXT_DOMAIN = 'simula-friendly-slugs';
    const OPTION_KEY  = 'simula_friendly_slugs_options';

    private static $instance;
    /** @var Simula_Friendly_Slugs_Provider_Interface[] */
    private $providers = [];

    /** Setup WordPress hooks */
    private function __construct() {
        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );
        
        // Admin settings
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Override slug on save
        add_filter( 'wp_unique_post_slug', [ $this, 'generate_friendly_slug' ], 10, 6 );
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /** Load plugin textdomain */
    public function load_textdomain() {
        load_plugin_textdomain( 
            self::TEXT_DOMAIN, 
            false, 
            dirname( plugin_basename( __FILE__ ) ) . '/languages/' 
        );
    }

    /** Add settings page under Settings menu */
    public function register_settings_page() {
        add_options_page(
            __( 'Arabic Slugs', self::TEXT_DOMAIN ),
            __( 'Arabic Slugs', self::TEXT_DOMAIN ),
            'manage_options',
            self::TEXT_DOMAIN,
            [ $this, 'render_settings_page' ]
        );
    }

    /** Register settings and fields */
    public function register_settings() {
        // 1) Register the main option
        register_setting(
            self::TEXT_DOMAIN,
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );
    
        // 2) “Default Slug Method” section + dropdown
        add_settings_section(
            'simula_friendly_slugs_main',
            __( 'Default Slug Method', self::TEXT_DOMAIN ),
            '__return_false',
            self::TEXT_DOMAIN
        );
        add_settings_field(
            'method',
            __( 'Method', self::TEXT_DOMAIN ),
            [ $this, 'field_method_html' ],
            self::TEXT_DOMAIN,
            'simula_friendly_slugs_main'
        );
    
        // 3) Only if the current saved method is “translation”…
        $options = get_option( self::OPTION_KEY, [] );
        $method  = $options['method'] ?? '';
    
        if ( 'translation' === $method ) {
    
            // 3a) Translation Settings section + service selector
            add_settings_section(
                'simula_friendly_slugs_translation',
                __( 'Translation Settings', self::TEXT_DOMAIN ),
                '__return_false',
                self::TEXT_DOMAIN
            );
            add_settings_field(
                'translation_service',
                __( 'Translation Service', self::TEXT_DOMAIN ),
                [ $this, 'field_translation_service_html' ],
                self::TEXT_DOMAIN,
                'simula_friendly_slugs_translation'
            );
    
            // 3b) Provider definitions & per‐provider API key / endpoint fields
            $definitions = apply_filters( 'simula_friendly_slugs_translation_providers', [
                'google' => [
                    'label' => __( 'Google Translate', self::TEXT_DOMAIN ),
                    'class' => 'Simula_Friendly_Slugs_Provider_Google',
                ],
                'custom' => [
                    'label' => __( 'Custom API', self::TEXT_DOMAIN ),
                    'class' => 'Simula_Friendly_Slugs_Provider_Custom',
                ],
            ] );
    
            foreach ( $definitions as $key => $def ) {
                // instantiate provider
                $opts   = get_option( self::OPTION_KEY, [] );
                $api    = $opts['api_keys'][ $key ] ?? '';
                if ( 'custom' === $key ) {
                    $endpoint = $opts['custom_api_endpoint'] ?? '';
                    $this->providers[ $key ] = new $def['class']( $endpoint, $api );
                } else {
                    $this->providers[ $key ] = new $def['class']( $api );
                }
    
                // API key field
                add_settings_field(
                    "{$key}_api_key",
                    sprintf( __( '%s API Key', self::TEXT_DOMAIN ), $def['label'] ),
                    [ $this, 'field_api_key_html' ],
                    self::TEXT_DOMAIN,
                    'simula_friendly_slugs_translation',
                    [ 'service' => $key ]
                );
    
                // Custom‐endpoint only for “custom”
                if ( 'custom' === $key ) {
                    add_settings_field(
                        'custom_api_endpoint',
                        __( 'Custom API Endpoint', self::TEXT_DOMAIN ),
                        [ $this, 'field_custom_endpoint_html' ],
                        self::TEXT_DOMAIN,
                        'simula_friendly_slugs_translation'
                    );
                }
            }
        }
    }
    

    /** Sanitize settings */
    public function sanitize_settings( $input ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return [];
        }
        $valid   = [];
        $methods = [ 'wp_transliteration', 'custom_transliteration', 'arabizi', 'translation', 'none' ];
        if ( isset( $input['method'] ) && in_array( $input['method'], $methods, true ) ) {
            $valid['method'] = $input['method'];
        }
        if ( 'translation' === ( $valid['method'] ?? '' ) ) {
            // service
            $definitions = apply_filters( 'simula_friendly_slugs_translation_providers', [] );
            $services    = array_keys( $definitions );
            if ( isset( $input['translation_service'] ) && in_array( $input['translation_service'], $services, true ) ) {
                $valid['translation_service'] = $input['translation_service'];
            }
            // api keys
            $valid['api_keys'] = [];
            if ( ! empty( $input['api_keys'] ) && is_array( $input['api_keys'] ) ) {
                foreach ( $services as $s ) {
                    if ( isset( $input['api_keys'][ $s ] ) ) {
                        $valid['api_keys'][ $s ] = sanitize_text_field( $input['api_keys'][ $s ] );
                    }
                }
            }
            // custom endpoint
            if ( ! empty( $input['custom_api_endpoint'] ) ) {
                $valid['custom_api_endpoint'] = esc_url_raw( $input['custom_api_endpoint'] );
            }
        }
        return $valid;
    }


    /** Settings field HTML */
    public function field_method_html() {
        $options = get_option( self::OPTION_KEY, [] );
        $current = $options['method'] ?? 'wp_transliteration';
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[method]" onchange="this.form.submit()">
            <option value="wp_transliteration" <?php selected( $current, 'wp_transliteration' ); ?>><?php esc_html_e( 'Transliteration', self::TEXT_DOMAIN ); ?></option>
            <option value="arabizi" <?php selected( $current, 'arabizi' ); ?>><?php esc_html_e( '3arabizi', self::TEXT_DOMAIN ); ?></option>
            <option value="translation" <?php selected( $current, 'translation' ); ?>><?php esc_html_e( 'Translation', self::TEXT_DOMAIN ); ?></option>
            <option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( 'No Change', self::TEXT_DOMAIN ); ?></option>
        </select>
        <?php
    }

    public function field_translation_service_html() {
        $options = get_option( self::OPTION_KEY, [] );
        if ( ( $options['method'] ?? '' ) !== 'translation' ) {
            return;
        }
        $current = $options['translation_service'] ?? '';
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[translation_service]" onchange="this.form.submit()">
            <?php foreach ( $this->providers as $key => $provider ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( apply_filters( "simula_friendly_slugs_provider_label_{$key}", ucfirst( $key ) ) ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function field_api_key_html( $args ) {
        $service = $args['service'];
        $options = get_option( self::OPTION_KEY, [] );
        if ( ( $options['method'] ?? '' ) !== 'translation' || ( $options['translation_service'] ?? '' ) !== $service ) {
            return;
        }
        $value = $options['api_keys'][ $service ] ?? '';
        ?>
        <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_keys][<?php echo esc_attr( $service ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <?php
    }

    public function field_custom_endpoint_html() {
        $options  = get_option( self::OPTION_KEY, [] );
        if ( ( $options['method'] ?? '' ) !== 'translation' || ( $options['translation_service'] ?? '' ) !== 'custom' ) {
            return;
        }
        $endpoint = $options['custom_api_endpoint'] ?? '';
        ?>
        <input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_api_endpoint]" value="<?php echo esc_attr( $endpoint ); ?>" class="regular-text" />
        <?php
    }
    
    /** Render settings page */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Simula Friendly Arabic Slugs Settings', self::TEXT_DOMAIN ); ?></h1>
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

    public function generate_friendly_slug( $override_slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
        $method = ( get_option( self::OPTION_KEY, [] )['method'] ?? 'none' );
        $post   = get_post( $post_ID );
        if ( ! $post instanceof WP_Post ) {
            return $override_slug;
        }
        $converter = "convert_{$method}";
        if ( is_callable( [ $this, $converter ] ) ) {
            $new_slug = $this->$converter( $post->post_title );
            return sanitize_title( $new_slug, $original_slug, 'save' );
        }
        return $override_slug;
    }

    private function convert_translation( $text ) {
        $options = get_option( self::OPTION_KEY, [] );
        $service = $options['translation_service'] ?? '';
        if ( ! isset( $this->providers[ $service ] ) ) {
            return $text;
        }
        return $this->providers[ $service ]->translate( $text );
    }

    /**
     * Transliteration converter (using WP/ICU)
     */
    private function convert_wp_transliteration( $text ) {
        // 1) First, let PHP/ICU do the heavy lifting (Any‐Latin → ASCII-Latin)
        if ( function_exists( 'transliterator_transliterate' ) ) {
            // “Any-Latin; Latin-ASCII” pulls in every script → Latin, then strips diacritics.
            $text = transliterator_transliterate( 'Any-Latin; Latin-ASCII;', $text );
        }

        // 2) Fallback for accents (remove_accents covers Latin, Greek, Cyrillic, etc.)
        $text = remove_accents( $text );

        // 3) Normalize to lowercase and return
        return strtolower( $text );
    }

    /** Transliteration converter */
    private function convert_custom_transliteration( $text ) {
        // Remove diacritics
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{06D6}-\x{06ED}]/u', '', $text);
        // Mapping table
        $map = [
            'ا'=>'a','أ'=>'a','إ'=>'i','آ'=>'a','ء'=>'','ؤ'=>'u','ئ'=>'i','ب'=>'b','ت'=>'t',
            'ث'=>'th','ج'=>'j','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'dh','ر'=>'r','ز'=>'z',
            'س'=>'s','ش'=>'sh','ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z','ع'=>'','غ'=>'gh',
            'ف'=>'f','ق'=>'q','ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','و'=>'w',
            'ي'=>'y','ى'=>'a','ة'=>'h','ﻻ'=>'la','_'=>'_','-'=>'-'
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
}

// Initialize plugin
Simula_Friendly_Slugs::instance();
