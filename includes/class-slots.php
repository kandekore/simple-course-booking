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
        wp_nonce_field('scb_save_slots', 'scb_slots_nonce');
        ?>
        <style>
            .scb-slot-table input { width: 100%; }
            .scb-remove-slot { color:red; cursor:pointer; }
        </style>
        <table class="widefat scb-slot-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration (mins)</th>
                    <th>Capacity</th>
                    <th>Zoom Link</th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $i => $slot): ?>
                    <tr>
                        <td><input type="date" name="scb_slots[<?php echo $i; ?>][date]" value="<?php echo esc_attr($slot['date']); ?>" required></td>
                        <td><input type="time" name="scb_slots[<?php echo $i; ?>][time]" value="<?php echo esc_attr($slot['time']); ?>" required></td>
                        <td><input type="number" name="scb_slots[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($slot['duration']); ?>" required></td>
                        <td><input type="number" name="scb_slots[<?php echo $i; ?>][capacity]" value="<?php echo esc_attr($slot['capacity']); ?>" required></td>
                        <td><input type="text" name="scb_slots[<?php echo $i; ?>][zoom]" value="<?php echo esc_attr($slot['zoom']); ?>"></td>
                        <td><span class="scb-remove-slot">X</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><button class="button" id="scb-add-slot">+ Add Slot</button></p>

        <script>
        jQuery(function($){
            $('#scb-add-slot').on('click', function(e){
                e.preventDefault();
                let i = $('.scb-slot-table tbody tr').length;
                $('.scb-slot-table tbody').append(`
                    <tr>
                        <td><input type="date" name="scb_slots[${i}][date]" required></td>
                        <td><input type="time" name="scb_slots[${i}][time]" required></td>
                        <td><input type="number" name="scb_slots[${i}][duration]" required></td>
                        <td><input type="number" name="scb_slots[${i}][capacity]" required></td>
                        <td><input type="text" name="scb_slots[${i}][zoom]"></td>
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

    /** Save slots */
    public function save_slots($post_id) {
        if (!isset($_POST['scb_slots_nonce']) || !wp_verify_nonce($_POST['scb_slots_nonce'], 'scb_save_slots')) return;
        if (!isset($_POST['scb_slots'])) return;

        $slots = array_values($_POST['scb_slots']);
        foreach ($slots as &$slot) {
            if (!isset($slot['booked'])) $slot['booked'] = 0;
        }
        update_post_meta($post_id, '_scb_slots', $slots);
    }
}
