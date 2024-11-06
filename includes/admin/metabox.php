<?php

namespace RenewAI\FeaturedImageGenerator\Admin;

use RenewAI\FeaturedImageGenerator\API\Handler as APIHandler;
use WP_Error;
use RenewAI\FeaturedImageGenerator\API\Handler;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_log;
use function RenewAI\FeaturedImageGenerator\renewai_ig1;
use function RenewAI\FeaturedImageGenerator\renewai_ig1_get_default_generator;
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}
class MetaBox {
    private $api_handler;

    /**
     * Constructor. Sets up hooks and initializes API handler.
     */
    public function __construct() {
        $this->api_handler = new Handler();
        add_action( 'add_meta_boxes', [$this, 'add_meta_box'] );
        add_action( 'wp_ajax_renewai_ig1_generate_prompt', [$this, 'ajax_generate_prompt'] );
        add_action( 'wp_ajax_renewai_ig1_generate_image', [$this, 'ajax_generate_image'] );
    }

    /**
     * Add the meta box to post editor.
     */
    public function add_meta_box() : void {
        add_meta_box(
            'renewai-ig1-metabox',
            esc_html__( 'Generate Featured Image', 'renewai-featured-image-generator' ),
            [$this, 'render_meta_box'],
            'post',
            'side',
            'high',
            [
                'generator' => get_option( 'renewai_ig1_image_generator', renewai_ig1_get_default_generator() ),
            ]
        );
    }

    /**
     * Render the meta box content.
     *
     * @param WP_Post $post The post object
     * @param array $meta The meta box arguments
     */
    public function render_meta_box( $post, $meta ) : void {
        wp_nonce_field( 'renewai_ig1_generate_image', 'renewai_ig1_nonce' );
        // Get current settings and ensure we have a valid generator
        $current_generator = $meta['args']['generator'] ?? 'flux';
        // Debug output
        error_log( 'Current generator in metabox: ' . $current_generator );
        // Get model display name
        $model_display = 'DALL-E 3';
        if ( $current_generator === 'flux' ) {
            $current_model = get_option( 'renewai_ig1_fal_model', 'flux_dev' );
            $model_names = [
                'flux_pro_1_1' => 'Flux Pro 1.1',
                'flux_pro'     => 'Flux Pro',
                'flux_dev'     => 'Flux Dev',
            ];
            $model_display = $model_names[$current_model] ?? 'Flux Dev';
        }
        // Get prompt model display name for premium users
        $prompt_model_display = '';
        // Define sizes based on generator
        $sizes = $this->get_sizes_for_generator( $current_generator );
        // Get available styles for premium users
        $styles = [
            'natural'   => __( 'Natural', 'renewai-featured-image-generator' ),
            'artistic'  => __( 'Artistic', 'renewai-featured-image-generator' ),
            'realistic' => __( 'Realistic', 'renewai-featured-image-generator' ),
            'abstract'  => __( 'Abstract', 'renewai-featured-image-generator' ),
        ];
        $current_style = get_option( 'renewai_ig1_image_style', 'natural' );
        ?>
    <div id="renewai-ig1-metabox" data-generator="<?php 
        echo esc_attr( $current_generator );
        ?>">
      <div class="renewai-ig1-metabox-content">
        <div class="renewai-ig1-info">
          <p><?php 
        echo esc_html__( 'Image Generator:', 'renewai-featured-image-generator' );
        ?>
            <strong><?php 
        echo esc_html( ucfirst( $current_generator ) );
        ?></strong>
          </p>
          <p><?php 
        echo esc_html__( 'Model:', 'renewai-featured-image-generator' );
        ?>
            <strong><?php 
        echo esc_html( $model_display );
        ?></strong>
          </p>
          <?php 
        ?>
        </div>

        <div class="renewai-ig1-prompt-section">
          <textarea
            id="renewai-ig1-prompt"
            name="renewai-ig1-prompt"
            rows="4"
            maxlength="1000"
            placeholder="<?php 
        esc_attr_e( 'Generated prompt will appear here...', 'renewai-featured-image-generator' );
        ?>"></textarea>
          <div id="renewai-ig1-char-count">0 / 1000</div>
          <button type="button" id="renewai-ig1-generate-prompt" class="button">
            <?php 
        esc_html_e( 'Generate Prompt', 'renewai-featured-image-generator' );
        ?>
            <span class="spinner"></span>
          </button>
        </div>

        <div id="renewai-ig1-image-options" style="display: none;">
          <div class="renewai-ig1-image-controls">
            <label for="renewai-ig1-size">
              <?php 
        esc_html_e( 'Image Size:', 'renewai-featured-image-generator' );
        ?>
            </label>
            <select id="renewai-ig1-size" name="renewai-ig1-size">
              <?php 
        foreach ( $sizes as $value => $label ) {
            printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $label ) );
        }
        ?>
            </select>
            <button type="button" id="renewai-ig1-generate-image" class="button button-primary">
              <?php 
        esc_html_e( 'Generate Image', 'renewai-featured-image-generator' );
        ?>
              <span class="spinner"></span>
            </button>
          </div>
        </div>
        <div id="renewai-ig1-status-message"></div>
      </div>
    </div>
