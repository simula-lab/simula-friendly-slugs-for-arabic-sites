<?php
/**
 * Plugin Name: Simula Friendly Slugs for Arabic Sites
 * Plugin URI: https://github.com/simula-lab/simula-friendly-slugs-for-arabic-sites
 * Description: Automatically generate friendly slugs for Arabic posts/pages via transliteration, 3arabizi or translation.
 * Version: 1.2.2
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

    /** Setup WordPress hooks */
    private function __construct() {
        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'setup_providers' ], 11 );
        
        // Admin settings
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

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
            if ( class_exists( $class_name ) ) {
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
     * Returns the list of translation providers,
     * filtered via the single canonical filter.
     *
     * @return array<string,array{label:string,class:string}>
     */
    private static function get_translation_providers_definitions(): array {
        return (array) apply_filters(
            'simula_friendly_slugs_for_arabic_sites_translation_providers',
            [
                'google' => [
                    'label' => __( 'Google Translate', 'simula-friendly-slugs-for-arabic-sites' ),
                    'class' => 'Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Google',
                ],
                // you can add 'custom' here by default if you wish
                // ],
                // 'custom' => [
                //     'label' => __( 'Custom API',      'simula-friendly-slugs-for-arabic-sites' ),
                //     'class' => 'Simula_Friendly_Slugs_For_Arabic_Sites_Provider_Custom',
                // ]
            ]
        );
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
    
        // Field: API key input for Google
        add_settings_field(
            'google_api_key',
            __( 'Google API Key', 'simula-friendly-slugs-for-arabic-sites' ),
            [ $this, 'field_api_key_html' ],
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
        $current = $options['method'] ?? 'none';
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
        $current   = $options['translation_service'] ?? 'google';
        $providers = [
            'google' => [ 'label' => __( 'Google Translate', 'simula-friendly-slugs-for-arabic-sites' ), 'desc' => __( 'Use Google Translate API.', 'simula-friendly-slugs-for-arabic-sites' ) ],
            // Add other providers here with 'key' => [ label, desc ]
        ];
        foreach ( $providers as $key => $data ) {
            printf(
                '<label style="display:block; margin-bottom:8px;"><input type="radio" name="%s[translation_service]" value="%s"%s> <strong>%s</strong> <span class="description" style="margin-left:8px;">%s</span></label>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                checked( $current, $key, false ),
                esc_html( $data['label'] ),
                esc_html( $data['desc'] )
            );
        }
    }

    /**
     * Render API key input
     */
    public function field_api_key_html() {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = $options['api_keys']['google'] ?? '';
        printf(
            '<input type="text" name="%s[api_keys][google]" value="%s" class="regular-text">',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );
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

        $valid   = [];
        $methods = [ 'wp_transliteration', 'custom_transliteration', 'arabizi', 'translation', 'hash', 'none' ];

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
                    __( 'Please choose a valid Translation Service.', 'simula-friendly-slugs-for-arabic-sites' ),
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
                        $valid['api_keys'][ $s ] = sanitize_text_field( $result['key'] ?? '' );
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

        // 3) Preserve existing translation settings when *not* in translation mode
        if ( 'translation' !== $valid['method'] ) {
            if ( isset( $previous['translation_service'] ) ) {
                $valid['translation_service'] = $previous['translation_service'];
            }
            if ( isset( $previous['api_keys'] ) ) {
                $valid['api_keys'] = $previous['api_keys'];
            }
            if ( isset( $previous['custom_api_endpoint'] ) ) {
                $valid['custom_api_endpoint'] = $previous['custom_api_endpoint'];
            }
        }

        $valid['regenerate_on_change'] = ! empty( $input['regenerate_on_change'] ) ? 1 : 0;

        return $valid;
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
            <h1><?php esc_html_e( 'Simula Friendly Slugs Settings', 'simula-friendly-slugs-for-arabic-sites' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'simula-friendly-slugs-for-arabic-sites' );
                do_settings_sections( 'simula-friendly-slugs-for-arabic-sites' );
                submit_button();
                ?>
            </form>
        </div>
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

        $method = ( get_option( self::OPTION_KEY, [] )['method'] ?? 'none' );
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
        $method = $opts['method'] ?? 'none';
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
