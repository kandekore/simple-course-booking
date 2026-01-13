<?php
/**
 * Frontend booking UI and add-to-cart logic
 */
if (!defined('ABSPATH')) exit;

class SCB_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_booking_ui'], 5);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_add_cart_item', [$this, 'adjust_quantity'], 10, 1);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_filter('woocommerce_cart_item_quantity', [$this, 'disable_cart_quantity'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
        add_action('woocommerce_order_status_completed', [$this, 'update_slot_capacity']);
        add_action('wp_footer', [$this, 'render_success_popup']);
    }

    public function update_slot_capacity($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_scb_recorded')) return;
        $updated = false;
        foreach ($order->get_items() as $item) {
            $slot_id = $item->get_meta('_scb_slot_id');
            $count = $item->get_meta('_scb_count');
            if ($slot_id === '' || empty($count)) continue;
            $product_id = $item->get_product_id();
            $slots = get_post_meta($product_id, '_scb_slots', true);
            if (isset($slots[$slot_id])) {
                $current_booked = isset($slots[$slot_id]['booked']) ? intval($slots[$slot_id]['booked']) : 0;
                $slots[$slot_id]['booked'] = $current_booked + intval($count);
                update_post_meta($product_id, '_scb_slots', $slots);
                $updated = true;
            }
        }
        if ($updated) {
            $order->update_meta_data('_scb_recorded', 'yes');
            $order->save();
        }
    }

    public function enqueue_assets() {
        if (!is_product()) return;
        global $post;
        $slots = get_post_meta($post->ID, '_scb_slots', true);
        if (empty($slots)) return;
        wp_enqueue_style('scb-style', SCB_URL . 'assets/css/style.css');
        wp_enqueue_script('scb-booking', SCB_URL . 'assets/js/booking.js', ['jquery'], false, true);
        wp_localize_script('scb-booking', 'SCB_SLOTS', $slots);
    }

    public function render_booking_ui() {
        global $product;
        $slots = get_post_meta($product->get_id(), '_scb_slots', true);
        if (empty($slots)) return;
        $slots = array_filter($slots, function($s){
            $capacity = intval($s['capacity']);
            $booked = isset($s['booked']) ? intval($s['booked']) : 0;
            return ($capacity - $booked) > 0;
        });
        if (empty($slots)) {
            echo '<p><strong>All sessions are fully booked.</strong></p>';
            return;
        }
        echo '<script id="scb-slots-data">window.SCB_SLOTS = ' . wp_json_encode($slots) . ';</script>';
        ?>
        <div id="scb-booking-wrapper">
            <div class="scb-step" id="scb-step-1">
                <h3>1. Choose a Session</h3>
                <?php foreach ($slots as $i => $slot): 
                    $remaining = intval($slot['capacity']) - (isset($slot['booked']) ? intval($slot['booked']) : 0); 
                    ?>
                    <label class="scb-slot-option">
                        <input type="radio" name="scb_slot" value="<?php echo esc_attr($i); ?>">
                        <?php echo esc_html(date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time'] . " ({$remaining} seats left)"); ?>
                    </label><br>
                <?php endforeach; ?>
            </div>
            <div class="scb-step hidden" id="scb-step-2">
                <h3>2. Number of Attendees</h3>
                <select id="scb-attendee-count" name="scb_attendee_count"><option value="">Choose…</option></select>
            </div>
            <div class="scb-step hidden" id="scb-step-3">
                <h3>3. Attendee Details</h3>
                <div id="scb-attendee-details"></div>
            </div>
            <div class="scb-step hidden" id="scb-step-4">
                <h3>4. How should instructions be delivered?</h3>
                <label><input type="radio" name="scb_email_send" value="purchaser"> Send ONLY to purchaser</label><br>
                <label><input type="radio" name="scb_email_send" value="all"> Send to purchaser AND attendees</label>
            </div>
            <div class="scb-step hidden" id="scb-step-5">
                <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt">Add to cart</button>
            </div>
        </div>
        <style>.quantity, form.cart > .single_add_to_cart_button { display:none !important; }</style>
        <?php
    }

    public function validate_add_to_cart($passed, $product_id, $qty) {
        if (!isset($_POST['add-to-cart']) || intval($_POST['add-to-cart']) !== $product_id) return $passed;
        $slots = get_post_meta($product_id, '_scb_slots', true);
        if (empty($slots)) return $passed;
        if (!isset($_POST['scb_slot'])) { wc_add_notice('Please select a session.', 'error'); return false; }
        
        $slot_id = $_POST['scb_slot'];
        $slot = $slots[$slot_id];

        if (empty($_POST['scb_attendee_count'])) { wc_add_notice('Please select number of attendees.', 'error'); return false; }
        if (empty($_POST['scb_attendees'])) { wc_add_notice('Please fill in attendee details.', 'error'); return false; }
        
        if (!empty($slot['zoom']) && !isset($_POST['scb_email_send'])) {
            wc_add_notice('Please choose how joining instructions should be delivered.', 'error');
            return false;
        }
        return $passed;
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!isset($_POST['scb_slot'])) return $cart_item_data;
        $cart_item_data['scb_slot'] = sanitize_text_field($_POST['scb_slot']);
        $cart_item_data['scb_attendee_count'] = intval($_POST['scb_attendee_count']);
        $cart_item_data['scb_attendees'] = isset($_POST['scb_attendees']) ? $_POST['scb_attendees'] : [];
        $cart_item_data['scb_email_send'] = sanitize_text_field($_POST['scb_email_send'] ?? 'purchaser');
        $cart_item_data['unique_key'] = md5(microtime());
        return $cart_item_data;
    }

    public function adjust_quantity($cart_item) {
        if (isset($cart_item['scb_attendee_count'])) { $cart_item['quantity'] = intval($cart_item['scb_attendee_count']); }
        return $cart_item;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!isset($cart_item['scb_slot'])) return $item_data;
        $slots = get_post_meta($cart_item['product_id'], '_scb_slots', true);
        if (isset($slots[$cart_item['scb_slot']])) {
            $slot = $slots[$cart_item['scb_slot']];
            $item_data[] = ['name' => 'Session', 'value' => date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time']];
        }
        $count = intval($cart_item['scb_attendee_count']);
        $html = '';
        if (!empty($cart_item['scb_attendees'])) {
            foreach ($cart_item['scb_attendees'] as $a) { $html .= esc_html($a['name'] . ' (' . $a['email'] . ')') . '<br>'; }
        }
        $item_data[] = ['name' => '(' . $count . ') Attendees', 'value' => $html];
        return $item_data;
    }

    public function disable_cart_quantity($product_quantity, $cart_item_key, $cart_item) {
        if (isset($cart_item['scb_attendee_count'])) { return '<strong>' . intval($cart_item['scb_attendee_count']) . '</strong>'; }
        return $product_quantity;
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!isset($values['scb_slot'])) return;
        $product_id = $item->get_product_id();
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[$values['scb_slot']];
        $extra_info = get_post_meta($product_id, '_scb_extra_info', true);

        $item->add_meta_data('_scb_slot_id', $values['scb_slot']);
        $item->add_meta_data('_scb_count', intval($values['scb_attendee_count']));
        $item->add_meta_data('Session', date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time']);
        $item->add_meta_data('Zoom Link', $slot['zoom'] ?? '');
        $item->add_meta_data('Meeting ID', $slot['meeting_id'] ?? '');
        $item->add_meta_data('Password', $slot['password'] ?? '');
        $item->add_meta_data('Extra Info', $extra_info);
        $item->add_meta_data('Email Mode', $values['scb_email_send']);
        
        $attendees = [];
        foreach ($values['scb_attendees'] as $a) { $attendees[] = $a['name'] . ' <' . $a['email'] . '>'; }
        $item->add_meta_data('Attendees', implode(', ', $attendees));
    }

    public function render_success_popup() {
        static $printed = false;
        if ($printed) return;
        $printed = true;
        $initial_class = isset($_GET['add-to-cart']) ? 'show' : '';
        ?>
        <div class="scb-success-toast <?php echo esc_attr($initial_class); ?>">Item added to cart!</div>
        <style>
            .scb-success-toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 4px; padding: 16px; position: fixed; z-index: 9999; right: 30px; bottom: 30px; opacity: 0; transition: opacity 0.5s, bottom 0.5s; pointer-events: none; }
            .scb-success-toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        </style>
        <script>
        jQuery(function($){
            var toastTimer = null;
            function showToast() {
                if (toastTimer) clearTimeout(toastTimer);
                $('.scb-success-toast').addClass('show');
                toastTimer = setTimeout(function(){ $('.scb-success-toast').removeClass('show'); }, 3000);
            }
            $(document.body).on('added_to_cart', function(){ showToast(); });
            if ($('.scb-success-toast').hasClass('show')) {
                setTimeout(function(){ $('.scb-success-toast').removeClass('show'); }, 3000);
            }
        });
        </script>
        <?php
    }
}