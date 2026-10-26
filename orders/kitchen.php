<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Legacy URL Redirect Wrapper: orders/kitchen.php -> orders/kitchen_queue.php
 */
require_once __DIR__ . '/../includes/functions.php';
redirect('orders/kitchen_queue.php');
