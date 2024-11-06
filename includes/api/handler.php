<?php

namespace RenewAI\FeaturedImageGenerator\API;

use RenewAI\FeaturedImageGenerator\Admin\APIKeys;
use WP_Error;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;
use function RenewAI\FeaturedImageGenerator\renewai_ig1;
use RenewAI\FeaturedImageGenerator\ImageGenerators\FluxGenerator;
use RenewAI\FeaturedImageGenerator\ImageGenerators\DallE3Generator;
use RenewAI\FeaturedImageGenerator\ImageGenerators\ImageGenerator;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_get_default_generator;
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}
class Handler {
    private $openai_api_key;

    private $flux_api_key;

    private $image_generator;

    /**
     * Constructor. Initialize API keys and image generator.
     * 
     * @throws \RuntimeException If image generator class is not found
     */
    public function __construct() {
        $api_keys = APIKeys::get_instance();
        $this->openai_api_key = $api_keys->get_api_key( 'openai' );
        $generator = get_option( 'renewai_ig1_image_generator', renewai_ig1_get_default_generator() );
        if ( $generator === 'flux' && renewai_ig1()->can_use_premium_code__premium_only() ) {
            if ( class_exists( FluxGenerator::class ) ) {
                $this->image_generator = new FluxGenerator($this->flux_api_key);
            } else {
                renewai_ig1_log( 'FluxGenerator class not found', 'error' );
                throw new \RuntimeException(esc_html__( 'Image generator class not found', 'renewai-featured-image-generator' ));
            }
        } else {
            if ( class_exists( DallE3Generator::class ) ) {
                $this->image_generator = new DallE3Generator($this->openai_api_key);
            } else {
                renewai_ig1_log( 'DallE3Generator class not found', 'error' );
                throw new \RuntimeException(esc_html__( 'Image generator class not found', 'renewai-featured-image-generator' ));
            }
        }
    }

    /**
     * Generate image using the selected image generator.
     *
     * @param string $prompt The image prompt
     * @param string $size The image size
     * @return array|WP_Error The image data or WP_Error on failure
     */
    public function generate_image( string $prompt, string $size ) : array|WP_Error {
        $current_generator = get_option( 'renewai_ig1_image_generator', renewai_ig1_get_default_generator() );
        if ( $current_generator === 'flux' && !renewai_ig1()->can_use_premium_code__premium_only() ) {
            renewai_ig1_log( 'Attempted to use Flux without premium', 'error' );
            return new WP_Error('premium_required', esc_html__( 'Flux image generation requires a premium license.', 'renewai-featured-image-generator' ));
        }
        // Add file type checking before generation
        $allowed_mime_types = get_allowed_mime_types();
        $image_mime_types = array_filter( $allowed_mime_types, function ( $mime ) {
            return strpos( $mime, 'image/' ) === 0;
        } );
        renewai_ig1_log( 'Allowed image MIME types: ' . wp_json_encode( $image_mime_types ) );
        // Ensure 'image/png' and 'image/jpeg' are allowed
        if ( !in_array( 'image/png', $image_mime_types ) && !in_array( 'image/jpeg', $image_mime_types ) ) {
            renewai_ig1_log( 'Required image MIME types not allowed', 'error' );
            return new WP_Error('mime_type_error', esc_html__( 'PNG or JPEG image types must be allowed in WordPress settings.', 'renewai-featured-image-generator' ));
        }
        $style = ( renewai_ig1()->can_use_premium_code__premium_only() ? get_option( 'renewai_ig1_image_style', 'natural' ) : 'natural' );
        $style_prompts = [
            'natural'   => esc_html__( 'Create a natural, realistic image with balanced composition and lighting.', 'renewai-featured-image-generator' ),
            'cartoon'   => esc_html__( 'Generate a cartoon-style image with bold outlines, vibrant colors, and exaggerated features.', 'renewai-featured-image-generator' ),
            'abstract'  => esc_html__( 'Create an abstract image with non-representational forms, bold colors, and emphasis on shapes and textures.', 'renewai-featured-image-generator' ),
            'realistic' => esc_html__( 'Produce a highly detailed, photorealistic image with accurate lighting, textures, and proportions.', 'renewai-featured-image-generator' ),
        ];
        $style_prompt = $style_prompts[$style] ?? $style_prompts['natural'];
        $enhanced_prompt = $style_prompt . ' ' . $prompt;
        renewai_ig1_log( 'Generating image with prompt: ' . $enhanced_prompt );
        // Generate the image
        renewai_ig1_log( 'Attempting to generate image with ' . get_class( $this->image_generator ) );
        $result = $this->image_generator->generate_image( $enhanced_prompt, $size );
        if ( is_wp_error( $result ) ) {
            renewai_ig1_log( 'Image generation failed: ' . $result->get_error_message(), 'error' );
            return $result;
        }
        // If result is successful, verify the file type
        if ( !is_wp_error( $result ) && isset( $result['url'] ) ) {
            renewai_ig1_log( 'Image generated successfully. URL: ' . $result['url'] );
            $file_info = wp_check_filetype( $result['filename'] );
            renewai_ig1_log( 'Generated image file type: ' . wp_json_encode( $file_info ) );
            if ( empty( $file_info['type'] ) || !in_array( $file_info['type'], $image_mime_types ) ) {
                renewai_ig1_log( 'Generated image file type not allowed: ' . ($file_info['type'] ?? 'unknown'), 'error' );
                // Clean up temporary file if it exists
                if ( isset( $result['tmp_file'] ) && file_exists( $result['tmp_file'] ) ) {
                    wp_delete_file( $result['tmp_file'] );
                }
                return new WP_Error('invalid_file_type', esc_html__( 'Generated image file type is not allowed.', 'renewai-featured-image-generator' ));
            }
            // Clean up temporary file after successful verification
            if ( isset( $result['tmp_file'] ) && file_exists( $result['tmp_file'] ) ) {
                wp_delete_file( $result['tmp_file'] );
            }
        } else {
            renewai_ig1_log( 'Image generation result is missing URL', 'error' );
            return new WP_Error('missing_url', esc_html__( 'Generated image URL is missing from the response.', 'renewai-featured-image-generator' ));
        }
        return $result;
    }

