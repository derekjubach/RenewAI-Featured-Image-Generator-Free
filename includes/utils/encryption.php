<?php

namespace RenewAI\FeaturedImageGenerator\Utils;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Encryption
{
  private static $instance = null;
  private $salt;

  public static function get_instance(): self
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    $this->salt = $this->get_salt();
  }

  /**
   * Get or generate a salt for encryption.
   */
  private function get_salt(): string
  {
    $salt = get_option('renewai_ig1_salt');
    if (!$salt) {
      $salt = bin2hex(random_bytes(32));
      update_option('renewai_ig1_salt', $salt);
    }
    return $salt;
  }

  /**
   * Encrypt API key
   */
  public function encrypt($key)
  {
    if (!extension_loaded('openssl')) {
      return base64_encode($key);
    }
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($key, 'AES-256-CBC', $this->salt, 0, $iv);
    return base64_encode($iv . $encrypted);
  }

  /**
   * Decrypt API key
   */
  public function decrypt($encrypted_key)
  {
    if (empty($encrypted_key)) {
      return '';
    }
    if (!extension_loaded('openssl')) {
      return base64_decode($encrypted_key);
    }
    $encrypted = base64_decode($encrypted_key);
    $iv = substr($encrypted, 0, 16);
    $encrypted = substr($encrypted, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->salt, 0, $iv);
    return $decrypted !== false ? $decrypted : '';
  }
}