<?php 
    }

    /**
     * Handle AJAX request to generate image prompt.
     */
    public function ajax_generate_prompt() : void {
        check_ajax_referer( 'renewai_ig1_generate_image', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'renewai-featured-image-generator' ) );
            return;
        }
        $post_id = intval( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( !$post_id ) {
            wp_send_json_error( esc_html__( 'Invalid post ID.', 'renewai-featured-image-generator' ) );
            return;
        }
        $post = get_post( $post_id );
        if ( !$post ) {
            wp_send_json_error( esc_html__( 'Post not found.', 'renewai-featured-image-generator' ) );
            return;
        }
        $content = $post->post_title . "\n\n" . $post->post_content;
        $content = wp_strip_all_tags( $content );
        $content = substr( $content, 0, 1500 );
        // Limit content length
        $prompt = $this->api_handler->call_openai_api( $content );
        if ( is_wp_error( $prompt ) ) {
            wp_send_json_error( $prompt->get_error_message() );
            return;
        }
        wp_send_json_success( [
            'prompt' => $prompt,
        ] );
    }

    /**
     * Handle AJAX request to generate image.
     */
    public function ajax_generate_image() : void {
        check_ajax_referer( 'renewai_ig1_generate_image', 'nonce' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'renewai-featured-image-generator' ) );
            return;
        }
        $post_id = intval( wp_unslash( $_POST['post_id'] ?? 0 ) );
        $prompt = wp_unslash( sanitize_textarea_field( $_POST['prompt'] ?? '' ) );
        $size = wp_unslash( sanitize_text_field( $_POST['size'] ?? 'square_hd' ) );
        if ( !$post_id || !$prompt ) {
            wp_send_json_error( esc_html__( 'Missing required parameters.', 'renewai-featured-image-generator' ) );
            return;
        }
        $result = $this->api_handler->generate_image( $prompt, $size );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
            return;
        }
        // Set as featured image
        $attachment_id = $this->create_attachment( $result['url'], $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( $attachment_id->get_error_message() );
            return;
        }
        set_post_thumbnail( $post_id, $attachment_id );
        wp_send_json_success( [
            'attachment_id' => $attachment_id,
        ] );
    }

    /**
     * Create attachment from generated image URL.
     *
     * @param string $image_url The URL of the generated image
     * @param int $post_id The post ID to attach the image to
     * @return int|WP_Error Attachment ID or error
     */
    private function create_attachment( $image_url, $post_id ) : int|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        // Download the file
        $tmp = download_url( $image_url );
        if ( is_wp_error( $tmp ) ) {
            renewai_ig1_log( 'Failed to download image: ' . $tmp->get_error_message(), 'error' );
            return new WP_Error('download_failed', sprintf( 
                /* translators: %s: Error message */
                esc_html__( 'Failed to download image: %s', 'renewai-featured-image-generator' ),
                $tmp->get_error_message()
             ));
        }
        // Prepare the file array
        $file_array = array(
            'name'     => 'featured-image-' . $post_id . '-' . time() . '.png',
            'tmp_name' => $tmp,
            'type'     => 'image/png',
            'error'    => 0,
            'size'     => filesize( $tmp ),
        );
        // Add upload filters
        add_filter( 'upload_mimes', function ( $mimes ) {
            $mimes['png'] = 'image/png';
            return $mimes;
        } );
        add_filter(
            'wp_check_filetype_and_ext',
            function (
                $data,
                $file,
                $filename,
                $mimes
            ) {
                if ( empty( $data['type'] ) ) {
                    $wp_filetype = wp_check_filetype( $filename, $mimes );
                    $ext = $wp_filetype['ext'];
                    $type = $wp_filetype['type'];
                    $proper_filename = $filename;
                    if ( $type === 'image/png' ) {
                        $data['ext'] = 'png';
                        $data['type'] = 'image/png';
                        $data['proper_filename'] = $proper_filename;
                    }
                }
                return $data;
            },
            10,
            4
        );
        // Do the upload
        $id = media_handle_sideload( $file_array, $post_id );
        // Clean up
        if ( file_exists( $tmp ) ) {
            wp_delete_file( $tmp );
        }
        if ( is_wp_error( $id ) ) {
            renewai_ig1_log( 'Failed to create attachment: ' . $id->get_error_message(), 'error' );
            return new WP_Error('sideload_failed', sprintf( 
                /* translators: %s: Error message */
                esc_html__( 'Failed to create attachment: %s', 'renewai-featured-image-generator' ),
                $id->get_error_message()
             ));
        }
        renewai_ig1_log( 'Successfully created attachment with ID: ' . $id );
        return $id;
    }

    /**
     * Get available sizes for the specified generator.
     *
     * @param string $generator The generator type ('dalle' or 'flux')
     * @return array Array of size options
     */
    private function get_sizes_for_generator( string $generator ) : array {
        if ( $generator === 'dalle' ) {
            return [
                'square'    => esc_html__( 'Square (1024x1024)', 'renewai-featured-image-generator' ),
                'portrait'  => esc_html__( 'Portrait (1024x1792)', 'renewai-featured-image-generator' ),
                'landscape' => esc_html__( 'Landscape (1792x1024)', 'renewai-featured-image-generator' ),
            ];
        } else {
            // Flux
            return [
                'square_hd'      => esc_html__( 'Square HD', 'renewai-featured-image-generator' ),
                'square'         => esc_html__( 'Square', 'renewai-featured-image-generator' ),
                'portrait_4_3'   => esc_html__( 'Portrait 4:3', 'renewai-featured-image-generator' ),
                'portrait_16_9'  => esc_html__( 'Portrait 16:9', 'renewai-featured-image-generator' ),
                'landscape_4_3'  => esc_html__( 'Landscape 4:3', 'renewai-featured-image-generator' ),
                'landscape_16_9' => esc_html__( 'Landscape 16:9', 'renewai-featured-image-generator' ),
            ];
        }
    }

}