    /**
     * Call OpenAI API to generate prompt.
     *
     * @param string $content The post content
     * @return string|WP_Error The generated prompt or WP_Error on failure
     */
    public function call_openai_api( string $content ) : string|WP_Error {
        renewai_ig1_log( "Calling OpenAI API" );
        $model = get_option( 'renewai_ig1_openai_model', 'gpt-3.5-turbo' );
        $model = 'gpt-3.5-turbo';
        $temperature = ( renewai_ig1()->can_use_premium_code__premium_only() ? get_option( 'renewai_ig1_prompt_temperature', 0.7 ) : 0.7 );
        // Get prompts and log if they're empty
        $system_prompt = $this->get_system_prompt();
        $user_prompt = $this->get_user_prompt();
        if ( empty( $system_prompt ) ) {
            renewai_ig1_log( "System prompt is empty", 'warning' );
        }
        if ( empty( $user_prompt ) ) {
            renewai_ig1_log( "User prompt is empty", 'warning' );
        }
        $body = wp_json_encode( [
            'model'       => $model,
            'messages'    => [[
                'role'    => 'system',
                'content' => $system_prompt,
            ], [
                'role'    => 'user',
                'content' => $user_prompt . ' ' . $content,
            ]],
            'max_tokens'  => 1000,
            'temperature' => floatval( $temperature ),
        ] );
        $api_url = 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Authorization' => 'Bearer ' . $this->openai_api_key,
            'Content-Type'  => 'application/json',
        ];
        renewai_ig1_log( "OpenAI API request body: " . $body );
        $response = wp_remote_post( $api_url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 30,
        ] );
        if ( is_wp_error( $response ) ) {
            renewai_ig1_log( "OpenAI API request failed: " . $response->get_error_message(), 'error' );
            return new WP_Error('openai_api_error', sprintf( 
                /* translators: %s: Error message */
                esc_html__( 'OpenAI API request failed: %s', 'renewai-featured-image-generator' ),
                $response->get_error_message()
             ));
        }
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        // Log the full API response for debugging
        renewai_ig1_log( "OpenAI API response: " . wp_json_encode( $data ) );
        if ( isset( $data['error'] ) ) {
            renewai_ig1_log( "OpenAI API error: " . wp_json_encode( $data['error'] ), 'error' );
            return new WP_Error('openai_api_error', esc_html__( 'OpenAI API error: ', 'renewai-featured-image-generator' ) . $data['error']['message']);
        }
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $prompt = trim( $data['choices'][0]['message']['content'] );
            renewai_ig1_log( "Generated prompt: " . $prompt );
            return $prompt;
        }
        renewai_ig1_log( "Failed to generate prompt from OpenAI API. Response: " . wp_json_encode( $data ), 'error' );
        return new WP_Error('openai_api_error', esc_html__( 'Failed to generate prompt from OpenAI API', 'renewai-featured-image-generator' ));
    }

    /**
     * Get the system prompt for OpenAI.
     *
     * @return string The system prompt
     */
    private function get_system_prompt() : string {
        return get_option( 'renewai_ig1_gpt_system_prompt', esc_html__( 'You are an AI assistant that generates image prompts based on blog post content.', 'renewai-featured-image-generator' ) );
    }

    /**
     * Get the user prompt for OpenAI.
     *
     * @return string The user prompt
     */
    private function get_user_prompt() : string {
        return get_option( 'renewai_ig1_gpt_user_prompt', esc_html__( 'Generate a concise and descriptive image prompt based on the following blog post content:', 'renewai-featured-image-generator' ) );
    }

}
