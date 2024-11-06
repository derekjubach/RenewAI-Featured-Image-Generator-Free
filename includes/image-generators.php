<?php

namespace RenewAI\FeaturedImageGenerator\ImageGenerators;

use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;
use WP_Error;

/**
 * Abstract base class for image generators.
 */
abstract class ImageGenerator
{
  /**
   * Generate an image based on the given prompt and size.
   *
   * @param string $prompt The text prompt to generate the image from
   * @param string $size The desired image size
   * @return array|WP_Error Array containing image data or WP_Error on failure
   */
  abstract public function generate_image(string $prompt, string $size): array|WP_Error;
}

/**
 * Flux image generator implementation using FAL API.
 */
class FluxGenerator extends ImageGenerator
{
  private $api_key;
  private const BASE_URL = 'https://api.bfl.ml/v1';
  private const MAX_RETRIES = 30;  // 30 retries = ~30 seconds max wait
  private const POLL_INTERVAL = 1000000; // 1 second in microseconds

  /**
   * Constructor.
   *
   * @param string $api_key The FAL API key
   */
  public function __construct($api_key)
  {
    $this->api_key = $api_key;
  }

  /**
   * Generate an image using the Flux API.
   *
   * @param string $prompt The text prompt to generate the image from
   * @param string $size The desired image size
   * @return array|WP_Error Array containing image URL and content type, or WP_Error on failure
   */
  public function generate_image(string $prompt, string $size): array|WP_Error
  {
    renewai_ig1_log("Flux: Starting image generation");

    // Step 1: Initialize generation task
    $task_id = $this->initialize_task($prompt, $size);
    if (is_wp_error($task_id)) {
      return $task_id;
    }

    // Step 2: Poll for results
    $result = $this->poll_for_result($task_id);
    if (is_wp_error($result)) {
      return $result;
    }

    // Step 3: Process and return the result
    return [
      'url' => $result['image_url'],
      'content_type' => 'image/png',
      'filename' => sanitize_title('featured-image-' . substr($prompt, 0, 50)) . '.png'
    ];
  }

  private function get_endpoint(): string
  {
    $model = get_option('renewai_ig1_fal_model', 'flux_dev');

    $endpoints = [
      'flux_pro_1_1' => '/flux-pro-1.1',
      'flux_pro' => '/flux-pro',
      'flux_dev' => '/flux-dev'
    ];

    return self::BASE_URL . ($endpoints[$model] ?? $endpoints['flux_dev']);
  }

  private function initialize_task(string $prompt, string $size): string|WP_Error
  {
    // Convert size string to width/height
    $dimensions = $this->convert_size($size);

    $body = wp_json_encode([
      'prompt' => $prompt,
      'width' => $dimensions['width'],
      'height' => $dimensions['height'],
      'steps' => 28,
      'prompt_upsampling' => false,
      'guidance' => 3,
      'safety_tolerance' => 2,
      'output_format' => 'jpeg'
    ]);

    renewai_ig1_log("Flux: Initializing task with body: " . $body);
    renewai_ig1_log("Flux: Using endpoint: " . $this->get_endpoint());

    $response = wp_remote_post($this->get_endpoint(), [
      'headers' => [
        'X-Key' => $this->api_key,
        'Content-Type' => 'application/json'
      ],
      'body' => $body,
      'timeout' => 30
    ]);

    if (is_wp_error($response)) {
      renewai_ig1_log("Flux: Task initialization failed: " . $response->get_error_message(), 'error');
      return new WP_Error(
        'flux_api_error',
        sprintf(
          /* translators: %s: Error message */
          esc_html__('Flux API request failed: %s', 'renewai-featured-image-generator'),
          $response->get_error_message()
        )
      );
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['id'])) {
      renewai_ig1_log("Flux: No task ID in response: " . wp_json_encode($data), 'error');
      return new WP_Error(
        'flux_api_error',
        esc_html__('Invalid response from Flux API', 'renewai-featured-image-generator')
      );
    }

    renewai_ig1_log("Flux: Task initialized with ID: " . $data['id']);
    return $data['id'];
  }

  private function poll_for_result(string $task_id): array|WP_Error
  {
    $retries = 0;

    while ($retries < self::MAX_RETRIES) {
      // Append the ID parameter directly to the URL
      $url = add_query_arg('id', $task_id, self::BASE_URL . '/get_result');
      renewai_ig1_log("Flux: Polling URL: " . $url);

      $response = wp_remote_get($url, [
        'headers' => [
          'X-Key' => $this->api_key
        ],
        'timeout' => 5
      ]);

      if (is_wp_error($response)) {
        renewai_ig1_log("Flux: Poll request failed: " . $response->get_error_message(), 'error');
        return new WP_Error(
          'flux_api_error',
          esc_html__('Failed to check image generation status', 'renewai-featured-image-generator')
        );
      }

      $data = json_decode(wp_remote_retrieve_body($response), true);
      renewai_ig1_log("Flux: Poll response: " . wp_json_encode($data));

      if (isset($data['status'])) {
        switch (strtolower($data['status'])) {
          case 'ready':
            if (isset($data['result']['sample'])) {
              return [
                'image_url' => $data['result']['sample'],
                'duration' => $data['result']['duration'] ?? 0
              ];
            }
            return new WP_Error(
              'flux_api_error',
              esc_html__('Image URL missing from completed task', 'renewai-featured-image-generator')
            );

          case 'failed':
            return new WP_Error(
              'flux_api_error',
              isset($data['error']) ? $data['error'] : esc_html__('Image generation failed', 'renewai-featured-image-generator')
            );

          case 'pending':
          case 'processing':
            $retries++;
            usleep(self::POLL_INTERVAL);
            continue 2; // Use continue 2 to continue the outer while loop

          default:
            return new WP_Error(
              'flux_api_error',
              esc_html__('Unknown task status', 'renewai-featured-image-generator')
            );
        }
      }

      $retries++;
      usleep(self::POLL_INTERVAL);
    }

    return new WP_Error(
      'flux_timeout',
      esc_html__('Image generation timed out', 'renewai-featured-image-generator')
    );
  }

  private function convert_size(string $size): array
  {
    $size_map = [
      'square_hd' => ['width' => 1024, 'height' => 1024],
      'square' => ['width' => 768, 'height' => 768],
      'portrait_4_3' => ['width' => 768, 'height' => 1024],
      'portrait_16_9' => ['width' => 576, 'height' => 1024],
      'landscape_4_3' => ['width' => 1024, 'height' => 768],
      'landscape_16_9' => ['width' => 1024, 'height' => 576]
    ];

    return $size_map[$size] ?? ['width' => 1024, 'height' => 1024];
  }
}

