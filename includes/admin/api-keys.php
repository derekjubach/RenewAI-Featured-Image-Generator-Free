<?php

namespace RenewAI\FeaturedImageGenerator\Admin;

use RenewAI\FeaturedImageGenerator\Utils\Encryption;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;
use function RenewAI\FeaturedImageGenerator\renewai_ig1;
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}
class APIKeys {
    private static $instance = null;

    private $encryption;

    /**
     * Get the singleton instance of this class.
     *
     * @return APIKeys The singleton instance
     */
    public static function get_instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Sets up encryption and hooks for admin menu and settings.
     */
    private function __construct() {
        $this->encryption = Encryption::get_instance();
        add_action( 'admin_menu', [$this, 'add_submenu_page'] );
        add_action( 'admin_init', [$this, 'register_settings'] );
        add_action( 'admin_notices', [$this, 'admin_notices'] );
    }

    /**
     * Add API Keys submenu page to the main plugin menu.
     */
    public function add_submenu_page() : void {
        add_submenu_page(
            'renewai-ig1-settings',
            esc_html__( 'API Keys', 'renewai-featured-image-generator' ),
            esc_html__( 'API Keys', 'renewai-featured-image-generator' ),
            'manage_options',
            'renewai-ig1-api-keys',
            [$this, 'render_page']
        );
    }

