<?php

namespace RenewAI\FeaturedImageGenerator\Admin;

use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;
use function RenewAI\FeaturedImageGenerator\renewai_ig1;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_get_default_generator;

if (!defined('ABSPATH')) {
  exit;
  // Exit if accessed directly
}
class Settings
{
  private static $instance = null;

  private $process_newsletter = false;

  /**
   * Get the singleton instance of this class.
   *
   * @return Settings The singleton instance
   */
  public static function get_instance(): self
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor. Sets up hooks for admin menu, settings registration and notices.
   */
  private function __construct()
  {
    add_action('admin_menu', [$this, 'add_settings_page']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_notices', [$this, 'admin_notices']);
  }

  /**
   * Add settings page and submenus to WordPress admin menu.
   */
  public function add_settings_page(): void
  {
    $main_menu_slug = 'renewai-ig1-settings';
    // Add the main menu page
    $page = add_menu_page(
      esc_html__('RenewAI Featured Image Generator', 'renewai-featured-image-generator'),
      esc_html__('RenewAI Image Creator', 'renewai-featured-image-generator'),
      'manage_options',
      $main_menu_slug,
      [$this, 'render_settings_page'],
      'dashicons-format-image',
      30
    );
    // Add Settings as first submenu to avoid duplicate menu entry
    add_submenu_page(
      $main_menu_slug,
      esc_html__('Settings', 'renewai-featured-image-generator'),
      esc_html__('Settings', 'renewai-featured-image-generator'),
      'manage_options',
      $main_menu_slug,
      // Same as parent slug to make it the default page
      [$this, 'render_settings_page']
    );
    // Add Help submenu
    add_submenu_page(
      $main_menu_slug,
      esc_html__('Help', 'renewai-featured-image-generator'),
      esc_html__('Help', 'renewai-featured-image-generator'),
      'manage_options',
      'renewai-ig1-help',
      [$this, 'render_help_page']
    );
  }

  /**
   * Render the main settings page content.
   */
  public function render_settings_page(): void
  {
?>
    <div class="wrap renewai-ig1-wrap">
      <?php
      include RENEWAI_IG1_PLUGIN_DIR . 'includes/header.php';
      ?>
      <form action="options.php" method="post" class="renewai-ig1-settings-form">
        <h1><?php
            esc_html_e('Settings', 'renewai-featured-image-generator');
            ?></h1>
        <div class="renewai-ig1-settings">
          <div class="renewai-ig1-settings-section">
            <?php
            settings_fields('renewai_ig1_settings');
            do_settings_sections('renewai-ig1-settings');
            submit_button();
            ?>
          </div>
        </div>
      </form>
    </div>
  <?php
  }

  /**
   * Register all plugin settings and sections.
   */
  public function register_settings(): void
  {
    static $registered = false;
    if ($registered) {
      renewai_ig1_log('Settings already registered, skipping');
      return;
    }
    register_setting('renewai_ig1_settings', 'renewai_ig1_debug_mode', [
      'type'    => 'boolean',
      'default' => false,
    ]);
    register_setting('renewai_ig1_settings', 'renewai_ig1_gpt_system_prompt', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_textarea_field',
      'default' => '',
    ]);
    register_setting('renewai_ig1_settings', 'renewai_ig1_gpt_user_prompt', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_textarea_field',
      'default' => '',
    ]);
    register_setting('renewai_ig1_settings', 'renewai_ig1_fal_model', [
      'type' => 'string',
      'sanitize_callback' => [$this, 'sanitize_fal_model'],
      'default' => 'flux_dev',
    ]);
    register_setting('renewai_ig1_settings', 'renewai_ig1_newsletter_optin', [
      'sanitize_callback' => [$this, 'process_newsletter_settings'],
    ]);
    // Add settings section
    add_settings_section(
      'renewai_ig1_api_settings',
      esc_html__('Main Settings', 'renewai-featured-image-generator'),
      [$this, 'render_api_settings_section'],
      'renewai-ig1-settings'
    );
    add_settings_field(
      'renewai_ig1_image_generator',
      esc_html__('Select Image Generator', 'renewai-featured-image-generator'),
      [$this, 'render_image_generator_field'],
      'renewai-ig1-settings',
      'renewai_ig1_api_settings'
    );
    add_settings_field(
      'renewai_ig1_gpt_system_prompt',
      esc_html__('GPT System Prompt', 'renewai-featured-image-generator'),
      [$this, 'render_gpt_system_prompt_field'],
      'renewai-ig1-settings',
      'renewai_ig1_api_settings'
    );
    add_settings_field(
      'renewai_ig1_gpt_user_prompt',
      esc_html__('GPT User Prompt', 'renewai-featured-image-generator'),
      [$this, 'render_gpt_user_prompt_field'],
      'renewai-ig1-settings',
      'renewai_ig1_api_settings'
    );
    add_settings_field(
      'renewai_ig1_debug_mode',
      esc_html__('Enable Debugging', 'renewai-featured-image-generator'),
      [$this, 'render_debug_mode_field'],
      'renewai-ig1-settings',
      'renewai_ig1_api_settings'
    );
    if (!$this->has_completed_first_interaction()) {
      add_settings_field(
        'renewai_ig1_newsletter',
        '',
        [$this, 'render_newsletter_field'],
        'renewai-ig1-settings',
        'renewai_ig1_api_settings'
      );
    }
    $registered = true;
  }

  /**
   * Render the API settings section description.
   */
  public function render_api_settings_section(): void
  {
    // This needs to exist as it's referenced in add_settings_section()
  }

  /**
   * Render the image generator selection field.
   * Handles both free and premium versions.
   */
  public function render_image_generator_field(): void
  {
    // Retrieve the OpenAI API key
    $api_key = get_option('renewai_ig1_openai_api_key', '');
    if (empty($api_key)) {
      echo '<p class="description">' . esc_html__('No API key set. Please visit the ', 'renewai-featured-image-generator') . '<a href="' . esc_url(admin_url('admin.php?page=renewai-ig1-api-keys')) . '">' . esc_html__('API Keys page', 'renewai-featured-image-generator') . '</a>' . esc_html__(' to set your API key.', 'renewai-featured-image-generator') . '</p>';
      return;
    }
    $current_generator = get_option('renewai_ig1_image_generator', renewai_ig1_get_default_generator());
    // Free version - force DALL-E 3 and show upgrade message
    echo '<input type="hidden" name="renewai_ig1_image_generator" value="dalle">';
    echo '<div class="renewai-ig1-free-version-notice">';
    echo '<p><strong>' . esc_html__('DALL-E 3', 'renewai-featured-image-generator') . '</strong></p>';
    echo '<p class="description">' . sprintf(
      /* translators: %s: Upgrade link */
      esc_html__('The free version uses DALL-E 3 for image generation. %s to access all Flux image generation models!', 'renewai-featured-image-generator'),
      '<a href="/wp-admin/admin.php?page=renewai-ig1-settings-pricing" class="renewai-ig1-upgrade-link">' . esc_html__('Upgrade to Premium', 'renewai-featured-image-generator') . '</a>'
    ) . '</p>';
    echo '</div>';
    // Force the option to 'dalle' in free mode
    if ($current_generator !== 'dalle') {
      update_option('renewai_ig1_image_generator', 'dalle');
    }
  }

  /**
   * Render the debug mode field and log file controls.
   */
  public function render_debug_mode_field(): void
  {
    $debug_mode = get_option('renewai_ig1_debug_mode', false);
  ?>
    <input type="checkbox"
      id="renewai_ig1_debug_mode"
      name="renewai_ig1_debug_mode"
      value="1"
      <?php
      checked(1, $debug_mode);
      ?>>
    <label for="renewai_ig1_debug_mode">
      <?php
      esc_html_e('When enabled, debug information will be written to the log file.', 'renewai-featured-image-generator');
      ?>
    </label>

    <?php
    if ($debug_mode) {
    ?>
      <div class="notice notice-warning">
        <p>
          <?php
          esc_html_e('Debug mode is enabled for RenewAI Featured Image Generator. For security, disable this on live sites.', 'renewai-featured-image-generator');
          ?>
        </p>
      </div>
    <?php
    }
    ?>

    <?php
    $log_file = RENEWAI_IG1_LOG_FILE;
    // Set up WP_Filesystem
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      WP_Filesystem();
    }
    echo '<div class="renewai-ig1-log-actions">';
    if ($wp_filesystem->exists($log_file)) {
    ?>
      <p>
        <a href="<?php
                  echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=renewai_ig1_view_log'), 'renewai_ig1_view_log'));
                  ?>"
          class="button"
          target="_blank">
          <?php
          esc_html_e('View Log', 'renewai-featured-image-generator');
          ?>
        </a>
        <a href="#"
          id="renewai-ig1-delete-log"
          class="button">
          <?php
          esc_html_e('Delete Log', 'renewai-featured-image-generator');
          ?>
        </a>
      </p>
      <p>
        <?php
        printf(
          /* translators: %s: Log file size */
          esc_html__('Log file size: %s', 'renewai-featured-image-generator'),
          esc_html(size_format($wp_filesystem->size($log_file)))
        );
        ?>
      </p>
    <?php
    } else {
    ?>
      <p class="description">
        <?php
        esc_html_e('No log file exists. Enable debug mode to create one.', 'renewai-featured-image-generator');
        ?>
      </p>
    <?php
    }
    echo '</div>';
  }

  /**
   * Display admin notices for settings updates and log operations.
   */
  public function admin_notices(): void
  {
    $notices = [];
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
      $notices[] = [
        'type'    => 'success',
        'message' => esc_html__('Settings saved successfully.', 'renewai-featured-image-generator'),
      ];
    }
    if (isset($_GET['log_deleted']) && $_GET['log_deleted'] === 'true') {
      $notices[] = [
        'type'    => 'success',
        'message' => esc_html__('Log file deleted successfully. Uncheck debug mode and save settings.', 'renewai-featured-image-generator'),
      ];
    }
    if (isset($_GET['log_delete_failed']) && $_GET['log_delete_failed'] === 'true') {
      $error_message = (isset($_GET['error']) ? wp_unslash(sanitize_text_field($_GET['error'])) : '');
      $message = esc_html__('Failed to delete log file. ', 'renewai-featured-image-generator');
      if ($error_message === 'not_writable') {
        $message .= esc_html__('The log file is not writable. Please check file permissions.', 'renewai-featured-image-generator');
      } else {
        $message .= $error_message;
      }
      $notices[] = [
        'type'    => 'error',
        'message' => $message,
      ];
    }
    if (isset($_GET['log_not_found']) && $_GET['log_not_found'] === 'true') {
      $notices[] = [
        'type'    => 'warning',
        'message' => esc_html__('Log file not found. It may have been already deleted.', 'renewai-featured-image-generator'),
      ];
    }
    foreach ($notices as $notice) {
      printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($notice['type']), esc_html($notice['message']));
    }
  }

  /**
   * Get the default system prompt based on selected generator.
   *
   * @return string The default system prompt
   */
  private function get_default_system_prompt(): string
  {
    $generator = get_option('renewai_ig1_image_generator', renewai_ig1_get_default_generator());
    if ($generator === 'dalle') {
      return esc_html__('You are an AI assistant that generates image prompts optimized for DALL-E 3.', 'renewai-featured-image-generator');
    }
    return esc_html__('You are an AI assistant that generates image prompts based on blog post content.', 'renewai-featured-image-generator');
  }

  /**
   * Render the GPT system prompt field.
   */
  public function render_gpt_system_prompt_field(): void
  {
    $value = get_option('renewai_ig1_gpt_system_prompt', '');
    echo '<textarea name="renewai_ig1_gpt_system_prompt" rows="10" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">' . esc_html__('When set, this overrides the default system prompt for ChatGPT.', 'renewai-featured-image-generator') . '</p>';
  }

  /**
   * Render the GPT user prompt field.
   */
  public function render_gpt_user_prompt_field(): void
  {
    $value = get_option('renewai_ig1_gpt_user_prompt', '');
    echo '<textarea name="renewai_ig1_gpt_user_prompt" rows="10" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">' . esc_html__('When set, this overrides the default user prompt for ChatGPT.', 'renewai-featured-image-generator') . '</p>';
  }

  /**
   * Render the help page content.
   */
  public function render_help_page(): void
  {
    require_once RENEWAI_IG1_PLUGIN_DIR . 'includes/admin/help.php';
    \RenewAI\FeaturedImageGenerator\Admin\render_help_page();
  }

  /**
   * Render the FAL model selection field.
   * Only available in premium version.
   */
  public function render_fal_model_field(): void
  {
    $api_key = get_option('renewai_ig1_flux_api_key', '');
    if (empty($api_key)) {
      echo '<p class="description">' . esc_html__('No API key set. Please visit the ', 'renewai-featured-image-generator') . '<a href="' . esc_url(admin_url('admin.php?page=renewai-ig1-api-keys')) . '">' . esc_html__('API Keys page', 'renewai-featured-image-generator') . '</a>' . esc_html__(' to set your API key.', 'renewai-featured-image-generator') . '</p>';
      return;
    }
    $current_model = get_option('renewai_ig1_fal_model', 'flux_dev');
    $models = [
      'flux_dev' => [
        'label' => esc_html__('Flux Dev', 'renewai-featured-image-generator'),
        'note'  => esc_html__('Development version of Flux - suitable for testing and development.', 'renewai-featured-image-generator'),
      ],
    ];
    echo '<select id="renewai_ig1_fal_model" name="renewai_ig1_fal_model">';
    foreach ($models as $key => $model) {
      echo '<option value="' . esc_attr($key) . '" ' . selected($current_model, $key, false) . '>' . esc_html($model['label']) . '</option>';
    }
    echo '</select>';
    echo '<p class="description model-note" id="renewai_ig1_fal_model_note">' . esc_html((isset($models[$current_model]['note']) ? $models[$current_model]['note'] : '')) . '</p>';
    echo '<p class="description bottom-label">' . esc_html__('Note: Please review the pricing for each model before selecting.', 'renewai-featured-image-generator') . '<br><a href="https://docs.bfl.ml/pricing/" target="_blank">' . esc_html__('Learn more', 'renewai-featured-image-generator') . '</a></p>';
  }

  /**
   * Render the OpenAI model selection field.
   * Shows limited options in free version.
   */
  public function render_openai_model_field(): void
  {
    // Retrieve the OpenAI API key
    $api_key = get_option('renewai_ig1_openai_api_key', '');
    if (empty($api_key)) {
      echo '<p class="description">' . esc_html__('No API key set. Please visit the ', 'renewai-featured-image-generator') . '<a href="' . esc_url(admin_url('admin.php?page=renewai-ig1-api-keys')) . '">' . esc_html__('API Keys page', 'renewai-featured-image-generator') . '</a>' . esc_html__(' to set your API key.', 'renewai-featured-image-generator') . '</p>';
      return;
    }
    echo '<input type="hidden" name="renewai_ig1_openai_model" value="gpt-3.5-turbo">';
    echo '<p><strong>' . esc_html__('GPT-3.5 Turbo', 'renewai-featured-image-generator') . '</strong></p>';
    echo '<p class="description">' . esc_html__('The free version uses GPT-3.5 Turbo for prompt generation.', 'renewai-featured-image-generator') . '</p>';
    echo '<p class="description">' . esc_html__('Upgrade to premium to access more OpenAI models.', 'renewai-featured-image-generator') . '</p>';
  }

  /**
   * Check if user has completed first interaction.
   *
   * @return bool True if first interaction is complete
   */
  private function has_completed_first_interaction(): bool
  {
    $user_id = get_current_user_id();
    return (bool) get_user_meta($user_id, 'renewai_ig1_first_interaction', true);
  }

  /**
   * Mark user's first interaction as complete.
   */
  private function mark_first_interaction_complete(): void
  {
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'renewai_ig1_first_interaction', true);
  }

  /**
   * Process newsletter signup submission.
   *
   * @param string $email User's email address
   * @param string $first_name User's first name
   * @param string $last_name User's last name
   */
  private function process_newsletter_signup($email, $first_name, $last_name): void
  {
    renewai_ig1_log('Processing newsletter signup for email: ' . $email);
    $to = (defined('RENEWAI_IG1_NEWSLETTER_ADDR') ? RENEWAI_IG1_NEWSLETTER_ADDR : 'success@perpetuaiconsult.com');
    $subject = sprintf(
      /* translators: %s: Site name */
      esc_html__('[%s] New Newsletter Signup', 'renewai-featured-image-generator'),
      get_bloginfo('name')
    );
    $message = sprintf(
      /* translators: %1$s: Site name, %2$s: Email, %3$s: First name, %4$s: Last name, %5$s: Site URL */
      esc_html__("New newsletter signup from %1\$s:\n\nEmail: %2\$s\nFirst Name: %3\$s\nLast Name: %4\$s\nSite: %5\$s", 'renewai-featured-image-generator'),
      get_bloginfo('name'),
      $email,
      $first_name,
      $last_name,
      home_url()
    );
    $headers = ['Content-Type: text/plain; charset=UTF-8', sprintf(
      /* translators: %1$s: Site name, %2$s: Admin email */
      'From: %1$s <%2$s>',
      get_bloginfo('name'),
      get_bloginfo('admin_email')
    )];
    $result = wp_mail(
      $to,
      $subject,
      $message,
      $headers
    );
    renewai_ig1_log('Newsletter signup email sent: ' . (($result ? 'success' : 'failed')));
    if ($result) {
      // Store signup status in user meta
      update_user_meta(get_current_user_id(), 'renewai_ig1_newsletter_signup', true);
    }
  }

  /**
   * Render the newsletter signup field.
   */
  public function render_newsletter_field(): void
  {
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    // Add hidden field to ensure form processing
    echo '<input type="hidden" name="renewai_ig1_newsletter_submitted" value="1">';
    echo '<input type="hidden" name="renewai_ig1_newsletter_fname" value="' . esc_attr($current_user->first_name) . '">';
    echo '<input type="hidden" name="renewai_ig1_newsletter_lname" value="' . esc_attr($current_user->last_name) . '">';
    ?>
    <div class="renewai-ig1-settings-section">
      <h2><?php
          esc_html_e('Stay Up to Date!', 'renewai-featured-image-generator');
          ?></h2>
      <p><?php
          esc_html_e('Subscribe to our newsletter to stay up to date on new features, upcoming promotions and important news.', 'renewai-featured-image-generator');
          ?></p>
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="renewai_ig1_newsletter_optin">
              <?php
              esc_html_e('Subscribe to Newsletter', 'renewai-featured-image-generator');
              ?>
            </label>
          </th>
          <td>
            <input type="checkbox" id="renewai_ig1_newsletter_optin" name="renewai_ig1_newsletter_optin" value="1" checked>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="renewai_ig1_newsletter_email">
              <?php
              esc_html_e('Email Address', 'renewai-featured-image-generator');
              ?>
            </label>
          </th>
          <td>
            <input type="email" id="renewai_ig1_newsletter_email" name="renewai_ig1_newsletter_email" value="<?php echo esc_attr($user_email); ?>" class="regular-text">
          </td>
        </tr>
      </table>
    </div>
<?php
  }

  /**
   * Process newsletter settings and signup form submission.
   *
   * @param mixed $value The form value being processed
   * @return mixed The processed value
   */
  public function process_newsletter_settings($value)
  {
    // Check if form was submitted
    if (!isset($_POST['renewai_ig1_newsletter_submitted'])) {
      return $value;
    }
    // Only process if this is the first interaction
    if (!$this->has_completed_first_interaction()) {
      renewai_ig1_log('Processing newsletter settings for first time user');
      // Check if newsletter opt-in was checked
      if (isset($_POST['renewai_ig1_newsletter_optin']) && $_POST['renewai_ig1_newsletter_optin'] == '1') {
        $email = wp_unslash(sanitize_email($_POST['renewai_ig1_newsletter_email'] ?? ''));
        $first_name = wp_unslash(sanitize_text_field($_POST['renewai_ig1_newsletter_fname'] ?? ''));
        $last_name = wp_unslash(sanitize_text_field($_POST['renewai_ig1_newsletter_lname'] ?? ''));
        if ($email) {
          $this->process_newsletter_signup($email, $first_name, $last_name);
        }
      }
      // Mark first interaction complete
      $this->mark_first_interaction_complete();
      // Add success message
      add_settings_error(
        'renewai_ig1_settings',
        'settings_updated',
        esc_html__('Settings saved and newsletter preferences updated.', 'renewai-featured-image-generator'),
        'success'
      );
    }
    return $value;
  }

  /**
   * Sanitize the FAL model setting.
   * 
   * @param string $model The model value to sanitize
   * @return string Sanitized model value
   */
  public function sanitize_fal_model(string $model): string
  {
    $allowed_models = ['flux_dev']; // Add other valid models here

    if (!in_array($model, $allowed_models, true)) {
      add_settings_error(
        'renewai_ig1_settings',
        'invalid_fal_model',
        esc_html__('Invalid FAL model selected.', 'renewai-featured-image-generator')
      );
      return 'flux_dev'; // Return default if invalid
    }

    return $model;
  }
}
