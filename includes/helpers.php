<?php

namespace RenewAI\FeaturedImageGenerator;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

define('RENEWAI_IG1_IS_PREMIUM', true);  // Set to false to test free version

function renewai_ig1_is_premium(): bool
{
  return RENEWAI_IG1_IS_PREMIUM;
}

function renewai_ig1_get_default_generator(): string
{
  return renewai_ig1()->can_use_premium_code__premium_only() ? 'flux' : 'dalle';
}