/**
 * DALL-E 3 image generator implementation using OpenAI API.
 */
class DallE3Generator extends ImageGenerator
{
  private $api_key;

  /**
   * Constructor.
   *
   * @param string $api_key The OpenAI API key
   */
  public function __construct($api_key)
  {
    $this->api_key = $api_key;
  }

  /**
   * Generate an image using the DALL-E 3 API.
   *
   * @param string $prompt The text prompt to generate the image from
   * @param string $size The desired image size
   * @return array|WP_Error Array containing image URL and content type, or WP_Error on failure
   */
  public function generate_image(string $prompt, string $size): array|WP_Error
  {
    renewai_ig1_log("DALL-E 3: Generating image with size: " . $size);

    $dalle_size = $this->convert_size($size);
    renewai_ig1_log("DALL-E 3: Converted size to: " . $dalle_size);

    $body = [
      'model' => 'dall-e-3',
      'prompt' => $prompt,
      'size' => $dalle_size,
      'n' => 1,
      'quality' => 'standard',
      'response_format' => 'url'
    ];

    renewai_ig1_log("DALL-E 3: Sending request with body: " . wp_json_encode($body));

    $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->api_key,
        'Content-Type' => 'application/json'
      ],
      'body' => wp_json_encode($body),
      'timeout' => 120,
      'httpversion' => '1.1',
      'sslverify' => true,
      'blocking' => true
    ]);

    if (is_wp_error($response)) {
      $error_message = $response->get_error_message();
      renewai_ig1_log("DALL-E 3: Request failed: " . $error_message, 'error');

      if (strpos($error_message, 'Operation timed out') !== false) {
        return new WP_Error(
          'dalle_api_timeout',
          esc_html__('The image generation request timed out. Please try again.', 'renewai-featured-image-generator')
        );
      }

      return new WP_Error(
        'dalle_api_error',
        sprintf(
          /* translators: %s: Error message */
          esc_html__('DALL-E API request failed: %s', 'renewai-featured-image-generator'),
          $error_message
        )
      );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    renewai_ig1_log("DALL-E 3: Response code: " . $response_code);

    $body = wp_remote_retrieve_body($response);
    renewai_ig1_log("DALL-E 3: Response body: " . $body);

    if ($response_code !== 200) {
      $error_message = "DALL-E 3 API request failed with status code: " . $response_code;
      if (!empty($body)) {
        $data = json_decode($body, true);
        if (isset($data['error']['message'])) {
          $error_message .= " - " . $data['error']['message'];
        }
      }
      renewai_ig1_log($error_message, 'error');
      return new WP_Error(
        'dalle_api_error',
        sprintf(
          /* translators: %s: Error message */
          esc_html__('DALL-E API error: %s', 'renewai-featured-image-generator'),
          $error_message
        )
      );
    }

    $data = json_decode($body, true);

    if (!isset($data['data'][0]['url'])) {
      renewai_ig1_log("DALL-E 3: No image URL in response", 'error');
      return new WP_Error(
        'dalle_api_error',
        esc_html__('Failed to get image URL from DALL-E 3 API response', 'renewai-featured-image-generator')
      );
    }

    // Generate a clean filename from the prompt
    $filename = sanitize_title('featured-image-' . substr($prompt, 0, 50)) . '.png';
    renewai_ig1_log("DALL-E 3: Generated filename: " . $filename);

    return [
      'url' => $data['data'][0]['url'],
      'filename' => $filename,
      'content_type' => 'image/png'
    ];
  }

  /**
   * Convert internal size format to DALL-E 3 size format.
   *
   * @param string $size The internal size format
   * @return string The DALL-E 3 size format
   */
  private function convert_size(string $size): string
  {
    renewai_ig1_log("DALL-E 3: Converting size from: " . $size);

    // Map internal sizes to DALL-E 3 sizes
    $size_map = [
      'square' => '1024x1024',
      'square_hd' => '1024x1024',
      'portrait' => '1024x1792',
      'portrait_4_3' => '1024x1792',
      'portrait_16_9' => '1024x1792',
      'landscape' => '1792x1024',
      'landscape_4_3' => '1792x1024',
      'landscape_16_9' => '1792x1024'
    ];

    $dalle_size = $size_map[$size] ?? '1024x1024';
    renewai_ig1_log("DALL-E 3: Converted to size: " . $dalle_size);
    return $dalle_size;
  }
}
