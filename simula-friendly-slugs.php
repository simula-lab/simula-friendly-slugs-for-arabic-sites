<?php
/**
 * Plugin Name: Simula Friendly Slugs for Arabic Sites
 * Plugin URI: https://github.com/simula-lab/simula-friendly-slugs-for-arabic-sites

 * Description: Automatically generate friendly slugs for Arabic posts/pages via transliteration, 3arabizi or translation.
 * Version: 0.6.3
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
    public function translate( string $text ): string;

    /**
     * Validate & sanitize this provider’s settings.
     *
     * @param array $raw  The raw input for this provider (e.g. [ 'key' => ..., 'endpoint' => ... ]).
     * @return string|WP_Error  Sanitized API key (or endpoint if that’s the only thing), or WP_Error on failure.
     */
    public function validate_settings( array $raw );    
}

// Google provider
class Simula_Friendly_Slugs_Provider_Google implements Simula_Friendly_Slugs_Provider_Interface {

    const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    private $api_key;
    public function __construct( $key ) {
        $this->api_key = $key;
    }

    /**
     * Send a request to Google Translate API for the given text/key.
     *
     * @param string $text The text to translate.
     * @param string $key  The API key to use.
     * @return array|WP_Error Decoded JSON array on success, WP_Error on failure.
     */
    private function do_request( string $text, string $key ) {
        // build the body payload
        $body = [
            'q'      => $text,
            'target' => 'en',
            'format' => 'text',
            'key'    => $key,
        ];

        $args = [
            'timeout'   => 5,
            'sslverify' => true,
            'body'      => $body,
        ];
        // Allow filters, but guard if they return something unexpected
        $filtered = simula_friendly_slugs_http_args( $args, 'google', self::ENDPOINT, $text );
        if ( is_array( $filtered ) ) {
            $args = $filtered;
        }

        // Fire the request
        $response = wp_remote_post( self::ENDPOINT, $args );
        if ( is_wp_error( $response ) ) {
            return $response;  // pass through WP_Error
        }

        // Check HTTP status
        $code = intval( wp_remote_retrieve_response_code( $response ) );
        if ( 200 !== $code ) {
            $message = wp_remote_retrieve_response_message( $response ) ?: 'Unknown error';
            return new WP_Error( 'http_error',
                sprintf( 'Google API HTTP %d: %s', $code, $message )
            );
        }

        // Parse body
        $raw_body = wp_remote_retrieve_body( $response );
        if ( '' === $raw_body ) {
            return new WP_Error( 'empty_body', 'Empty response from Google API.' );
        }

        $data = json_decode( $raw_body, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error( 'json_error',
                'Invalid JSON from Google API: ' . json_last_error_msg()
            );
        }

        // Validate structure
        if ( ! isset( $data['data']['translations'][0]['translatedText'] ) ) {
            return new WP_Error( 'no_translation', 'Missing translatedText in Google API response.' );
        }

        return $data;
    }
    
    /**
     * Translate text via Google Translate.
     *
     * @param string $text
     * @return string
     */
    public function translate( string $text ): string {
        if ( empty( $this->api_key ) ) {
            return $text;
        }

        try {
            $result = $this->do_request( $text, $this->api_key );
            if ( is_wp_error( $result ) ) {
                error_log( '[SimulaFriendlySlugs] Google translate error: ' . $result->get_error_message() );
                return $text;
            }

            return (string) $result['data']['translations'][0]['translatedText'];
        } catch ( \Throwable $e ) {
            error_log( '[SimulaFriendlySlugs] Exception in Google translate: ' . $e->getMessage() );
            return $text;
        }
    }

