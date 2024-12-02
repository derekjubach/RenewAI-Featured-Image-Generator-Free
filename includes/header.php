<?php

use function RenewAI\FeaturedImageGenerator\renewai_ig1;
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Get the current admin page slug
 * 
 * @var string $current_page The current page slug from $_GET
 */
// Verify nonce for page navigation
$current_page = '';
if ( isset( $_GET['page'] ) ) {
    if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'renewai_ig1_page_navigation' ) ) {
        $current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
    }
}
?>

<div class="renewai-ig1-header">
  <h3 class="renewai-ig1-title"><?php 
esc_html_e( 'RenewAI Featured Image Generator', 'renewai-featured-image-generator' );
?></h3>
  <nav class="renewai-ig1-nav">
    <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=renewai-ig1-settings' ) );
?>"
      class="<?php 
echo ( $current_page === 'renewai-ig1-settings' ? 'active' : '' );
?>">
      <?php 
esc_html_e( 'Settings', 'renewai-featured-image-generator' );
?>
    </a>
    <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=renewai-ig1-api-keys' ) );
?>"
      class="<?php 
echo ( $current_page === 'renewai-ig1-api-keys' ? 'active' : '' );
?>">
      <?php 
esc_html_e( 'API Keys', 'renewai-featured-image-generator' );
?>
    </a>
    <?php 
?>
    <a href="<?php 
echo esc_url( admin_url( 'admin.php?page=renewai-ig1-help' ) );
?>"
      class="<?php 
echo ( $current_page === 'renewai-ig1-help' ? 'active' : '' );
?>">
      <?php 
esc_html_e( 'Help', 'renewai-featured-image-generator' );
?>
    </a>
  </nav>
</div>