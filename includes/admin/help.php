<?php

namespace RenewAI\FeaturedImageGenerator\Admin;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * Render the help page content.
 * Displays documentation, FAQs, and troubleshooting information.
 */
function render_help_page(): void
{
?>
  <div class="wrap renewai-ig1-wrap">
    <?php include RENEWAI_IG1_PLUGIN_DIR . 'includes/header.php'; ?>
    <div class="renewai-ig1-settings-form">
      <h1><?php esc_html_e('Help', 'renewai-featured-image-generator'); ?></h1>
      <div class="renewai-ig1-settings">
        <div class="renewai-ig1-settings-section">
          <h2><?php esc_html_e('How It Works', 'renewai-featured-image-generator'); ?></h2>
          <p><?php esc_html_e('The RenewAI Featured Image Generator uses AI to create unique featured images for your posts. Here\'s how it works:', 'renewai-featured-image-generator'); ?></p>
          <ol>
            <li><?php esc_html_e('When editing a post, you\'ll see a "Generate Featured Image" meta box.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Click "Generate Prompt" to create an AI-generated prompt based on your post content.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('You can edit this prompt if you wish.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Click "Generate Image" to create an AI-generated image based on the prompt.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('The generated image will be set as your post\'s featured image.', 'renewai-featured-image-generator'); ?></li>
          </ol>

          <h2><?php esc_html_e('Free vs Premium Features', 'renewai-featured-image-generator'); ?></h2>
          <h3><?php esc_html_e('Free Version:', 'renewai-featured-image-generator'); ?></h3>
          <ul>
            <li><?php esc_html_e('DALL-E 3 for image generation', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('GPT-3.5 Turbo for prompt generation', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Basic image size options', 'renewai-featured-image-generator'); ?></li>
          </ul>

          <h3><?php esc_html_e('Premium Version:', 'renewai-featured-image-generator'); ?></h3>
          <ul>
            <li><?php esc_html_e('Access to all Flux image generation models:', 'renewai-featured-image-generator'); ?>
              <ul>
                <li><?php esc_html_e('Flux Pro 1.1 - Latest version with improved quality', 'renewai-featured-image-generator'); ?></li>
                <li><?php esc_html_e('Flux Pro - Stable production version', 'renewai-featured-image-generator'); ?></li>
                <li><?php esc_html_e('Flux Dev - Development version', 'renewai-featured-image-generator'); ?></li>
              </ul>
            </li>
            <li><?php esc_html_e('Advanced GPT models (GPT-4, GPT-4-32k)', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Additional image styles and customization options', 'renewai-featured-image-generator'); ?></li>
          </ul>

          <h2><?php esc_html_e('Setting Up API Keys', 'renewai-featured-image-generator'); ?></h2>

          <h3><?php esc_html_e('OpenAI API Key (Required for All Versions)', 'renewai-featured-image-generator'); ?></h3>
          <ol>
            <li>
              <?php
              printf(
                /* translators: %s: OpenAI signup URL */
                esc_html__('Go to %s and create an account.', 'renewai-featured-image-generator'),
                '<a href="' . esc_url('https://platform.openai.com/signup') . '" target="_blank">platform.openai.com/signup</a>'
              );
              ?>
            </li>
            <li>
              <?php
              printf(
                /* translators: %s: OpenAI API keys URL */
                esc_html__('Once logged in, go to %s', 'renewai-featured-image-generator'),
                '<a href="' . esc_url('https://platform.openai.com/account/api-keys') . '" target="_blank">platform.openai.com/account/api-keys</a>'
              );
              ?>
            </li>
            <li><?php esc_html_e('Click "Create new secret key" to generate your API key.', 'renewai-featured-image-generator'); ?></li>
          </ol>

          <h3><?php esc_html_e('Flux API Key (Premium Version Only)', 'renewai-featured-image-generator'); ?></h3>
          <ol>
            <li>
              <?php
              printf(
                /* translators: %s: Flux account URL */
                esc_html__('Go to %s and create an account.', 'renewai-featured-image-generator'),
                '<a href="' . esc_url('https://docs.bfl.ml/quick_start/create_account/') . '" target="_blank">docs.bfl.ml/quick_start/create_account</a>'
              );
              ?>
            </li>
            <li>
              <?php
              printf(
                /* translators: %s: Flux pricing URL */
                esc_html__('Review the pricing information at %s', 'renewai-featured-image-generator'),
                '<a href="' . esc_url('https://docs.bfl.ml/pricing/') . '" target="_blank">docs.bfl.ml/pricing</a>'
              );
              ?>
            </li>
            <li>
              <?php
              printf(
                /* translators: %s: Flux account management URL */
                esc_html__('Add credits to your account at %s', 'renewai-featured-image-generator'),
                '<a href="' . esc_url('https://docs.bfl.ml/quick_start/managing_account/') . '" target="_blank">docs.bfl.ml/quick_start/managing_account</a>'
              );
              ?>
            </li>
          </ol>

          <h2><?php esc_html_e('Best Practices', 'renewai-featured-image-generator'); ?></h2>
          <ul>
            <li><?php esc_html_e('Write clear, descriptive prompts for better image generation results.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Experiment with different image sizes to find what works best for your theme.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Always review and potentially edit generated images to ensure they meet your standards.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Consider legal and ethical implications of using AI-generated images on your site.', 'renewai-featured-image-generator'); ?></li>
          </ul>

          <h2><?php esc_html_e('Troubleshooting', 'renewai-featured-image-generator'); ?></h2>
          <ul>
            <li><?php esc_html_e('If you\'re having issues generating prompts or images, check that your API keys are entered correctly in the plugin settings.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Ensure your OpenAI and FAL accounts have sufficient credits for API usage.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('If you\'re seeing error messages, try enabling debug mode in the plugin settings to get more detailed error information.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Check your website\'s error logs for any PHP errors or warnings related to the plugin.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Ensure your server meets the minimum requirements for the plugin (PHP 7.4+, WordPress 5.0+).', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('If images aren\'t generating, check your server\'s outbound connection to ensure it can reach the OpenAI and FAL APIs.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Try deactivating other plugins to check for conflicts.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('If using a caching plugin, clear the cache after making changes or if you\'re experiencing issues.', 'renewai-featured-image-generator'); ?></li>
          </ul>

          <h2><?php esc_html_e('FAQs', 'renewai-featured-image-generator'); ?></h2>
          <dl>
            <dt><?php esc_html_e('Q: Can I use the generated images for commercial purposes?', 'renewai-featured-image-generator'); ?></dt>
            <dd><?php esc_html_e('A: The usage rights for AI-generated images can be complex. Please refer to the terms of service of OpenAI and FAL for the most up-to-date information on image usage rights.', 'renewai-featured-image-generator'); ?></dd>

            <dt><?php esc_html_e('Q: How much does it cost to use this plugin?', 'renewai-featured-image-generator'); ?></dt>
            <dd><?php esc_html_e('A: While the plugin itself is free, you will need to pay for API usage from OpenAI and FAL. Please check their respective websites for current pricing information.', 'renewai-featured-image-generator'); ?></dd>

            <dt><?php esc_html_e('Q: Can I use this plugin with any WordPress theme?', 'renewai-featured-image-generator'); ?></dt>
            <dd><?php esc_html_e('A: Yes, this plugin should work with any WordPress theme that supports featured images.', 'renewai-featured-image-generator'); ?></dd>

            <dt><?php esc_html_e('Q: How can I improve the quality of generated images?', 'renewai-featured-image-generator'); ?></dt>
            <dd><?php esc_html_e('A: Try to be as specific and descriptive as possible in your prompts. Experiment with different phrasings and details to get the best results.', 'renewai-featured-image-generator'); ?></dd>
          </dl>

          <h2><?php esc_html_e('Terms of Service', 'renewai-featured-image-generator'); ?></h2>
          <h3><?php esc_html_e('OpenAI Terms of Service:', 'renewai-featured-image-generator'); ?></h3>
          <ul>
            <li><?php esc_html_e('Website:', 'renewai-featured-image-generator'); ?> <a href="https://www.openai.com/" target="_blank">https://www.openai.com/</a></li>
            <li><?php esc_html_e('Terms of Use:', 'renewai-featured-image-generator'); ?> <a href="https://openai.com/policies/terms-of-use" target="_blank">https://openai.com/policies/terms-of-use</a></li>
            <li><?php esc_html_e('Privacy Policy:', 'renewai-featured-image-generator'); ?> <a href="https://openai.com/policies/privacy-policy" target="_blank">https://openai.com/policies/privacy-policy</a></li>
          </ul>

          <h3><?php esc_html_e('Flux Terms of Service:', 'renewai-featured-image-generator'); ?></h3>
          <ul>
            <li><?php esc_html_e('Website:', 'renewai-featured-image-generator'); ?> <a href="https://blackforestlabs.ai/" target="_blank">https://blackforestlabs.ai/</a></li>
            <li><?php esc_html_e('Terms of Service:', 'renewai-featured-image-generator'); ?> <a href="https://blackforestlabs.ai/terms-of-service/" target="_blank">https://blackforestlabs.ai/terms-of-service/</a></li>
            <li><?php esc_html_e('Privacy Policy:', 'renewai-featured-image-generator'); ?> <a href="https://blackforestlabs.ai/privacy-policy/" target="_blank">https://blackforestlabs.ai/privacy-policy/</a></li>
            <li><?php esc_html_e('Pricing:', 'renewai-featured-image-generator'); ?> <a href="https://docs.bfl.ml/pricing/" target="_blank">https://docs.bfl.ml/pricing/</a></li>
          </ul>
          <p><?php esc_html_e('Please note that you are responsible for ensuring that your use of AI-generated images complies with all applicable laws and regulations.', 'renewai-featured-image-generator'); ?></p>

          <h2><?php esc_html_e('Privacy Considerations', 'renewai-featured-image-generator'); ?></h2>
          <p><?php esc_html_e('When using this plugin, be aware that your post content is sent to OpenAI for prompt generation, and the resulting prompt is sent to FAL for image generation. Ensure that you comply with privacy laws and regulations, especially when dealing with sensitive or personal information in your posts.', 'renewai-featured-image-generator'); ?></p>

          <h2><?php esc_html_e('Need More Help?', 'renewai-featured-image-generator'); ?></h2>
          <p><?php esc_html_e('If you\'re still having issues or have questions after reviewing this help page, please follow these steps:', 'renewai-featured-image-generator'); ?></p>
          <ol>
            <li><?php esc_html_e('Check our online documentation for the most up-to-date information and guides.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('Search our support forum to see if your issue has already been addressed.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('If you can\'t find a solution, please create a new thread in our support forum with a detailed description of your issue, including any error messages and steps to reproduce the problem.', 'renewai-featured-image-generator'); ?></li>
            <li><?php esc_html_e('For urgent issues or if you need personalized assistance, you can contact our support team at support@example.com. Please include as much relevant information as possible to help us assist you quickly.', 'renewai-featured-image-generator'); ?></li>
          </ol>
        </div>
      </div>
    </div>
  </div>
<?php
}
