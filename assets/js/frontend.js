jQuery(document).ready(function($) {
    const modal = $('#smdm-modal');
    const modalBody = $('#smdm-modal-body');
    const closeBtn = $('.smdm-close');

    $('.smdm-view-details-btn').on('click', function() {
        const data = $(this).data('member');
        
        let html = `
            <div class="smdm-modal-header">
                <div class="smdm-avatar-large">${data.name.charAt(0)}</div>
                <h2>${data.name}</h2>
                <span class="smdm-tag">${data.cat || 'Member'}</span>
            </div>
            <div class="smdm-modal-grid">
                <div class="smdm-info-item"><strong>Email</strong><br>${data.email}</div>
                <div class="smdm-info-item"><strong>Phone</strong><br>${data.phone}</div>
                <div class="smdm-info-item"><strong>Gender</strong><br>${data.gender || 'N/A'}</div>
                <div class="smdm-info-item"><strong>Location</strong><br>${data.city}, ${data.state}</div>
                <div class="smdm-info-item" style="grid-column: span 2;">
                    <strong>Address</strong><br>${data.address}<br>${data.postcode} ${data.city}
                </div>
            </div>
        `;

        modalBody.html(html);
        modal.fadeIn(300);
    });

    closeBtn.on('click', function() {
        modal.fadeOut(200);
    });

    // Close when clicking outside the box
    $(window).on('click', function(event) {
        if (event.target == modal[0]) {
            modal.fadeOut(200);
        }
    });
});