<?php
/**
 * Admin booking dashboard
 */
if (!defined('ABSPATH')) exit;

class SCB_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        // Fix: Registered early enough to handle file downloads
        add_action('admin_post_scb_export_csv', [$this, 'export_csv']);
    }

    public function add_menu() {
        add_submenu_page('woocommerce', 'Course Bookings', 'Course Bookings', 'manage_woocommerce', 'scb-bookings', [$this, 'render_dashboard']);
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>Course Bookings</h1>';
        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        echo '<table class="widefat fixed striped"><thead><tr><th>Product</th><th>Slot Date</th><th>Slot Time</th><th>Capacity</th><th>Booked</th><th>Remaining</th><th>Actions</th></tr></thead><tbody>';
        foreach ($products as $p) {
            $slots = get_post_meta($p->get_id(), '_scb_slots', true);
            if (empty($slots)) continue;
            foreach ($slots as $i => $slot) {
                $booked = isset($slot['booked']) ? intval($slot['booked']) : 0;
                $remaining = $slot['capacity'] - $booked;
                $view_url = admin_url('admin.php?page=scb-bookings&view=slot&product=' . $p->get_id() . '&slot=' . $i);
                echo "<tr><td>".esc_html($p->get_name())."</td><td>".esc_html($slot['date'])."</td><td>".esc_html($slot['time'])."</td><td>".intval($slot['capacity'])."</td><td>$booked</td><td>$remaining</td><td><a class='button' href='$view_url'>View Attendees</a></td></tr>";
            }
        }
        echo '</tbody></table>';
        if (isset($_GET['view']) && $_GET['view'] === 'slot') { $this->render_attendees_view(); }
        echo '</div>';
    }

    public function render_attendees_view() {
        $product_id = intval($_GET['product']);
        $slot_id = intval($_GET['slot']);
        $product = wc_get_product($product_id);
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[$slot_id];

        echo "<h2>Attendees for: ".esc_html($product->get_name())."</h2><h3>".esc_html($slot['date']." @ ".$slot['time'])."</h3>";
        
        $orders = wc_get_orders(['limit' => -1, 'status' => ['completed','processing']]);
        $rows = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() != $product_id) continue;
                $item_slot_id = $item->get_meta('_scb_slot_id');
                if ($item_slot_id !== '' && intval($item_slot_id) !== $slot_id) continue;
                $attendees = explode(',', $item->get_meta('Attendees'));
                foreach ($attendees as $a) {
                    if (preg_match('/(.*)<(.*)>/', trim($a), $m)) {
                        $rows[] = ['name' => trim($m[1]), 'email' => trim($m[2]), 'order' => $order->get_id(), 'status' => $order->get_status()];
                    }
                }
            }
        }

        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>Order</th><th>Status</th></tr></thead><tbody>';
        if (empty($rows)) { echo '<tr><td colspan="4">No attendees found.</td></tr>'; } 
        else {
            foreach ($rows as $r) { echo "<tr><td>".esc_html($r['name'])."</td><td>".esc_html($r['email'])."</td><td><a href='".admin_url('post.php?post='.$r['order'].'&action=edit')."'>#".$r['order']."</a></td><td>".esc_html($r['status'])."</td></tr>"; }
        }
        echo '</tbody></table>';
        $csv_url = wp_nonce_url(admin_url('admin-post.php?action=scb_export_csv&product='.$product_id.'&slot='.$slot_id), 'scb_export_csv');
        echo '<p><a class="button button-primary" href="'.$csv_url.'">Download CSV</a></p>';
    }

    public function export_csv() {
        if (!current_user_can('manage_woocommerce')) wp_die('Not allowed');
        if (!wp_verify_nonce($_GET['_wpnonce'], 'scb_export_csv')) wp_die('Nonce error');
        $product_id = intval($_GET['product']);
        $slot_id = intval($_GET['slot']);
        $product = wc_get_product($product_id);
        $slots = get_post_meta($product_id, '_scb_slots', true);
        $slot = $slots[$slot_id];

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendees-'.$slot['date'].'.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name','Email','Order','Status']);
        $orders = wc_get_orders(['limit' => -1]);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id && intval($item->get_meta('_scb_slot_id')) === $slot_id) {
                    $attendees = explode(',', $item->get_meta('Attendees'));
                    foreach ($attendees as $a) {
                        if (preg_match('/(.*)<(.*)>/', trim($a), $m)) {
                            fputcsv($output, [trim($m[1]), trim($m[2]), $order->get_id(), $order->get_status()]);
                        }
                    }
                }
            }
        }
        fclose($output);
        exit;
    }
}