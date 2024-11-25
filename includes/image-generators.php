<?php

namespace RenewAI\FeaturedImageGenerator\ImageGenerators;

use function RenewAI\FeaturedImageGenerator\renewai_ig1;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;
use WP_Error;
/**
 * Abstract base class for image generators.
 */
abstract class ImageGenerator {
    /**
     * Generate an image based on the given prompt and size.
     *
     * @param string $prompt The text prompt to generate the image from
     * @param string $size The desired image size
     * @return array|WP_Error Array containing image data or WP_Error on failure
     */
    public abstract function generate_image( string $prompt, string $size ) : array|WP_Error;

}

/**
 * DALL-E 3 image generator implementation using OpenAI API.
 */
class DallE3Generator extends ImageGenerator {
    private $api_key;

    /**
     * Constructor.
     *
     * @param string $api_key The OpenAI API key
     */
    public function __construct( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * Generate an image using the DALL-E 3 API.
     *
     * @param string $prompt The text prompt to generate the image from
     * @param string $size The desired image size
     * @return array|WP_Error Array containing image URL and content type, or WP_Error on failure
     */
    public function generate_image( string $prompt, string $size ) : array|WP_Error {
        renewai_ig1_log( "DALL-E 3: Generating image with size: " . $size );
        $dalle_size = $this->convert_size( $size );
        renewai_ig1_log( "DALL-E 3: Converted size to: " . $dalle_size );
        $body = [
            'model'           => 'dall-e-3',
            'prompt'          => $prompt,
            'size'            => $dalle_size,
            'n'               => 1,
            'quality'         => 'standard',
            'response_format' => 'url',
        ];
        renewai_ig1_log( "DALL-E 3: Sending request with body: " . wp_json_encode( $body ) );
        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'headers'     => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode( $body ),
            'timeout'     => 120,
            'httpversion' => '1.1',
            'sslverify'   => true,
            'blocking'    => true,
        ] );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            renewai_ig1_log( "DALL-E 3: Request failed: " . $error_message, 'error' );
            if ( strpos( $error_message, 'Operation timed out' ) !== false ) {
                return new WP_Error('dalle_api_timeout', esc_html__( 'The image generation request timed out. Please try again.', 'renewai-featured-image-generator' ));
            }
            return new WP_Error('dalle_api_error', sprintf( 
                /* translators: %s: Error message */
                esc_html__( 'DALL-E API request failed: %s', 'renewai-featured-image-generator' ),
                $error_message
             ));
        }
        $response_code = wp_remote_retrieve_response_code( $response );
        renewai_ig1_log( "DALL-E 3: Response code: " . $response_code );
        $body = wp_remote_retrieve_body( $response );
        renewai_ig1_log( "DALL-E 3: Response body: " . $body );
        if ( $response_code !== 200 ) {
            $error_message = "DALL-E 3 API request failed with status code: " . $response_code;
            if ( !empty( $body ) ) {
                $data = json_decode( $body, true );
                if ( isset( $data['error']['message'] ) ) {
                    $error_message .= " - " . $data['error']['message'];
                }
            }
            renewai_ig1_log( $error_message, 'error' );
            return new WP_Error('dalle_api_error', sprintf( 
                /* translators: %s: Error message */
                esc_html__( 'DALL-E API error: %s', 'renewai-featured-image-generator' ),
                $error_message
             ));
        }
        $data = json_decode( $body, true );
        if ( !isset( $data['data'][0]['url'] ) ) {
            renewai_ig1_log( "DALL-E 3: No image URL in response", 'error' );
            return new WP_Error('dalle_api_error', esc_html__( 'Failed to get image URL from DALL-E 3 API response', 'renewai-featured-image-generator' ));
        }
        // Generate a clean filename from the prompt
        $filename = sanitize_title( 'featured-image-' . substr( $prompt, 0, 50 ) ) . '.png';
        renewai_ig1_log( "DALL-E 3: Generated filename: " . $filename );
        return [
            'url'          => $data['data'][0]['url'],
            'filename'     => $filename,
            'content_type' => 'image/png',
        ];
    }

    /**
     * Convert internal size format to DALL-E 3 size format.
     *
     * @param string $size The internal size format
     * @return string The DALL-E 3 size format
     */
    private function convert_size( string $size ) : string {
        renewai_ig1_log( "DALL-E 3: Converting size from: " . $size );
        // Map internal sizes to DALL-E 3 sizes
        $size_map = [
            'square'         => '1024x1024',
            'square_hd'      => '1024x1024',
            'portrait'       => '1024x1792',
            'portrait_4_3'   => '1024x1792',
            'portrait_16_9'  => '1024x1792',
            'landscape'      => '1792x1024',
            'landscape_4_3'  => '1792x1024',
            'landscape_16_9' => '1792x1024',
        ];
        $dalle_size = $size_map[$size] ?? '1024x1024';
        renewai_ig1_log( "DALL-E 3: Converted to size: " . $dalle_size );
        return $dalle_size;
    }

}
