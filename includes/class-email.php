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
        echo '<a href="' . $url . '" class="button button-primary">Resend Teams Instructions</a>';
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
        $session    = $item->get_meta('Session');
        $zoom       = $item->get_meta('Zoom Link');
        $meeting_id = $item->get_meta('Meeting ID');
        $password   = $item->get_meta('Password');
        $extra_info = $item->get_meta('Extra Info');
        $mode       = $item->get_meta('Email Mode');
        $att_raw    = $item->get_meta('Attendees');

        if (!$session || !$mode) continue;

        $attendees = array_map('trim', explode(',', $att_raw));
        $subject = "Your Course Booking Details";
        
        // Build message including new fields
        $message = $this->build_html_email($session, $zoom, $attendees, false, $meeting_id, $password, $extra_info);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($order->get_billing_email(), $subject, $message, $headers);

        if ($mode === 'all') {
            foreach ($attendees as $entry) {
                if (!preg_match('/(.*)<(.*)>/', $entry, $m)) continue;
                $msg = $this->build_html_email($session, $zoom, [$entry], true, $meeting_id, $password, $extra_info);
                wp_mail(trim($m[2]), $subject, $msg, $headers);
            }
        }
    }
}

    /** Build HTML email template */
    private function build_html_email($session, $zoom, $attendees, $personalized = false, $meeting_id = '', $password = '', $extra = '') {
    $greeting = $personalized ? ('Hi ' . preg_replace('/<(.*)>/', '', $attendees[0])) : 'Hello,';

    ob_start(); ?>
    <div style="font-family:Arial, sans-serif; font-size:15px; line-height:1.6; color:#333;">
        <p><?php echo esc_html($greeting); ?></p>
        <p>You are registered for the following course session:</p>
        <p><strong>Session:</strong><br><?php echo esc_html($session); ?></p>

        <?php if (!empty($zoom)): ?>
            <p><strong>Joining Link:</strong><br>
                <a href="<?php echo esc_url($zoom); ?>" target="_blank">Join Meeting</a>
            </p>
        <?php endif; ?>

        <?php if (!empty($meeting_id)): ?>
            <p><strong>Meeting ID:</strong> <?php echo esc_html($meeting_id); ?></p>
        <?php endif; ?>

        <?php if (!empty($password)): ?>
            <p><strong>Password:</strong> <?php echo esc_html($password); ?></p>
        <?php endif; ?>

        <?php if (!empty($extra)): ?>
            <div style="margin-top:15px; padding:15px; background-color:#f4f4f4; border-left:4px solid #007cba;">
                <strong>Additional Information:</strong><br>
                <?php echo wpautop(esc_html($extra)); ?>
            </div>
        <?php endif; ?>

        <?php if (!$personalized): ?>
            <p><strong>Attendees:</strong><br>
            <?php foreach ($attendees as $a) echo esc_html($a) . '<br>'; ?></p>
        <?php endif; ?>

        <p>Thank you!</p>
    </div>
    <?php return ob_get_clean();
}