    /**
     * Validate & sanitize a raw Google settings array.
     *
     * @param array $raw ['key' => <api-key>]
     * @return string|WP_Error Sanitized key on success, WP_Error on failure.
     */
    public function validate_settings( array $raw ) {
        $key = sanitize_text_field( $raw['key'] ?? '' );

        if ( '' === $key ) {
            return new WP_Error( 'empty', __( 'Google API key cannot be empty.', Simula_Friendly_Slugs::TEXT_DOMAIN ) );
        }
        if ( ! preg_match( '/^AIza[0-9A-Za-z\-_]{35}$/', $key ) ) {
            return new WP_Error( 'format', __( 'Invalid Google API key format.', Simula_Friendly_Slugs::TEXT_DOMAIN ) );
        }

        // Live‐test the key with a dummy translation
        $test = $this->do_request( 'validation_test', $key );
        if ( is_wp_error( $test ) ) {
            return new WP_Error( 'invalid_key',
                __( 'Google API key validation failed.', Simula_Friendly_Slugs::TEXT_DOMAIN )
                . ' ' . $test->get_error_message()
            );
        }

        return $key;
    }    
}


// // Custom provider
// class Simula_Friendly_Slugs_Provider_Custom implements Simula_Friendly_Slugs_Provider_Interface {
//     /** @var string The custom API endpoint URL */
//     private $endpoint;

//     /** @var string The bearer token or API key */
//     private $api_key;

//     public function __construct( string $endpoint, string $key ) {
//         $this->endpoint = trim( esc_url_raw( $endpoint ) );
//         $this->api_key  = trim( $key );
//     }

//     /**
//      * Perform a request against the custom endpoint.
//      *
//      * @param string $text The text to send.
//      * @return array|WP_Error   Decoded JSON array on success; WP_Error on failure.
//      */
//     private function do_request( string $text ) {
//         if ( empty( $this->endpoint ) || empty( $this->api_key ) ) {
//             return new WP_Error( 'missing_config', 'Custom provider not fully configured.' );
//         }

//         $args = [
//             'timeout'   => 5,
//             'sslverify' => true,
//             'headers'   => [
//                 'Authorization' => 'Bearer ' . $this->api_key,
//                 'Content-Type'  => 'application/json',
//             ],
//             'body'      => wp_json_encode( [ 'text' => $text ] ),
//         ];

//         // allow overrides, but only if they return an array
//         $filtered = simula_friendly_slugs_http_args( $args, 'custom', $this->endpoint, $text );
//         if ( is_array( $filtered ) ) {
//             $args = $filtered;
//         }

//         $response = wp_remote_post( $this->endpoint, $args );
//         if ( is_wp_error( $response ) ) {
//             return $response;
//         }

//         $code = intval( wp_remote_retrieve_response_code( $response ) );
//         if ( 200 !== $code ) {
//             $msg = wp_remote_retrieve_response_message( $response ) ?: 'Unknown error';
//             return new WP_Error( 'http_error', sprintf(
//                 'Custom API HTTP %d: %s', $code, $msg
//             ) );
//         }

//         $raw = wp_remote_retrieve_body( $response );
//         if ( '' === $raw ) {
//             return new WP_Error( 'empty_body', 'Empty response from custom API.' );
//         }

//         $data = json_decode( $raw, true );
//         if ( JSON_ERROR_NONE !== json_last_error() ) {
//             return new WP_Error( 'json_error',
//                 'Invalid JSON from custom API: ' . json_last_error_msg()
//             );
//         }

//         return $data;
//     }

//     /**
//      * Translate via the custom API.
//      *
//      * @param string $text
//      * @return string
//      */
//     public function translate( string $text ): string {
//         try {
//             $result = $this->do_request( $text );
//             if ( is_wp_error( $result ) ) {
//                 error_log( '[SimulaFriendlySlugs] Custom translate error: ' . $result->get_error_message() );
//                 return $text;
//             }

//             /**
//              * Allow themes/plugins to map the custom response
//              * back to a simple string.
//              *
//              * @param string $text       Original text.
//              * @param array  $response   Decoded custom-API response.
//              */
//             $translated = apply_filters( 'simula_friendly_slugs_custom_parse_response', $text, $result );
//             return is_string( $translated ) ? $translated : $text;
//         } catch ( \Throwable $e ) {
//             error_log( '[SimulaFriendlySlugs] Exception in custom translate: ' . $e->getMessage() );
//             return $text;
//         }
//     }

