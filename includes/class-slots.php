<?php
/**
 * Slot storage, retrieval, capacity management
 */

if (!defined('ABSPATH')) exit;

class SCB_Slots {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_slots_metabox']);
        add_action('save_post_product', [$this, 'save_slots']);
    }

    /** Add slot metabox */
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

    /** Render slot editor */
    public function render_slots_metabox($post) {
        $slots = get_post_meta($post->ID, '_scb_slots', true);
        if (!is_array($slots)) $slots = [];
        
        $extra_info = get_post_meta($post->ID, '_scb_extra_info', true);
        
        wp_nonce_field('scb_save_slots', 'scb_slots_nonce');
        ?>
        <style>
            .scb-slot-table input { width: 100%; }
            .scb-remove-slot { color:red; cursor:pointer; font-weight:bold; }
            .scb-extra-info-wrap { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px; }
        </style>

        <table class="widefat scb-slot-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Capacity</th>
                    <th>Teams/Zoom Link</th>
                    <th>Meeting ID</th>
                    <th>Password</th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $i => $slot): ?>
                    <tr>
                        <td><input type="date" name="scb_slots[<?php echo $i; ?>][date]" value="<?php echo esc_attr($slot['date']); ?>" required></td>
                        <td><input type="time" name="scb_slots[<?php echo $i; ?>][time]" value="<?php echo esc_attr($slot['time']); ?>" required></td>
                        <td><input type="number" name="scb_slots[<?php echo $i; ?>][capacity]" value="<?php echo esc_attr($slot['capacity']); ?>" required></td>
                        <td><input type="text" name="scb_slots[<?php echo $i; ?>][zoom]" value="<?php echo esc_attr($slot['zoom'] ?? ''); ?>"></td>
                        <td><input type="text" name="scb_slots[<?php echo $i; ?>][meeting_id]" value="<?php echo esc_attr($slot['meeting_id'] ?? ''); ?>"></td>
                        <td><input type="text" name="scb_slots[<?php echo $i; ?>][password]" value="<?php echo esc_attr($slot['password'] ?? ''); ?>"></td>
                        <td><span class="scb-remove-slot">X</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p><button class="button" id="scb-add-slot">+ Add Slot</button></p>

        <div class="scb-extra-info-wrap">
            <p><strong>General Joining Instructions (Sent with link):</strong></p>
            <textarea name="scb_extra_info" rows="4" style="width:100%;" placeholder="Add extra info like door codes or preparation materials..."><?php echo esc_textarea($extra_info); ?></textarea>
        </div>

        <script>
        jQuery(function($){
            $('#scb-add-slot').on('click', function(e){
                e.preventDefault();
                let i = $('.scb-slot-table tbody tr').length;
                $('.scb-slot-table tbody').append(`
                    <tr>
                        <td><input type="date" name="scb_slots[${i}][date]" required></td>
                        <td><input type="time" name="scb_slots[${i}][time]" required></td>
                        <td><input type="number" name="scb_slots[${i}][capacity]" required></td>
                        <td><input type="text" name="scb_slots[${i}][zoom]"></td>
                        <td><input type="text" name="scb_slots[${i}][meeting_id]"></td>
                        <td><input type="text" name="scb_slots[${i}][password]"></td>
                        <td><span class="scb-remove-slot">X</span></td>
                    </tr>`);
            });
            $(document).on('click', '.scb-remove-slot', function(){
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    public function save_slots($post_id) {
        if (!isset($_POST['scb_slots_nonce']) || !wp_verify_nonce($_POST['scb_slots_nonce'], 'scb_save_slots')) return;
        
        if (isset($_POST['scb_slots'])) {
            $slots = array_values($_POST['scb_slots']);
            foreach ($slots as &$slot) {
                if (!isset($slot['booked'])) $slot['booked'] = 0;
            }
            update_post_meta($post_id, '_scb_slots', $slots);
        }

        if (isset($_POST['scb_extra_info'])) {
            update_post_meta($post_id, '_scb_extra_info', sanitize_textarea_field($_POST['scb_extra_info']));
        }
    }
}