    /**
     * Register API key settings and sections.
     */
    public function register_settings() : void {
        // Register OpenAI API key setting
        register_setting( 'renewai_ig1_api_keys', 'renewai_ig1_openai_api_key', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
        ] );
        // Add settings section
        add_settings_section(
            'renewai_ig1_api_keys_section',
            esc_html__( 'API Keys Configuration', 'renewai-featured-image-generator' ),
            [$this, 'render_section'],
            'renewai-ig1-api-keys'
        );
        // Add settings fields
        add_settings_field(
            'renewai_ig1_openai_api_key',
            esc_html__( 'OpenAI API Key', 'renewai-featured-image-generator' ),
            [$this, 'render_openai_api_key_field'],
            'renewai-ig1-api-keys',
            'renewai_ig1_api_keys_section'
        );
    }

    /**
     * Render the API Keys settings page.
     */
    public function render_page() : void {
        ?>
    <div class="wrap renewai-ig1-wrap">
      <?php 
        include RENEWAI_IG1_PLUGIN_DIR . 'includes/header.php';
        ?>
      <form action="options.php" method="post" class="renewai-ig1-settings-form">
        <h1><?php 
        esc_html_e( 'API Keys', 'renewai-featured-image-generator' );
        ?></h1>
        <?php 
        settings_fields( 'renewai_ig1_api_keys' );
        do_settings_sections( 'renewai-ig1-api-keys' );
        submit_button();
        ?>
      </form>
    </div>
  <?php 
    }

    /**
     * Render the API Keys section description.
     */
    public function render_section() : void {
        ?>
    <p><?php 
        esc_html_e( 'Configure your API keys for the image generation services.', 'renewai-featured-image-generator' );
        ?></p>
  <?php 
    }

    /**
     * Render the OpenAI API key field.
     */
    public function render_openai_api_key_field() : void {
        $value = $this->get_api_key( 'openai' );
        $display_value = ( !empty( $value ) ? str_repeat( '•', 32 ) : '' );
        ?>
    <input type="password"
      id="renewai_ig1_openai_api_key"
      name="renewai_ig1_openai_api_key"
      value="<?php 
        echo esc_attr( $display_value );
        ?>"
      class="regular-text">
    <button type="button" class="button" onclick="toggleApiKeyField('openai')">
      <?php 
        esc_html_e( 'Change API Key', 'renewai-featured-image-generator' );
        ?>
    </button>
    <p class="description bottom-label">
      <?php 
        printf( 
            /* translators: %s: OpenAI pricing URL */
            esc_html__( 'Note: Before using an OpenAI model, please review the API pricing details to understand the associated usage costs. %s', 'renewai-featured-image-generator' ),
            '<br><a href="' . esc_url( 'https://openai.com/api/pricing/' ) . '" target="_blank">' . esc_html__( 'Learn more', 'renewai-featured-image-generator' ) . '</a>.'
         );
        ?>
    </p>
  <?php 
    }

    /**
     * Render the Flux API key field.
     * Only available in premium version.
     */
    public function render_flux_api_key_field() : void {
        return;
        $value = $this->get_api_key( 'flux' );
        $display_value = ( !empty( $value ) ? str_repeat( '•', 32 ) : '' );
        ?>
    <input type="password"
      id="renewai_ig1_flux_api_key"
      name="renewai_ig1_flux_api_key"
      value="<?php 
        echo esc_attr( $display_value );
        ?>"
      class="regular-text">
    <button type="button" class="button" onclick="toggleApiKeyField('flux')">
      <?php 
        esc_html_e( 'Change API Key', 'renewai-featured-image-generator' );
        ?>
    </button>
    <p class="description bottom-label">
      <?php 
        printf( 
            /* translators: %s: Flux pricing URL */
            esc_html__( 'Note: Before using Flux models, please review the API pricing details to understand the associated usage costs. %s', 'renewai-featured-image-generator' ),
            '<br><a href="' . esc_url( 'https://docs.bfl.ml/pricing/' ) . '" target="_blank">' . esc_html__( 'Learn more', 'renewai-featured-image-generator' ) . '</a>. '
         );
        printf( 
            /* translators: %s: Flux account URL */
            esc_html__( 'Need a Flux API key? %s', 'renewai-featured-image-generator' ),
            '<a href="' . esc_url( 'https://docs.bfl.ml/quick_start/create_account/' ) . '" target="_blank">' . esc_html__( 'Create an account', 'renewai-featured-image-generator' ) . '</a>.'
         );
        ?>
    </p>
    <?php 
    }

    /**
     * Display admin notices for settings updates.
     */
    public function admin_notices() : void {
        if ( isset( $_GET['settings-updated'] ) && isset( $_GET['page'] ) && $_GET['page'] === 'renewai-ig1-api-keys' ) {
            ?>
      <div class="notice notice-success is-dismissible">
        <p><?php 
            esc_html_e( 'API Keys updated successfully.', 'renewai-featured-image-generator' );
            ?></p>
      </div>
<?php 
        }
    }

    /**
     * Sanitize and encrypt API key values.
     *
     * @param string $key The API key to sanitize
     * @return string The sanitized and encrypted API key
     */
    public function sanitize_api_key( $key ) {
        $option_name = $this->get_current_option_name();
        $current_filter = current_filter();
        renewai_ig1_log( "Sanitizing API key - Filter: {$current_filter}, Option: {$option_name}" );
        $key = sanitize_text_field( $key );
        // If key is empty or just bullet points, return existing value
        if ( empty( $key ) || $key === str_repeat( '•', 32 ) ) {
            renewai_ig1_log( "Returning existing API key (no changes) - Filter: {$current_filter}" );
            return get_option( $option_name );
        }
        // Check if the key is already encrypted (base64 encoded)
        if ( base64_decode( $key, true ) !== false && strlen( $key ) > 100 ) {
            renewai_ig1_log( "Key appears to be already encrypted - skipping encryption - Filter: {$current_filter}" );
            return $key;
        }
        // Validate key format based on provider
        if ( strpos( $option_name, 'openai' ) !== false ) {
            if ( !preg_match( '/^sk-[A-Za-z0-9]{48}$/', $key ) ) {
                renewai_ig1_log( "Invalid OpenAI API key format", 'error' );
            }
        } elseif ( strpos( $option_name, 'flux' ) !== false ) {
            if ( empty( $key ) ) {
                renewai_ig1_log( "Empty Flux API key", 'error' );
            }
        }
        // Encrypt the key
        $encrypted_key = $this->encryption->encrypt( $key );
        renewai_ig1_log( "API key encrypted: " . (( empty( $encrypted_key ) ? 'failed' : 'success' )) . " (Filter: {$current_filter})" );
        return $encrypted_key;
    }

    /**
     * Get decrypted API key for specified provider.
     *
     * @param string $provider The API provider (openai or flux)
     * @return string The decrypted API key
     */
    public function get_api_key( $provider ) {
        renewai_ig1_log( "Retrieving API key for provider: {$provider}" );
        $encrypted_key = get_option( "renewai_ig1_{$provider}_api_key", '' );
        $decrypted_key = $this->encryption->decrypt( $encrypted_key );
        renewai_ig1_log( "API key retrieved for {$provider}: " . (( empty( $decrypted_key ) ? 'empty' : 'not empty' )) );
        return $decrypted_key;
    }

    /**
     * Get the current option name being processed.
     *
     * @return string The option name
     */
    private function get_current_option_name() {
        $current_filter = current_filter();
        if ( $current_filter === 'sanitize_option_renewai_ig1_openai_api_key' ) {
            return 'renewai_ig1_openai_api_key';
        } elseif ( $current_filter === 'sanitize_option_renewai_ig1_flux_api_key' ) {
            return 'renewai_ig1_flux_api_key';
        }
        return '';
    }

}
