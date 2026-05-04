jQuery(document).ready(function($) {
    const modal = $('#smdm-modal');
    const modalBody = $('#smdm-modal-body');
    const closeBtn = $('.smdm-close');

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderModal(data) {
        const name = data.name || '';
        const cat = data.category || 'Member';
        const rows = Array.isArray(data.rows) ? data.rows : [];

        let rowsHtml = '';
        rows.forEach(function(row) {
            rowsHtml += '<div class="smdm-info-item"><strong>' + escapeHtml(row.label) + '</strong><br>' + escapeHtml(row.value) + '</div>';
        });

        if (!rowsHtml && data.email) {
            rowsHtml = '<div class="smdm-info-item"><strong>Email</strong><br>' + escapeHtml(data.email) + '</div>';
        }

        return (
            '<div class="smdm-modal-header">' +
                '<div class="smdm-avatar-large">' + escapeHtml(name.charAt(0)) + '</div>' +
                '<h2>' + escapeHtml(name) + '</h2>' +
                '<span class="smdm-tag">' + escapeHtml(cat) + '</span>' +
            '</div>' +
            '<div class="smdm-modal-grid">' + rowsHtml + '</div>'
        );
    }

    $(document).on('click', '.smdm-view-details-btn', function() {
        const raw = $(this).attr('data-member');
        let data = {};
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (e) {
            data = {};
        }
        modalBody.html(renderModal(data));
        modal.addClass('smdm-modal--open');
        modal.fadeIn(300);
    });

    closeBtn.on('click', function() {
        modal.fadeOut(200, function () {
            modal.removeClass('smdm-modal--open');
        });
    });

    $(window).on('click', function(event) {
        if (event.target === modal[0]) {
            modal.fadeOut(200, function () {
                modal.removeClass('smdm-modal--open');
            });
        }
    });
});
