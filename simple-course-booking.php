<?php
/**
 * Plugin Name: Simple Course Booking
 * Description: Custom booking plugin with slots, attendees, and Zoom delivery.
 * Version: 1.1.0
 * Author: D Kandekore  
 */

if (!defined('ABSPATH')) exit;

// Define paths
define('SCB_PATH', plugin_dir_path(__FILE__));
define('SCB_URL', plugin_dir_url(__FILE__));

// Includes
require_once SCB_PATH . 'includes/class-frontend.php';
require_once SCB_PATH . 'includes/class-admin.php';
require_once SCB_PATH . 'includes/class-slots.php';
require_once SCB_PATH . 'includes/class-email.php';

class Simple_Course_Booking {
    public function __construct() {
        new SCB_Frontend();
        new SCB_Admin();
        new SCB_Slots();
        new SCB_Email();
    }
}

new Simple_Course_Booking();
