<?php
/**
 * Plugin Name: Simula Friendly Slugs for Arabic Sites
 * Plugin URI: https://github.com/simula-lab/simula-friendly-slugs-for-arabic-sites
 * Description: Automatically generate friendly slugs for Arabic posts/pages via transliteration, 3arabizi or translation.
 * Version: 1.2.4
 * Requires at least: 4.6
 * Requires PHP: 7.0
 * Author: Simula
 * Author URI: https://simulalab.org/
 * License: GPL v2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simula-friendly-slugs-for-arabic-sites
 * Domain Path: /languages
 */


// Abort, if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//Opt into Consent API
add_action( 'plugins_loaded', function() {
  $plugin = plugin_basename( __FILE__ );
  add_filter( "wp_consent_api_registered_{$plugin}", '__return_true' );
} );

/**
 * Apply filters to HTTP args for requests.
 *
 * @param array  $args        HTTP request arguments.
 * @param string $provider    Provider key (e.g., 'google', 'yandex', 'custom').
 * @param string $endpoint    Request URL endpoint.
 * @param string $text        Text being translated.
 * @return array Modified HTTP args.
 */
function simula_friendly_slugs_for_arabic_sites_http_args( $args, $provider, $endpoint, $text ) {
    return apply_filters( 'simula_friendly_slugs_for_arabic_sites_http_args', $args, $provider, $endpoint, $text );
}


// Provider interface
interface Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface {
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
class Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Google implements Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface {

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
        $filtered = simula_friendly_slugs_for_arabic_sites_http_args( $args, 'google', self::ENDPOINT, $text );
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
                sprintf( 'Google Translate API HTTP %d: %s', $code, $message )
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
            // if ( defined('WP_DEBUG') && WP_DEBUG ) {
            //     error_log( "[SimulaFriendlySlugs] No API key, skipping translation of “{$text}”" );
            // }
            return $text;
        }

        try {
            $result = $this->do_request( $text, $this->api_key );
            if ( is_wp_error( $result ) ) {
                // if ( defined('WP_DEBUG') && WP_DEBUG ) {
                //     error_log( '[SimulaFriendlySlugs] Google translate error: ' . $result->get_error_message() );
                // }
                return $text;
            }

            return (string) $result['data']['translations'][0]['translatedText'];
        } catch ( \Throwable $e ) {
            // if ( defined('WP_DEBUG') && WP_DEBUG ) {
            //     error_log( '[SimulaFriendlySlugs] Exception in Google translate: ' . $e->getMessage() );
            // }
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
            return new WP_Error( 'empty', __( 'Google API key cannot be empty.', 'simula-friendly-slugs-for-arabic-sites' ) );
        }
        if ( ! preg_match( '/^AIza[0-9A-Za-z\-_]{35}$/', $key ) ) {
            return new WP_Error( 'format', __( 'Invalid Google API key format.', 'simula-friendly-slugs-for-arabic-sites' ) );
        }

        // Live‐test the key with a dummy translation
        $test = $this->do_request( 'validation_test', $key );
        if ( is_wp_error( $test ) ) {
            return new WP_Error( 'invalid_key',
                __( 'Google API key validation failed.', 'simula-friendly-slugs-for-arabic-sites' )
                . ' ' . $test->get_error_message()
            );
        }

        return $key;
    }    
}

// /**
//  * QA-only mock provider used to exercise multi-provider settings behavior
//  * without external API dependencies.
//  */
// class Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Mock implements Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface {

//     private $token;

//     public function __construct( $key ) {
//         $this->token = sanitize_text_field( (string) $key );
//     }

//     public function translate( string $text ): string {
//         if ( '' === $this->token ) {
//             return $text;
//         }

//         return 'mock-' . sanitize_title( $text, '', 'save' );
//     }

//     public function validate_settings( array $raw ) {
//         $token = sanitize_text_field( $raw['key'] ?? '' );
//         $endpoint = esc_url_raw( $raw['endpoint'] ?? '' );

//         if ( '' === $token ) {
//             return new WP_Error( 'empty_mock_token', __( 'Mock provider token cannot be empty.', 'simula-friendly-slugs-for-arabic-sites' ) );
//         }

//         if ( strlen( $token ) < 6 ) {
//             return new WP_Error( 'invalid_mock_token', __( 'Mock provider token must be at least 6 characters.', 'simula-friendly-slugs-for-arabic-sites' ) );
//         }

//         if ( '' !== $endpoint && ! wp_http_validate_url( $endpoint ) ) {
//             return new WP_Error( 'invalid_mock_endpoint', __( 'Mock provider endpoint must be a valid URL.', 'simula-friendly-slugs-for-arabic-sites' ) );
//         }

//         return [
//             'key' => $token,
//             'endpoint' => $endpoint,
//         ];
//     }
// }


// // Custom provider
// class Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Custom implements Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface {
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
//         $filtered = simula_friendly_slugs_for_arabic_sites_http_args( $args, 'custom', $this->endpoint, $text );
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
//                 if ( defined('WP_DEBUG') && WP_DEBUG ) {
//                     error_log( '[SimulaFriendlySlugs] Custom translate error: ' . $result->get_error_message() );
//                 }
//                 return $text;
//             }


//             /**
//              * Allow themes/plugins to map the custom response
//              * back to a simple string.
//              *
//              * @param string $text       Original text.
//              * @param array  $response   Decoded custom-API response.
//              */
//             $translated = apply_filters( 'simula_friendly_slugs_for_arabic_sites_custom_parse_response', $text, $result );
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
//                 __( 'Custom API endpoint is invalid.', 'simula-friendly-slugs-for-arabic-sites' )
//             );
//         }

//         if ( '' === $key ) {
//             return new WP_Error( 'empty_key',
//                 __( 'Custom API key cannot be empty.', 'simula-friendly-slugs-for-arabic-sites' )
//             );
//         }

//         // Temporarily set props for live test
//         $this->endpoint = $endpoint;
//         $this->api_key  = $key;

//         $test = $this->do_request( 'validation_test' );
//         if ( is_wp_error( $test ) ) {
//             return new WP_Error( 'invalid_credentials',
//                 __( 'Custom API validation failed.', 'simula-friendly-slugs-for-arabic-sites' )
//                 . ' ' . $test->get_error_message()
//             );
//         }

//         // Return both so the settings layer can save endpoint+key
//         return [ 'endpoint' => $endpoint, 'key' => $key ];
//     }
// }

class Simula_Friendly_Slugs_For_Arabic_Sites {
    const OPTION_KEY  = 'simula_friendly_slugs_for_arabic_sites_options';
    const META_SLUG_LOCKED_MANUAL = '_simula_slug_locked_manual';
    const META_LAST_GENERATED_SLUG = '_simula_last_generated_slug';
    const META_ACKNOWLEDGED_DIVERGENT_SUGGESTION = '_simula_acknowledged_divergent_suggestion';
    const ACTION_NONCE = 'simula_slug_action';
    const AJAX_NONCE = 'simula_slug_editor_state';
    const ACTION_REGENERATE = 'regenerate_friendly_slug';
    const ACTION_USE_FRIENDLY = 'use_friendly_slug';
    const ACTION_KEEP_CURRENT = 'keep_current_slug';

    private static $instance;
    /** @var Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface[] */
    private $providers = [];
    /** @var array<int,bool> Request-scoped bypass map for uniqueness-stage overrides. */
    private $skip_unique_override_for_post_ids = [];
    /** @var bool Whether we queued ownership meta for a post ID that was not known yet. */
    private $has_pending_ownership_meta = false;
    /** @var bool Pending manual lock value for deferred persistence. */
    private $pending_manual_lock_value = false;
    /** @var string Pending last-generated slug for deferred persistence. */
    private $pending_last_generated_slug_value = '';
    /** @var bool Request-scoped global uniqueness override bypass. */
    private $skip_unique_override_globally = false;

    /**
     * Supported slug-generation methods currently exposed by the plugin UI.
     *
     * @return string[]
     */
    private function get_supported_methods(): array {
        return [ 'wp_transliteration', 'arabizi', 'translation', 'hash', 'none' ];
    }