//     /**
//      * Validate & sanitize raw settings for this provider.
//      *
//      * @param array $raw  ['endpoint' => '', 'key' => '']
//      * @return string|array|WP_Error
//      *    - On success, return either the sanitized key (string) or an array
//      *      ['endpoint'=>..., 'key'=>...] if you want both saved.
//      *    - On failure, return WP_Error.
//      */
//     public function validate_settings( array $raw ) {
//         $endpoint = trim( esc_url_raw( $raw['endpoint'] ?? '' ) );
//         $key      = trim( sanitize_text_field( $raw['key'] ?? '' ) );

//         if ( '' === $endpoint || ! filter_var( $endpoint, FILTER_VALIDATE_URL ) ) {
//             return new WP_Error( 'invalid_endpoint',
//                 __( 'Custom API endpoint is invalid.', Simula_Friendly_Slugs::TEXT_DOMAIN )
//             );
//         }

//         if ( '' === $key ) {
//             return new WP_Error( 'empty_key',
//                 __( 'Custom API key cannot be empty.', Simula_Friendly_Slugs::TEXT_DOMAIN )
//             );
//         }

//         // Temporarily set props for live test
//         $this->endpoint = $endpoint;
//         $this->api_key  = $key;

//         $test = $this->do_request( 'validation_test' );
//         if ( is_wp_error( $test ) ) {
//             return new WP_Error( 'invalid_credentials',
//                 __( 'Custom API validation failed.', Simula_Friendly_Slugs::TEXT_DOMAIN )
//                 . ' ' . $test->get_error_message()
//             );
//         }

