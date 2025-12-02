<?php
/**
 * Frontend booking UI and add-to-cart logic
 */
if (!defined('ABSPATH')) exit;

class SCB_Frontend {

    public function __construct() {

        // Load assets AFTER WooCommerce enqueues scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);

        // IMPORTANT: Render booking UI BEFORE the WooCommerce form
        add_action('woocommerce_before_add_to_cart_form', [$this, 'render_booking_ui']);

        // Validation + cart/meta handling
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
    }


    /** Load CSS + JS only on booking products */
    public function enqueue_assets() {
        if (!is_product()) return;

        global $post;
        $slots = get_post_meta($post->ID, '_scb_slots', true);

        if (empty($slots)) return;

        wp_enqueue_style('scb-style', SCB_URL . 'assets/css/style.css');
        wp_enqueue_script('scb-booking', SCB_URL . 'assets/js/booking.js', ['jquery'], false, true);

        // Initial localization (full slot list)
        wp_localize_script('scb-booking', 'SCB_SLOTS', $slots);
    }


    /** Render UI */
    public function render_booking_ui() {
        global $product;

        $slots = get_post_meta($product->get_id(), '_scb_slots', true);
        if (empty($slots)) return;

        // Remove full slots
        $slots = array_filter($slots, function($s){
            return ($s['capacity'] - $s['booked']) > 0;
        });

        // Reindex to numeric keys (VERY IMPORTANT)
        $slots = array_values($slots);

        if (empty($slots)) {
            echo '<p><strong>All sessions are booked.</strong></p>';
            return;
        }

        // Localize filtered slots for JS
        wp_localize_script('scb-booking', 'SCB_SLOTS', $slots);
        ?>

        <div id="scb-booking-wrapper">

            <!-- STEP 1 -->
            <div class="scb-step" id="scb-step-1">
                <h3>1. Choose a Session</h3>
                <?php foreach ($slots as $i => $slot): 
                    $remaining = $slot['capacity'] - $slot['booked']; ?>
                    <label class="scb-slot-option">
                        <input type="radio" name="scb_slot" value="<?php echo $i; ?>">
                        <?php echo esc_html(
                            date('D j M', strtotime($slot['date']))
                            . ' @ ' . $slot['time']
                            . " ({$remaining} seats left)"
                        ); ?>
                    </label><br>
                <?php endforeach; ?>
            </div>

            <!-- STEP 2 -->
            <div class="scb-step hidden" id="scb-step-2">
                <h3>2. Number of Attendees</h3>
                <select id="scb-attendee-count" name="scb_attendee_count">
                    <option value="">Choose…</option>
                </select>
            </div>

            <!-- STEP 3 -->
            <div class="scb-step hidden" id="scb-step-3">
                <h3>3. Attendee Details</h3>
                <div id="scb-attendee-details"></div>
            </div>

            <!-- STEP 4 -->
            <div class="scb-step hidden" id="scb-step-4">
                <h3>4. How should Zoom instructions be delivered?</h3>
                <label><input type="radio" name="scb_email_send" value="purchaser"> Send ONLY to purchaser</label><br>
                <label><input type="radio" name="scb_email_send" value="all"> Send to purchaser AND attendees</label>
            </div>

            <!-- STEP 5 — CUSTOM ADD TO CART BUTTON INSIDE THE FORM -->
            <div class="scb-step hidden" id="scb-step-5">
                <button type="submit"
                    name="add-to-cart"
                    value="<?php echo esc_attr($product->get_id()); ?>"
                    class="single_add_to_cart_button button alt">
                    Add to cart
                </button>
            </div>

        </div>

        <?php
        // FIX: Output filtered slot data as inline JS BEFORE booking.js runs
        echo '<script id="scb-slots-data">window.SCB_SLOTS = ' . wp_json_encode($slots) . ';</script>';

        // Hide default WooCommerce quantity + button (not our custom one)
        echo '<style>
            .quantity { display:none !important; }
            form.cart > .single_add_to_cart_button { display:none !important; }
        </style>';
    }


    /** Validate required fields */
    public function validate_add_to_cart($passed, $product_id, $qty) {

        if (!isset($_POST['scb_slot'])) {
            wc_add_notice('Please select a session.', 'error');
            return false;
        }

        if (empty($_POST['scb_attendee_count'])) {
            wc_add_notice('Please select number of attendees.', 'error');
            return false;
        }

        if (empty($_POST['scb_attendees'])) {
            wc_add_notice('Please fill in attendee details.', 'error');
            return false;
        }

        if (!isset($_POST['scb_email_send'])) {
            wc_add_notice('Please choose how Zoom instructions should be delivered.', 'error');
            return false;
        }

        // Capacity validation
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[intval($_POST['scb_slot'])];

        $remaining = $slot['capacity'] - $slot['booked'];
        $attendees = intval($_POST['scb_attendee_count']);

        if ($attendees > $remaining) {
            wc_add_notice('Not enough seats available.', 'error');
            return false;
        }

        return $passed;
    }


    /** Add to cart item data */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $cart_item_data['scb_slot'] = sanitize_text_field($_POST['scb_slot']);
        $cart_item_data['scb_attendee_count'] = intval($_POST['scb_attendee_count']);
        $cart_item_data['scb_attendees'] = $_POST['scb_attendees'];
        $cart_item_data['scb_email_send'] = sanitize_text_field($_POST['scb_email_send']);
        $cart_item_data['unique_key'] = md5(microtime());

        return $cart_item_data;
    }


    /** Output in cart */
    public function display_cart_item_data($item_data, $cart_item) {
        if (!isset($cart_item['scb_slot'])) return $item_data;

        $slots = get_post_meta($cart_item['product_id'], '_scb_slots', true);
        $slot = $slots[$cart_item['scb_slot']];

        $item_data[] = [
            'name'  => 'Session',
            'value' => date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time']
        ];

        $list = '';
        foreach ($cart_item['scb_attendees'] as $a) {
            $list .= esc_html($a['name'] . ' (' . $a['email'] . ')') . '<br>';
        }

        $item_data[] = [
            'name'  => 'Attendees',
            'value' => $list
        ];

        return $item_data;
    }


    /** Add booking data to order */
    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!isset($values['scb_slot'])) return;

        $slots = get_post_meta($item->get_product_id(), '_scb_slots', true);
        $slot = $slots[$values['scb_slot']];

        $item->add_meta_data('Session', date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time']);
        $item->add_meta_data('Zoom Link', $slot['zoom']);
        $item->add_meta_data('Email Mode', $values['scb_email_send']);

        $attendees = [];
        foreach ($values['scb_attendees'] as $a) {
            $attendees[] = $a['name'] . ' <' . $a['email'] . '>';
        }

        $item->add_meta_data('Attendees', implode(', ', $attendees));
    }
}
