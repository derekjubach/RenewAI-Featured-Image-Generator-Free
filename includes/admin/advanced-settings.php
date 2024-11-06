<?php

namespace RenewAI\FeaturedImageGenerator\Admin;

use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class AdvancedSettings
{
  /**
   * Initialize the advanced settings functionality.
   */
  public static function init(): void
  {
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_menu', [__CLASS__, 'add_submenu_page']);
  }

  /**
   * Add Advanced Settings submenu page to the main plugin menu.
   */
  public static function add_submenu_page(): void
  {
    add_submenu_page(
      'renewai-ig1-settings',
      esc_html__('Advanced Settings', 'renewai-featured-image-generator'),
      esc_html__('Advanced Settings', 'renewai-featured-image-generator'),
      'manage_options',
      'renewai-ig1-advanced-settings',
      [__CLASS__, 'render_page']
    );
  }

  /**
   * Register advanced settings and sections.
   */
  public static function register_settings(): void
  {
    // Register settings
    register_setting('renewai_ig1_advanced_settings', 'renewai_ig1_prompt_temperature', [
      'type' => 'number',
      'default' => 0.7,
      'sanitize_callback' => [__CLASS__, 'sanitize_temperature'],
    ]);

    register_setting('renewai_ig1_advanced_settings', 'renewai_ig1_image_style', [
      'type' => 'string',
      'default' => 'natural',
    ]);

    // Add settings section
    add_settings_section(
      'renewai_ig1_advanced_settings_section',
      esc_html__('Advanced Configuration', 'renewai-featured-image-generator'),
      [__CLASS__, 'render_section'],
      'renewai-ig1-advanced-settings'
    );

    // Add settings fields
    add_settings_field(
      'renewai_ig1_prompt_temperature',
      esc_html__('Prompt Temperature', 'renewai-featured-image-generator'),
      [__CLASS__, 'render_temperature_field'],
      'renewai-ig1-advanced-settings',
      'renewai_ig1_advanced_settings_section'
    );

    add_settings_field(
      'renewai_ig1_image_style',
      esc_html__('Image Style', 'renewai-featured-image-generator'),
      [__CLASS__, 'render_style_field'],
      'renewai-ig1-advanced-settings',
      'renewai_ig1_advanced_settings_section'
    );
  }

  /**
   * Render the advanced settings page.
   */
  public static function render_page(): void
  {
?>
    <div class="wrap renewai-ig1-wrap">
      <?php include RENEWAI_IG1_PLUGIN_DIR . 'includes/header.php'; ?>
      <form action="options.php" method="post" class="renewai-ig1-advanced-settings">
        <h1><?php esc_html_e('Advanced Settings', 'renewai-featured-image-generator'); ?></h1>
        <?php
        settings_fields('renewai_ig1_advanced_settings');
        do_settings_sections('renewai-ig1-advanced-settings');
        submit_button();
        ?>
      </form>
    </div>
  <?php
  }

  /**
   * Render the advanced settings section description.
   */
  public static function render_section(): void
  {
  ?>
    <p>
      <?php esc_html_e('Configure advanced settings for the image generation process.', 'renewai-featured-image-generator'); ?>
    </p>
  <?php
  }

  /**
   * Render the temperature control field.
   */
  public static function render_temperature_field(): void
  {
    $value = get_option('renewai_ig1_prompt_temperature', 0.7);
  ?>
    <input type="range"
      id="renewai_ig1_prompt_temperature"
      name="renewai_ig1_prompt_temperature"
      min="0"
      max="2"
      step="0.1"
      value="<?php echo esc_attr($value); ?>">
    <span class="temperature-value"><?php echo esc_html($value); ?></span>
    <p class="description">
      <?php esc_html_e('Controls the randomness of prompt generation. Higher values make the output more random, lower values make it more focused.', 'renewai-featured-image-generator'); ?>
    </p>
<?php
  }

  /**
   * Render the image style selection field.
   */
  public static function render_style_field(): void
  {
    $current_style = get_option('renewai_ig1_image_style', 'natural');
    $styles = [
      'natural' => esc_html__('Natural', 'renewai-featured-image-generator'),
      'cartoon' => esc_html__('Cartoon', 'renewai-featured-image-generator'),
      'abstract' => esc_html__('Abstract', 'renewai-featured-image-generator'),
      'realistic' => esc_html__('Realistic', 'renewai-featured-image-generator'),
    ];

    echo '<select id="renewai_ig1_image_style" name="renewai_ig1_image_style">';
    foreach ($styles as $value => $label) {
      printf(
        '<option value="%s" %s>%s</option>',
        esc_attr($value),
        selected($current_style, $value, false),
        esc_html($label)
      );
    }
    echo '</select>';
    echo '<p class="description">';
    esc_html_e('Select the preferred style for generated images.', 'renewai-featured-image-generator');
    echo '</p>';
  }

  /**
   * Sanitize the temperature value.
   *
   * @param float $value The temperature value to sanitize
   * @return float The sanitized temperature value
   */
  public static function sanitize_temperature($value): float
  {
    $value = floatval($value);
    if ($value < 0) {
      add_settings_error(
        'renewai_ig1_advanced_settings',
        'temperature_too_low',
        esc_html__('Temperature must be greater than or equal to 0.', 'renewai-featured-image-generator')
      );
      return 0;
    }
    if ($value > 2) {
      add_settings_error(
        'renewai_ig1_advanced_settings',
        'temperature_too_high',
        esc_html__('Temperature must be less than or equal to 2.', 'renewai-featured-image-generator')
      );
      return 2;
    }
    return $value;
  }
}
