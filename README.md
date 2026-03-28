# Simple Course Booking (v2.0.0)

A lightweight but powerful WooCommerce extension that transforms standard products into bookable course sessions with slot management, attendee tracking, and automated delivery of meeting instructions (Zoom/Teams).

## Features

* **Slot Management**: Add multiple dates and times to any WooCommerce product with individual capacity limits.
* **Dynamic Frontend UI**: A multi-step booking form that guides users through session selection, attendee counts, and detail entry.
* **Attendee Tracking**: Collect unique names and email addresses for every seat booked.
* **Smart Email Delivery**: 
    * Automatically sends joining instructions (Links, Meeting IDs, Passwords) upon order completion.
    * Choose to send instructions only to the purchaser or to all individual attendees.
* **Admin Dashboard**: View real-time booking stats, manage attendee lists per slot, and export data to CSV.
* **Capacity Protection**: Automatically updates remaining seats when orders are completed and prevents overbooking via frontend validation.

## Installation

1.  Upload the `simple-course-booking` folder to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Edit any product to find the **Course Booking Slots** meta box.

## How It Works

1.  **Create Slots**: In the product editor, add dates, times, and capacities. You can also add specific Zoom/Teams links and passwords for each slot.
2.  **Customer Booking**: Customers select a session and the number of attendees. The plugin dynamically generates forms for each attendee's details.
3.  **Checkout**: The cart quantity is automatically synchronized with the number of attendees selected.
4.  **Fulfillment**: Once the order status changes to "Completed," the plugin fires the instruction emails to the designated recipients.

## Technical Overview

* **Logic**: Built using a modular PHP class structure (`Frontend`, `Admin`, `Slots`, `Email`).
* **Hooks**: Utilizes WooCommerce fragments and AJAX for a seamless "Add to Cart" experience.
* **Requirements**: WordPress and WooCommerce.

## Author
**D Kandekore**
