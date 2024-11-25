<?php

/**
 * Plugin Name: RenewAI Featured Image Generator
 * Description: Generates featured images for posts using OpenAI and Flux API's.
 * Version: 2.0.2
 * Text Domain: renewai-featured-image-generator
 * Author: Derek Jubach
 * Author URI:  https://github.com/derekjubach/RenewAI-Featured-Image-Creator-Free
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace RenewAI\FeaturedImageGenerator;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}
// Define plugin constants.
define('RENEWAI_IG1_VERSION', '1.0.2');
define('RENEWAI_IG1_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RENEWAI_IG1_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RENEWAI_IG1_LOG_FILE', RENEWAI_IG1_PLUGIN_DIR . 'renewai-ig1-debug.log');
define('RENEWAI_IG1_NEWSLETTER_ADDR', 'success@perpetuaiconsult.com');
if (!function_exists('RenewAI\\FeaturedImageGenerator\\renewai_ig1')) {
  // Create a helper function for easy SDK access.
  function renewai_ig1()
  {
    global $renewai_ig1;
    if (!isset($renewai_ig1)) {
      // Include Freemius SDK.
      require_once dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/start.php';
      $renewai_ig1 = fs_dynamic_init(array(
        'id'             => '16973',
        'slug'           => 'renewai-featured-image-generator',
        'type'           => 'plugin',
        'public_key'     => 'pk_e9b8e80a22e12b8863e93c15c6894',
        'is_premium'     => false,
        'premium_suffix' => 'Premium',
        'has_addons'     => false,
        'has_paid_plans' => true,
        'menu'           => array(
          'slug'    => 'renewai-ig1-settings',
          'support' => false,
        ),
        'is_live'        => true,
      ));
    }
    return $renewai_ig1;
  }

  // Init Freemius.
  renewai_ig1();
  // Signal that SDK was initiated.
  do_action('renewai_ig1_loaded');
  /**
   * Log function for debugging.
   *
   * @param string $message The message to log
   * @param string $level The log level (default: 'info')
   */
  function renewai_ig1_log($message, $level = 'info')
  {
    if (get_option('renewai_ig1_debug_mode', false)) {
      $log_file = RENEWAI_IG1_LOG_FILE;
      $timestamp = current_time('mysql');
      $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
      $caller = (isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'Unknown');
      $log_message = "[{$timestamp}] [{$level}] [{$caller}] {$message}\n";
      // Set up WP_Filesystem
      global $wp_filesystem;
      if (empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
      }
      // Append to log file using WP_Filesystem
      if ($wp_filesystem->exists($log_file)) {
        $current_content = $wp_filesystem->get_contents($log_file);
        $wp_filesystem->put_contents($log_file, $current_content . $log_message, FS_CHMOD_FILE);
      } else {
        $wp_filesystem->put_contents($log_file, $log_message, FS_CHMOD_FILE);
      }
    }
  }

  /**
   * Autoloader for plugin classes.
   * Automatically loads class files based on their namespace.
   */
  spl_autoload_register(function ($class) {
    $prefix = 'RenewAI\\FeaturedImageGenerator\\';
    $base_dir = RENEWAI_IG1_PLUGIN_DIR . 'includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
      return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
      require $file;
    }
  });
  /**
   * Main plugin class.
   */
  class RenewAI_Featured_Image_Generator
  {
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return RenewAI_Featured_Image_Generator
     */
    public static function get_instance(): self
    {
      if (null === self::$instance) {
        self::$instance = new self();
      }
      return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
      $this->init();
    }

    /**
     * Initialize plugin.
     */
    private function init(): void
    {
      // First load all required files
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/utils/encryption.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/admin/settings.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/admin/api-keys.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/api/handler.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/admin/metabox.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/admin/help.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/image-generators.php';
      require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/helpers.php';
      add_action('plugins_loaded', [$this, 'load_textdomain']);
      add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
      // Initialize Settings first to set up menu structure
      \RenewAI\FeaturedImageGenerator\Admin\Settings::get_instance();
      // Then initialize APIKeys to add to existing menu
      \RenewAI\FeaturedImageGenerator\Admin\APIKeys::get_instance();
      new \RenewAI\FeaturedImageGenerator\Admin\MetaBox();
      add_filter('upload_mimes', function ($mimes) {
        // Ensure PNG and JPEG are allowed
        $mimes['jpg|jpeg|jpe'] = 'image/jpeg';
        $mimes['png'] = 'image/png';
        return $mimes;
      });
      add_filter(
        'wp_check_filetype_and_ext',
        function (
          $data,
          $file,
          $filename,
          $mimes
        ) {
          if (!empty($data['ext']) && !empty($data['type'])) {
            return $data;
          }
          $filetype = wp_check_filetype($filename, $mimes);
          if ('jpg' === $filetype['ext']) {
            $data['ext'] = 'jpg';
            $data['type'] = 'image/jpeg';
          } elseif ('png' === $filetype['ext']) {
            $data['ext'] = 'png';
            $data['type'] = 'image/png';
          }
          return $data;
        },
        10,
        4
      );
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain(): void
    {
      load_plugin_textdomain('renewai-featured-image-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page
     */
    public function enqueue_admin_scripts(string $hook): void
    {
      $screen = get_current_screen();
      // Debug log the hook and screen
      renewai_ig1_log('Current hook: ' . $hook);
      renewai_ig1_log('Current screen ID: ' . (($screen ? $screen->id : 'no screen')));
      // Enqueue styles for post editor, settings page, and help page
      if ($hook == 'post-new.php' || $hook == 'post.php' || $screen && $screen->is_block_editor() || $hook == 'toplevel_page_renewai-ig1-settings' || $hook == 'renewai-ig1_page_renewai-ig1-help' || $hook == 'renewai-ig1_page_renewai-ig1-api-keys' || strpos($hook, 'renewai-ig1') !== false) {
        wp_enqueue_style(
          'renewai-ig1-style',
          RENEWAI_IG1_PLUGIN_URL . 'assets/css/styles.css',
          [],
          RENEWAI_IG1_VERSION
        );
      }
      // Enqueue scripts for post editor pages, settings page, and API keys page
      if ($hook == 'post-new.php' || $hook == 'post.php' || $screen && $screen->is_block_editor() || $hook == 'toplevel_page_renewai-ig1-settings' || $hook == 'renewai-ig1_page_renewai-ig1-api-keys' || strpos($hook, 'renewai-ig1') !== false) {
        wp_enqueue_script(
          'renewai-ig1-script',
          RENEWAI_IG1_PLUGIN_URL . 'assets/js/app.js',
          ['jquery', 'wp-editor', 'wp-data'],
          RENEWAI_IG1_VERSION,
          true
        );
        wp_localize_script('renewai-ig1-script', 'renewai_ig1_ajax', [
          'ajax_url'            => admin_url('admin-ajax.php'),
          'nonce'               => wp_create_nonce('renewai_ig1_generate_image'),
          'no_log_file_text'    => esc_html__('No log file exists.', 'renewai-featured-image-generator'),
          'cancel_text'         => esc_html__('Cancel', 'renewai-featured-image-generator'),
          'change_api_key_text' => esc_html__('Change API Key', 'renewai-featured-image-generator'),
        ]);
      }
    }
  }

  /**
   * Handle AJAX logging request.
   */
  function renewai_ig1_ajax_log()
  {
    check_ajax_referer('renewai_ig1_generate_image', 'nonce');
    if (isset($_POST['message'])) {
      $message = sanitize_text_field(wp_unslash($_POST['message']));
    } else {
      wp_send_json_error('Message not provided');
      return;
    }
    if (isset($_POST['level'])) {
      $level = sanitize_text_field(wp_unslash($_POST['level']));
    } else {
      wp_send_json_error('Level not provided');
      return;
    }
    renewai_ig1_log($message, $level);
    wp_die();
  }

  /**
   * Handle AJAX request to view log file.
   */
  function renewai_ig1_view_log()
  {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'renewai-featured-image-generator'));
    }
    $log_file = RENEWAI_IG1_LOG_FILE;
    // Set up WP_Filesystem
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      WP_Filesystem();
    }
    if ($wp_filesystem->exists($log_file)) {
      header('Content-Type: text/plain');
      header('Content-Disposition: attachment; filename="renewai-featured-image-generator-log.txt"');
      echo esc_html($wp_filesystem->get_contents($log_file));
      exit;
    } else {
      echo esc_html__('No log file exists. Enable debug mode to create one.', 'renewai-featured-image-generator');
    }
    wp_die();
  }

  /**
   * Handle AJAX request to delete log file.
   */
  function renewai_ig1_delete_log()
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }
    if (!check_ajax_referer('renewai_ig1_generate_image', 'nonce', false)) {
      wp_send_json_error('Invalid nonce');
      return;
    }
    $log_file = RENEWAI_IG1_LOG_FILE;
    // Set up WP_Filesystem
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      WP_Filesystem();
    }
    if (!$wp_filesystem->exists($log_file)) {
      wp_send_json_error('Log file does not exist');
      return;
    }
    if (!$wp_filesystem->is_writable($log_file)) {
      wp_send_json_error('Log file is not writable');
      return;
    }
    $deleted = $wp_filesystem->delete($log_file);
    if ($deleted) {
      // Disable debug mode
      update_option('renewai_ig1_debug_mode', false);
      renewai_ig1_log('Log file deleted successfully and debug mode disabled');
      wp_send_json_success('Log file deleted successfully and debug mode disabled');
    } else {
      renewai_ig1_log('Failed to delete log file', 'error');
      wp_send_json_error('Failed to delete log file');
    }
  }

  /**
   * Set default prompts if they don't exist
   * Don't overwrite user prompts if they exist
   */
  register_activation_hook(__FILE__, 'RenewAI\\FeaturedImageGenerator\\plugin_activate');
  function plugin_activate()
  {
    // Set default prompts if they don't exist
    if (!get_option('renewai_ig1_gpt_system_prompt')) {
      $default_system_prompt = 'You are a skilled image prompt engineer. Your task is to create detailed, vivid image prompts from blog content. Focus on key visual elements, artistic style, and mood. Include specific details about composition, lighting, and important elements. Keep prompts under 1000 characters.';
      update_option('renewai_ig1_gpt_system_prompt', $default_system_prompt);
    }
    if (!get_option('renewai_ig1_gpt_user_prompt')) {
      $default_user_prompt = 'Create a detailed image prompt from this blog content. Include: 1) Main subject and visual elements 2) Composition and framing 3) Style and mood 4) Lighting and colors. Be specific but concise.';
      update_option('renewai_ig1_gpt_user_prompt', $default_user_prompt);
    }
  }

  // Add action hooks for AJAX functions
  add_action('wp_ajax_renewai_ig1_log', 'RenewAI\\FeaturedImageGenerator\\renewai_ig1_ajax_log');
  add_action('wp_ajax_renewai_ig1_view_log', 'RenewAI\\FeaturedImageGenerator\\renewai_ig1_view_log');
  add_action('wp_ajax_renewai_ig1_delete_log', 'RenewAI\\FeaturedImageGenerator\\renewai_ig1_delete_log');
  // Initialize the plugin.
  RenewAI_Featured_Image_Generator::get_instance();
}
