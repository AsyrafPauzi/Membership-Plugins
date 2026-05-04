(function () {
    var root = document.getElementById('smdm-app-root');
    if (!root) return;

    var toggle = document.getElementById('smdm-nav-toggle');
    var sidebar = document.getElementById('smdm-sidebar');
    var overlay = document.getElementById('smdm-sidebar-overlay');

    function setOpen(open) {
        root.classList.toggle('smdm-nav-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) overlay.hidden = !open;
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setOpen(!root.classList.contains('smdm-nav-open'));
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            setOpen(false);
        });
    }
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('a.smdm-nav-item')) {
                setOpen(false);
            }
        });
    }

    function initFieldBuilderDnD() {
        var tbody = document.querySelector('.smdm-field-def-table tbody');
        if (!tbody) return;

        var draggedRow = null;

        function updateOrderIndexes() {
            var rows = tbody.querySelectorAll('tr.smdm-field-row');
            rows.forEach(function (row, idx) {
                var order = row.querySelector('.smdm-order-index');
                if (order) order.textContent = String(idx + 1);
            });
        }

        function getDragAfterElement(container, y, skipRow) {
            var rows = Array.prototype.slice.call(container.querySelectorAll('tr.smdm-field-row:not(.is-dragging)'));
            var closest = null;
            var closestOffset = Number.NEGATIVE_INFINITY;

            rows.forEach(function (row) {
                if (row === skipRow) return;
                var rect = row.getBoundingClientRect();
                var offset = y - rect.top - rect.height / 2;
                if (offset < 0 && offset > closestOffset) {
                    closestOffset = offset;
                    closest = row;
                }
            });
            return closest;
        }

        tbody.querySelectorAll('tr.smdm-field-row').forEach(function (row) {
            var handle = row.querySelector('.smdm-drag-handle');
            if (!handle) return;

            handle.addEventListener('dragstart', function (event) {
                draggedRow = row;
                row.classList.add('is-dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', row.getAttribute('data-field-id') || '');
                }
            });

            handle.addEventListener('dragend', function () {
                row.classList.remove('is-dragging');
                draggedRow = null;
                updateOrderIndexes();
            });
        });

        tbody.addEventListener('dragover', function (event) {
            event.preventDefault();
            if (!draggedRow) return;
            var afterElement = getDragAfterElement(tbody, event.clientY, draggedRow);
            if (!afterElement) {
                tbody.appendChild(draggedRow);
            } else if (afterElement !== draggedRow) {
                tbody.insertBefore(draggedRow, afterElement);
            }
        });

        tbody.addEventListener('drop', function (event) {
            event.preventDefault();
            updateOrderIndexes();
        });

        updateOrderIndexes();
    }

    function initMemberBulkSelect() {
        var form = document.querySelector('.smdm-members-bulk-form');
        if (!form) return;

        var master = form.querySelector('.smdm-member-bulk-select-all');

        function allRowBoxes() {
            return form.querySelectorAll('.smdm-member-bulk-cb');
        }

        function syncPartners(changed) {
            var v = changed.value;
            if (!v) return;
            allRowBoxes().forEach(function (cb) {
                if (cb !== changed && cb.value === v) {
                    cb.checked = changed.checked;
                }
            });
        }

        function uniqueIdsFromChecked() {
            var seen = {};
            allRowBoxes().forEach(function (cb) {
                if (cb.checked) {
                    seen[cb.value] = true;
                }
            });
            return Object.keys(seen);
        }

        function totalDistinctMembers() {
            var seen = {};
            allRowBoxes().forEach(function (cb) {
                seen[cb.value] = true;
            });
            return Object.keys(seen).length;
        }

        function updateMasterState() {
            if (!master) return;
            var total = totalDistinctMembers();
            var checked = uniqueIdsFromChecked().length;
            master.checked = total > 0 && checked === total;
            master.indeterminate = checked > 0 && checked < total;
        }

        if (master) {
            master.addEventListener('change', function () {
                var on = master.checked;
                allRowBoxes().forEach(function (cb) {
                    cb.checked = on;
                });
                master.indeterminate = false;
            });
        }

        form.addEventListener('change', function (e) {
            var t = e.target;
            if (!t || !t.classList.contains('smdm-member-bulk-cb')) return;
            syncPartners(t);
            updateMasterState();
        });

        form.addEventListener('submit', function (e) {
            var sub = e.submitter;
            if (!sub || sub.name !== 'smdm_bulk_delete_members') return;
            var n = uniqueIdsFromChecked().length;
            if (n === 0) {
                e.preventDefault();
                window.alert(form.getAttribute('data-msg-select-one') || 'Select at least one member.');
                return;
            }
            var msg = form.getAttribute('data-msg-confirm-bulk') || 'Delete selected members?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });

        updateMasterState();
    }

    initFieldBuilderDnD();
    initMemberBulkSelect();
})();
