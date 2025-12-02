<?php
/**
 * Admin booking dashboard
 */
if (!defined('ABSPATH')) exit;

class SCB_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    /** Add admin menu under WooCommerce */
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Course Bookings',
            'Course Bookings',
            'manage_woocommerce',
            'scb-bookings',
            [$this, 'render_dashboard']
        );
    }

    /** Main dashboard */
    public function render_dashboard() {
        echo '<div class="wrap"><h1>Course Bookings</h1>';

        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>
                <th>Product</th>
                <th>Slot Date</th>
                <th>Slot Time</th>
                <th>Capacity</th>
                <th>Booked</th>
                <th>Remaining</th>
                <th>Actions</th>
              </tr></thead><tbody>';

        foreach ($products as $p) {
            $slots = get_post_meta($p->get_id(), '_scb_slots', true);
            if (empty($slots)) continue; // Only products with booking slots

            foreach ($slots as $i => $slot) {
                $remaining = $slot['capacity'] - $slot['booked'];
                $view_url = admin_url('admin.php?page=scb-bookings&view=slot&product=' . $p->get_id() . '&slot=' . $i);

                echo '<tr>';
                echo '<td>' . esc_html($p->get_name()) . '</td>';
                echo '<td>' . esc_html($slot['date']) . '</td>';
                echo '<td>' . esc_html($slot['time']) . '</td>';
                echo '<td>' . intval($slot['capacity']) . '</td>';
                echo '<td>' . intval($slot['booked']) . '</td>';
                echo '<td>' . intval($remaining) . '</td>';
                echo '<td><a class="button" href="' . $view_url . '">View Attendees</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        // If viewing attendees for a slot
        if (isset($_GET['view']) && $_GET['view'] === 'slot') {
            $this->render_attendees_view();
        }

        echo '</div>';
    }

    /** Attendee list for a slot */
    public function render_attendees_view() {
        $product_id = intval($_GET['product']);
        $slot_id = intval($_GET['slot']);

        $product = wc_get_product($product_id);
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[$slot_id];

        echo '<h2>Attendees for: ' . esc_html($product->get_name()) . '</h2>';
        echo '<h3>' . esc_html($slot['date'] . ' @ ' . $slot['time']) . '</h3>';

        // Gather attendees from orders
        $orders = wc_get_orders([ 'limit' => -1, 'status' => ['completed','processing'] ]);

        $rows = [];

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() != $product_id) continue;
                if ($item->get_meta('Session') != date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time']) continue;

                $attendees = explode(',', $item->get_meta('Attendees'));
                foreach ($attendees as $a) {
                    if (!preg_match('/(.*)<(.*)>/', trim($a), $m)) continue;
                    $rows[] = [
                        'name' => trim($m[1]),
                        'email' => trim($m[2]),
                        'order' => $order->get_id(),
                        'status' => $order->get_status(),
                        'date' => $slot['date'],
                        'time' => $slot['time'],
                        'product' => $product->get_name()
                    ];
                }
            }
        }

        echo '<table class="widefat striped"><thead><tr>
                <th>Name</th>
                <th>Email</th>
                <th>Order</th>
                <th>Status</th>
                <th>Product</th>
                <th>Date</th>
                <th>Time</th>
              </tr></thead><tbody>';

        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html($r['name']) . '</td>';
            echo '<td>' . esc_html($r['email']) . '</td>';
            echo '<td><a href="' . admin_url('post.php?post=' . $r['order'] . '&action=edit') . '">#' . $r['order'] . '</a></td>';
            echo '<td>' . esc_html($r['status']) . '</td>';
            echo '<td>' . esc_html($r['product']) . '</td>';
            echo '<td>' . esc_html($r['date']) . '</td>';
            echo '<td>' . esc_html($r['time']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // CSV Export
        $csv_url = wp_nonce_url(admin_url('admin-post.php?action=scb_export_csv&product=' . $product_id . '&slot=' . $slot_id), 'scb_export_csv');
        echo '<p><a class="button button-primary" href="' . $csv_url . '">Download CSV</a></p>';

        add_action('admin_post_scb_export_csv', [$this, 'export_csv']);
    }

    /** Export CSV */
    public function export_csv() {
        if (!current_user_can('manage_woocommerce')) wp_die('Not allowed');
        if (!wp_verify_nonce($_GET['_wpnonce'], 'scb_export_csv')) wp_die('Nonce error');

        $product_id = intval($_GET['product']);
        $slot_id = intval($_GET['slot']);

        $product = wc_get_product($product_id);
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[$slot_id];

        // CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="course-attendees.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name','Email','Order','Status','Product','Date','Time']);

        $orders = wc_get_orders(['limit' => -1]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() != $product_id) continue;
                if ($item->get_meta('Session') != date('D j M', strtotime($slot['date'])) . ' @ ' . $slot['time']) continue;

                $attendees = explode(',', $item->get_meta('Attendees'));
                foreach ($attendees as $a) {
                    if (!preg_match('/(.*)<(.*)>/', trim($a), $m)) continue;
                    fputcsv($output, [ trim($m[1]), trim($m[2]), $order->get_id(), $order->get_status(), $product->get_name(), $slot['date'], $slot['time'] ]);
                }
            }
        }

        fclose($output);
        exit;
    }
}
    