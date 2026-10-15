(function () {
    'use strict';

    let newRowCounter = 0;
    const conditionIndexes = new WeakMap();
    let dragItem = null;
    let dragContainer = null;

    function directChildren(parent, selector) {
        return Array.from(parent.children).filter((el) => el.matches(selector));
    }

    function buildConditionIndex(form) {
        if (!form) return new Map();
        const index = new Map();
        form.querySelectorAll('[data-pressundo-conditions]').forEach((field) => {
            let config;
            try { config = JSON.parse(field.dataset.pressundoConditions || '{}'); } catch (e) { return; }
            [...(config.show || []), ...(config.hide || [])].forEach((rule) => {
                const name = rule && rule.field ? String(rule.field) : '';
                if (!name) return;
                if (!index.has(name)) index.set(name, new Set());
                index.get(name).add(field);
            });
        });
        conditionIndexes.set(form, index);
        return index;
    }

    function updateRepeater(repeater) {
        const prefix = repeater.dataset.namePrefix || '';
        const rowsHolder = repeater.querySelector(':scope > .pressundo-repeater__rows');
        const rows = rowsHolder ? directChildren(rowsHolder, '.pressundo-repeater-row') : [];

        rows.forEach((row, index) => {
            const title = row.querySelector(':scope > .pressundo-repeater-row__title .pressundo-repeater-row__name');
            const firstText = row.querySelector(':scope > .pressundo-repeater-row__body input[type="text"], :scope > .pressundo-repeater-row__body input[type="email"], :scope > .pressundo-repeater-row__body input[type="url"], :scope > .pressundo-repeater-row__body textarea');
            if (title) title.textContent = firstText && firstText.value.trim() ? firstText.value.trim().slice(0, 64) : 'Item ' + (index + 1);
            row.querySelectorAll('[name]').forEach((control) => {
                const name = control.getAttribute('name') || '';
                if (!prefix || !name.startsWith(prefix + '[')) return;
                const suffix = name.slice(prefix.length).replace(/^\[[^\]]+\]/, '[' + index + ']');
                control.setAttribute('name', prefix + suffix);
            });
        });

        const minRows = parseInt(repeater.dataset.minRows || '0', 10) || 0;
        const maxRows = parseInt(repeater.dataset.maxRows || '0', 10) || 0;
        const add = repeater.querySelector(':scope > .pressundo-repeater__footer .pressundo-repeater-add');
        if (add) add.disabled = maxRows > 0 && rows.length >= maxRows;
        rows.forEach((row) => {
            const remove = row.querySelector(':scope > .pressundo-repeater-row__title .pressundo-repeater-remove');
            if (remove) remove.disabled = minRows > 0 && rows.length <= minRows;
        });

        const form = repeater.closest('.pressundo-modern-form');
        if (form) buildConditionIndex(form);
        refreshConditions(repeater);
    }

    function addRepeaterRow(repeater) {
        const template = repeater.querySelector(':scope > .pressundo-repeater-template');
        const rows = repeater.querySelector(':scope > .pressundo-repeater__rows');
        if (!template || !rows) return;
        const index = directChildren(rows, '.pressundo-repeater-row').length;
        const maxRows = parseInt(repeater.dataset.maxRows || '0', 10) || 0;
        if (maxRows > 0 && index >= maxRows) return;
        const rowKey = 'new-' + Date.now() + '-' + (++newRowCounter);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__NEW__', rowKey);
        const row = wrapper.firstElementChild;
        if (!row) return;
        rows.appendChild(row);
        initializeLazy(row);
        updateRepeater(repeater);
    }

    function duplicateRepeaterRow(button) {
        const row = button.closest('.pressundo-repeater-row');
        const repeater = button.closest('[data-pressundo-repeater]');
        if (!row || !repeater) return;
        const maxRows = parseInt(repeater.dataset.maxRows || '0', 10) || 0;
        const rows = repeater.querySelectorAll(':scope > .pressundo-repeater__rows > .pressundo-repeater-row');
        if (maxRows > 0 && rows.length >= maxRows) return;
        const clone = row.cloneNode(true);
        clone.classList.remove('is-collapsed', 'is-dragging', 'is-drag-armed');
        clone.draggable = false;
        const rowKey = 'new-' + Date.now() + '-' + (++newRowCounter);
        clone.dataset.rowKey = rowKey;
        const keyInput = clone.querySelector('.pressundo-repeater-row-key');
        if (keyInput) keyInput.value = rowKey;
        clone.querySelectorAll('details[open]').forEach((details) => details.removeAttribute('open'));
        row.parentNode.insertBefore(clone, row.nextSibling);
        initializeLazy(clone);
        updateRepeater(repeater);
    }

    function toggleRepeaterRow(button) {
        const row = button.closest('.pressundo-repeater-row');
        if (!row) return;
        const collapsed = row.classList.toggle('is-collapsed');
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function moveRow(button, direction) {
        const row = button.closest('.pressundo-repeater-row');
        const repeater = button.closest('[data-pressundo-repeater]');
        if (!row || !repeater) return;
        const sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling || !sibling.classList.contains('pressundo-repeater-row')) return;
        if (direction < 0) row.parentNode.insertBefore(row, sibling);
        else row.parentNode.insertBefore(sibling, row);
        updateRepeater(repeater);
        row.focus({ preventScroll: true });
    }

    function moveSortable(button, direction) {
        const item = button.closest('li');
        const container = button.closest('[data-pressundo-sortable]');
        if (!item || !container) return;
        const sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        if (direction < 0) item.parentNode.insertBefore(item, sibling);
        else item.parentNode.insertBefore(sibling, item);
        refreshSortable(container);
    }

    function sortableItem(container, value, label) {
        const fieldName = container.dataset.fieldName || '';
        const li = document.createElement('li');
        li.dataset.value = value;
        li.dataset.label = label;
        li.draggable = true;
        li.innerHTML = '<button type="button" class="pressundo-icon-button pressundo-drag-handle pressundo-sortable-drag" aria-label="Drag to reorder item"><span aria-hidden="true">⋮⋮</span></button>' +
            '<span class="pressundo-sortable-label"></span><input type="hidden"><details class="pressundo-row-menu"><summary aria-label="Item actions">•••</summary><div class="pressundo-row-menu__panel">' +
            '<button type="button" class="pressundo-row-menu__action pressundo-sortable-up">Move up</button>' +
            '<button type="button" class="pressundo-row-menu__action pressundo-sortable-down">Move down</button>' +
            '<button type="button" class="pressundo-row-menu__action is-danger pressundo-sortable-remove">Delete</button></div></details>';
        li.children[1].textContent = label;
        const input = li.querySelector('input');
        input.name = fieldName + '[]';
        input.value = value;
        return li;
    }

    function refreshSortable(container) {
        const max = parseInt(container.dataset.maxSelection || '0', 10) || 0;
        const count = container.querySelectorAll(':scope > ol > li').length;
        const add = container.querySelector('.pressundo-sortable-add');
        const select = container.querySelector('.pressundo-sortable-available');
        const hasAvailable = select instanceof HTMLSelectElement && Array.from(select.options).some((option) => option.value !== '');
        if (add) {
            add.disabled = (max > 0 && count >= max) || !hasAvailable;
            add.title = !hasAvailable ? 'All available items are already added.' : '';
        }
    }

    function addSortable(container) {
        const select = container.querySelector('.pressundo-sortable-available');
        const list = container.querySelector(':scope > ol');
        if (!select || !list || !select.value) return;
        const max = parseInt(container.dataset.maxSelection || '0', 10) || 0;
        if (max > 0 && list.children.length >= max) return;
        const option = select.options[select.selectedIndex];
        list.appendChild(sortableItem(container, option.value, option.textContent || option.value));
        option.remove();
        select.value = '';
        refreshSortable(container);
    }

    function removeSortable(button) {
        const item = button.closest('li');
        const container = button.closest('[data-pressundo-sortable]');
        if (!item || !container) return;
        const select = container.querySelector('.pressundo-sortable-available');
        if (select) {
            const option = document.createElement('option');
            option.value = item.dataset.value || '';
            option.textContent = item.dataset.label || option.value;
            select.appendChild(option);
        }
        item.remove();
        refreshSortable(container);
    }

    function fieldValue(wrapper) {
        if (!wrapper) return null;
        const controls = Array.from(wrapper.querySelectorAll('input, select, textarea')).filter((el) => !el.disabled);
        const checkboxes = controls.filter((el) => el instanceof HTMLInputElement && el.type === 'checkbox');
        if (checkboxes.length > 1) return checkboxes.filter((el) => el.checked).map((el) => el.value);
        const radios = controls.filter((el) => el instanceof HTMLInputElement && el.type === 'radio');
        if (radios.length) {
            const selected = radios.find((el) => el.checked);
            return selected ? selected.value : '';
        }
        const select = controls.find((el) => el instanceof HTMLSelectElement);
        if (select) return select.multiple ? Array.from(select.selectedOptions).map((o) => o.value) : select.value;
        const checkbox = checkboxes[0];
        if (checkbox) return checkbox.checked;
        const input = controls.find((el) => !(el instanceof HTMLInputElement && el.type === 'hidden')) || controls[0];
        return input ? input.value : null;
    }

    function conditionTarget(field, name) {
        const row = field.closest('.pressundo-repeater-row');
        if (row) {
            const local = row.querySelector('.pressundo-modern-field[data-pressundo-field="' + CSS.escape(name) + '"]');
            if (local) return local;
        }
        const group = field.closest('.pressundo-modern-group');
        if (group) {
            const local = group.querySelector('.pressundo-modern-field[data-pressundo-field="' + CSS.escape(name) + '"]');
            if (local) return local;
        }
        const form = field.closest('.pressundo-modern-form') || document;
        return form.querySelector('.pressundo-modern-field[data-pressundo-field="' + CSS.escape(name) + '"]');
    }

    function emptyValue(value) { return value === null || value === '' || (Array.isArray(value) && value.length === 0); }
    function truthy(value) {
        if (Array.isArray(value)) return value.length > 0;
        if (value === true) return true;
        if (value === false || value === null || value === '' || value === '0' || value === 0) return false;
        return Boolean(value);
    }

    function compareCondition(actual, rule) {
        const op = rule.operator || '=';
        const expected = rule.value;
        const equal = (a, b) => String(a ?? '') === String(b ?? '');
        switch (op) {
            case '=': case '==': return equal(actual, expected);
            case '!=': case '<>': return !equal(actual, expected);
            case 'in': return (Array.isArray(expected) ? expected : [expected]).some((v) => equal(actual, v));
            case 'not_in': return !(Array.isArray(expected) ? expected : [expected]).some((v) => equal(actual, v));
            case '>': return Number(actual) > Number(expected);
            case '>=': return Number(actual) >= Number(expected);
            case '<': return Number(actual) < Number(expected);
            case '<=': return Number(actual) <= Number(expected);
            case 'empty': return emptyValue(actual);
            case 'not_empty': return !emptyValue(actual);
            case 'truthy': return truthy(actual);
            case 'falsy': return !truthy(actual);
            case 'contains': return Array.isArray(actual) ? actual.some((v) => equal(v, expected)) : String(actual ?? '').includes(String(expected ?? ''));
            case 'not_contains': return Array.isArray(actual) ? !actual.some((v) => equal(v, expected)) : !String(actual ?? '').includes(String(expected ?? ''));
            default: return false;
        }
    }

    function rulesMatch(field, rules, relation) {
        if (!Array.isArray(rules) || rules.length === 0) return true;
        const results = rules.map((rule) => compareCondition(fieldValue(conditionTarget(field, rule.field || '')), rule));
        return relation === 'or' ? results.some(Boolean) : results.every(Boolean);
    }

    function refreshConditionField(field) {
        let config;
        try { config = JSON.parse(field.dataset.pressundoConditions || '{}'); } catch (e) { return; }
        const relation = config.relation === 'or' ? 'or' : 'and';
        const show = rulesMatch(field, config.show || [], relation);
        const hide = Array.isArray(config.hide) && config.hide.length ? rulesMatch(field, config.hide, relation) : false;
        const visible = show && !hide;
        field.classList.toggle('is-condition-hidden', !visible);
        field.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    function refreshConditions(root, changedName) {
        const scope = root || document;
        const form = scope.classList && scope.classList.contains('pressundo-modern-form') ? scope : scope.closest?.('.pressundo-modern-form');
        if (changedName && form) {
            const index = conditionIndexes.get(form) || buildConditionIndex(form);
            const targets = index.get(changedName);
            if (targets) targets.forEach(refreshConditionField);
            return;
        }
        scope.querySelectorAll('[data-pressundo-conditions]').forEach(refreshConditionField);
    }

    function attachmentThumb(item) {
        if (!item) return '';
        if (item.sizes && item.sizes.thumbnail && item.sizes.thumbnail.url) return item.sizes.thumbnail.url;
        if (item.sizes && item.sizes.medium && item.sizes.medium.url) return item.sizes.medium.url;
        if (item.icon) return item.icon;
        return item.url || '';
    }

    function renderAttachment(preview, item) {
        const url = attachmentThumb(item);
        preview.textContent = '';
        if (!url) return;
        const img = document.createElement('img');
        img.src = url;
        img.alt = item.alt || item.filename || '';
        img.loading = 'lazy';
        preview.appendChild(img);
    }

    function loadAttachment(id, callback) {
        if (!window.wp || !wp.media || !id) return;
        const attachment = wp.media.attachment(Number(id));
        const current = attachment.toJSON();
        if (current && current.url) callback(current);
        else attachment.fetch().then(() => callback(attachment.toJSON())).catch(() => {});
    }

    function refreshMediaPreview(holder) {
        const preview = holder.querySelector('.pressundo-media-preview');
        const input = holder.querySelector('input');
        const clear = holder.querySelector('.pressundo-media-clear');
        if (!preview || !input) return;
        const value = input.value.trim();
        preview.textContent = '';
        if (clear) clear.hidden = value === '';
        if (!value) return;
        if ((holder.dataset.return || 'id') === 'url') {
            renderAttachment(preview, { url: value });
        } else {
            loadAttachment(value, (item) => renderAttachment(preview, item));
        }
    }

    function refreshGalleryPreview(holder) {
        const preview = holder.querySelector('.pressundo-gallery-preview');
        const input = holder.querySelector('input');
        const clear = holder.querySelector('.pressundo-gallery-clear');
        if (!preview || !input) return;
        const values = input.value.split(',').map((v) => v.trim()).filter(Boolean);
        preview.textContent = '';
        if (clear) clear.hidden = values.length === 0;
        values.slice(0, 24).forEach((value) => {
            const cell = document.createElement('span');
            cell.className = 'pressundo-gallery-preview__item';
            preview.appendChild(cell);
            if ((holder.dataset.return || 'id') === 'url') renderAttachment(cell, { url: value });
            else loadAttachment(value, (item) => renderAttachment(cell, item));
        });
    }

    let previewObserver = null;
    function initializeLazy(root) {
        const scope = root || document;
        const nodes = scope.querySelectorAll('[data-pressundo-media-preview], [data-pressundo-gallery-preview]');
        if (!('IntersectionObserver' in window)) {
            nodes.forEach((node) => node.matches('[data-pressundo-gallery-preview]') ? refreshGalleryPreview(node) : refreshMediaPreview(node));
            return;
        }
        if (!previewObserver) {
            previewObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const node = entry.target;
                    node.matches('[data-pressundo-gallery-preview]') ? refreshGalleryPreview(node) : refreshMediaPreview(node);
                    observer.unobserve(node);
                });
            }, { rootMargin: '180px' });
        }
        nodes.forEach((node) => previewObserver.observe(node));
    }

    function openMediaPicker(button, multiple) {
        if (!window.wp || !wp.media) return;
        const holder = button.closest(multiple ? '.pressundo-gallery-field' : '.pressundo-media-field');
        const input = holder ? holder.querySelector('input') : null;
        if (!holder || !input) return;
        const frame = wp.media({ title: multiple ? 'Select gallery' : 'Select media', multiple: multiple });
        frame.on('select', function () {
            const selection = frame.state().get('selection').toJSON();
            const returnType = holder.dataset.return || 'id';
            if (multiple) input.value = selection.map((item) => returnType === 'url' ? item.url : item.id).join(',');
            else if (selection[0]) input.value = returnType === 'url' ? selection[0].url : selection[0].id;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            multiple ? refreshGalleryPreview(holder) : refreshMediaPreview(holder);
        });
        frame.open();
    }

    function clearMedia(button, multiple) {
        const holder = button.closest(multiple ? '.pressundo-gallery-field' : '.pressundo-media-field');
        const input = holder ? holder.querySelector('input') : null;
        if (!holder || !input) return;
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        multiple ? refreshGalleryPreview(holder) : refreshMediaPreview(holder);
    }

    function armDrag(handle) {
        const item = handle.closest('.pressundo-repeater-row, .pressundo-sortable-select > ol > li');
        if (!item) return;
        item.classList.add('is-drag-armed');
    }

    function disarmDrag(item) {
        if (!item) return;
        item.classList.remove('is-drag-armed');
    }

    function dragAfterElement(container, y, selector) {
        const items = directChildren(container, selector).filter((el) => el !== dragItem && !el.classList.contains('is-dragging'));
        let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
        items.forEach((child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) closest = { offset, element: child };
        });
        return closest.element;
    }

    function normalizeHex(value) {
        const raw = String(value || '').trim().toUpperCase();
        const short = raw.match(/^#([0-9A-F])([0-9A-F])([0-9A-F])$/);
        if (short) return '#' + short[1] + short[1] + short[2] + short[2] + short[3] + short[3];
        return /^#[0-9A-F]{6}$/.test(raw) ? raw : '';
    }

    function updateColorVisuals(holder, normalized) {
        if (!holder || !normalized) return;
        const hidden = holder.querySelector('[data-pressundo-color-value]');
        const picker = holder.querySelector('[data-pressundo-color-picker]');
        const hex = holder.querySelector('[data-pressundo-color-hex]');
        const current = holder.querySelector('.pressundo-color-current');
        const swatch = holder.querySelector('.pressundo-color-swatch');
        if (hidden instanceof HTMLInputElement) hidden.value = normalized;
        if (picker instanceof HTMLInputElement) picker.value = normalized.toLowerCase();
        if (hex instanceof HTMLInputElement) hex.value = normalized;
        if (current) current.textContent = normalized;
        if (swatch instanceof HTMLElement) swatch.style.setProperty('--pressundo-color', normalized);
    }

    function syncColorFromPicker(picker) {
        const holder = picker.closest('[data-pressundo-color-field]');
        if (!holder) return;
        const normalized = normalizeHex(picker.value) || String(picker.value || '').toUpperCase();
        updateColorVisuals(holder, normalized);
        const hidden = holder.querySelector('[data-pressundo-color-value]');
        if (hidden instanceof HTMLInputElement) hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function syncColorFromHex(hex, normalizeOnBlur) {
        const holder = hex.closest('[data-pressundo-color-field]');
        if (!holder) return;
        const normalized = normalizeHex(hex.value);
        if (normalized) {
            updateColorVisuals(holder, normalized);
            if (normalizeOnBlur) hex.value = normalized;
            hex.setCustomValidity('');
            const hidden = holder.querySelector('[data-pressundo-color-value]');
            if (hidden instanceof HTMLInputElement) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (String(hex.value || '').trim() !== '') {
            hex.setCustomValidity('Use a HEX color such as #2563EB.');
        } else {
            hex.setCustomValidity('');
        }
        setFieldValidity(hex);
    }

    function filterSearchableSelect(input) {
        const holder = input.closest('[data-pressundo-searchable-select]');
        const select = holder ? holder.querySelector('[data-pressundo-searchable-source]') : null;
        const empty = holder ? holder.querySelector('.pressundo-searchable-select__empty') : null;
        if (!(select instanceof HTMLSelectElement)) return;
        const query = String(input.value || '').trim().toLocaleLowerCase();
        let visible = 0;
        Array.from(select.options).forEach((option) => {
            const match = query === '' || option.text.toLocaleLowerCase().includes(query) || option.value.toLocaleLowerCase().includes(query);
            option.hidden = !match;
            if (match) visible++;
        });
        if (empty) empty.hidden = visible > 0;
    }

    function addKeyValueRow(container) {
        const template = container.querySelector('template');
        const rows = container.querySelector('.pressundo-key-value__rows');
        if (!template || !rows) return;
        const wrap = document.createElement('div');
        wrap.innerHTML = template.innerHTML.trim();
        const row = wrap.firstElementChild;
        if (!row) return;
        const index = 'new_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 6);
        row.querySelectorAll('[data-name-template]').forEach((input) => {
            input.name = input.dataset.nameTemplate.replace('__INDEX__', index);
            input.removeAttribute('data-name-template');
        });
        rows.appendChild(row);
        const first = row.querySelector('input');
        if (first) first.focus();
    }

    function toggleLinkedValues(button) {
        const holder = button.closest('.pressundo-box-control');
        if (!holder) return;
        const active = button.getAttribute('aria-pressed') !== 'true';
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.classList.toggle('is-active', active);
    }

    function syncLinkedBoxInput(input) {
        const holder = input.closest('.pressundo-box-control');
        if (!holder) return;
        const button = holder.querySelector('.pressundo-link-values');
        if (!button || button.getAttribute('aria-pressed') !== 'true') return;
        holder.querySelectorAll('.pressundo-box-control__grid input').forEach((other) => {
            if (other !== input) other.value = input.value;
        });
    }

    function setFieldValidity(control) {
        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) return;
        const field = control.closest('.pressundo-modern-field');
        if (!field) return;
        const error = field.querySelector('.pressundo-field-error');
        const invalid = !control.validity.valid;
        field.classList.toggle('is-invalid', invalid);
        control.setAttribute('aria-invalid', invalid ? 'true' : 'false');
        if (error) {
            error.hidden = !invalid;
            error.textContent = invalid ? control.validationMessage : '';
        }
    }

    document.addEventListener('pointerdown', (event) => {
        const handle = event.target instanceof Element ? event.target.closest('.pressundo-drag-handle') : null;
        if (handle) armDrag(handle);
    });
    document.addEventListener('pointerup', () => {
        document.querySelectorAll('.is-drag-armed:not(.is-dragging)').forEach(disarmDrag);
    });
    document.addEventListener('dragstart', (event) => {
        const item = event.target instanceof Element ? event.target.closest('.pressundo-repeater-row, .pressundo-sortable-select > ol > li') : null;
        if (!item || !item.classList.contains('is-drag-armed')) { event.preventDefault(); return; }
        dragItem = item;
        dragContainer = item.parentElement;
        item.classList.add('is-dragging');
        if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', 'pressundo-reorder'); }
    });
    document.addEventListener('dragover', (event) => {
        if (!dragItem || !dragContainer || !(event.target instanceof Element)) return;
        const hoverContainer = event.target.closest('.pressundo-repeater__rows, .pressundo-sortable-select > ol');
        if (hoverContainer !== dragContainer) return;
        event.preventDefault();
        const selector = dragItem.classList.contains('pressundo-repeater-row') ? '.pressundo-repeater-row' : 'li';
        const after = dragAfterElement(dragContainer, event.clientY, selector);
        if (after) dragContainer.insertBefore(dragItem, after);
        else dragContainer.appendChild(dragItem);
    });
    document.addEventListener('dragend', () => {
        if (!dragItem) return;
        const repeater = dragItem.closest('[data-pressundo-repeater]');
        const sortable = dragItem.closest('[data-pressundo-sortable]');
        dragItem.classList.remove('is-dragging');
        disarmDrag(dragItem);
        if (repeater) updateRepeater(repeater);
        if (sortable) refreshSortable(sortable);
        dragItem = null;
        dragContainer = null;
    });

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const add = target.closest('.pressundo-repeater-add');
        if (add) { event.preventDefault(); const r = add.closest('[data-pressundo-repeater]'); if (r) addRepeaterRow(r); return; }
        const collapse = target.closest('.pressundo-repeater-collapse');
        if (collapse) { event.preventDefault(); toggleRepeaterRow(collapse); return; }
        const duplicate = target.closest('.pressundo-repeater-duplicate');
        if (duplicate) { event.preventDefault(); duplicateRepeaterRow(duplicate); return; }
        const remove = target.closest('.pressundo-repeater-remove');
        if (remove) {
            event.preventDefault(); const row = remove.closest('.pressundo-repeater-row'); const r = remove.closest('[data-pressundo-repeater]');
            if (row && r) { const count = r.querySelectorAll(':scope > .pressundo-repeater__rows > .pressundo-repeater-row').length; const min = parseInt(r.dataset.minRows || '0', 10) || 0; if (count > min) { row.remove(); updateRepeater(r); } }
            return;
        }
        const up = target.closest('.pressundo-repeater-up'); if (up) { event.preventDefault(); moveRow(up, -1); return; }
        const down = target.closest('.pressundo-repeater-down'); if (down) { event.preventDefault(); moveRow(down, 1); return; }
        const media = target.closest('.pressundo-media-select'); if (media) { event.preventDefault(); openMediaPicker(media, false); return; }
        const gallery = target.closest('.pressundo-gallery-select'); if (gallery) { event.preventDefault(); openMediaPicker(gallery, true); return; }
        const mediaClear = target.closest('.pressundo-media-clear'); if (mediaClear) { event.preventDefault(); clearMedia(mediaClear, false); return; }
        const galleryClear = target.closest('.pressundo-gallery-clear'); if (galleryClear) { event.preventDefault(); clearMedia(galleryClear, true); return; }
        const keyValueAdd = target.closest('.pressundo-key-value-add'); if (keyValueAdd) { event.preventDefault(); const c = keyValueAdd.closest('[data-pressundo-key-value]'); if (c) addKeyValueRow(c); return; }
        const keyValueRemove = target.closest('.pressundo-key-value-remove'); if (keyValueRemove) { event.preventDefault(); const row = keyValueRemove.closest('.pressundo-key-value__row'); if (row) row.remove(); return; }
        const linkValues = target.closest('.pressundo-link-values'); if (linkValues) { event.preventDefault(); toggleLinkedValues(linkValues); return; }
        const sortableAdd = target.closest('.pressundo-sortable-add'); if (sortableAdd) { event.preventDefault(); const c = sortableAdd.closest('[data-pressundo-sortable]'); if (c) addSortable(c); return; }
        const sortableRemove = target.closest('.pressundo-sortable-remove'); if (sortableRemove) { event.preventDefault(); removeSortable(sortableRemove); return; }
        const sortableUp = target.closest('.pressundo-sortable-up'); if (sortableUp) { event.preventDefault(); moveSortable(sortableUp, -1); return; }
        const sortableDown = target.closest('.pressundo-sortable-down'); if (sortableDown) { event.preventDefault(); moveSortable(sortableDown, 1); }
    });

    document.addEventListener('invalid', (event) => setFieldValidity(event.target), true);
    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!(target instanceof Element) || !target.matches('input, select, textarea')) return;
        setFieldValidity(target);
        const field = target.closest('.pressundo-modern-field');
        const form = target.closest('.pressundo-modern-form');
        refreshConditions(form || document, field ? field.dataset.pressundoField : '');
        const media = target.closest('.pressundo-media-field'); if (media) refreshMediaPreview(media);
        const gallery = target.closest('.pressundo-gallery-field'); if (gallery) refreshGalleryPreview(gallery);
        if (target instanceof HTMLInputElement && target.matches('[data-pressundo-color-picker]')) syncColorFromPicker(target);
        if (target instanceof HTMLInputElement && target.matches('[data-pressundo-color-hex]')) syncColorFromHex(target, true);
    });
    document.addEventListener('input', function (event) {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.type === 'range') {
            const output = target.parentElement ? target.parentElement.querySelector('.pressundo-range-value') : null;
            if (output) output.textContent = target.value;
        }
        if (target instanceof HTMLInputElement && target.matches('.pressundo-searchable-select__input')) {
            filterSearchableSelect(target);
            return;
        }
        if (target instanceof HTMLInputElement && target.matches('[data-pressundo-color-hex]')) {
            syncColorFromHex(target, false);
        }
        if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
            if (target.matches('.pressundo-box-control__grid input')) syncLinkedBoxInput(target);
            setFieldValidity(target);
            const repeater = target.closest('[data-pressundo-repeater]');
            if (repeater) updateRepeater(repeater);
        }
    });


    document.addEventListener('blur', function (event) {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches('[data-pressundo-color-hex]')) syncColorFromHex(target, true);
    }, true);


    document.addEventListener('click', function (event) {
        const link = event.target instanceof Element ? event.target.closest('.pressundo-options-nav a') : null;
        if (link) {
            const nav = link.closest('.pressundo-options-nav');
            if (nav) nav.querySelectorAll('a').forEach((item) => item.classList.toggle('is-active', item === link));
        }
    });
    document.querySelectorAll('.pressundo-modern-form').forEach((form) => buildConditionIndex(form));
    document.querySelectorAll('[data-pressundo-repeater]').forEach(updateRepeater);
    document.querySelectorAll('[data-pressundo-sortable]').forEach(refreshSortable);
    refreshConditions(document);
    initializeLazy(document);
}());
