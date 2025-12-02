<?php
/**
 * Email handling for Simple Course Booking
 */
if (!defined('ABSPATH')) exit;

class SCB_Email {

    public function __construct() {
        add_action('woocommerce_order_status_completed', [$this, 'send_zoom_emails']);
        add_action('add_meta_boxes', [$this, 'add_resend_metabox']);
        add_action('admin_post_scb_resend_zoom', [$this, 'resend_zoom']);
    }

    /** Add a resend button metabox on order screen */
    public function add_resend_metabox() {
        add_meta_box(
            'scb_resend_zoom',
            'Course Booking Emails',
            [$this, 'render_resend_metabox'],
            'shop_order',
            'side',
            'default'
        );
    }

    public function render_resend_metabox($post) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=scb_resend_zoom&order=' . $post->ID), 'scb_resend_zoom');
        echo '<a href="' . $url . '" class="button button-primary">Resend Zoom Instructions</a>';
    }

    /** Resend email via admin button */
    public function resend_zoom() {
        if (!current_user_can('manage_woocommerce')) wp_die('Not permitted');
        if (!wp_verify_nonce($_GET['_wpnonce'], 'scb_resend_zoom')) wp_die('Nonce error');

        $order_id = intval($_GET['order']);
        $this->process_email_send($order_id);

        wp_safe_redirect(wp_get_referer());
        exit;
    }

    /** Triggered automatically on order completed */
    public function send_zoom_emails($order_id) {
        $this->process_email_send($order_id);
    }

    /** Main email processing function */
    private function process_email_send($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        foreach ($order->get_items() as $item) {
            $session = $item->get_meta('Session');
            $zoom    = $item->get_meta('Zoom Link');
            $mode    = $item->get_meta('Email Mode');
            $att_raw = $item->get_meta('Attendees');

            if (!$session || !$zoom || !$mode) continue;

            $attendees = array_map('trim', explode(',', $att_raw));

            // Build purchaser email
            $purchaser_email = $order->get_billing_email();
            $subject = "Your Course Booking Details";
            $message = $this->build_html_email($session, $zoom, $attendees);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            wp_mail($purchaser_email, $subject, $message, $headers);

            // If mode is purchaser + attendees
            if ($mode === 'all') {
                foreach ($attendees as $entry) {
                    if (!preg_match('/(.*)<(.*)>/', $entry, $m)) continue;
                    $att_name = trim($m[1]);
                    $att_email = trim($m[2]);

                    $msg = $this->build_html_email($session, $zoom, [$entry], true);
                    wp_mail($att_email, $subject, $msg, $headers);
                }
            }
        }
    }

    /** Build HTML email template */
    private function build_html_email($session, $zoom, $attendees, $personalized = false) {
        $greeting = $personalized ? ('Hi ' . preg_replace('/<(.*)>/', '', $attendees[0])) : 'Hello,';

        ob_start(); ?>

        <div style="font-family:Arial, sans-serif; font-size:15px; line-height:1.6; color:#333;">
            <p><?php echo esc_html($greeting); ?></p>

            <p>You are registered for the following course session:</p>

            <p><strong>Session:</strong><br><?php echo esc_html($session); ?></p>

            <p><strong>Zoom Link:</strong><br>
                <a href="<?php echo esc_url($zoom); ?>" target="_blank">Join via Zoom</a>
            </p>

            <?php if (!$personalized): ?>
                <p><strong>Attendees:</strong><br>
                <?php foreach ($attendees as $a) echo esc_html($a) . '<br>'; ?></p>
            <?php endif; ?>

            <p>If you have any questions, feel free to reply to this email.</p>
            <p>Thank you!</p>
        </div>

        <?php return ob_get_clean();
    }
}
