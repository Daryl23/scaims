document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('cropForm');
    const tableBody = document.getElementById('cropTableBody');
    const searchInput = document.getElementById('cropSearch');
    const message = document.getElementById('formMessage');
    const formTitle = document.getElementById('formTitle');
    const saveButton = document.getElementById('saveCropButton');
    const resetButton = document.getElementById('resetFormButton');
    const fields = {
        id: document.getElementById('cropId'),
        name: document.getElementById('cropName'),
        commodity: document.getElementById('cropCommodity'),
        sortOrder: document.getElementById('cropSortOrder'),
        isActive: document.getElementById('cropIsActive')
    };

    const commodityLabels = {
        grains: 'Grains',
        plantation: 'Plantation Crops',
        root: 'Root Crops',
        vegetables: 'Vegetables',
        fruits: 'Fruits'
    };

    let rows = [];

    function setMessage(text, type) {
        message.textContent = text || '';
        message.classList.toggle('is-success', type === 'success');
        message.classList.toggle('is-error', type === 'error');
    }

    function request(action, data) {
        const body = new URLSearchParams({ action });
        Object.keys(data || {}).forEach((key) => {
            body.append(key, data[key]);
        });

        return fetch('cropActions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        }).then((response) => response.json());
    }

    function loadRows() {
        tableBody.innerHTML = '<tr><td colspan="5" class="empty-state">Loading crops...</td></tr>';

        fetch('cropActions.php?action=list', { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload.success) {
                    throw new Error(payload.message || 'Unable to load crops.');
                }
                rows = payload.rows || [];
                renderRows();
            })
            .catch((error) => {
                tableBody.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(error.message)}</td></tr>`;
            });
    }

    function renderRows() {
        const query = searchInput.value.trim().toLowerCase();
        const filtered = rows.filter((row) => {
            const haystack = `${row.crop_name} ${commodityLabels[row.commodity] || row.commodity}`.toLowerCase();
            return !query || haystack.includes(query);
        });

        const activeCount = rows.filter((row) => row.is_active).length;
        const inactiveCount = rows.length - activeCount;
        document.getElementById('activeCropCount').textContent = activeCount;
        document.getElementById('inactiveCropCount').textContent = inactiveCount;

        if (!filtered.length) {
            tableBody.innerHTML = '<tr><td colspan="5" class="empty-state">No crops found.</td></tr>';
            return;
        }

        tableBody.innerHTML = filtered.map((row) => {
            const statusClass = row.is_active ? 'active' : 'inactive';
            const statusText = row.is_active ? 'Active' : 'Inactive';
            const secondaryAction = row.is_active
                ? `<button type="button" class="table-action delete" data-action="delete" data-id="${row.id}">Delete</button>`
                : `<button type="button" class="table-action restore" data-action="restore" data-id="${row.id}">Restore</button>`;

            return `
                <tr>
                    <td><strong>${escapeHtml(row.crop_name)}</strong></td>
                    <td>${escapeHtml(commodityLabels[row.commodity] || row.commodity)}</td>
                    <td><span class="status-pill ${statusClass}">${statusText}</span></td>
                    <td>${Number(row.sort_order) || 0}</td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="table-action edit" data-action="edit" data-id="${row.id}">Edit</button>
                            ${secondaryAction}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char];
        });
    }

    function resetForm() {
        form.reset();
        fields.id.value = '';
        fields.sortOrder.value = '100';
        fields.isActive.checked = true;
        formTitle.textContent = 'Add Crop';
        saveButton.textContent = 'Save Crop';
        setMessage('', '');
    }

    function editRow(id) {
        const row = rows.find((item) => Number(item.id) === Number(id));
        if (!row) return;

        fields.id.value = row.id;
        fields.name.value = row.crop_name;
        fields.commodity.value = row.commodity;
        fields.sortOrder.value = row.sort_order;
        fields.isActive.checked = Boolean(row.is_active);
        formTitle.textContent = 'Update Crop';
        saveButton.textContent = 'Update Crop';
        setMessage('Editing ' + row.crop_name + '.', '');
        fields.name.focus();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const action = fields.id.value ? 'update' : 'create';
        const payload = {
            id: fields.id.value,
            crop_name: fields.name.value,
            commodity: fields.commodity.value,
            sort_order: fields.sortOrder.value || '100',
            is_active: fields.isActive.checked ? '1' : '0'
        };

        saveButton.disabled = true;
        request(action, payload)
            .then((result) => {
                if (!result.success) {
                    throw new Error(result.message || 'Unable to save crop.');
                }
                setMessage(result.message || 'Saved.', 'success');
                resetForm();
                return loadRows();
            })
            .catch((error) => {
                setMessage(error.message, 'error');
            })
            .finally(() => {
                saveButton.disabled = false;
            });
    });

    tableBody.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const id = button.dataset.id;

        if (action === 'edit') {
            editRow(id);
            return;
        }

        const row = rows.find((item) => Number(item.id) === Number(id));
        const verb = action === 'restore' ? 'restore' : 'deactivate';
        if (!row || !confirm(`Are you sure you want to ${verb} ${row.crop_name}?`)) {
            return;
        }

        request(action, { id })
            .then((result) => {
                if (!result.success) {
                    throw new Error(result.message || 'Unable to update crop.');
                }
                setMessage(result.message, 'success');
                return loadRows();
            })
            .catch((error) => {
                setMessage(error.message, 'error');
            });
    });

    searchInput.addEventListener('input', renderRows);
    resetButton.addEventListener('click', resetForm);

    loadRows();
});