    /** Setup WordPress hooks */
    private function __construct() {
        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'setup_providers' ], 11 );
        
        // Admin settings
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_simula_slug_action', [ $this, 'handle_explicit_slug_action' ] );
        add_action( 'admin_notices', [ $this, 'render_classic_editor_slug_notices' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_slug_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_classic_editor_slug_actions' ] );
        add_action( 'wp_ajax_simula_get_slug_divergence_state', [ $this, 'ajax_get_slug_divergence_state' ] );
        add_action( 'wp_ajax_simula_run_slug_action', [ $this, 'ajax_run_slug_action' ] );

        // Override slug on save
        add_filter( 'wp_unique_post_slug', [ $this, 'generate_friendly_slug' ], 10, 6 );
        add_filter( 'wp_insert_post_data', [ $this, 'maybe_generate_slug_on_save' ], 9, 2 );
        add_action( 'save_post', [ $this, 'persist_pending_slug_ownership_meta' ], 20, 3 );

    }

    /**
    * After load_textdomain(), build the $this->providers array.
    */
    public function setup_providers() {    
        $options     = get_option( self::OPTION_KEY, [] );
        $api_keys    = $options['api_keys'] ?? [];
        $defs        = self::get_translation_providers_definitions();
        foreach ( $defs as $key => $def ) {
            $class_name = $def['class'];
            if ( class_exists( $class_name ) && is_subclass_of( $class_name, Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Interface::class ) ) {
                $key_to_use = $api_keys[ $key ] ?? '';
                $this->providers[ $key ] = new $class_name( $key_to_use );
            }
        }
    }    

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Normalize a slug value for deterministic comparisons and storage.
     *
     * @param mixed $slug
     * @return string
     */
    private function normalize_slug_value( $slug ): string {
        if ( ! is_scalar( $slug ) ) {
            return '';
        }

        return sanitize_title( (string) $slug, '', 'save' );
    }

    /**
     * Normalize a saved method against the supported UI/runtime contract.
     *
     * @param mixed $method
     * @return string
     */
    private function normalize_method_value( $method ): string {
        $method = is_scalar( $method ) ? (string) $method : '';

        return in_array( $method, $this->get_supported_methods(), true ) ? $method : 'none';
    }

    /**
     * Normalize lock meta value to boolean.
     *
     * @param mixed $raw_value
     * @return bool
     */
    private function normalize_manual_lock_value( $raw_value ): bool {
        if ( is_bool( $raw_value ) ) {
            return $raw_value;
        }

        if ( is_numeric( $raw_value ) ) {
            return (int) $raw_value === 1;
        }

        if ( is_string( $raw_value ) ) {
            $value = strtolower( trim( $raw_value ) );
            return in_array( $value, [ '1', 'true', 'yes', 'on' ], true );
        }

        return false;
    }

    /**
     * Read manual-lock state from post meta.
     * Missing meta is treated as unlocked (false).
     *
     * @param int $post_id
     * @return bool
     */
    private function get_manual_slug_lock( int $post_id ): bool {
        if ( $post_id <= 0 ) {
            return false;
        }

        $raw = get_post_meta( $post_id, self::META_SLUG_LOCKED_MANUAL, true );
        return $this->normalize_manual_lock_value( $raw );
    }

    /**
     * Persist manual-lock state as deterministic scalar meta.
     *
     * @param int  $post_id
     * @param bool $is_locked
     * @return void
     */
    private function set_manual_slug_lock( int $post_id, bool $is_locked ): void {
        if ( $post_id <= 0 ) {
            return;
        }

        update_post_meta( $post_id, self::META_SLUG_LOCKED_MANUAL, $is_locked ? '1' : '0' );
    }

    /**
     * Read the last plugin-generated slug from post meta.
     * Missing meta is treated as empty string.
     *
     * @param int $post_id
     * @return string
     */
    private function get_last_generated_slug( int $post_id ): string {
        if ( $post_id <= 0 ) {
            return '';
        }

        $raw = get_post_meta( $post_id, self::META_LAST_GENERATED_SLUG, true );
        return $this->normalize_slug_value( $raw );
    }

    /**
     * Persist the latest plugin-generated slug in normalized form.
     * Empty values clear the stored meta.
     *
     * @param int    $post_id
     * @param string $slug
     * @return void
     */
    private function set_last_generated_slug( int $post_id, string $slug ): void {
        if ( $post_id <= 0 ) {
            return;
        }

        $normalized = $this->normalize_slug_value( $slug );
        if ( '' === $normalized ) {
            delete_post_meta( $post_id, self::META_LAST_GENERATED_SLUG );
            return;
        }

        update_post_meta( $post_id, self::META_LAST_GENERATED_SLUG, $normalized );
    }

    /**
     * Read the acknowledged divergent suggestion for notice suppression.
     *
     * @param int $post_id
     * @return string
     */
    private function get_acknowledged_divergent_suggestion( int $post_id ): string {
        if ( $post_id <= 0 ) {
            return '';
        }

        return $this->normalize_slug_value(
            get_post_meta( $post_id, self::META_ACKNOWLEDGED_DIVERGENT_SUGGESTION, true )
        );
    }

    /**
     * Persist or clear the acknowledged divergent suggestion.
     *
     * @param int    $post_id
     * @param string $slug
     * @return void
     */
    private function set_acknowledged_divergent_suggestion( int $post_id, string $slug ): void {
        if ( $post_id <= 0 ) {
            return;
        }

        $normalized = $this->normalize_slug_value( $slug );
        if ( '' === $normalized ) {
            delete_post_meta( $post_id, self::META_ACKNOWLEDGED_DIVERGENT_SUGGESTION );
            return;
        }

        update_post_meta( $post_id, self::META_ACKNOWLEDGED_DIVERGENT_SUGGESTION, $normalized );
    }

    /**
     * Returns normalized ownership state with deterministic defaults.
     *
     * @param int $post_id
     * @return array
     */
    private function get_slug_ownership_state( int $post_id ): array {
        return [
            'manual_lock' => $this->get_manual_slug_lock( $post_id ),
            'last_generated_slug' => $this->get_last_generated_slug( $post_id ),
        ];
    }

    /**
     * Determine if current save request is an autosave/revision context.
     *
     * @param int   $post_id
     * @param array $postarr
     * @return bool
     */
    private function is_autosave_or_revision_context( int $post_id, array $postarr ): bool {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return true;
        }

        if ( isset( $_REQUEST['action'] ) && 'heartbeat' === sanitize_key( wp_unslash( $_REQUEST['action'] ) ) ) {
            return true;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
            if ( is_string( $request_uri ) && false !== strpos( $request_uri, '/autosaves' ) ) {
                return true;
            }
        }

        if ( ! empty( $postarr['post_type'] ) && 'revision' === $postarr['post_type'] ) {
            return true;
        }

        $submitted_slug = $this->normalize_slug_value( $postarr['post_name'] ?? '' );
        if ( '' !== $submitted_slug && preg_match( '/-autosave-v\d+$/', $submitted_slug ) ) {
            return true;
        }

        if ( $post_id > 0 ) {
            if ( false !== wp_is_post_autosave( $post_id ) ) {
                return true;
            }
            if ( false !== wp_is_post_revision( $post_id ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide whether save logic may apply a generated slug automatically.
     *
     * @param int    $post_id
     * @param string $incoming_slug
     * @param string $current_db_slug
     * @param string $last_generated_slug
     * @param string $new_title
     * @param bool   $regenerate_on_change
     * @return bool
     */
    private function should_apply_generated_slug_on_save(
        int $post_id,
        string $incoming_slug,
        string $current_db_slug,
        string $last_generated_slug,
        string $new_title,
        bool $regenerate_on_change
    ): bool {
        // Gutenberg often submits the current slug on title-only saves.
        // Only treat incoming slug as blocking when it diverges from the persisted/plugin-owned value.
        if (
            '' !== $incoming_slug &&
            $incoming_slug !== $current_db_slug &&
            ( '' === $last_generated_slug || $incoming_slug !== $last_generated_slug )
        ) {
            return false;
        }

        if ( $post_id <= 0 || '' === $current_db_slug ) {
            return true;
        }

        if ( ! $regenerate_on_change ) {
            return false;
        }

        $existing = get_post( $post_id );
        if ( ! $existing instanceof WP_Post ) {
            return false;
        }

        if ( $existing->post_title === $new_title ) {
            return false;
        }

        // Only auto-refresh slugs we know the plugin generated previously.
        if ( '' === $last_generated_slug ) {
            return false;
        }

        return $current_db_slug === $last_generated_slug;
    }

    /**
     * Detect explicit manual slug divergence in the current save request.
     *
     * @param string $incoming_slug
     * @param string $current_db_slug
     * @param string $last_generated_slug
     * @param string $generated_slug
     * @return bool
     */
    private function is_manual_slug_edit_detected(
        string $incoming_slug,
        string $current_db_slug,
        string $last_generated_slug,
        string $generated_slug
    ): bool {
        if ( '' === $incoming_slug ) {
            return false;
        }

        // If the submitted slug already matches the plugin suggestion for this save,
        // treat it as plugin-owned rather than a manual override.
        if ( '' !== $generated_slug && $incoming_slug === $generated_slug ) {
            return false;
        }

        if ( '' !== $current_db_slug && $incoming_slug !== $current_db_slug ) {
            return true;
        }

        if ( '' !== $last_generated_slug && $incoming_slug !== $last_generated_slug ) {
            return true;
        }

        // First-save/manual-entry fallback when no comparison baseline exists yet.
        if ( '' === $current_db_slug && '' === $last_generated_slug && '' !== $generated_slug && $incoming_slug !== $generated_slug ) {
            return true;
        }

        return false;
    }

    /**
     * Mark a post to bypass uniqueness-stage friendly override in this request.
     *
     * @param int $post_id
     * @return void
     */
    private function mark_skip_unique_override_for_post_id( int $post_id ): void {
        if ( $post_id > 0 ) {
            $this->skip_unique_override_for_post_ids[ $post_id ] = true;
        }
    }

    /**
     * Whether uniqueness-stage override should be skipped in this request.
     *
     * @param int $post_id
     * @return bool
     */
    private function should_skip_unique_override_for_post_id( int $post_id ): bool {
        return $post_id > 0 && ! empty( $this->skip_unique_override_for_post_ids[ $post_id ] );
    }

    /**
     * Resolve post ID from known save payload locations.
     *
     * @param array $data
     * @param array $postarr
     * @return int
     */
    private function resolve_post_id_from_save_payload( array $data, array $postarr ): int {
        $candidates = [
            $postarr['ID'] ?? null,
            $postarr['id'] ?? null,
            $postarr['post_ID'] ?? null,
            $data['ID'] ?? null,
        ];

        if ( isset( $_POST['post_ID'] ) ) {
            $candidates[] = wp_unslash( $_POST['post_ID'] );
        }
        if ( isset( $_REQUEST['post'] ) ) {
            $candidates[] = wp_unslash( $_REQUEST['post'] );
        }
        if ( isset( $_GET['post'] ) ) {
            $candidates[] = wp_unslash( $_GET['post'] );
        }

        foreach ( $candidates as $candidate ) {
            if ( ! is_scalar( $candidate ) ) {
                continue;
            }
            $post_id = absint( $candidate );
            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        return 0;
    }

    /**
     * Queue ownership meta until a concrete post ID exists.
     *
     * @param bool   $manual_lock
     * @param string $last_generated_slug
     * @return void
     */
    private function queue_pending_slug_ownership_meta( bool $manual_lock, string $last_generated_slug ): void {
        $this->has_pending_ownership_meta = true;
        $this->pending_manual_lock_value = $manual_lock;
        $this->pending_last_generated_slug_value = $this->normalize_slug_value( $last_generated_slug );
    }

    /**
     * Whether the current title is eligible for plugin slug generation.
     *
     * @param string $title
     * @return bool
     */
    private function is_slug_generation_eligible_title( string $title ): bool {
        return '' !== $title && (bool) preg_match( '/\p{Arabic}/u', $title );
    }

    /**
     * Build the plugin suggestion for a title according to current settings.
     *
     * @param string $title
     * @param string $fallback_slug
     * @return string
     */
    private function generate_plugin_slug_suggestion( string $title, string $fallback_slug = '' ): string {
        $opts = get_option( self::OPTION_KEY, [] );
        $method = $this->normalize_method_value( $opts['method'] ?? 'none' );

        if ( 'none' === $method || ! $this->is_slug_generation_eligible_title( $title ) ) {
            return '';
        }

        $converter = "convert_{$method}";
        if ( ! is_callable( [ $this, $converter ] ) ) {
            return '';
        }

        $new_slug_source = $this->$converter( $title );
        return sanitize_title( $new_slug_source, $fallback_slug, 'save' );
    }

    /**
     * Return admin redirect target for explicit slug actions.
     *
     * @param int $post_id
     * @return string
     */
    private function get_slug_action_redirect_url( int $post_id ): string {
        $post = get_post( $post_id );
        if ( $post instanceof WP_Post ) {
            return get_edit_post_link( $post_id, 'url' );
        }

        return admin_url( 'edit.php' );
    }

    /**
     * Redirect after an explicit slug action with deterministic status args.
     *
     * @param int    $post_id
     * @param string $status
     * @return void
     */
    private function redirect_after_slug_action( int $post_id, string $status ): void {
        $redirect_url = add_query_arg(
            [
                'simula_slug_action_status' => sanitize_key( $status ),
                'post' => $post_id,
            ],
            $this->get_slug_action_redirect_url( $post_id )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Build a nonce-protected admin URL for an explicit slug action.
     *
     * @param int    $post_id
     * @param string $action
     * @return string
     */
    private function get_slug_action_url( int $post_id, string $action ): string {
        return add_query_arg(
            [
                'action' => 'simula_slug_action',
                'post_id' => $post_id,
                'post' => $post_id,
                'simula_slug_action' => sanitize_key( $action ),
                'simula_slug_action_nonce' => wp_create_nonce( self::ACTION_NONCE ),
            ],
            admin_url( 'admin-post.php' )
        );
    }

    /**
     * Resolve the explicit slug-action post ID from request payload or referer.
     *
     * @return int
     */
    private function resolve_slug_action_post_id_from_request(): int {
        $candidates = [
            $_REQUEST['post_id'] ?? null,
            $_REQUEST['post'] ?? null,
            $_REQUEST['post_ID'] ?? null,
            $_REQUEST['id'] ?? null,
        ];

        foreach ( $candidates as $candidate ) {
            if ( ! is_scalar( $candidate ) ) {
                continue;
            }

            $post_id = absint( wp_unslash( $candidate ) );
            if ( $post_id > 0 ) {
                return $post_id;
            }
        }

        if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
            $referer = wp_unslash( $_REQUEST['_wp_http_referer'] );
            if ( is_string( $referer ) ) {
                $referer_query = wp_parse_url( $referer, PHP_URL_QUERY );
                if ( is_string( $referer_query ) ) {
                    parse_str( $referer_query, $referer_args );
                    foreach ( [ 'post', 'post_id', 'post_ID', 'id' ] as $key ) {
                        if ( empty( $referer_args[ $key ] ) || ! is_scalar( $referer_args[ $key ] ) ) {
                            continue;
                        }

                        $post_id = absint( $referer_args[ $key ] );
                        if ( $post_id > 0 ) {
                            return $post_id;
                        }
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Calculate the plugin-vs-current slug divergence state for a post.
     *
     * @param int $post_id
     * @return array
     */
    private function get_slug_divergence_state( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post ) {
            return [
                'post_id' => $post_id,
                'is_supported' => false,
                'should_show_notice' => false,
                'current_slug' => '',
                'suggested_slug' => '',
                'manual_lock' => false,
                'action_urls' => [],
            ];
        }

        $current_slug = $this->normalize_slug_value( $post->post_name );
        $suggested_slug = $this->generate_plugin_slug_suggestion( $post->post_title, $post->post_name );
        $ownership_state = $this->get_slug_ownership_state( $post_id );
        $manual_lock = ! empty( $ownership_state['manual_lock'] );
        $is_supported = '' !== $suggested_slug && $this->is_slug_generation_eligible_title( $post->post_title );
        $has_divergence = $is_supported && '' !== $current_slug && $current_slug !== $suggested_slug;
        $acknowledged_suggestion = $this->get_acknowledged_divergent_suggestion( $post_id );
        $is_acknowledged = $has_divergence && '' !== $acknowledged_suggestion && $acknowledged_suggestion === $suggested_slug;

        return [
            'post_id' => $post_id,
            'is_supported' => $is_supported,
            'should_show_notice' => $has_divergence && ! $is_acknowledged,
            'has_divergence' => $has_divergence,
            'current_slug' => $current_slug,
            'suggested_slug' => $suggested_slug,
            'manual_lock' => $manual_lock,
            'last_generated_slug' => $ownership_state['last_generated_slug'] ?? '',
            'is_acknowledged' => $is_acknowledged,
            'action_urls' => $has_divergence ? [
                self::ACTION_KEEP_CURRENT => $this->get_slug_action_url( $post_id, self::ACTION_KEEP_CURRENT ),
                self::ACTION_USE_FRIENDLY => $this->get_slug_action_url( $post_id, self::ACTION_USE_FRIENDLY ),
            ] : [],
        ];
    }

    /**
     * Public accessor for editor integrations that need slug divergence state.
     *
     * @param int $post_id
     * @return array
     */
    public function get_editor_slug_divergence_state( int $post_id ): array {
        return $this->get_slug_divergence_state( $post_id );
    }

    /**
     * Return a renderable message configuration for slug action status.
     *
     * @param string $status
     * @return array|null
     */
    private function get_slug_action_status_message( string $status ) {
        $messages = [
            'kept_current' => [
                'type' => 'success',
                'text' => __( 'Current slug kept. Manual slug ownership is now locked.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
            'used_friendly' => [
                'type' => 'success',
                'text' => __( 'Friendly slug applied successfully.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
            'regenerated' => [
                'type' => 'success',
                'text' => __( 'Friendly slug regenerated successfully.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
            'keep_failed' => [
                'type' => 'error',
                'text' => __( 'Could not keep the current slug.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
            'use_friendly_failed' => [
                'type' => 'error',
                'text' => __( 'Could not apply the friendly slug.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
            'regenerate_failed' => [
                'type' => 'error',
                'text' => __( 'Could not regenerate the friendly slug.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
            'generation_failed' => [
                'type' => 'error',
                'text' => __( 'No valid friendly slug could be generated for this title.', 'simula-friendly-slugs-for-arabic-sites' ),
            ],
        ];

        return $messages[ $status ] ?? null;
    }

    /**
     * Whether the current admin screen is a classic post editor surface.
     *
     * @return bool
     */
    private function is_classic_post_editor_screen(): bool {
        if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }

        if ( ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
            return false;
        }

        if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
            return false;
        }

        return true;
    }

    /**
     * Resolve current post ID from common admin query locations.
     *
     * @return int
     */
    private function get_current_admin_post_id(): int {
        if ( isset( $_GET['post'] ) ) {
            return absint( wp_unslash( $_GET['post'] ) );
        }

        if ( isset( $_POST['post_ID'] ) ) {
            return absint( wp_unslash( $_POST['post_ID'] ) );
        }

        return 0;
    }

    /**
     * Enqueue Gutenberg notice integration using the shared divergence state.
     *
     * @return void
     */
    public function enqueue_block_editor_slug_notices(): void {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || empty( $screen->is_block_editor() ) ) {
            return;
        }

        $asset_path = plugin_dir_path( __FILE__ ) . 'assets/block-editor-slug-notice.js';
        if ( ! file_exists( $asset_path ) ) {
            return;
        }

        wp_enqueue_script(
            'simula-friendly-slugs-block-editor',
            plugin_dir_url( __FILE__ ) . 'assets/block-editor-slug-notice.js',
            [ 'wp-data', 'wp-dom-ready', 'wp-i18n' ],
            (string) filemtime( $asset_path ),
            true
        );

        $status = isset( $_GET['simula_slug_action_status'] ) ? sanitize_key( wp_unslash( $_GET['simula_slug_action_status'] ) ) : '';
        wp_localize_script(
            'simula-friendly-slugs-block-editor',
            'simulaFriendlySlugsBlockEditor',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'ajaxAction' => 'simula_get_slug_divergence_state',
                'runActionAjaxAction' => 'simula_run_slug_action',
                'ajaxNonce' => wp_create_nonce( self::AJAX_NONCE ),
                'initialPostId' => $this->get_current_admin_post_id(),
                'status' => $status,
                'statusMessage' => '' !== $status ? $this->get_slug_action_status_message( $status ) : null,
                'noticeId' => 'simula-friendly-slugs-divergence-notice',
                'statusNoticeId' => 'simula-friendly-slugs-status-notice',
                'labels' => [
                    'title' => __( 'Friendly slug differs from the current slug.', 'simula-friendly-slugs-for-arabic-sites' ),
                    'body' => __( 'Choose whether to keep the current slug or apply the plugin suggestion.', 'simula-friendly-slugs-for-arabic-sites' ),
                    'current' => __( 'Current slug:', 'simula-friendly-slugs-for-arabic-sites' ),
                    'suggested' => __( 'Suggested slug:', 'simula-friendly-slugs-for-arabic-sites' ),
                    'keep' => __( 'Keep current slug', 'simula-friendly-slugs-for-arabic-sites' ),
                    'useFriendly' => __( 'Use friendly slug', 'simula-friendly-slugs-for-arabic-sites' ),
                ],
            ]
        );
    }

    /**
     * Enqueue Classic editor AJAX action handling.
     *
     * @return void
     */
    public function enqueue_classic_editor_slug_actions(): void {
        if ( ! $this->is_classic_post_editor_screen() ) {
            return;
        }

        $asset_path = plugin_dir_path( __FILE__ ) . 'assets/classic-editor-slug-actions.js';
        if ( ! file_exists( $asset_path ) ) {
            return;
        }

        wp_enqueue_script(
            'simula-friendly-slugs-classic-editor',
            plugin_dir_url( __FILE__ ) . 'assets/classic-editor-slug-actions.js',
            [],
            (string) filemtime( $asset_path ),
            true
        );

        wp_localize_script(
            'simula-friendly-slugs-classic-editor',
            'simulaFriendlySlugsClassicEditor',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'ajaxAction' => 'simula_run_slug_action',
                'ajaxNonce' => wp_create_nonce( self::AJAX_NONCE ),
            ]
        );
    }

    /**
     * Return divergence state for Gutenberg via AJAX.
     *
     * @return void
     */
    public function ajax_get_slug_divergence_state(): void {
        check_ajax_referer( self::AJAX_NONCE, 'nonce' );

        $post_id = isset( $_REQUEST['post_id'] ) ? absint( wp_unslash( $_REQUEST['post_id'] ) ) : 0;
        if ( $post_id <= 0 ) {
            wp_send_json_error(
                [
                    'message' => __( 'Invalid post ID.', 'simula-friendly-slugs-for-arabic-sites' ),
                ],
                400
            );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'You are not allowed to edit this post.', 'simula-friendly-slugs-for-arabic-sites' ),
                ],
                403
            );
        }

        wp_send_json_success( $this->get_slug_divergence_state( $post_id ) );
    }

    /**
     * Execute an explicit slug action over AJAX.
     *
     * @return void
     */
    public function ajax_run_slug_action(): void {
        check_ajax_referer( self::AJAX_NONCE, 'nonce' );

        $post_id = $this->resolve_slug_action_post_id_from_request();
        if ( $post_id <= 0 ) {
            wp_send_json_error(
                [ 'message' => __( 'Invalid post ID.', 'simula-friendly-slugs-for-arabic-sites' ) ],
                400
            );
        }

        $action = isset( $_REQUEST['simula_slug_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['simula_slug_action'] ) ) : '';
        $result = $this->execute_explicit_slug_action( $post_id, $action );
        if ( empty( $result['ok'] ) ) {
            wp_send_json_error( $result, 400 );
        }

        wp_send_json_success( $result );
    }

    /**
     * Render feedback and divergence notices on Classic editor screens.
     *
     * @return void
     */
    public function render_classic_editor_slug_notices(): void {
        if ( ! $this->is_classic_post_editor_screen() ) {
            return;
        }

        $post_id = $this->get_current_admin_post_id();
        if ( $post_id <= 0 ) {
            return;
        }

        $this->render_slug_action_status_notice();
        $this->render_slug_divergence_notice( $post_id );
    }

    /**
     * Render the result of the latest explicit slug action, if present.
     *
     * @return void
     */
    private function render_slug_action_status_notice(): void {
        if ( empty( $_GET['simula_slug_action_status'] ) ) {
            return;
        }

        $status = sanitize_key( wp_unslash( $_GET['simula_slug_action_status'] ) );
        $message = $this->get_slug_action_status_message( $status );
        if ( empty( $message ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr( $message['type'] ),
            esc_html( $message['text'] )
        );
    }

    /**
     * Render the divergence notice for Classic editor flows.
     *
     * @param int $post_id
     * @return void
     */
    private function render_slug_divergence_notice( int $post_id ): void {
        $state = $this->get_slug_divergence_state( $post_id );
        if ( empty( $state['should_show_notice'] ) ) {
            return;
        }

        $action_urls = $state['action_urls'];
        if (
            empty( $action_urls[ self::ACTION_KEEP_CURRENT ] ) ||
            empty( $action_urls[ self::ACTION_USE_FRIENDLY ] )
        ) {
            return;
        }

        $current_slug = $state['current_slug'];
        $suggested_slug = $state['suggested_slug'];

        printf(
            '<div class="notice notice-warning simula-slug-divergence-notice" data-post-id="%13$s"><p><strong>%1$s</strong> %2$s</p><p>%3$s <code>%4$s</code><br>%5$s <code>%6$s</code></p><p><a class="button button-secondary simula-slug-action" href="%7$s" data-post-id="%13$s" data-action-name="%14$s">%8$s</a> <a class="button button-primary simula-slug-action" href="%9$s" data-post-id="%13$s" data-action-name="%15$s">%10$s</a></p></div>',
            esc_html__( 'Friendly slug differs from the current slug.', 'simula-friendly-slugs-for-arabic-sites' ),
            esc_html__( 'Choose whether to keep the current slug or apply the plugin suggestion.', 'simula-friendly-slugs-for-arabic-sites' ),
            esc_html__( 'Current slug:', 'simula-friendly-slugs-for-arabic-sites' ),
            esc_html( $current_slug ),
            esc_html__( 'Suggested slug:', 'simula-friendly-slugs-for-arabic-sites' ),
            esc_html( $suggested_slug ),
            esc_url( $action_urls[ self::ACTION_KEEP_CURRENT ] ),
            esc_html__( 'Keep current slug', 'simula-friendly-slugs-for-arabic-sites' ),
            esc_url( $action_urls[ self::ACTION_USE_FRIENDLY ] ),
            esc_html__( 'Use friendly slug', 'simula-friendly-slugs-for-arabic-sites' ),
            esc_attr( $post_id ),
            esc_attr( self::ACTION_KEEP_CURRENT ),
            esc_attr( self::ACTION_USE_FRIENDLY )
        );
    }

    /**
     * Apply an explicit slug ownership transition for an existing post.
     *
     * @param int    $post_id
     * @param string $slug
     * @param bool   $manual_lock
     * @return bool
     */
    private function apply_explicit_slug_update( int $post_id, string $slug, bool $manual_lock, string $last_generated_slug = '' ): bool {
        if ( $post_id <= 0 ) {
            return false;
        }

        $normalized_slug = $this->normalize_slug_value( $slug );
        if ( '' === $normalized_slug ) {
            return false;
        }

        remove_action( 'save_post', [ $this, 'persist_pending_slug_ownership_meta' ], 20 );

        $result = wp_update_post(
            [
                'ID' => $post_id,
                'post_name' => $normalized_slug,
            ],
            true
        );

        add_action( 'save_post', [ $this, 'persist_pending_slug_ownership_meta' ], 20, 3 );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        $this->set_manual_slug_lock( $post_id, $manual_lock );
        if ( $manual_lock ) {
            $this->set_last_generated_slug( $post_id, $last_generated_slug );
            return true;
        }

        $this->set_last_generated_slug( $post_id, $normalized_slug );
        return true;
    }

    /**
     * Execute the requested explicit slug action and return a structured result.
     *
     * @param int    $post_id
     * @param string $action
     * @return array
     */
    private function execute_explicit_slug_action( int $post_id, string $action ): array {
        if ( $post_id <= 0 ) {
            return [
                'ok' => false,
                'status' => 'invalid_post',
                'message' => __( 'Invalid post ID.', 'simula-friendly-slugs-for-arabic-sites' ),
            ];
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return [
                'ok' => false,
                'status' => 'forbidden',
                'message' => __( 'You are not allowed to edit this post.', 'simula-friendly-slugs-for-arabic-sites' ),
            ];
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post ) {
            return [
                'ok' => false,
                'status' => 'missing_post',
                'message' => __( 'Post not found.', 'simula-friendly-slugs-for-arabic-sites' ),
            ];
        }

        if ( self::ACTION_KEEP_CURRENT === $action ) {
            $current_slug = $this->normalize_slug_value( $post->post_name );
            if ( '' === $current_slug ) {
                return [
                    'ok' => false,
                    'status' => 'keep_failed',
                    'message' => __( 'Could not keep the current slug.', 'simula-friendly-slugs-for-arabic-sites' ),
                ];
            }

            $ownership_state = $this->get_slug_ownership_state( $post_id );
            $success = $this->apply_explicit_slug_update(
                $post_id,
                $current_slug,
                true,
                $ownership_state['last_generated_slug'] ?? ''
            );

            if ( $success ) {
                $suggested_slug = $this->generate_plugin_slug_suggestion( $post->post_title, $post->post_name );
                $this->set_acknowledged_divergent_suggestion( $post_id, $suggested_slug );
            }

            $status = $success ? 'kept_current' : 'keep_failed';
            return [
                'ok' => $success,
                'status' => $status,
                'message' => $this->get_slug_action_status_message( $status ),
                'divergence' => $this->get_slug_divergence_state( $post_id ),
            ];
        }

        if ( ! in_array( $action, [ self::ACTION_REGENERATE, self::ACTION_USE_FRIENDLY ], true ) ) {
            return [
                'ok' => false,
                'status' => 'unknown_action',
                'message' => __( 'Unknown slug action.', 'simula-friendly-slugs-for-arabic-sites' ),
            ];
        }

        $generated_slug = $this->generate_plugin_slug_suggestion( $post->post_title, $post->post_name );
        if ( '' === $generated_slug ) {
            return [
                'ok' => false,
                'status' => 'generation_failed',
                'message' => $this->get_slug_action_status_message( 'generation_failed' ),
                'divergence' => $this->get_slug_divergence_state( $post_id ),
            ];
        }

        $success = $this->apply_explicit_slug_update( $post_id, $generated_slug, false );
        if ( $success ) {
            $this->set_acknowledged_divergent_suggestion( $post_id, '' );
        }

        $status = self::ACTION_REGENERATE === $action
            ? ( $success ? 'regenerated' : 'regenerate_failed' )
            : ( $success ? 'used_friendly' : 'use_friendly_failed' );

        return [
            'ok' => $success,
            'status' => $status,
            'message' => $this->get_slug_action_status_message( $status ),
            'divergence' => $this->get_slug_divergence_state( $post_id ),
        ];
    }

    /**
     * Handle admin-side explicit slug actions.
     *
     * @return void
     */
    public function handle_explicit_slug_action(): void {
        $post_id = $this->resolve_slug_action_post_id_from_request();
        if ( $post_id <= 0 ) {
            wp_die( esc_html__( 'Invalid post ID.', 'simula-friendly-slugs-for-arabic-sites' ), 400 );
        }

        check_admin_referer( self::ACTION_NONCE, 'simula_slug_action_nonce' );

        $action = isset( $_REQUEST['simula_slug_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['simula_slug_action'] ) ) : '';
        $result = $this->execute_explicit_slug_action( $post_id, $action );
        if ( empty( $result['status'] ) ) {
            wp_die( esc_html__( 'Unknown slug action.', 'simula-friendly-slugs-for-arabic-sites' ), 400 );
        }

        $this->redirect_after_slug_action( $post_id, (string) $result['status'] );
    }

    /**
     * Persist queued ownership meta once save_post has a concrete post ID.
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     * @return void
     */
    public function persist_pending_slug_ownership_meta( $post_id, $post, $update ): void {
        if ( ! $this->has_pending_ownership_meta ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $this->set_manual_slug_lock( (int) $post_id, $this->pending_manual_lock_value );
        $this->set_last_generated_slug( (int) $post_id, $this->pending_last_generated_slug_value );

        $this->has_pending_ownership_meta = false;
        $this->pending_manual_lock_value = false;
        $this->pending_last_generated_slug_value = '';
    }

    /** Load plugin textdomain */
    public function load_textdomain() {
        load_plugin_textdomain( 
            'simula-friendly-slugs-for-arabic-sites', 
            false, 
            dirname( plugin_basename( __FILE__ ) ) . '/languages/' 
        );
    }

    /**
     * Returns the normalized list of translation providers,
     * filtered via the single canonical filter.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function get_translation_providers_definitions(): array {
        $definitions = (array) apply_filters(
            'simula_friendly_slugs_for_arabic_sites_translation_providers',
            [
                'google' => [
                    'label' => __( 'Google Translate', 'simula-friendly-slugs-for-arabic-sites' ),
                    'description' => __( 'Use Google Translate API.', 'simula-friendly-slugs-for-arabic-sites' ),
                    'class' => 'Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Google',
                    'fields' => [
                        [
                            'type' => 'api_key',
                            'option_path' => [ 'api_keys', 'google' ],
                            'validation_key' => 'key',
                            'label' => __( 'Google API Key', 'simula-friendly-slugs-for-arabic-sites' ),
                            'description' => __( 'API key used for Google Translate requests.', 'simula-friendly-slugs-for-arabic-sites' ),
                        ],
                    ],
                ],
                // 'mock' => [
                //     'label' => __( 'Mock Provider', 'simula-friendly-slugs-for-arabic-sites' ),
                //     'description' => __( 'QA-only provider for testing multi-provider settings flows without external API calls.', 'simula-friendly-slugs-for-arabic-sites' ),
                //     'class' => 'Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Mock',
                //     'fields' => [
                //         [
                //             'type' => 'text',
                //             'option_path' => [ 'api_keys', 'mock' ],
                //             'validation_key' => 'key',
                //             'label' => __( 'Mock Token', 'simula-friendly-slugs-for-arabic-sites' ),
                //             'description' => __( 'Enter any token with at least 6 characters.', 'simula-friendly-slugs-for-arabic-sites' ),
                //         ],
                //         [
                //             'type' => 'url',
                //             'option_path' => [ 'provider_endpoints', 'mock' ],
                //             'validation_key' => 'endpoint',
                //             'label' => __( 'Mock Endpoint', 'simula-friendly-slugs-for-arabic-sites' ),
                //             'description' => __( 'Optional URL used only to test provider-specific field persistence.', 'simula-friendly-slugs-for-arabic-sites' ),
                //         ],
                //     ],
                // ],
                // you can add 'custom' here by default if you wish
                // ],
                // 'custom' => [
                //     'label' => __( 'Custom API', 'simula-friendly-slugs-for-arabic-sites' ),
                //     'description' => __( 'Use a custom translation endpoint.', 'simula-friendly-slugs-for-arabic-sites' ),
                //     'class' => 'Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Custom',
                //     'fields' => [
                //         [
                //             'type' => 'url',
                //             'option_path' => [ 'custom_api_endpoint' ],
                //             'validation_key' => 'endpoint',
                //             'label' => __( 'Custom API Endpoint', 'simula-friendly-slugs-for-arabic-sites' ),
                //         ],
                //         [
                //             'type' => 'api_key',
                //             'option_path' => [ 'api_keys', 'custom' ],
                //             'validation_key' => 'key',
                //             'label' => __( 'Custom API Key', 'simula-friendly-slugs-for-arabic-sites' ),
                //         ],
                //     ],
                // ]
            ]
        );

        $normalized = [];

        foreach ( $definitions as $key => $definition ) {
            if ( ! is_string( $key ) || '' === $key || ! is_array( $definition ) ) {
                continue;
            }

            $label = isset( $definition['label'] ) ? wp_strip_all_tags( (string) $definition['label'] ) : '';
            $class = isset( $definition['class'] ) ? trim( (string) $definition['class'] ) : '';

            if ( '' === $label || '' === $class ) {
                continue;
            }

            $provider = [
                'label' => $label,
                'class' => $class,
            ];

            if ( isset( $definition['description'] ) ) {
                $provider['description'] = wp_strip_all_tags( (string) $definition['description'] );
            }

            $provider['fields'] = [];
            if ( isset( $definition['fields'] ) && is_array( $definition['fields'] ) ) {
                foreach ( $definition['fields'] as $field ) {
                    if ( ! is_array( $field ) ) {
                        continue;
                    }

                    $type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : '';
                    $field_label = isset( $field['label'] ) ? wp_strip_all_tags( (string) $field['label'] ) : '';
                    $option_path = $field['option_path'] ?? null;

                    if ( '' === $type || '' === $field_label || ! is_array( $option_path ) || empty( $option_path ) ) {
                        continue;
                    }

                    $normalized_path = [];
                    foreach ( $option_path as $segment ) {
                        if ( ! is_scalar( $segment ) ) {
                            continue 2;
                        }

                        $segment = (string) $segment;
                        if ( '' === $segment ) {
                            continue 2;
                        }

                        $normalized_path[] = $segment;
                    }

                    $normalized_field = [
                        'type' => $type,
                        'label' => $field_label,
                        'option_path' => $normalized_path,
                        'validation_key' => isset( $field['validation_key'] ) ? sanitize_key( (string) $field['validation_key'] ) : $this->infer_provider_field_validation_key( $type, $normalized_path ),
                    ];

                    if ( isset( $field['description'] ) ) {
                        $normalized_field['description'] = wp_strip_all_tags( (string) $field['description'] );
                    }

                    $provider['fields'][] = $normalized_field;
                }
            }

            $normalized[ $key ] = $provider;
        }

        return $normalized;
    }

    /** Add settings page under Settings menu */
    public function register_settings_page() {
        add_options_page(
            __( 'Friendly Slugs', 'simula-friendly-slugs-for-arabic-sites' ),
            __( 'Friendly Slugs', 'simula-friendly-slugs-for-arabic-sites' ),
            'manage_options',
            'simula-friendly-slugs-for-arabic-sites',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
    * Register settings and fields. Always show all options at once.
    */
    public function register_settings() {
        // Register the main option container
        register_setting(
            'simula-friendly-slugs-for-arabic-sites',
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );
    
        // Main section for method
        add_settings_section(
            'simula_friendly_slugs_for_arabic_sites_main',
            __( 'Slug Generation Method', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'section_main_html' ],
            'simula-friendly-slugs-for-arabic-sites'
        );
    
        // Field: method radio list
        add_settings_field(
            'method',
            __( 'Method', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'field_method_html' ],
            'simula-friendly-slugs-for-arabic-sites',
            'simula_friendly_slugs_for_arabic_sites_main'
        );
    
        // Translation settings section
        add_settings_section(
            'simula_friendly_slugs_for_arabic_sites_translation',
            __( 'Translation Settings', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'section_translation_html' ],
            'simula-friendly-slugs-for-arabic-sites'
        );
    
        // Field: Translation service dropdown
        add_settings_field(
            'translation_service',
            __( 'Translation Service', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'field_translation_service_html' ],
            'simula-friendly-slugs-for-arabic-sites',
            'simula_friendly_slugs_for_arabic_sites_translation'
        );
    
        // Field: provider-specific settings
        add_settings_field(
            'translation_provider_fields',
            __( 'Provider Settings', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'field_translation_provider_settings_html' ],
            'simula-friendly-slugs-for-arabic-sites',
            'simula_friendly_slugs_for_arabic_sites_translation'
        );

        add_settings_field(
            'regenerate_on_change',
            __( 'Auto-refresh plugin-owned slug on title change', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'field_regenerate_on_change_html' ],
            'simula-friendly-slugs-for-arabic-sites',
            'simula_friendly_slugs_for_arabic_sites_main'
        );
    }

    /**
     * Description for the main section
     */
    public function section_main_html() {
        echo '<p>' . esc_html__( 'Choose how slugs are generated for Arabic titles.', 'simula-friendly-slugs-for-arabic-sites' ) . '</p>';
    }

    /**
     * Description for the translation section
     */
    public function section_translation_html() {
        echo '<p>' . esc_html__( 'Configure translation provider and credentials.', 'simula-friendly-slugs-for-arabic-sites' ) . '</p>';
    }

    /**
     * Render radio list for method with inline descriptions
     */
    public function field_method_html() {
        $options = get_option( self::OPTION_KEY, [] );
        $current = $this->normalize_method_value( $options['method'] ?? 'none' );
        $methods = [
            'none'               => [ 'label' => __( 'No Change', 'simula-friendly-slugs-for-arabic-sites' ), 'desc' => __( 'Leave the slug unchanged.', 'simula-friendly-slugs-for-arabic-sites' ) ],
            'wp_transliteration' => [ 'label' => __( 'Transliteration', 'simula-friendly-slugs-for-arabic-sites' ), 'desc' => __( 'Use PHP/ICU to transliterate Arabic characters.', 'simula-friendly-slugs-for-arabic-sites' ) ],
            'arabizi'           => [ 'label' => __( '3arabizi', 'simula-friendly-slugs-for-arabic-sites' ), 'desc' => __( 'Convert Arabic to 3arabizi numerals.', 'simula-friendly-slugs-for-arabic-sites' ) ],
            'hash'              => [ 'label' => __( 'Hash', 'simula-friendly-slugs-for-arabic-sites' ), 'desc' => __( 'Generate a short, unique hash from the title.', 'simula-friendly-slugs-for-arabic-sites' ) ],
            'translation'       => [ 'label' => __( 'Translation', 'simula-friendly-slugs-for-arabic-sites' ), 'desc' => __( 'Translate the title to English via the selected service.', 'simula-friendly-slugs-for-arabic-sites' ) ],
        ];
        foreach ( $methods as $key => $data ) {
            printf(
                '<label style="display:block; margin-bottom:8px;"><input type="radio" name="%s[method]" value="%s"%s> <strong>%s</strong> <span class="description" style="margin-left:8px;">%s</span></label>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                checked( $current, $key, false ),
                esc_html( $data['label'] ),
                esc_html( $data['desc'] )
            );
        }
    }

    public function field_regenerate_on_change_html() {
        $opts    = get_option( self::OPTION_KEY, [] );
        $enabled = ! empty( $opts['regenerate_on_change'] );
        printf(
            '<label><input type="checkbox" name="%1$s[regenerate_on_change]" value="1" %2$s> %3$s</label>',
            esc_attr( self::OPTION_KEY ),
            checked( $enabled, true, false ),
            esc_html__( 'When this is checked, changing the title will re-build the slug only while the current slug still matches the last plugin-generated slug.', 'simula-friendly-slugs-for-arabic-sites' )
        );
    }    

    public function field_translation_service_html() {
        $options   = get_option( self::OPTION_KEY, [] );
        $providers = self::get_translation_providers_definitions();
        $current   = $this->get_selected_translation_service( $options, $providers );

        foreach ( $providers as $key => $data ) {
            $description = $data['description'] ?? '';
            printf(
                '<label style="display:block; margin-bottom:8px;"><input type="radio" name="%s[translation_service]" value="%s"%s> <strong>%s</strong> <span class="description" style="margin-left:8px;">%s</span></label>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                checked( $current, $key, false ),
                esc_html( $data['label'] ),
                esc_html( $description )
            );
        }
    }

    /**
     * Render provider-defined settings for the currently selected provider.
     */
    public function field_translation_provider_settings_html() {
        $options   = get_option( self::OPTION_KEY, [] );
        $providers = self::get_translation_providers_definitions();
        $service   = $this->get_selected_translation_service( $options, $providers );

        if ( empty( $providers ) || '' === $service || ! isset( $providers[ $service ] ) ) {
            echo '<p class="description">' . esc_html__( 'No provider settings are available.', 'simula-friendly-slugs-for-arabic-sites' ) . '</p>';
            return;
        }

        echo '<div id="simula-translation-provider-fields">';

        foreach ( $providers as $provider_key => $provider_definition ) {
            $fields = $provider_definition['fields'] ?? [];
            $is_active = $provider_key === $service;

            printf(
                '<div class="simula-provider-fields-group" data-provider="%1$s"%2$s>',
                esc_attr( $provider_key ),
                $is_active ? '' : ' style="display:none;"'
            );

            if ( empty( $fields ) ) {
                echo '<p class="description">' . esc_html__( 'This provider does not require additional settings.', 'simula-friendly-slugs-for-arabic-sites' ) . '</p>';
                echo '</div>';
                continue;
            }

            foreach ( $fields as $field ) {
                $field_name = $this->build_option_input_name( $field['option_path'] );
                $field_id   = $this->build_provider_field_id( $provider_key, $field['option_path'] );
                $field_type = $field['type'];
                $value      = $this->get_nested_option_value( $options, $field['option_path'] );

                if ( ! is_scalar( $value ) ) {
                    $value = '';
                }

                echo '<div style="margin-bottom:12px;">';
                printf(
                    '<label for="%1$s" style="display:block; font-weight:600; margin-bottom:4px;">%2$s</label>',
                    esc_attr( $field_id ),
                    esc_html( $field['label'] )
                );

                switch ( $field_type ) {
                    case 'url':
                        printf(
                            '<input id="%1$s" type="url" name="%2$s" value="%3$s" class="regular-text">',
                            esc_attr( $field_id ),
                            esc_attr( $field_name ),
                            esc_attr( (string) $value )
                        );
                        break;

                    case 'api_key':
                        printf(
                            '<input id="%1$s" type="password" name="%2$s" value="" class="regular-text" autocomplete="new-password" spellcheck="false">',
                            esc_attr( $field_id ),
                            esc_attr( $field_name )
                        );
                        break;

                    case 'text':
                    default:
                        printf(
                            '<input id="%1$s" type="text" name="%2$s" value="%3$s" class="regular-text">',
                            esc_attr( $field_id ),
                            esc_attr( $field_name ),
                            esc_attr( (string) $value )
                        );
                        break;
                }

                if ( ! empty( $field['description'] ) ) {
                    printf(
                        '<p class="description">%s</p>',
                        esc_html( $field['description'] )
                    );
                }

                if ( 'api_key' === $field_type ) {
                    echo '<p class="description">' . esc_html__( 'Stored keys stay unchanged when this field is left blank. Enter a new value only when you want to replace the existing key.', 'simula-friendly-slugs-for-arabic-sites' ) . '</p>';
                }

                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Resolve the selected translation service against known provider definitions.
     *
     * @param array $options
     * @param array $providers
     * @return string
     */
    private function get_selected_translation_service( array $options, array $providers ): string {
        $keys    = array_keys( $providers );
        $default = in_array( 'google', $keys, true ) ? 'google' : ( $keys[0] ?? '' );
        $current = $options['translation_service'] ?? $default;

        return isset( $providers[ $current ] ) ? $current : $default;
    }

    /**
     * Infer the validation payload key for a provider field.
     *
     * @param string $type
     * @param array  $option_path
     * @return string
     */
    private function infer_provider_field_validation_key( string $type, array $option_path ): string {
        $last_segment = (string) end( $option_path );

        if ( 'api_key' === $type ) {
            return 'key';
        }

        if ( 'url' === $type && 'custom_api_endpoint' === $last_segment ) {
            return 'endpoint';
        }

        return sanitize_key( $last_segment );
    }

    /**
     * Build the selected provider validation payload from declared fields.
     *
     * @param array  $input
     * @param array  $provider_definition
     * @param string $service
     * @return array
     */
    private function build_provider_validation_payload( array $input, array $provider_definition, string $service ): array {
        $payload = [];
        $fields = $provider_definition['fields'] ?? [];
        $previous = get_option( self::OPTION_KEY, [] );
        if ( is_wp_error( $previous ) || ! is_array( $previous ) ) {
            $previous = [];
        }

        if ( is_array( $fields ) ) {
            foreach ( $fields as $field ) {
                $validation_key = $field['validation_key'] ?? '';
                $option_path = $field['option_path'] ?? [];

                if ( '' === $validation_key || ! is_array( $option_path ) || empty( $option_path ) ) {
                    continue;
                }

                $value = $this->get_nested_option_value( $input, $option_path );
                if ( 'api_key' === ( $field['type'] ?? '' ) && '' === ( is_scalar( $value ) ? trim( (string) $value ) : '' ) ) {
                    $value = $this->get_nested_option_value( $previous, $option_path );
                }
                $payload[ $validation_key ] = is_scalar( $value ) ? (string) $value : '';
            }
        }

        if ( ! array_key_exists( 'key', $payload ) ) {
            $value = $this->get_nested_option_value( $input, [ 'api_keys', $service ] );
            if ( '' === ( is_scalar( $value ) ? trim( (string) $value ) : '' ) ) {
                $value = $this->get_nested_option_value( $previous, [ 'api_keys', $service ] );
            }
            $payload['key'] = is_scalar( $value ) ? (string) $value : '';
        }

        if ( ! array_key_exists( 'endpoint', $payload ) ) {
            $value = $this->get_nested_option_value( $input, [ 'custom_api_endpoint' ] );
            $payload['endpoint'] = is_scalar( $value ) ? (string) $value : '';
        }

        return $payload;
    }

    /**
     * Apply provider validation results back into the stored options array.
     *
     * @param array        $valid
     * @param array        $provider_definition
     * @param string       $service
     * @param string|array $result
     * @param array        $fallback_payload
     * @return void
     */
    private function apply_provider_validation_result( array &$valid, array $provider_definition, string $service, $result, array $fallback_payload ): void {
        $fields = $provider_definition['fields'] ?? [];
        $result_map = is_array( $result ) ? $result : [];
        $previous = get_option( self::OPTION_KEY, [] );
        if ( is_wp_error( $previous ) || ! is_array( $previous ) ) {
            $previous = [];
        }

        if ( ! is_array( $fields ) || empty( $fields ) ) {
            $valid['api_keys'][ $service ] = sanitize_text_field( is_string( $result ) ? $result : (string) ( $fallback_payload['key'] ?? '' ) );
            if ( isset( $fallback_payload['endpoint'] ) ) {
                $valid['custom_api_endpoint'] = esc_url_raw( (string) $fallback_payload['endpoint'] );
            }
            return;
        }

        foreach ( $fields as $index => $field ) {
            $validation_key = $field['validation_key'] ?? '';
            $option_path = $field['option_path'] ?? [];

            if ( '' === $validation_key || ! is_array( $option_path ) || empty( $option_path ) ) {
                continue;
            }

            if ( is_string( $result ) && 0 === $index ) {
                $value = $result;
            } elseif ( array_key_exists( $validation_key, $result_map ) ) {
                $value = $result_map[ $validation_key ];
            } elseif ( array_key_exists( $validation_key, $fallback_payload ) ) {
                $value = $fallback_payload[ $validation_key ];
            } else {
                $value = $this->get_nested_option_value( $valid, $option_path );
            }

            if ( 'api_key' === ( $field['type'] ?? '' ) && '' === ( is_scalar( $value ) ? trim( (string) $value ) : '' ) ) {
                $value = $this->get_nested_option_value( $previous, $option_path );
            }

            $this->set_nested_option_value(
                $valid,
                $option_path,
                $this->sanitize_provider_field_value( $field['type'] ?? 'text', is_scalar( $value ) ? (string) $value : '' )
            );
        }
    }

    /**
     * Resolve a nested option value from an array path.
     *
     * @param array $options
     * @param array $path
     * @return mixed
     */
    private function get_nested_option_value( array $options, array $path ) {
        $value = $options;

        foreach ( $path as $segment ) {
            if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
                return '';
            }

            $value = $value[ $segment ];
        }

        return $value;
    }

    /**
     * Persist a nested option value into an array path.
     *
     * @param array  $options
     * @param array  $path
     * @param string $value
     * @return void
     */
    private function set_nested_option_value( array &$options, array $path, string $value ): void {
        if ( empty( $path ) ) {
            return;
        }

        $current = &$options;
        $last_index = count( $path ) - 1;

        foreach ( $path as $index => $segment ) {
            if ( $index === $last_index ) {
                $current[ $segment ] = $value;
                return;
            }

            if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
                $current[ $segment ] = [];
            }

            $current = &$current[ $segment ];
        }
    }

    /**
     * Sanitize a provider field value by declared type.
     *
     * @param string $type
     * @param string $value
     * @return string
     */
    private function sanitize_provider_field_value( string $type, string $value ): string {
        switch ( $type ) {
            case 'url':
                return esc_url_raw( $value );

            case 'api_key':
            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Build an option input name from an array path.
     *
     * @param array $path
     * @return string
     */
    private function build_option_input_name( array $path ): string {
        $name = self::OPTION_KEY;

        foreach ( $path as $segment ) {
            $name .= '[' . $segment . ']';
        }

        return $name;
    }

    /**
     * Build a deterministic input ID for a provider field.
     *
     * @param string $service
     * @param array  $path
     * @return string
     */
    private function build_provider_field_id( string $service, array $path ): string {
        $parts = array_merge( [ self::OPTION_KEY, $service ], $path );
        $parts = array_map(
            static function ( $part ) {
                return sanitize_html_class( (string) $part );
            },
            $parts
        );

        return implode( '-', array_filter( $parts, 'strlen' ) );
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
                __( 'You do not have permission to edit these settings.', 'simula-friendly-slugs-for-arabic-sites' ),
                'error'
            );
            return $previous;
        }

        $valid = [];

        $previous_api_keys = [];
        if ( isset( $previous['api_keys'] ) && is_array( $previous['api_keys'] ) ) {
            $previous_api_keys = $previous['api_keys'];
        }

        $valid['api_keys'] = $previous_api_keys;
        if ( isset( $previous['custom_api_endpoint'] ) ) {
            $valid['custom_api_endpoint'] = $previous['custom_api_endpoint'];
        }

        // 1) Method
        $submitted_method = $this->normalize_method_value( $input['method'] ?? '' );
        $previous_method  = $this->normalize_method_value( $previous['method'] ?? 'none' );
        $valid['method']  = isset( $input['method'] ) ? $submitted_method : $previous_method;

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
                    __( 'Please choose a valid Translation Service.', 'simula-friendly-slugs-for-arabic-sites' ),
                    'error'
                );
                return $previous;
            }
            $valid['translation_service'] = $service;

            // Validate only the selected provider; preserve saved settings for all others.
            $selected_provider  = $definitions[ $service ];
            $validation_payload = $this->build_provider_validation_payload( $input, $selected_provider, $service );

            if ( isset( $this->providers[ $service ] ) && method_exists( $this->providers[ $service ], 'validate_settings' ) ) {
                $result = $this->providers[ $service ]->validate_settings( $validation_payload );
                if ( is_wp_error( $result ) ) {
                    add_settings_error(
                        self::OPTION_KEY,
                        "{$service}_invalid",
                        $result->get_error_message(),
                        'error'
                    );
                    return $previous;
                }

                $this->apply_provider_validation_result( $valid, $selected_provider, $service, $result, $validation_payload );
            } else {
                // If a selected provider has no runtime validator, store its submitted values safely.
                $this->apply_provider_validation_result( $valid, $selected_provider, $service, $validation_payload, $validation_payload );
            }
        }

        // 3) Preserve existing translation settings when *not* in translation mode
        if ( 'translation' !== $valid['method'] ) {
            if ( isset( $previous['translation_service'] ) ) {
                $valid['translation_service'] = $previous['translation_service'];
            }
        }

        $valid['regenerate_on_change'] = ! empty( $input['regenerate_on_change'] ) ? 1 : 0;

        return $valid;
    }

    /** Render settings page */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Simula Friendly Slugs Settings', 'simula-friendly-slugs-for-arabic-sites' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'simula-friendly-slugs-for-arabic-sites' );
                do_settings_sections( 'simula-friendly-slugs-for-arabic-sites' );
                submit_button();
                ?>
            </form>
        </div>
        <script>
        (function() {
            var radios = document.querySelectorAll('input[name="<?php echo esc_js( self::OPTION_KEY ); ?>[translation_service]"]');
            var groups = document.querySelectorAll('.simula-provider-fields-group');

            function updateProviderFields() {
                var active = '';

                radios.forEach(function(radio) {
                    if (radio.checked) {
                        active = radio.value;
                    }
                });

                groups.forEach(function(group) {
                    group.style.display = group.getAttribute('data-provider') === active ? '' : 'none';
                });
            }

            if (!radios.length || !groups.length) {
                return;
            }

            radios.forEach(function(radio) {
                radio.addEventListener('change', updateProviderFields);
            });

            updateProviderFields();
        }());
        </script>
        <?php
    }

    public function generate_friendly_slug( $override_slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
        $post_id = (int) $post_ID;

        if ( $this->skip_unique_override_globally ) {
            return $override_slug;
        }

        if ( $this->should_skip_unique_override_for_post_id( $post_id ) ) {
            return $override_slug;
        }

        // Ownership-first guard: never replace a manually-owned slug automatically.
        if ( $post_id > 0 && $this->get_manual_slug_lock( $post_id ) ) {
            return $override_slug;
        }

        $method = $this->normalize_method_value( get_option( self::OPTION_KEY, [] )['method'] ?? 'none' );
        if ( 'none' === $method  ) {
            return $override_slug;
        }

        $post   = get_post( $post_id );
        if ( ! $post instanceof WP_Post ) {
            return $override_slug;
        }

        // For existing posts, never auto-replace established slugs in uniqueness stage.
        if ( '' !== $this->normalize_slug_value( $post->post_name ) ) {
            return $override_slug;
        }

        // Only apply if title contains Arabic characters
        if ( ! preg_match( '/\p{Arabic}/u', $post->post_title ) ) {
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
     * Force a friendly slug into the post_name on save,
     * including for draft statuses.
     *
     * @param array $data    Sanitized post data about to be inserted.
     * @param array $postarr Raw $_POST data for the post.
     * @return array Modified $data with our custom slug.
     */
    public function maybe_generate_slug_on_save( array $data, array $postarr ): array {
        $opts = get_option( self::OPTION_KEY, [] );
        $method = $this->normalize_method_value( $opts['method'] ?? 'none' );
        $post_id = $this->resolve_post_id_from_save_payload( $data, $postarr );
        $regenerate_on_change = ! empty( $opts['regenerate_on_change'] );

        // Never mutate ownership state or slug in autosave/revision/auto-draft contexts.
        if ( 'auto-draft' === $data['post_status'] || $this->is_autosave_or_revision_context( $post_id, $postarr ) ) {
            return $data;
        }

        $ownership_state = $this->get_slug_ownership_state( $post_id );
        if ( ! empty( $ownership_state['manual_lock'] ) ) {
            return $data;
        }

        if ( 'none' === $method ) {
            return $data;
        }

        // Only run if we have an Arabic title.
        if ( empty( $data['post_title'] ) || ! preg_match( '/\p{Arabic}/u', $data['post_title'] ) ) {
            return $data;
        }

        $incoming_slug = $this->normalize_slug_value( $postarr['post_name'] ?? ( $data['post_name'] ?? '' ) );
        $current_db_slug = '';
        if ( $post_id > 0 ) {
            $existing = get_post( $post_id );
            if ( $existing instanceof WP_Post ) {
                $current_db_slug = $this->normalize_slug_value( $existing->post_name );
            }
        }
        $last_generated_slug = $ownership_state['last_generated_slug'] ?? '';

        // Build converter method name, e.g. "convert_translation"
        $converter = "convert_{$method}";
        if ( ! is_callable( [ $this, $converter ] ) ) {
            return $data;
        }

        // Perform conversion on the raw title
        $new_slug_source = $this->$converter( $data['post_title'] );

        // Sanitize into a slug
        // Use $data['post_name'] as fallback (in case WP already wrote something)
        $generated_slug = sanitize_title( $new_slug_source, $data['post_name'], 'save' );
        if ( '' === $generated_slug ) {
            return $data;
        }

        if ( $this->is_manual_slug_edit_detected( $incoming_slug, $current_db_slug, $last_generated_slug, $generated_slug ) ) {
            if ( $post_id > 0 ) {
                $this->set_manual_slug_lock( $post_id, true );
                $this->mark_skip_unique_override_for_post_id( $post_id );
                if ( '' === $last_generated_slug ) {
                    $this->set_last_generated_slug( $post_id, $generated_slug );
                }
            } else {
                $this->skip_unique_override_globally = true;
                $this->queue_pending_slug_ownership_meta( true, '' !== $last_generated_slug ? $last_generated_slug : $generated_slug );
            }
            $data['post_name'] = $incoming_slug;
            return $data;
        }

        if ( ! $this->should_apply_generated_slug_on_save(
            $post_id,
            $incoming_slug,
            $current_db_slug,
            $last_generated_slug,
            $data['post_title'],
            $regenerate_on_change
        ) ) {
            return $data;
        }

        $data['post_name'] = $generated_slug;

        // Existing posts can persist tracking meta immediately; new-post persistence is handled later.
        if ( $post_id > 0 ) {
            $this->set_last_generated_slug( $post_id, $generated_slug );
        } else {
            $this->queue_pending_slug_ownership_meta( false, $generated_slug );
        }

        return $data;
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

    /**
     * Hash converter: generate a short, 8-character hash of the title.
     *
     * @param string $text
     * @return string
     */
    private function convert_hash( $text ) {
        // Create an MD5-based hash, then take first 8 chars
        // You could also use crc32() or any other algorithm.
        $hash = substr( md5( $text ), 0, 8 );
        // Ensure it always starts with a letter (optional):
        if ( preg_match( '/^[0-9]/', $hash ) ) {
            $hash = 'h' . substr( $hash, 0, 7 );
        }
        return $hash;
    }
}

// Initialize plugin
Simula_Friendly_Slugs_For_Arabic_Sites::instance();


/**
 * On activate, ensure we have a default method of “none”
 */
register_activation_hook( __FILE__, function() {
    // don’t overwrite if they’ve already got settings
    if ( false === get_option( Simula_Friendly_Slugs_For_Arabic_Sites::OPTION_KEY, false ) ) {
        add_option( Simula_Friendly_Slugs_For_Arabic_Sites::OPTION_KEY, [ 'method' => 'none' ] );
    }
} );
