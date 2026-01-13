jQuery(function($){

    // When a session is selected
    $(document).on('change', 'input[name="scb_slot"]', function(){
        let slots = window.SCB_SLOTS || {};
        let slotId = $(this).val();
        let slot = slots[slotId]; // Added missing semicolon

        let remaining = slot.capacity - (slot.booked || 0);

        // Populate attendee dropdown
        let select = $('#scb-attendee-count');
        select.html('<option value="">Choose…</option>');
        for (let i=1; i<=remaining; i++) {
            select.append(`<option value="${i}">${i}</option>`);
        }

        // Show step 2, hide others
        $('#scb-step-2').removeClass('hidden');
        $('#scb-step-3, #scb-step-4, #scb-step-5').addClass('hidden');

        // Logic to skip email delivery question if no link exists
        if (!slot.zoom || slot.zoom.trim() === '') {
            $('#scb-step-4').addClass('scb-skip-email'); 
            // Remove required attribute from the radio buttons so the form can submit
            $('#scb-step-4 input[type="radio"]').prop('required', false).prop('checked', false);
        } else {
            $('#scb-step-4').removeClass('scb-skip-email');
            $('#scb-step-4 input[type="radio"]').prop('required', true);
        }
    });

    // When attendee count selected
    $(document).on('change', '#scb-attendee-count', function(){
        let count = parseInt($(this).val());
        if (!count) {
            $('#scb-step-3, #scb-step-4, #scb-step-5').addClass('hidden');
            return;
        }

        let container = $('#scb-attendee-details');
        
        // Only rebuild if the count has actually changed to avoid wiping data
        if (container.find('.scb-attendee-block').length !== count) {
            container.html('');
            for (let i=1; i<=count; i++) {
                container.append(`
                    <div class="scb-attendee-block">
                        <p><strong>Attendee #${i}</strong></p>
                        <label>Name:<br><input type="text" name="scb_attendees[${i}][name]" required></label><br>
                        <label>Email:<br><input type="email" name="scb_attendees[${i}][email]" required></label>
                    </div>
                `);
            }
        }

        // Show steps
        $('#scb-step-3').removeClass('hidden');
        if (!$('#scb-step-4').hasClass('scb-skip-email')) {
            $('#scb-step-4').removeClass('hidden');
        }
        $('#scb-step-5').removeClass('hidden');
    });
});