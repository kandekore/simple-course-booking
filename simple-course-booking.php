<?php
/**
 * Plugin Name: Simple Course Booking
 * Description: Adds date/time slots with capacity, attendee booking, Zoom links, admin booking dashboard, and custom email delivery.
 * Version: 1.0
 * Author: D Kandekore
 */

if (!defined('ABSPATH')) exit;

class Simple_Course_Booking {

    public function __construct() {

        // Product edit slot fields
        add_action('add_meta_boxes', [$this, 'add_slots_metabox']);
        add_action('save_post_product', [$this, 'save_slots']);

        // Frontend display
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_booking_fields']);

        // Cart validation + save data
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data',       [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data',            [$this, 'display_cart_item_data'], 10, 2);

        // Add to order
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);

        // Deduct capacity on completion
        add_action('woocommerce_order_status_completed', [$this, 'reduce_capacity_on_completed']);

        // Emails
        add_action('woocommerce_order_status_completed', [$this, 'send_zoom_emails']);

        // Admin menu
        add_action('admin_menu', [$this, 'register_admin_pages']);
    }

    /* -----------------------------------------
        PRODUCT EDIT SCREEN – SLOTS METABOX
    ------------------------------------------*/

    public function add_slots_metabox() {
        add_meta_box(
            'scb_slots',
            'Course Booking Slots',
            [$this, 'render_slots_metabox'],
            'product',
            'normal',
            'default'
        );
    }

    public function render_slots_metabox($post) {
        $slots = get_post_meta($post->ID, '_scb_slots', true);
        if (!is_array($slots)) $slots = [];

        wp_nonce_field('scb_save_slots', 'scb_slots_nonce');

        echo '<div id="scb-slots-wrap">';
        echo '<p><strong>Add date/time slots for this course.</strong></p>';

        echo '<table class="widefat scb-slots-table">';
        echo '<thead><tr>
                <th>Date</th>
                <th>Time</th>
                <th>Duration (mins)</th>
                <th>Capacity</th>
                <th>Zoom Link</th>
                <th>Remove</th>
              </tr></thead><tbody>';

        if (!empty($slots)) {
            foreach ($slots as $index => $slot) {
                echo '<tr>';
                echo '<td><input type="date" name="scb_slots['.$index.'][date]" value="'.esc_attr($slot['date']).'" required></td>';
                echo '<td><input type="time" name="scb_slots['.$index.'][time]" value="'.esc_attr($slot['time']).'" required></td>';
                echo '<td><input type="number" name="scb_slots['.$index.'][duration]" value="'.esc_attr($slot['duration']).'" min="1" required></td>';
                echo '<td><input type="number" name="scb_slots['.$index.'][capacity]" value="'.esc_attr($slot['capacity']).'" min="1" required></td>';
                echo '<td><input type="text" name="scb_slots['.$index.'][zoom]" value="'.esc_attr($slot['zoom']).'" placeholder="https://..."></td>';
                echo '<td><a href="#" class="scb-remove-slot">X</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<p><a href="#" id="scb-add-slot" class="button">+ Add Slot</a></p>';

        // JS template row
        ?>
        <script>
        jQuery(function($){

            $('#scb-add-slot').on('click', function(e){
                e.preventDefault();
                let rowCount = $('.scb-slots-table tbody tr').length;
                $('.scb-slots-table tbody').append(`
                    <tr>
                        <td><input type="date" name="scb_slots[${rowCount}][date]" required></td>
                        <td><input type="time" name="scb_slots[${rowCount}][time]" required></td>
                        <td><input type="number" name="scb_slots[${rowCount}][duration]" min="1" required></td>
                        <td><input type="number" name="scb_slots[${rowCount}][capacity]" min="1" required></td>
                        <td><input type="text" name="scb_slots[${rowCount}][zoom]" placeholder="https://..."></td>
                        <td><a href="#" class="scb-remove-slot">X</a></td>
                    </tr>
                `);
            });

            $(document).on('click','.scb-remove-slot',function(e){
                e.preventDefault();
                $(this).closest('tr').remove();
            });

        });
        </script>
        <?php

        echo '</div>';
    }

    public function save_slots($post_id) {
        if (!isset($_POST['scb_slots_nonce']) || !wp_verify_nonce($_POST['scb_slots_nonce'], 'scb_save_slots')) return;

        if (isset($_POST['scb_slots']) && is_array($_POST['scb_slots'])) {
            $slots = array_values($_POST['scb_slots']);

            // Add initial booked count if missing
            foreach ($slots as &$slot) {
                if (!isset($slot['booked'])) $slot['booked'] = 0;
            }
            update_post_meta($post_id, '_scb_slots', $slots);
        } else {
            delete_post_meta($post_id, '_scb_slots');
        }
    }

    /* -----------------------------------------
        FRONTEND DISPLAY – SLOT + ATTENDEE FIELDS
    ------------------------------------------*/

    public function render_booking_fields() {
        global $product;

        $slots = get_post_meta($product->get_id(), '_scb_slots', true);
        if (empty($slots)) return;

        // Filter out fully booked slots
        $slots = array_filter($slots, function($slot){
            return $slot['capacity'] - $slot['booked'] > 0;
        });

        if (empty($slots)) {
            echo "<p><strong>All sessions are fully booked.</strong></p>";
            return;
        }

        ?>
        <div id="scb-booking-fields">
            <h3>Choose Your Session</h3>

            <p>
            <?php foreach ($slots as $i => $slot): 
                $remaining = $slot['capacity'] - $slot['booked'];
                ?>
                <label>
                    <input type="radio" name="scb_slot" value="<?php echo $i; ?>" required>
                    <?php echo esc_html(date("D j M", strtotime($slot['date'])) . " – " . $slot['time'] . " (" . $remaining . " seats left)"); ?>
                </label><br>
            <?php endforeach; ?>
            </p>

            <h3>Number of Attendees</h3>
            <p><select name="scb_attendee_count" id="scb-attendee-count" required>
                <option value="">Choose…</option>
            </select></p>

            <div id="scb-attendee-details"></div>

            <h3>How should Zoom instructions be delivered?</h3>
            <p>
                <label><input type="radio" name="scb_email_send" value="purchaser" required> Send ONLY to purchaser</label><br>
                <label><input type="radio" name="scb_email_send" value="all" required> Send to purchaser AND all attendees</label>
            </p>
        </div>

        <script>
        jQuery(function($){
            let slots = <?php echo json_encode($slots); ?>;

            $('input[name="scb_slot"]').on('change', function(){
                let slotId = $(this).val();
                let remaining = slots[slotId].capacity - slots[slotId].booked;

                $('#scb-attendee-count').html('<option value="">Choose…</option>');
                for (let i=1; i<=remaining; i++){
                    $('#scb-attendee-count').append(`<option value="${i}">${i}</option>`);
                }
            });

            $('#scb-attendee-count').on('change', function(){
                let c = $(this).val();
                let html = '<h3>Attendee Details</h3>';
                for (let i=1; i<=c; i++){
                    html += `
                        <div class="scb-attendee-block">
                            <p><strong>Attendee #${i}</strong></p>
                            <p>Name: <input type="text" name="scb_attendees[${i}][name]" required></p>
                            <p>Email: <input type="email" name="scb_attendees[${i}][email]" required></p>
                        </div>
                    `;
                }
                $('#scb-attendee-details').html(html);
            });
        });
        </script>
        <?php
    }

    /* -----------------------------------------
        VALIDATION
    ------------------------------------------*/

    public function validate_add_to_cart($passed, $product_id, $qty) {

        if (empty($_POST['scb_slot']) || !isset($_POST['scb_attendee_count'])) {
            wc_add_notice("Please select a session and attendee count.", "error");
            return false;
        }

        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot_id = intval($_POST['scb_slot']);
        $slot = $slots[$slot_id];

        $remaining = $slot['capacity'] - $slot['booked'];
        $requested = intval($_POST['scb_attendee_count']);

        if ($requested > $remaining) {
            wc_add_notice("Not enough seats available for that session.", "error");
            return false;
        }

        // Validate attendee details
        if (empty($_POST['scb_attendees']) || count($_POST['scb_attendees']) != $requested) {
            wc_add_notice("Please fill in all attendee details.", "error");
            return false;
        }

        return $passed;
    }

    /* -----------------------------------------
        CART ITEM DATA
    ------------------------------------------*/

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $slots = get_post_meta($product_id, '_scb_slots', true);

        $slot_id = intval($_POST['scb_slot']);
        $cart_item_data['scb_slot']      = $slot_id;
        $cart_item_data['scb_attendees'] = $_POST['scb_attendees'];
        $cart_item_data['scb_send']      = sanitize_text_field($_POST['scb_email_send']);

        $cart_item_data['unique_key'] = md5(microtime().rand());
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['scb_slot'])) {

            $slots = get_post_meta($cart_item['product_id'], '_scb_slots', true);
            $slot  = $slots[$cart_item['scb_slot']];

            $item_data[] = [
                'name'  => 'Session',
                'value' => date("D j M", strtotime($slot['date'])) . " – " . $slot['time']
            ];

            $list = "";
            foreach ($cart_item['scb_attendees'] as $a) {
                $list .= esc_html($a['name'] . " (" . $a['email'] . ")") . "<br>";
            }
            $item_data[] = [
                'name'  => 'Attendees',
                'value' => $list
            ];
        }

        return $item_data;
    }

    /* -----------------------------------------
        ORDER META
    ------------------------------------------*/

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {

        if (!isset($values['scb_slot'])) return;

        $product_id = $item->get_product_id();
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[$values['scb_slot']];

        $item->add_meta_data('Session', date("D j M", strtotime($slot['date'])) . " – " . $slot['time']);
        $item->add_meta_data('Zoom Link', $slot['zoom']);

        $attendee_list = [];
        foreach ($values['scb_attendees'] as $a) {
            $attendee_list[] = $a['name'] . " <" . $a['email'] . ">";
        }

        $item->add_meta_data('Attendees', implode(", ", $attendee_list));
    }

    /* -----------------------------------------
        CAPACITY REDUCTION (ON ORDER COMPLETED)
    ------------------------------------------*/

    public function reduce_capacity_on_completed($order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();
            $slots = get_post_meta($product_id, '_scb_slots', true);

            $session_meta = $item->get_meta('Session');

            if (!$session_meta) continue;

            // Extract the slot ID by matching date/time
            foreach ($slots as $i => $slot) {
                $formatted = date("D j M", strtotime($slot['date'])) . " – " . $slot['time'];
                if ($formatted == $session_meta) {
                    $attendees = explode(",", $item->get_meta('Attendees'));
                    $count = count($attendees);

                    $slots[$i]['booked'] += $count;
                    update_post_meta($product_id, '_scb_slots', $slots);
                }
            }
        }
    }

    /* -----------------------------------------
        EMAIL SENDING
    ------------------------------------------*/

    public function send_zoom_emails($order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();
            $slots = get_post_meta($product_id, '_scb_slots', true);

            $session = $item->get_meta('Session');
            $zoom    = $item->get_meta('Zoom Link');

            $attendee_string = $item->get_meta('Attendees');
            $attendees = explode(",", $attendee_string);

            $subject = "Your Course Booking Details";
            $message = "Here are your booking details:\n\nSession: $session\nZoom: $zoom\n\nAttendees:\n$attendee_string";

            // Send to purchaser
            wp_mail($order->get_billing_email(), $subject, $message);

            // Send to attendees individually
            if (strpos($subject, "purchaser") !== false) continue;
            foreach ($attendees as $line) {
                if (preg_match('/<(.*?)>/', $line, $m)) {
                    wp_mail(trim($m[1]), $subject, $message);
                }
            }
        }
    }

    /* -----------------------------------------
        ADMIN – BOOKINGS DASHBOARD
    ------------------------------------------*/

    public function register_admin_pages() {
        add_submenu_page(
            'woocommerce',
            'Course Bookings',
            'Course Bookings',
            'manage_woocommerce',
            'scb-bookings',
            [$this, 'admin_bookings_page']
        );
    }

    public function admin_bookings_page() {

        echo "<div class='wrap'><h1>Course Bookings</h1>";

        $products = wc_get_products(['limit' => -1]);

        echo "<table class='widefat'><thead>
              <tr><th>Course</th><th>Slots</th></tr></thead><tbody>";

        foreach ($products as $p) {
            $slots = get_post_meta($p->get_id(), '_scb_slots', true);
            if (!$slots) continue;

            echo "<tr><td><strong>{$p->get_name()}</strong></td><td>";

            foreach ($slots as $slot) {
                $remaining = $slot['capacity'] - $slot['booked'];
                echo date("D j M", strtotime($slot['date'])) . " – " . $slot['time'] .
                    " ({$slot['booked']}/{$slot['capacity']} booked)<br>";
            }

            echo "</td></tr>";
        }

        echo "</tbody></table></div>";
    }
}

new Simple_Course_Booking();