//         // Return both so the settings layer can save endpoint+key
//         return [ 'endpoint' => $endpoint, 'key' => $key ];
//     }
// }

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

    /**
     * Returns the list of translation providers,
     * filtered via the single canonical filter.
     *
     * @return array<string,array{label:string,class:string}>
     */
    private static function get_translation_providers_definitions(): array {
        return (array) apply_filters(
            'simula_friendly_slugs_translation_providers',
            [
                'google' => [
                    'label' => __( 'Google Translate', self::TEXT_DOMAIN ),
                    'class' => 'Simula_Friendly_Slugs_Provider_Google',
                ],
                // you can add 'custom' here by default if you wish
                // ],
                // 'custom' => [
                //     'label' => __( 'Custom API',      self::TEXT_DOMAIN ),
                //     'class' => 'Simula_Friendly_Slugs_Provider_Custom',
                // ]
            ]
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
    
        // Bail early unless “Translation” is the chosen method.
        $opts   = get_option( self::OPTION_KEY, [] );
        $method = $opts['method'] ?? '';

        if ( 'translation' !== $method ) {
            return;
        }

        // 1) Translation section + the “Translation Service” dropdown
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

        // 2) Gather all the provider definitions…
        $definitions = self::get_translation_providers_definitions();

        // 3) Instantiate each provider (so the dropdown callback sees them)
        foreach ( $definitions as $key => $def ) {
            $api_key = $opts['api_keys'][ $key ] ?? '';
            if ( 'custom' === $key ) {
                $endpoint = $opts['custom_api_endpoint'] ?? '';
                $this->providers[ $key ] = new $def['class']( $endpoint, $api_key );
            } else {
                $this->providers[ $key ] = new $def['class']( $api_key );
            }
        }

        // 4) Figure out which service the user actually picked
        $selected = $opts['translation_service'] ?? '';
        if ( ! array_key_exists( $selected, $definitions ) ) {
            // fallback to the first available
            $selected = key( $definitions );
        }

        // 5) **Only** register the API‐Key field for that one service:
        add_settings_field(
            "{$selected}_api_key",
            sprintf( __( '%s API Key', self::TEXT_DOMAIN ), $definitions[ $selected ]['label'] ),
            [ $this, 'field_api_key_html' ],
            self::TEXT_DOMAIN,
            'simula_friendly_slugs_translation',
            [ 'service' => $selected ]
        );

        // 6) If “custom” is the selected service, also add the endpoint field
        if ( 'custom' === $selected ) {
            add_settings_field(
                'custom_api_endpoint',
                __( 'Custom API Endpoint', self::TEXT_DOMAIN ),
                [ $this, 'field_custom_endpoint_html' ],
                self::TEXT_DOMAIN,
                'simula_friendly_slugs_translation'
            );
        }
    }
    

/**
 * Sanitize and validate all plugin settings.
 *
 * @param array $input Raw input from the options form.
 * @return array Sanitized settings array (never WP_Error).
 */
public function sanitize_settings( $input ) {
    // Grab the previous, to fall back on on error.
    $previous = get_option( self::OPTION_KEY, [] );
    if ( is_wp_error( $previous ) || ! is_array( $previous ) ) {
        $previous = [];
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        add_settings_error(
            self::OPTION_KEY,
            'permission_denied',
            __( 'You do not have permission to edit these settings.', self::TEXT_DOMAIN ),
            'error'
        );
        return $previous;
    }

    $valid   = [];
    $methods = [ 'wp_transliteration', 'custom_transliteration', 'arabizi', 'translation', 'none' ];

    // 1) Method
    if ( isset( $input['method'] ) && in_array( $input['method'], $methods, true ) ) {
        $valid['method'] = $input['method'];
    } else {
        $valid['method'] = $previous['method'] ?? 'none';
    }

    // 2) If translation
    if ( 'translation' === $valid['method'] ) {
        // Validate service
        $definitions = self::get_translation_providers_definitions();
        $services    = array_keys( $definitions );
        $service     = $input['translation_service'] ?? '';
        if ( ! in_array( $service, $services, true ) ) {
            add_settings_error(
                self::OPTION_KEY,
                'invalid_service',
                __( 'Please choose a valid Translation Service.', self::TEXT_DOMAIN ),
                'error'
            );
            return $previous;
        }
        $valid['translation_service'] = $service;

        // Validate provider credentials
        $valid['api_keys'] = [];
        foreach ( $services as $s ) {
            $raw_key      = $input['api_keys'][ $s ] ?? '';
            $raw_endpoint = $input['custom_api_endpoint'] ?? '';

            if ( isset( $this->providers[ $s ] ) && method_exists( $this->providers[ $s ], 'validate_settings' ) ) {
                $result = $this->providers[ $s ]->validate_settings([
                    'key'      => $raw_key,
                    'endpoint' => $raw_endpoint,
                ]);
                if ( is_wp_error( $result ) ) {
                    add_settings_error(
                        self::OPTION_KEY,
                        "{$s}_invalid",
                        $result->get_error_message(),
                        'error'
                    );
                    return $previous;
                }
                // on success, result is either string or array
                if ( is_array( $result ) ) {
                    $valid['api_keys'][ $s ]             = sanitize_text_field( $result['key'] ?? '' );
                    $valid['custom_api_endpoint'] = esc_url_raw( $result['endpoint'] ?? '' );
                } else {
                    $valid['api_keys'][ $s ] = sanitize_text_field( $result );
                }
            } else {
                // fallback: just sanitize text
                $valid['api_keys'][ $s ] = sanitize_text_field( $raw_key );
            }
        }
    }

    return $valid;
}


    /** Settings field HTML */
    public function field_method_html() {
        $options = get_option( self::OPTION_KEY, [] );
        $current = $options['method'] ?? 'none';
        ?>
        <select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[method]" onchange="this.form.submit()">
            <option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( 'No Change', self::TEXT_DOMAIN ); ?></option>
            <option value="wp_transliteration" <?php selected( $current, 'wp_transliteration' ); ?>><?php esc_html_e( 'Transliteration', self::TEXT_DOMAIN ); ?></option>
            <option value="arabizi" <?php selected( $current, 'arabizi' ); ?>><?php esc_html_e( '3arabizi', self::TEXT_DOMAIN ); ?></option>
            <option value="translation" <?php selected( $current, 'translation' ); ?>><?php esc_html_e( 'Translation', self::TEXT_DOMAIN ); ?></option>
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
        if ( ( $options['method'] ?? '' ) !== 'translation' 
        // || ( $options['translation_service'] ?? '' ) !== $service 
        ) 
        {
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


/**
 * On activate, ensure we have a default method of “none”
 */
register_activation_hook( __FILE__, function() {
    // don’t overwrite if they’ve already got settings
    if ( false === get_option( Simula_Friendly_Slugs::OPTION_KEY, false ) ) {
        add_option( Simula_Friendly_Slugs::OPTION_KEY, [ 'method' => 'none' ] );
    }
} );