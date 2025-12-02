jQuery(function($){

    // When a session is selected
    $(document).on('change', 'input[name="scb_slot"]', function(){
        let slots = window.SCB_SLOTS || {};
        let slotId = $(this).val();

        let remaining = slots[slotId].capacity - slots[slotId].booked;

        // Populate attendee dropdown
        let select = $('#scb-attendee-count');
        select.html('<option value="">Choose…</option>');
        for (let i=1; i<=remaining; i++) {
            select.append(`<option value="${i}">${i}</option>`);
        }

        // Show step 2
        $('#scb-step-2').removeClass('hidden');
        $('#scb-step-3, #scb-step-4, #scb-step-5').addClass('hidden');
    });

    // When attendee count selected
    $(document).on('change', '#scb-attendee-count', function(){
        let count = parseInt($(this).val());
        if (!count) return;

        let container = $('#scb-attendee-details');
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

        // Show next steps
        $('#scb-step-3').removeClass('hidden');
        $('#scb-step-4').removeClass('hidden');
        $('#scb-step-5').removeClass('hidden');
    });
});
