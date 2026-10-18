(() => {
    document.documentElement.classList.add('js');

    const onReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    };

    function abstractRows(form) {
        return form ? Array.from(form.querySelectorAll('[data-abstract-row]')) : [];
    }

    function abstractBidData(form) {
        return abstractRows(form).map((row) => {
            const nameInput = row.querySelector('[data-abstract-supplier-name]');
            const amountInput = row.querySelector('[data-abstract-bid-amount]');

            return {
                name: nameInput ? nameInput.value.trim() : '',
                amount: amountInput ? amountInput.value.trim() : '',
            };
        });
    }

    function lowestAbstractBid(bids) {
        return bids.reduce((lowest, bid) => {
            if (!bid.name || bid.amount === '' || Number.isNaN(Number(bid.amount))) {
                return lowest;
            }

            if (!lowest || Number(bid.amount) < Number(lowest.amount)) {
                return bid;
            }

            return lowest;
        }, null);
    }

    function reindexAbstractRows(form) {
        const rows = abstractRows(form);

        rows.forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/supplier_bids\[\d+\]/, `supplier_bids[${index}]`);
            });
        });

        rows.forEach((row) => {
            const removeButton = row.querySelector('[data-remove-abstract-row]');
            if (removeButton) {
                removeButton.disabled = rows.length <= 1;
            }
        });
    }

    function syncSelectedSupplierAmount(select) {
        const form = select ? select.closest('form') : null;
        const amountInput = form ? form.querySelector('[data-abstract-selected-amount]') : null;
        const selectedOption = select ? select.options[select.selectedIndex] : null;
        if (amountInput) {
            amountInput.value = selectedOption ? (selectedOption.dataset.bidAmount || '') : '';
        }
    }

    function syncAbstractSelection(form, preferLowest = false) {
        const select = form ? form.querySelector('[data-abstract-selected-supplier]') : null;
        const amountInput = form ? form.querySelector('[data-abstract-selected-amount]') : null;
        if (!select || !amountInput) {
            return;
        }

        const bids = abstractBidData(form).filter((bid) => bid.name !== '');
        const previousValue = select.value;
        const lowestBid = lowestAbstractBid(bids);
        const previousStillExists = bids.some((bid) => bid.name === previousValue);
        const shouldUseLowest = preferLowest || select.dataset.manual !== '1' || !previousStillExists;
        const nextValue = shouldUseLowest && lowestBid ? lowestBid.name : previousValue;

        select.textContent = '';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Select supplier';
        select.appendChild(emptyOption);

        bids.forEach((bid) => {
            const option = document.createElement('option');
            option.value = bid.name;
            option.textContent = bid.name;
            select.appendChild(option);
        });

        select.value = bids.some((bid) => bid.name === nextValue) ? nextValue : '';
        if (select.value === '' && lowestBid) {
            select.value = lowestBid.name;
        }
        if (shouldUseLowest) {
            select.dataset.manual = '0';
        }

        const selectedBid = bids.find((bid) => bid.name === select.value);
        amountInput.value = selectedBid ? selectedBid.amount : '';
    }


    function reindexPurchaseRows(form) {
        const rows = form ? Array.from(form.querySelectorAll('[data-pr-row]')) : [];

        rows.forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });

            const itemNumberInput = row.querySelector('[data-pr-item-number]');
            if (itemNumberInput) {
                itemNumberInput.value = String(index + 1);
            }
        });

        rows.forEach((row) => {
            const removeButton = row.querySelector('[data-remove-pr-row]');
            if (removeButton) {
                removeButton.disabled = rows.length <= 1;
            }
        });

    }

    function addAbstractRow(button) {
        const form = button.closest('[data-abstract-form]');
        const tbody = form ? form.querySelector('[data-abstract-bids]') : null;
        const firstRow = tbody ? tbody.querySelector('[data-abstract-row]') : null;
        if (!form || !tbody || !firstRow) {
            return;
        }

        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach((input) => {
            input.value = '';
            input.disabled = false;
        });
        newRow.querySelectorAll('.field-error').forEach((error) => {
            error.remove();
        });
        newRow.querySelectorAll('button').forEach((rowButton) => {
            rowButton.disabled = false;
        });

        tbody.appendChild(newRow);
        reindexAbstractRows(form);
        syncAbstractSelection(form, false);
    }

    function addPurchaseRow(button) {
        const form = button.closest('[data-pr-form]');
        const tbody = form ? form.querySelector('[data-pr-items]') : null;
        const firstRow = tbody ? tbody.querySelector('[data-pr-row]') : null;
        if (!form || !tbody || !firstRow) {
            return;
        }

        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach((input) => {
            input.value = '';
            input.disabled = false;
        });
        newRow.querySelectorAll('.field-error').forEach((error) => {
            error.remove();
        });
        newRow.querySelectorAll('button').forEach((rowButton) => {
            rowButton.disabled = false;
        });

        tbody.appendChild(newRow);
        reindexPurchaseRows(form);
    }

    function syncPurchaseOrderAmount(row) {
        if (!row) {
            return;
        }

        const quantityRaw = row.dataset.poQuantity || '';
        const quantity = Number(quantityRaw);
        const unitCostInput = row.querySelector('[data-po-unit-cost]');
        const amountInput = row.querySelector('[data-po-amount]');
        const unitCost = unitCostInput ? Number(unitCostInput.value) : Number.NaN;

        if (!amountInput) {
            return;
        }

        amountInput.value = quantityRaw.trim() !== '' && Number.isFinite(quantity) && Number.isFinite(unitCost) && unitCostInput.value.trim() !== ''
            ? (quantity * unitCost).toFixed(2)
            : '';
    }

    function setupPurchaseOrderAmounts() {
        document.querySelectorAll('[data-po-row]').forEach(syncPurchaseOrderAmount);
    }

    function syncPurchaseOrderPaymentModes(changedInput = null) {
        const form = changedInput ? changedInput.closest('[data-po-form]') : null;
        const forms = form ? [form] : Array.from(document.querySelectorAll('[data-po-form]'));

        forms.forEach((poForm) => {
            const modes = Array.from(poForm.querySelectorAll('[data-po-payment-mode]'));
            if (changedInput && changedInput.checked) {
                modes.forEach((mode) => {
                    if (mode !== changedInput) {
                        mode.checked = false;
                    }
                });
            }

            const hasSelection = modes.some((mode) => mode.checked);
            modes.forEach((mode, index) => {
                mode.required = !hasSelection && index === 0;
            });
        });
    }

    function syncDisbursementVoucherAmounts(form) {
        if (!form) {
            return;
        }

        const grossInput = form.querySelector('[data-dv-gross]');
        const taxTypeSelect = form.querySelector('[data-dv-tax-type]');
        const taxInput = form.querySelector('[data-dv-tax-input]');
        const netInput = form.querySelector('[data-dv-net]');
        const grossValue = grossInput ? grossInput.value.trim() : '';
        const gross = Number(grossValue);
        const taxEnabled = taxTypeSelect ? taxTypeSelect.value !== '' : false;

        if (taxInput) {
            taxInput.disabled = !taxEnabled || taxInput.dataset.originalDisabled === '1';
            if (!taxEnabled) {
                taxInput.value = '0';
            }
        }

        if (!netInput) {
            return;
        }

        const taxValue = taxEnabled && taxInput ? taxInput.value.trim() : '0';
        const tax = Number(taxValue);
        netInput.value = grossValue !== '' && Number.isFinite(gross) && taxValue !== '' && Number.isFinite(tax)
            ? (gross - tax).toFixed(2)
            : '';
    }

    function setupDisbursementVoucherAmounts() {
        document.querySelectorAll('[data-dv-form]').forEach((form) => {
            const taxInput = form.querySelector('[data-dv-tax-input]');
            if (taxInput && taxInput.disabled && form.querySelector('[data-dv-tax-type]')?.value === '') {
                taxInput.dataset.originalDisabled = '0';
            }
            syncDisbursementVoucherAmounts(form);
        });
    }

    function reindexTaxTypes(section) {
        const rows = section ? Array.from(section.querySelectorAll('[data-tax-type-row]')) : [];
        rows.forEach((row, index) => {
            const input = row.querySelector('input');
            if (input) {
                input.name = `tax_types[${index}][name]`;
            }
        });
        const emptyState = section ? section.querySelector('[data-tax-types-empty]') : null;
        if (emptyState) {
            emptyState.hidden = rows.length !== 0;
        }
    }

    function addTaxType(button) {
        const section = button.closest('[data-tax-management]');
        const list = section ? section.querySelector('[data-tax-type-list]') : null;
        if (!section || !list) {
            return;
        }
        const row = document.createElement('div');
        row.className = 'tax-type-row';
        row.dataset.taxTypeRow = '';
        row.innerHTML = '<label><span>Tax Type Name</span><input type="text" maxlength="80" required></label><button type="button" class="secondary compact" data-remove-tax-type>Delete</button>';
        list.appendChild(row);
        reindexTaxTypes(section);
        row.querySelector('input')?.focus();
    }

    function hoverNoteContent(anchor) {
        const title = anchor.dataset.hoverNoteTitle || '';
        const body = anchor.dataset.hoverNoteBody || '';
        return { title, body };
    }

    function positionHoverNote(note, event) {
        const offset = 14;
        const margin = 12;
        const bounds = note.getBoundingClientRect();
        let left = event.clientX + offset;
        let top = event.clientY + offset;

        if (left + bounds.width + margin > window.innerWidth) {
            left = event.clientX - bounds.width - offset;
        }
        if (top + bounds.height + margin > window.innerHeight) {
            top = event.clientY - bounds.height - offset;
        }

        note.style.left = `${Math.max(margin, left)}px`;
        note.style.top = `${Math.max(margin, top)}px`;
    }

    function setupStartModal() {
        const modal = document.querySelector('[data-start-modal]');
        const form = modal ? modal.querySelector('[data-start-modal-form]') : null;
        if (!modal || !form) {
            return;
        }

        let opener = null;
        const closeModal = () => {
            modal.hidden = true;
            if (opener && opener.isConnected) {
                opener.focus();
            }
            opener = null;
        };

        modal.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                .filter((element) => !element.hidden && element.getClientRects().length > 0);
            if (focusable.length === 0) {
                event.preventDefault();
                modal.querySelector('.workflow-start-modal__panel')?.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) {
                event.preventDefault();
                first.focus();
            }
        });

        document.addEventListener('click', (event) => {
            const startButton = event.target.closest('[data-start-workflow]');
            if (startButton) {
                opener = startButton;
                form.action = startButton.dataset.startAction || form.action;
                modal.hidden = false;
                const proceedButton = modal.querySelector('button[type="submit"]');
                if (proceedButton) {
                    proceedButton.focus();
                } else {
                    modal.querySelector('.workflow-start-modal__panel')?.focus();
                }
                return;
            }

            if (event.target.closest('[data-start-modal-close]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                event.preventDefault();
                closeModal();
            }
        });
    }

    function setupSystemInputSaveState() {
        document.querySelectorAll('[data-system-input-form]').forEach((form) => {
            const status = form.querySelector('[data-save-status]');
            const statusLabel = status?.querySelector('[data-save-status-label]');
            const countBadge = status?.querySelector('[data-unsaved-count-badge]');
            const countText = countBadge?.querySelector('[data-unsaved-count]');
            const countLabel = countBadge?.querySelector('[data-unsaved-count-label]');
            let isDirty = false;
            const initialFields = new Map();
            const initialRadioValues = new Map();
            const initialTaxRows = new Map();

            Array.from(form.elements).forEach((control) => {
                if (!control.name || ['hidden', 'submit', 'button', 'reset', 'file'].includes(control.type)) return;
                if (control.type === 'radio') {
                    if (!initialRadioValues.has(control.name)) {
                        const selected = Array.from(form.elements).find((candidate) => candidate.type === 'radio' && candidate.name === control.name && candidate.checked);
                        initialRadioValues.set(control.name, selected?.value || '');
                    }
                    return;
                }
                if (control.closest('[data-tax-type-row]')) return;
                initialFields.set(control, control.type === 'checkbox' ? control.checked : control.value);
            });
            form.querySelectorAll('[data-tax-type-row]').forEach((row) => {
                initialTaxRows.set(row, row.querySelector('input')?.value.trim() || '');
            });

            const currentValue = (control) => {
                if (control.type === 'checkbox') return control.checked;
                if (control.tagName === 'SELECT') return control.value;
                return control.value;
            };
            const getUnsavedCount = () => {
                let count = 0;
                const seenRadioNames = new Set();
                Array.from(form.elements).forEach((control) => {
                    if (!control.name || ['hidden', 'submit', 'button', 'reset', 'file'].includes(control.type)) return;
                    if (control.type === 'radio') {
                        if (seenRadioNames.has(control.name)) return;
                        seenRadioNames.add(control.name);
                        const selected = Array.from(form.elements).find((candidate) => candidate.type === 'radio' && candidate.name === control.name && candidate.checked);
                        if ((selected?.value || '') !== (initialRadioValues.get(control.name) || '')) count += 1;
                        return;
                    }
                    if (control.closest('[data-tax-type-row]')) return;
                    if (!initialFields.has(control) || currentValue(control) !== initialFields.get(control)) count += 1;
                });

                const currentTaxRows = new Set(form.querySelectorAll('[data-tax-type-row]'));
                initialTaxRows.forEach((initialName, row) => {
                    if (!currentTaxRows.has(row) || (row.querySelector('input')?.value.trim() || '') !== initialName) count += 1;
                });
                currentTaxRows.forEach((row) => {
                    if (!initialTaxRows.has(row)) count += 1;
                });
                if (form.querySelector('input[type="file"][name]')?.files?.length) count += 1;
                return count;
            };

            const refreshStatus = () => {
                const count = getUnsavedCount();
                isDirty = count > 0;
                form.dataset.unsavedCount = String(count);
                form.classList.toggle('is-dirty', isDirty);
                if (status) {
                    if (statusLabel) statusLabel.textContent = isDirty ? 'Changes pending' : 'All changes saved';
                    if (countText) countText.textContent = String(count);
                    if (countLabel) countLabel.textContent = count === 1 ? 'unsaved change' : 'unsaved changes';
                    if (countBadge) countBadge.hidden = !isDirty;
                    status.classList.toggle('is-dirty', isDirty);
                }
            };

            form.dataset.unsavedCount = '0';
            form.addEventListener('input', refreshStatus);
            form.addEventListener('change', refreshStatus);
            form.addEventListener('click', (event) => {
                if (event.target.closest('[data-add-tax-type], [data-remove-tax-type]')) {
                    window.setTimeout(refreshStatus, 0);
                }
            });
            form.addEventListener('submit', () => {
                isDirty = false;
                form.dataset.unsavedCount = '0';
                form.classList.remove('is-dirty');
                if (status) {
                    if (statusLabel) statusLabel.textContent = 'Saving changes…';
                    if (countBadge) countBadge.hidden = true;
                    status.classList.remove('is-dirty');
                }
            });

            window.addEventListener('beforeunload', (event) => {
                if (!isDirty || window.__btsAllowUnsavedNavigation) {
                    return;
                }
                event.preventDefault();
                event.returnValue = '';
            });
        });
    }

    function setupSettingsSectionNavigation() {
        const scrollToSection = (hash, behavior = 'auto') => {
            if (!hash || !hash.startsWith('#')) {
                return;
            }
            const target = document.getElementById(decodeURIComponent(hash.slice(1)));
            if (!target || !target.matches('.system-input-form [id^="report-section-"], .system-ui-form [id^="system-ui-section-"]')) {
                return;
            }
            if (!target.hasAttribute('tabindex')) {
                target.setAttribute('tabindex', '-1');
            }
            target.focus({ preventScroll: true });
            target.scrollIntoView({ behavior, block: 'start' });
        };

        document.addEventListener('click', (event) => {
            const link = event.target.closest('.settings-section-nav a[href^="#"]');
            if (!link) {
                return;
            }
            const hash = link.getAttribute('href');
            if (!hash || !document.getElementById(decodeURIComponent(hash.slice(1)))) {
                return;
            }
            event.preventDefault();
            window.history.pushState({ settingsSection: hash }, '', hash);
            scrollToSection(hash, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth');
        });

        window.addEventListener('popstate', () => scrollToSection(window.location.hash));
    }

    function setupConfirmationModal() {
        const dialog = document.querySelector('[data-confirmation-modal]');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        const title = dialog.querySelector('[data-confirmation-title]');
        const message = dialog.querySelector('[data-confirmation-message]');
        const note = dialog.querySelector('[data-confirmation-note]');
        const eyebrow = dialog.querySelector('[data-confirmation-eyebrow]');
        const accept = dialog.querySelector('[data-confirmation-accept]');
        const cancel = dialog.querySelector('[data-confirmation-cancel]');
        const secondary = dialog.querySelector('[data-confirmation-secondary]');
        const changesList = dialog.querySelector('[data-confirmation-changes]');
        const successIcon = dialog.querySelector('[data-confirmation-success-icon]');
        let opener = null;
        let onConfirm = null;
        let successTimeoutId = null;

        const close = () => {
            if (successTimeoutId !== null) {
                window.clearTimeout(successTimeoutId);
                successTimeoutId = null;
            }
            if (dialog.open) dialog.close();
        };
        const setChanges = (changes) => {
            changesList.replaceChildren();
            changes.forEach((change) => {
                const item = document.createElement('li');
                item.textContent = change;
                changesList.appendChild(item);
            });
            changesList.hidden = changes.length === 0;
        };
        const open = (source, action, changes = []) => {
            opener = source;
            onConfirm = action;
            title.textContent = source.dataset.confirmTitle || 'Confirm this change';
            message.textContent = source.dataset.confirmMessage || source.dataset.confirmSubmit || source.dataset.confirmDelete || 'Are you sure you want to continue?';
            note.textContent = source.dataset.confirmNote || '';
            note.hidden = note.textContent === '';
            setChanges(changes);
            eyebrow.textContent = source.matches('[data-confirm-delete]') ? 'Trash and retention' : 'Review changes';
            accept.textContent = source.dataset.confirmLabel || 'Confirm';
            cancel.textContent = 'Cancel';
            cancel.hidden = false;
            secondary.hidden = true;
            successIcon.hidden = true;
            dialog.classList.remove('is-success');
            dialog.classList.toggle('is-danger', source.dataset.confirmDanger === 'true' || source.matches('[data-confirm-delete]'));
            dialog.showModal();
            accept.focus();
        };
        const openSuccess = (source) => {
            opener = null;
            onConfirm = null;
            eyebrow.textContent = 'Completed successfully';
            title.textContent = source.dataset.successTitle || 'Changes saved';
            message.textContent = source.dataset.successMessage || 'The requested change was completed successfully.';
            note.textContent = '';
            note.hidden = true;
            setChanges([]);
            accept.textContent = source.dataset.successLabel || 'Done';
            cancel.hidden = true;
            secondary.textContent = source.dataset.successSecondaryLabel || '';
            secondary.dataset.href = source.dataset.successSecondaryHref || '';
            secondary.hidden = secondary.dataset.href === '';
            successIcon.hidden = false;
            dialog.classList.remove('is-danger');
            dialog.classList.add('is-success');
            dialog.showModal();
            accept.focus();
            successTimeoutId = window.setTimeout(close, 2000);
        };
        const openNavigationWarning = (link, destination, changeCount) => {
            opener = link;
            onConfirm = () => {
                window.__btsAllowUnsavedNavigation = true;
                window.location.assign(destination.href);
            };
            eyebrow.textContent = 'Unsaved changes';
            title.textContent = 'Leave this page?';
            message.textContent = `You have ${changeCount} unsaved ${changeCount === 1 ? 'change' : 'changes'}.`;
            note.textContent = 'Your unsaved edits will be discarded if you leave.';
            note.hidden = false;
            setChanges([]);
            accept.textContent = 'Leave page';
            cancel.textContent = 'Stay on page';
            cancel.hidden = false;
            secondary.hidden = true;
            successIcon.hidden = true;
            dialog.classList.remove('is-success');
            dialog.classList.add('is-danger');
            dialog.showModal();
            cancel.focus();
        };

        const labelFor = (control) => {
            if (control.name === 'ui[app_title]') return 'Sidebar title';
            if (control.name === 'ui[reset_logo]') return 'Restore default logo';
            const row = control.closest('[data-tax-type-row]');
            if (row) return 'Tax type';
            const palette = control.closest('.palette-option')?.querySelector('.palette-option-copy strong')?.textContent?.trim();
            if (palette) return 'Theme palette';
            const reportColor = control.closest('.report-color-card')?.querySelector('.report-color-card-heading strong')?.textContent?.trim();
            if (reportColor) return `${reportColor} cell color`;
            const field = control.closest('.system-input-field')?.querySelector(':scope > span')?.textContent?.trim();
            if (field) return field;
            const label = control.closest('label');
            return label?.textContent?.replace(control.value || '', '').trim().replace(/\s+/g, ' ') || control.name;
        };
        const radioDisplayFor = (control) => control.closest('.palette-option')?.querySelector('.palette-option-copy strong')?.textContent?.trim() || control.value;
        const valueFor = (control) => {
            if (control.type === 'checkbox') return control.checked ? 'Enabled' : 'Disabled';
            if (control.type === 'file') return control.files?.[0]?.name || '';
            if (control.tagName === 'SELECT') return control.selectedOptions[0]?.textContent?.trim() || control.value;
            if (control.type === 'color') return control.value.toUpperCase();
            return String(control.value ?? '').trim();
        };
        const captureBaseline = (form) => {
            const fields = new Map();
            const radios = new Map();
            Array.from(form.elements).forEach((control) => {
                if (!control.name || ['hidden', 'submit', 'button', 'reset', 'file'].includes(control.type)) return;
                if (control.type === 'radio') {
                    if (!radios.has(control.name)) {
                        const selected = Array.from(form.elements).find((candidate) => candidate.type === 'radio' && candidate.name === control.name && candidate.checked);
                        radios.set(control.name, {
                            value: selected?.value || '',
                            label: control.name.includes('theme_preset') ? 'Theme palette' : labelFor(control),
                            display: selected ? radioDisplayFor(selected) : 'None',
                        });
                    }
                    return;
                }
                fields.set(control, { name: control.name, label: labelFor(control), value: valueFor(control) });
            });
            return { fields, radios };
        };
        const collectChanges = (form, baseline) => {
            const changes = [];
            const seen = new Set();
            const currentRadioNames = new Set();
            Array.from(form.elements).forEach((control) => {
                if (!control.name || ['hidden', 'submit', 'button', 'reset', 'file'].includes(control.type)) return;
                if (control.type === 'radio') {
                    if (currentRadioNames.has(control.name)) return;
                    currentRadioNames.add(control.name);
                    const selected = Array.from(form.elements).find((candidate) => candidate.type === 'radio' && candidate.name === control.name && candidate.checked);
                    const before = baseline.radios.get(control.name);
                    const after = selected?.value || '';
                    if (before && before.value !== after) {
                        changes.push(`${before.label}: ${before.display} → ${selected ? radioDisplayFor(selected) : 'None'}`);
                    }
                    return;
                }

                seen.add(control);
                const current = valueFor(control);
                const before = baseline.fields.get(control);
                if (before && before.value !== current) {
                    const label = before.label === 'Tax type' ? 'Tax type name' : before.label;
                    changes.push(`${label}: ${before.value || 'Empty'} → ${current || 'Empty'}`);
                } else if (!before && current !== '') {
                    const label = labelFor(control);
                    changes.push(`${label === 'Tax type' ? 'Added tax type' : `Added ${label}`}: ${current}`);
                }
            });

            baseline.fields.forEach((before, control) => {
                if (seen.has(control) || form.contains(control) || before.label !== 'Tax type') return;
                changes.push(`Removed tax type: ${before.value || 'Unnamed'}`);
            });
            baseline.radios.forEach((before, name) => {
                if (currentRadioNames.has(name)) return;
                changes.push(`${before.label}: ${before.label} → None`);
            });

            const file = form.querySelector('input[type="file"][name]');
            if (file?.files?.[0]) {
                const previousLogo = form.querySelector('[data-logo-preview] img');
                changes.push(`System logo: ${previousLogo ? 'current logo' : 'default logo'} → ${file.files[0].name}`);
            }
            return changes;
        };

        const formBaselines = new WeakMap();
        document.querySelectorAll('[data-confirm-submit], [data-confirm-delete]').forEach((form) => {
            if (form.tagName === 'FORM') formBaselines.set(form, captureBaseline(form));
        });

        dialog.addEventListener('close', () => {
            if (successTimeoutId !== null) {
                window.clearTimeout(successTimeoutId);
                successTimeoutId = null;
            }
            if (opener && opener.isConnected) opener.focus();
            opener = null;
            onConfirm = null;
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) close();
        });
        cancel.addEventListener('click', close);
        secondary.addEventListener('click', () => {
            const destination = secondary.dataset.href;
            close();
            if (destination) window.location.assign(destination);
        });
        accept.addEventListener('click', () => {
            const confirm = onConfirm;
            close();
            if (confirm) confirm();
        });

        const successMessage = document.querySelector('[data-success-modal]');
        if (successMessage) {
            openSuccess(successMessage);
            const url = new URL(window.location.href);
            ['saved', 'deleted', 'restored', 'purged'].forEach((key) => url.searchParams.delete(key));
            window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
        }

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-confirm-submit], [data-confirm-delete]');
            if (!form) return;
            if (form.dataset.confirmationApproved === 'true') {
                delete form.dataset.confirmationApproved;
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            const submitter = event.submitter;
            const baseline = formBaselines.get(form) || captureBaseline(form);
            const changes = collectChanges(form, baseline);
            if (changes.length === 0 && form.matches('[data-system-input-form]')) changes.push('No field changes detected.');
            open(form, () => {
                form.dataset.confirmationApproved = 'true';
                if (submitter && typeof form.requestSubmit === 'function') form.requestSubmit(submitter);
                else if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }, changes);
        }, true);

        document.addEventListener('click', (event) => {
            const action = event.target.closest('[data-confirm-action]');
            if (!action) return;
            if (action.dataset.confirmationApproved === 'true') {
                delete action.dataset.confirmationApproved;
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            open(action, () => {
                action.dataset.confirmationApproved = 'true';
                action.click();
            });
        }, true);

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest('a[href]');
            if (!link || link.target || link.hasAttribute('download')) return;
            const dirtyForms = Array.from(document.querySelectorAll('[data-system-input-form]'))
                .filter((form) => Number(form.dataset.unsavedCount || 0) > 0);
            if (dirtyForms.length === 0) return;

            let destination;
            try {
                destination = new URL(link.href, window.location.href);
            } catch {
                return;
            }
            if (destination.origin !== window.location.origin) return;
            if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;

            const changeCount = dirtyForms.reduce((total, form) => total + Number(form.dataset.unsavedCount || 0), 0);
            event.preventDefault();
            event.stopImmediatePropagation();
            openNavigationWarning(link, destination, changeCount);
        }, true);
    }

    function setupAuthInfoDialog() {
        const dialog = document.querySelector('[data-auth-info-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function') return;

        const title = dialog.querySelector('[data-auth-info-title]');
        const content = dialog.querySelector('[data-auth-info-content]');
        const closeButton = dialog.querySelector('[data-auth-info-close]');
        const copy = document.querySelector('[data-auth-info-copy]');
        const titles = {
            privacy: 'Privacy Policy',
            terms: 'Terms of Use',
            community: 'Community Standards',
            about: 'About the System',
        };
        if (!title || !content || !closeButton || !copy) return;

        document.querySelectorAll('[data-auth-info]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const key = trigger.dataset.authInfo;
                const source = copy.querySelector(`[data-auth-info-text="${key}"]`);
                if (!source) return;

                title.textContent = titles[key] || trigger.textContent.trim();
                content.replaceChildren(source.cloneNode(true));
                dialog.dataset.lastInfo = key;
                dialog.showModal();
                closeButton.focus();
            });
        });

        closeButton.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            const opener = document.querySelector(`[data-auth-info="${dialog.dataset.lastInfo || ''}"]`);
            if (opener) opener.focus();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    }
    function setupLoginLockout(form, usernameInput) {
        const status = form.querySelector('[data-login-lockout-status]');
        const error = form.querySelector('[data-login-error]');
        const statusUrl = form.dataset.loginLockoutStatusUrl;
        if (!status || !statusUrl || !usernameInput) return;

        const controls = Array.from(form.querySelectorAll('input, button'));
        let lockoutUntil = Number(form.dataset.loginLockoutUntil || 0) * 1000;
        let lockoutActive = lockoutUntil > Date.now();
        let countdownTimer = 0;
        let checkTimer = 0;
        let requestNumber = 0;

        const setDisabled = (disabled) => controls.forEach((control) => { control.disabled = disabled; });
        const formatRemaining = (seconds) => {
            const minutes = Math.floor(seconds / 60);
            const remainder = seconds % 60;
            return minutes > 0 ? `${minutes}:${String(remainder).padStart(2, '0')}` : `${remainder}s`;
        };
        const unlock = () => {
            window.clearTimeout(countdownTimer);
            lockoutActive = false;
            lockoutUntil = 0;
            form.dataset.loginLockoutUntil = '0';
            setDisabled(false);
            status.hidden = true;
            status.textContent = '';
        };
        const renderCountdown = () => {
            if (!lockoutActive) return;
            const remaining = Math.ceil((lockoutUntil - Date.now()) / 1000);
            if (remaining <= 0) {
                checkStatus();
                return;
            }

            setDisabled(true);
            if (error) error.hidden = true;
            status.hidden = false;
            status.textContent = `Too many failed attempts. Please wait ${formatRemaining(remaining)} before trying again.`;
            countdownTimer = window.setTimeout(renderCountdown, Math.min(1000, remaining * 1000));
        };
        const applyStatus = (remaining) => {
            if (remaining > 0) {
                lockoutActive = true;
                lockoutUntil = Date.now() + remaining * 1000;
                form.dataset.loginLockoutUntil = String(Math.ceil(lockoutUntil / 1000));
                renderCountdown();
                return;
            }
            unlock();
        };
        const checkStatus = async () => {
            const username = usernameInput.value.trim();
            if (!username || !usernameInput.checkValidity()) {
                if (lockoutActive && lockoutUntil <= Date.now()) unlock();
                return;
            }

            const currentRequest = ++requestNumber;
            try {
                const response = await fetch(statusUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: new URLSearchParams({ username }),
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                const result = await response.json();
                if (currentRequest !== requestNumber || username !== usernameInput.value.trim()) return;
                if (!Number.isInteger(result.retryAfterSeconds)) throw new Error('Lockout status unavailable');
                applyStatus(result.retryAfterSeconds);
            } catch {
                if (currentRequest !== requestNumber) return;
                if (lockoutActive) {
                    setDisabled(true);
                    countdownTimer = window.setTimeout(checkStatus, 1000);
                }
            }
        };
        const scheduleStatusCheck = () => {
            window.clearTimeout(checkTimer);
            requestNumber++;
            if (!usernameInput.value.trim() || !usernameInput.checkValidity()) {
                if (lockoutActive && lockoutUntil <= Date.now()) unlock();
                return;
            }
            checkTimer = window.setTimeout(checkStatus, 200);
        };

        usernameInput.addEventListener('input', scheduleStatusCheck);
        usernameInput.addEventListener('change', scheduleStatusCheck);
        usernameInput.addEventListener('blur', scheduleStatusCheck);
        if (lockoutActive) {
            setDisabled(true);
            renderCountdown();
        } else {
            scheduleStatusCheck();
        }
    }

    function setupLoginForm() {
        const form = document.querySelector('[data-login-form]');
        if (!form) return;

        const usernameInput = form.querySelector('[name="username"]');
        const passwordInput = form.querySelector('[name="password"]');
        const rememberInput = form.querySelector('[data-remember-username]');
        const passwordToggle = form.querySelector('[data-password-toggle]');
        const rememberedUsernameKey = 'bts.login.remembered-username';

        if (usernameInput && rememberInput) {
            try {
                const rememberedUsername = window.localStorage.getItem(rememberedUsernameKey);
                if (rememberedUsername) {
                    if (!usernameInput.value.trim()) usernameInput.value = rememberedUsername;
                    rememberInput.checked = true;
                }
            } catch {
                // Keep the login usable when browser storage is disabled.
            }

            const persistRememberedUsername = () => {
                try {
                    const username = usernameInput.value.trim();
                    if (rememberInput.checked && username) {
                        window.localStorage.setItem(rememberedUsernameKey, username);
                    } else if (!rememberInput.checked || !username) {
                        window.localStorage.removeItem(rememberedUsernameKey);
                    }
                } catch {
                    // Storage may be unavailable in private or restricted contexts.
                }
            };

            rememberInput.addEventListener('change', persistRememberedUsername);
            usernameInput.addEventListener('input', persistRememberedUsername);
            form.addEventListener('submit', persistRememberedUsername);
        }

        if (usernameInput) setupLoginLockout(form, usernameInput);

        if (passwordInput && passwordToggle) {
            passwordToggle.addEventListener('click', () => {
                const showPassword = passwordInput.type === 'password';
                passwordInput.type = showPassword ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', String(showPassword));
                passwordToggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
                passwordToggle.classList.toggle('is-visible', showPassword);
            });
        }
    }

    function setupPageLoadingSkeleton() {
        const skeleton = document.querySelector('[data-page-loading-skeleton]');
        if (!skeleton || document.body.classList.contains('is-guest')) return;

        let showTimer = 0;
        const reset = () => {
            window.clearTimeout(showTimer);
            document.body.classList.remove('is-page-loading');
            skeleton.hidden = true;
        };
        if (document.body.classList.contains('is-login-transition')) {
            showTimer = window.setTimeout(() => {
                document.body.classList.remove('is-login-transition');
                reset();
            }, 1500);
        }
        const schedule = (event) => {
            window.clearTimeout(showTimer);
            showTimer = window.setTimeout(() => {
                if (event.defaultPrevented) return;
                skeleton.hidden = false;
                document.body.classList.add('is-page-loading');
            }, 180);
        };

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const target = event.target instanceof Element ? event.target : null;
            const link = target?.closest('a[href]');
            if (!link || link.target || link.hasAttribute('download') || link.hasAttribute('data-page-loading-ignore')) return;
            if (link.matches('[role="tab"], [data-section-link], [data-pagination-link]')) return;

            let destination;
            try {
                destination = new URL(link.href, window.location.href);
            } catch {
                return;
            }
            if (destination.origin !== window.location.origin) return;
            if (destination.pathname === window.location.pathname && destination.search === window.location.search) return;
            schedule(event);
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.hasAttribute('data-page-loading-ignore')) return;
            if (form.target && form.target !== '_self') return;
            if ((form.method || 'get').toLowerCase() === 'dialog') return;
            schedule(event);
        });

        window.addEventListener('pageshow', (event) => {
            if (event.persisted || !document.body.classList.contains('is-login-transition')) reset();
        });
        window.addEventListener('pagehide', () => window.clearTimeout(showTimer));
    }

    function setupLoginBrandingAnimation() {
        const headings = Array.from(document.querySelectorAll('[data-brand-headline]'));
        const supportingLine = document.querySelector('.auth-welcome');
        if (!headings.length || !supportingLine || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        if (!window.gsap || !window.SplitText || !window.ScrollTrigger) {
            return;
        }

        window.gsap.registerPlugin(window.SplitText, window.ScrollTrigger);
        const gsap = window.gsap;
        const entranceTimeline = gsap.timeline();
        const animations = headings.map((heading) => {
            const split = window.SplitText.create(heading, {
                type: 'chars,words',
                charsClass: 'char',
                wordsClass: 'word',
                aria: 'auto',
            });
            if (!split.chars.length) {
                split.revert();
                return null;
            }

            heading.setAttribute('aria-label', heading.textContent.trim());
            heading.classList.add('is-animating');
            const fromVars = {
                opacity: 0,
                y: 46,
                rotationX: -95,
                transformPerspective: 600,
                transformOrigin: '50% 100%',
            };
            const entranceVars = {
                opacity: 1,
                y: 0,
                rotationX: 0,
                duration: 1.3,
                stagger: { each: 0.035, from: 'start' },
                ease: 'expo.out',
                overwrite: true,
            };
            const playEntrance = () => {
                gsap.killTweensOf(split.chars);
                gsap.fromTo(split.chars, fromVars, entranceVars);
            };
            const playExit = () => {
                gsap.killTweensOf(split.chars);
                gsap.to(split.chars, {
                    opacity: 0,
                    rotationX: 70,
                    y: -26,
                    duration: 0.35,
                    stagger: 0.012,
                    ease: 'power2.in',
                    overwrite: true,
                });
            };

            entranceTimeline.fromTo(split.chars, fromVars, entranceVars);
            return {
                split,
                scrollTrigger: window.ScrollTrigger.create({
                    trigger: heading,
                    start: 'top bottom',
                    end: 'bottom top',
                    onLeave: playExit,
                    onEnterBack: () => playEntrance(),
                }),
            };
        }).filter(Boolean);

        gsap.fromTo(supportingLine, { opacity: 0, y: 10 }, {
            opacity: 1,
            y: 0,
            duration: 0.7,
            delay: Math.max(0, entranceTimeline.duration() - 0.7),
            ease: 'power2.out',
            overwrite: true,
        });

        window.addEventListener('pagehide', () => {
            animations.forEach(({ split, scrollTrigger }) => {
                scrollTrigger.kill();
                gsap.killTweensOf(split.chars);
                split.revert();
            });
            gsap.killTweensOf(supportingLine);
        }, { once: true });
    }

    function setupArchiveDateRange() {
        const form = document.querySelector('[data-archive-filter-form]');
        const picker = form?.querySelector('[data-date-range-picker]');
        if (!form || !picker) {
            return;
        }

        const fromInput = picker.querySelector('[data-date-range-from]');
        const toInput = picker.querySelector('[data-date-range-to]');
        const trigger = picker.querySelector('[data-date-range-trigger]');
        const clearButton = picker.querySelector('[data-date-range-clear]');
        const dateRangeControl = picker.querySelector('.archive-date-range-control');
        const label = picker.querySelector('[data-date-range-label]');
        const popover = picker.querySelector('[data-date-range-popover]');
        const preview = picker.querySelector('[data-date-range-preview]');
        const applyButton = picker.querySelector('[data-date-range-apply]');
        const cancelButton = picker.querySelector('[data-date-range-cancel]');
        const manualFromInput = picker.querySelector('[data-date-range-manual-from]');
        const manualToInput = picker.querySelector('[data-date-range-manual-to]');
        const months = Array.from(picker.querySelectorAll('[data-calendar-month]'));
        if (!fromInput || !toInput || !trigger || !clearButton || !dateRangeControl || !label || !popover || !manualFromInput || !manualToInput || months.length !== 2) {
            return;
        }

        const parseDate = (value) => {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
            if (!match) {
                return null;
            }
            const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
            return date.getUTCFullYear() === Number(match[1])
                && date.getUTCMonth() === Number(match[2]) - 1
                && date.getUTCDate() === Number(match[3]) ? date : null;
        };
        const dateKey = (date) => date ? date.toISOString().slice(0, 10) : '';
        const monthStart = (date, offset = 0) => new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + offset, 1));
        const formatMonth = (date) => new Intl.DateTimeFormat('en-US', {
            month: 'long', year: 'numeric', timeZone: 'UTC',
        }).format(date);
        const formatDate = (date) => new Intl.DateTimeFormat('en-US', {
            month: '2-digit', day: '2-digit', year: 'numeric', timeZone: 'UTC',
        }).format(date);
        const formatManualDate = (date) => date
            ? `${String(date.getUTCMonth() + 1).padStart(2, '0')}/${String(date.getUTCDate()).padStart(2, '0')}/${date.getUTCFullYear()}`
            : '';
        const parseManualDate = (value) => {
            const match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(value.trim());
            if (!match) return null;
            const month = Number(match[1]);
            const day = Number(match[2]);
            const year = Number(match[3]);
            const date = new Date(Date.UTC(year, month - 1, day));
            return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day
                ? date
                : null;
        };
        const formatAccessibleDate = (date) => new Intl.DateTimeFormat('en-US', {
            weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC',
        }).format(date);
        const formatRangeLabel = (start, end) => {
            if (start && end) {
                return `${formatDate(start)} → ${formatDate(end)}`;
            }
            if (start) {
                return `${formatDate(start)} → Select end`;
            }
            if (end) {
                return `Select start → ${formatDate(end)}`;
            }
            return 'Select Start and End Date';
        };

        let appliedStart = parseDate(fromInput.value);
        let appliedEnd = parseDate(toInput.value);
        let selectedStart = appliedStart;
        let selectedEnd = appliedEnd;
        let visibleMonth = monthStart(appliedStart || appliedEnd || new Date());
        let manualDateError = '';

        const restoreAppliedRange = () => {
            appliedStart = parseDate(fromInput.value);
            appliedEnd = parseDate(toInput.value);
            selectedStart = appliedStart;
            selectedEnd = appliedEnd;
            visibleMonth = monthStart(appliedStart || appliedEnd || new Date());
            label.textContent = formatRangeLabel(appliedStart, appliedEnd);
            clearButton.hidden = !appliedStart && !appliedEnd;
            dateRangeControl.classList.toggle('has-clear', Boolean(appliedStart || appliedEnd));
            manualFromInput.value = formatManualDate(appliedStart);
            manualToInput.value = formatManualDate(appliedEnd);
            manualFromInput.removeAttribute('aria-invalid');
            manualToInput.removeAttribute('aria-invalid');
            manualDateError = '';
            render();
        };

        const updatePreview = () => {
            if (manualDateError) {
                preview.textContent = manualDateError;
                applyButton.disabled = true;
                return;
            }
            if (selectedStart && selectedEnd) {
                preview.textContent = `${formatDate(selectedStart)} → ${formatDate(selectedEnd)}`;
                applyButton.disabled = false;
                return;
            }
            if (!selectedStart && !selectedEnd) {
                preview.textContent = 'Select start and end dates';
                applyButton.disabled = true;
                return;
            }
            preview.textContent = selectedStart
                ? `${formatDate(selectedStart)} → Choose end date`
                : `${formatDate(selectedEnd)} → Choose start date`;
            applyButton.disabled = true;
        };

        const updateSelection = () => {
            const startKey = dateKey(selectedStart);
            const endKey = dateKey(selectedEnd);
            picker.querySelectorAll('[data-date]').forEach((button) => {
                const key = button.dataset.date;
                button.classList.toggle('is-in-range', Boolean(startKey && endKey && key > startKey && key < endKey));
                button.classList.toggle('is-range-start', key === startKey);
                button.classList.toggle('is-range-end', Boolean(endKey && key === endKey && endKey !== startKey));
                button.setAttribute('aria-pressed', String(key === startKey || key === endKey));
            });
            manualFromInput.value = formatManualDate(selectedStart);
            manualToInput.value = formatManualDate(selectedEnd);
            manualFromInput.removeAttribute('aria-invalid');
            manualToInput.removeAttribute('aria-invalid');
            updatePreview();
        };

        const syncManualDates = () => {
            const startValue = manualFromInput.value.trim();
            const endValue = manualToInput.value.trim();
            const start = startValue === '' ? null : parseManualDate(startValue);
            const end = endValue === '' ? null : parseManualDate(endValue);
            const invalidStart = startValue !== '' && !start;
            const invalidEnd = endValue !== '' && !end;
            manualFromInput.setAttribute('aria-invalid', String(invalidStart));
            manualToInput.setAttribute('aria-invalid', String(invalidEnd));

            if (invalidStart || invalidEnd) {
                manualDateError = 'Enter valid dates as MM/DD/YYYY.';
                updatePreview();
                return;
            }
            if (start && end && dateKey(start) > dateKey(end)) {
                manualToInput.setAttribute('aria-invalid', 'true');
                manualDateError = 'End date must be on or after start date.';
                updatePreview();
                return;
            }

            manualDateError = '';
            selectedStart = start;
            selectedEnd = end;
            visibleMonth = monthStart(selectedStart || selectedEnd || visibleMonth);
            render();
        };

        const renderMonth = (monthElement, monthOffset) => {
            const date = monthStart(visibleMonth, monthOffset);
            monthElement.querySelector('[data-calendar-title]').textContent = formatMonth(date);
            const daysContainer = monthElement.querySelector('[data-calendar-days]');
            const firstDay = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
            const sundayOffset = firstDay.getUTCDay();
            const firstCell = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1 - sundayOffset));
            const startKey = dateKey(selectedStart);
            const endKey = dateKey(selectedEnd);
            const cells = [];

            for (let index = 0; index < 42; index += 1) {
                const day = new Date(firstCell);
                day.setUTCDate(firstCell.getUTCDate() + index);
                const key = dateKey(day);
                const classes = ['archive-calendar-day'];
                if (day.getUTCMonth() !== date.getUTCMonth()) {
                    classes.push('is-outside-month');
                }
                if (startKey && endKey && key > startKey && key < endKey) {
                    classes.push('is-in-range');
                }
                if (key === startKey) {
                    classes.push('is-range-start');
                }
                if (key === endKey && endKey !== startKey) {
                    classes.push('is-range-end');
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.className = classes.join(' ');
                button.textContent = String(day.getUTCDate());
                button.setAttribute('aria-label', formatAccessibleDate(day));
                button.setAttribute('aria-pressed', String(key === startKey || key === endKey));
                button.dataset.date = key;
                cells.push(button);
            }
            daysContainer.replaceChildren(...cells);
        };

        const render = () => {
            months.forEach((monthElement, index) => renderMonth(monthElement, index));
            updateSelection();
        };

        const setOpen = (isOpen, focusFirstDay = false) => {
            popover.hidden = !isOpen;
            trigger.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                selectedStart = parseDate(fromInput.value);
                selectedEnd = parseDate(toInput.value);
                visibleMonth = monthStart(selectedStart || selectedEnd || new Date());
                render();
                if (focusFirstDay) {
                    months[0].querySelector('[data-calendar-days] button')?.focus();
                }
            }
        };

        trigger.addEventListener('click', () => {
            if (popover.hidden) {
                setOpen(true, true);
            } else {
                restoreAppliedRange();
                setOpen(false);
            }
        });
        [manualFromInput, manualToInput].forEach((input) => {
            input.addEventListener('change', syncManualDates);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    syncManualDates();
                }
            });
        });
        clearButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            fromInput.value = '';
            toInput.value = '';
            appliedStart = null;
            appliedEnd = null;
            selectedStart = null;
            selectedEnd = null;
            label.textContent = formatRangeLabel(null, null);
            clearButton.hidden = true;
            dateRangeControl.classList.remove('has-clear');
            setOpen(false);
            render();
            form.requestSubmit();
        });
        popover.addEventListener('click', (event) => {
            const dayButton = event.target.closest('[data-date]');
            if (dayButton) {
                event.preventDefault();
                event.stopPropagation();
                const picked = parseDate(dayButton.dataset.date);
                if (!picked) {
                    return;
                }
                manualDateError = '';
                if (!selectedStart && selectedEnd) {
                    if (dateKey(picked) <= dateKey(selectedEnd)) {
                        selectedStart = picked;
                    } else {
                        selectedStart = selectedEnd;
                        selectedEnd = picked;
                    }
                } else if (!selectedStart || selectedEnd) {
                    selectedStart = picked;
                    selectedEnd = null;
                } else if (dateKey(picked) < dateKey(selectedStart)) {
                    selectedEnd = selectedStart;
                    selectedStart = picked;
                } else {
                    selectedEnd = picked;
                }
                updateSelection();
                return;
            }

            if (event.target.closest('[data-calendar-previous]')) {
                visibleMonth = monthStart(visibleMonth, -1);
                render();
            } else if (event.target.closest('[data-calendar-next]')) {
                visibleMonth = monthStart(visibleMonth, 1);
                render();
            } else if (event.target.closest('[data-date-range-cancel]')) {
                restoreAppliedRange();
                setOpen(false);
                trigger.focus();
            } else if (event.target.closest('[data-date-range-apply]') && selectedStart && selectedEnd) {
                appliedStart = selectedStart;
                appliedEnd = selectedEnd || selectedStart;
                fromInput.value = dateKey(appliedStart);
                toInput.value = dateKey(appliedEnd);
                label.textContent = formatRangeLabel(appliedStart, appliedEnd);
                clearButton.hidden = false;
                dateRangeControl.classList.add('has-clear');
                setOpen(false);
                form.requestSubmit();
            }
        });

        document.addEventListener('click', (event) => {
            if (!picker.contains(event.target) && !popover.hidden) {
                restoreAppliedRange();
                setOpen(false);
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !popover.hidden) {
                restoreAppliedRange();
                setOpen(false);
                trigger.focus();
            }
        });
        render();
    }

    function setupArchiveCustomFilters() {
        const wrappers = Array.from(document.querySelectorAll('[data-archive-custom-select]'));
        if (!wrappers.length) {
            return;
        }

        const optionsFor = (wrapper) => Array.from(wrapper.querySelectorAll('[data-archive-select-option]'));
        const closeMenu = (wrapper, returnFocus = false) => {
            const trigger = wrapper.querySelector('[data-archive-select-trigger]');
            const menu = wrapper.querySelector('[data-archive-select-menu]');
            if (!trigger || !menu) {
                return;
            }
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            wrapper.classList.remove('is-open');
            if (returnFocus) {
                trigger.focus();
            }
        };
        const openMenu = (wrapper, focusSelected = false) => {
            wrappers.forEach((other) => {
                if (other !== wrapper) {
                    closeMenu(other);
                }
            });
            const trigger = wrapper.querySelector('[data-archive-select-trigger]');
            const menu = wrapper.querySelector('[data-archive-select-menu]');
            if (!trigger || !menu) {
                return;
            }
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            wrapper.classList.add('is-open');
            if (focusSelected) {
                const selected = optionsFor(wrapper).find((option) => option.getAttribute('aria-selected') === 'true');
                (selected || optionsFor(wrapper)[0])?.focus();
            }
        };

        wrappers.forEach((wrapper) => {
            const select = wrapper.querySelector('[data-archive-native-select]');
            const trigger = wrapper.querySelector('[data-archive-select-trigger]');
            const valueLabel = wrapper.querySelector('[data-archive-select-value]');
            const menu = wrapper.querySelector('[data-archive-select-menu]');
            const options = optionsFor(wrapper);
            if (!select || !trigger || !valueLabel || !menu || !options.length) {
                return;
            }

            const syncSelection = () => {
                const selectedOption = options.find((option) => option.dataset.value === select.value) || options[0];
                valueLabel.textContent = selectedOption.textContent.trim();
                options.forEach((option) => {
                    const isSelected = option === selectedOption;
                    option.setAttribute('aria-selected', String(isSelected));
                    option.classList.toggle('is-selected', isSelected);
                });
            };
            select.addEventListener('archive:select-sync', syncSelection);

            trigger.addEventListener('click', () => {
                if (menu.hidden) {
                    openMenu(wrapper);
                } else {
                    closeMenu(wrapper);
                }
            });
            trigger.addEventListener('keydown', (event) => {
                if (event.key === 'Tab' && !menu.hidden) {
                    closeMenu(wrapper);
                    return;
                }
                if (event.key === 'Escape' && !menu.hidden) {
                    event.preventDefault();
                    closeMenu(wrapper, true);
                    return;
                }
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                    event.preventDefault();
                    openMenu(wrapper, true);
                }
            });
            menu.addEventListener('click', (event) => {
                const option = event.target.closest('[data-archive-select-option]');
                if (!option || !menu.contains(option)) {
                    return;
                }
                const changed = select.value !== option.dataset.value;
                select.value = option.dataset.value;
                syncSelection();
                closeMenu(wrapper, true);
                if (changed) {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            menu.addEventListener('keydown', (event) => {
                const option = event.target.closest('[data-archive-select-option]');
                if (!option) {
                    return;
                }
                if (event.key === 'Tab') {
                    closeMenu(wrapper);
                    return;
                }
                const currentIndex = options.indexOf(option);
                let nextIndex = currentIndex;
                if (event.key === 'ArrowDown') {
                    nextIndex = Math.min(options.length - 1, currentIndex + 1);
                } else if (event.key === 'ArrowUp') {
                    nextIndex = Math.max(0, currentIndex - 1);
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = options.length - 1;
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMenu(wrapper, true);
                    return;
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    option.click();
                    return;
                } else {
                    return;
                }
                event.preventDefault();
                options[nextIndex]?.focus();
            });

            syncSelection();
        });

        document.addEventListener('click', (event) => {
            wrappers.forEach((wrapper) => {
                if (!wrapper.contains(event.target)) {
                    closeMenu(wrapper);
                }
            });
        });
    }

    function setupArchiveFilters() {
        const form = document.querySelector('[data-archive-filter-form]');
        if (!form) {
            return;
        }

        let searchTimer;
        const submitFilters = () => form.requestSubmit();

        setupArchiveCustomFilters();
        setupArchiveDateRange();

        form.querySelectorAll('[data-archive-filter-control]').forEach((control) => {
            control.addEventListener('change', submitFilters);
        });

        const search = form.querySelector('[data-archive-process-search]');
        const searchControl = form.querySelector('[data-archive-search-control]');
        const suggestions = form.querySelector('[data-archive-search-suggestions]');
        if (search && searchControl && suggestions) {
            let suggestionController = null;
            let activeSuggestion = -1;
            let hideTimer;

            const closeSuggestions = () => {
                window.clearTimeout(hideTimer);
                search.setAttribute('aria-expanded', 'false');
                search.removeAttribute('aria-activedescendant');
                suggestions.classList.remove('is-open');
                hideTimer = window.setTimeout(() => {
                    suggestions.hidden = true;
                }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 580);
                activeSuggestion = -1;
            };

            const openSuggestions = () => {
                window.clearTimeout(hideTimer);
                suggestions.hidden = false;
                search.setAttribute('aria-expanded', 'true');
                window.requestAnimationFrame(() => suggestions.classList.add('is-open'));
            };

            const setActiveSuggestion = (index) => {
                const options = Array.from(suggestions.querySelectorAll('[role="option"]'));
                if (!options.length) {
                    activeSuggestion = -1;
                    search.removeAttribute('aria-activedescendant');
                    return;
                }
                activeSuggestion = (index + options.length) % options.length;
                options.forEach((option, optionIndex) => {
                    const active = optionIndex === activeSuggestion;
                    option.classList.toggle('is-active', active);
                    option.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                search.setAttribute('aria-activedescendant', options[activeSuggestion].id);
                options[activeSuggestion].scrollIntoView({ block: 'nearest' });
            };

            const selectSuggestion = (value) => {
                window.clearTimeout(searchTimer);
                search.value = value;
                closeSuggestions();
                submitFilters();
            };

            const renderSuggestions = (values, query) => {
                suggestions.replaceChildren();
                activeSuggestion = -1;
                const normalizedQuery = query.toLocaleLowerCase();
                values.slice(0, 4).forEach((value, index) => {
                    const option = document.createElement('div');
                    const match = document.createElement('strong');
                    const remainder = document.createElement('span');
                    const matchLength = query.length;
                    option.id = `archive-search-suggestion-${index}`;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.dataset.suggestionValue = value;
                    match.className = 'archive-suggestion-match';
                    remainder.className = 'archive-suggestion-remainder';
                    match.textContent = value.slice(0, matchLength);
                    remainder.textContent = value.slice(matchLength);
                    if (!value.toLocaleLowerCase().startsWith(normalizedQuery)) return;
                    option.append(match, remainder);
                    suggestions.append(option);
                });

                if (!suggestions.children.length) {
                    closeSuggestions();
                    return;
                }
                openSuggestions();
            };

            const fetchSuggestions = async () => {
                const query = search.value.trim();
                if (!query) {
                    if (suggestionController) suggestionController.abort();
                    suggestions.replaceChildren();
                    closeSuggestions();
                    return;
                }

                if (suggestionController) suggestionController.abort();
                suggestionController = new AbortController();
                const parameters = new URLSearchParams(new FormData(form));
                parameters.delete('process_number');
                parameters.delete('page');
                parameters.set('q', query);
                try {
                    const suggestionsUrl = form.dataset.suggestionsUrl || '/BTS/archive/suggestions';
                    const response = await fetch(`${suggestionsUrl}?${parameters}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: suggestionController.signal,
                    });
                    if (!response.ok) throw new Error('Suggestions could not be loaded.');
                    const data = await response.json();
                    if (search.value.trim() !== query) return;
                    renderSuggestions(Array.isArray(data.suggestions) ? data.suggestions : [], query);
                    window.clearTimeout(searchTimer);
                    if (!suggestions.children.length) {
                        searchTimer = window.setTimeout(submitFilters, 450);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        closeSuggestions();
                        searchTimer = window.setTimeout(submitFilters, 450);
                    }
                }
            };

            search.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                fetchSuggestions();
            });

            search.addEventListener('keydown', (event) => {
                const options = suggestions.querySelectorAll('[role="option"]');
                const isExpanded = search.getAttribute('aria-expanded') === 'true';
                if (event.key === 'ArrowDown' && isExpanded && options.length) {
                    event.preventDefault();
                    setActiveSuggestion(activeSuggestion + 1);
                } else if (event.key === 'ArrowUp' && isExpanded && options.length) {
                    event.preventDefault();
                    setActiveSuggestion(activeSuggestion < 0 ? options.length - 1 : activeSuggestion - 1);
                } else if (event.key === 'Enter' && activeSuggestion >= 0 && options[activeSuggestion]) {
                    event.preventDefault();
                    selectSuggestion(options[activeSuggestion].dataset.suggestionValue || '');
                } else if (event.key === 'Escape') {
                    closeSuggestions();
                }
            });

            suggestions.addEventListener('pointerdown', (event) => {
                const option = event.target.closest('[role="option"]');
                if (!option) return;
                event.preventDefault();
                selectSuggestion(option.dataset.suggestionValue || '');
            });
            document.addEventListener('pointerdown', (event) => {
                if (!searchControl.contains(event.target)) closeSuggestions();
            });
        }
    }

    function setupArchivePagination() {
        const results = document.querySelector('[data-archive-results]');
        if (!results) {
            return;
        }

        let requestInProgress = false;
        const loadPage = async (url, addHistoryEntry = true) => {
            if (requestInProgress) {
                return;
            }

            requestInProgress = true;
            results.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Archive page request failed.');
                }

                const documentText = await response.text();
                const nextDocument = new DOMParser().parseFromString(documentText, 'text/html');
                const nextResults = nextDocument.querySelector('[data-archive-results]');
                if (!nextResults) {
                    throw new Error('Archive page content was not found.');
                }

                results.innerHTML = nextResults.innerHTML;
                results.classList.remove('is-page-changing');
                void results.offsetWidth;
                results.classList.add('is-page-changing');
                window.setTimeout(() => results.classList.remove('is-page-changing'), 220);
                results.setAttribute('aria-busy', 'false');
                if (addHistoryEntry) {
                    window.history.pushState({ archivePage: true }, '', url);
                }
                const pageSizeControl = document.querySelector('[data-archive-page-size]');
                if (pageSizeControl) {
                    pageSizeControl.value = new URL(url, window.location.href).searchParams.get('per_page') || '10';
                    pageSizeControl.dispatchEvent(new Event('archive:select-sync'));
                }
                if (nextDocument.title) {
                    document.title = nextDocument.title;
                }

                const page = document.querySelector('.content-shell > .page');
                if (page) {
                    page.scrollTo({ top: Math.max(0, results.offsetTop - 20), behavior: 'smooth' });
                }
            } catch (error) {
                window.location.assign(url);
            } finally {
                requestInProgress = false;
                results.setAttribute('aria-busy', 'false');
            }
        };

        document.addEventListener('click', (event) => {
            const link = event.target.closest('[data-archive-results] .archive-pagination a[href]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
                return;
            }

            event.preventDefault();
            loadPage(url.href);
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-archive-page-jump]');
            if (!form) {
                return;
            }

            event.preventDefault();
            if (!form.reportValidity()) {
                return;
            }

            const url = new URL(form.action, window.location.href);
            new FormData(form).forEach((value, key) => url.searchParams.set(key, String(value)));
            loadPage(url.href);
        });

        document.addEventListener('change', (event) => {
            const pageSize = event.target.closest('[data-archive-page-size]');
            if (!pageSize) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('per_page', pageSize.value);
            url.searchParams.set('page', '1');
            loadPage(url.href);
        });

        window.addEventListener('popstate', () => {
            loadPage(window.location.href, false);
        });
    }

    function setupHoverNotes() {
        const note = document.createElement('div');
        note.className = 'hover-note';
        note.setAttribute('role', 'tooltip');
        document.body.appendChild(note);

        document.addEventListener('pointerover', (event) => {
            const anchor = event.target.closest('[data-hover-note-title], [data-hover-note-body]');
            if (!anchor) {
                return;
            }

            const { title, body } = hoverNoteContent(anchor);
            note.textContent = '';
            if (title) {
                const strong = document.createElement('strong');
                strong.textContent = title;
                note.appendChild(strong);
            }
            if (body) {
                const span = document.createElement('span');
                span.textContent = body;
                note.appendChild(span);
            }
            note.classList.add('is-visible');
            positionHoverNote(note, event);
        });

        document.addEventListener('pointermove', (event) => {
            if (!note.classList.contains('is-visible')) {
                return;
            }
            positionHoverNote(note, event);
        });

        document.addEventListener('pointerout', (event) => {
            const anchor = event.target.closest('[data-hover-note-title], [data-hover-note-body]');
            if (!anchor || anchor.contains(event.relatedTarget)) {
                return;
            }
            note.classList.remove('is-visible');
        });
    }

    onReady(() => {
        setupPageLoadingSkeleton();
        setupLoginForm();
        setupLoginBrandingAnimation();
        setupHoverNotes();
        setupStartModal();
        setupSystemInputSaveState();
        setupSettingsSectionNavigation();
        setupConfirmationModal();
        setupAuthInfoDialog();
        setupArchiveFilters();
        setupArchivePagination();
        document.addEventListener('click', (event) => {
            const addTaxTypeButton = event.target.closest('[data-add-tax-type]');
            if (addTaxTypeButton) {
                addTaxType(addTaxTypeButton);
                return;
            }

            const removeTaxTypeButton = event.target.closest('[data-remove-tax-type]');
            if (removeTaxTypeButton) {
                const section = removeTaxTypeButton.closest('[data-tax-management]');
                removeTaxTypeButton.closest('[data-tax-type-row]')?.remove();
                reindexTaxTypes(section);
                return;
            }

            const addButton = event.target.closest('[data-add-pr-row]');
            if (addButton) {
                addPurchaseRow(addButton);
                return;
            }

            const addAbstractButton = event.target.closest('[data-add-abstract-row]');
            if (addAbstractButton) {
                addAbstractRow(addAbstractButton);
                return;
            }

            const removeAbstractButton = event.target.closest('[data-remove-abstract-row]');
            if (removeAbstractButton) {
                const form = removeAbstractButton.closest('[data-abstract-form]');
                const rows = form ? form.querySelectorAll('[data-abstract-row]') : [];
                if (rows.length > 1) {
                    removeAbstractButton.closest('[data-abstract-row]').remove();
                    reindexAbstractRows(form);
                    syncAbstractSelection(form, false);
                }
                return;
            }

            const removeButton = event.target.closest('[data-remove-pr-row]');
            if (removeButton) {
                const form = removeButton.closest('[data-pr-form]');
                const rows = form ? form.querySelectorAll('[data-pr-row]') : [];
                if (rows.length > 1) {
                    removeButton.closest('[data-pr-row]').remove();
                    reindexPurchaseRows(form);
                }
            }
        });

        document.addEventListener('input', (event) => {
            const poUnitCostInput = event.target.closest('[data-po-unit-cost]');
            if (poUnitCostInput) {
                syncPurchaseOrderAmount(poUnitCostInput.closest('[data-po-row]'));
                return;
            }

            const dvInput = event.target.closest('[data-dv-gross], [data-dv-tax-input]');
            if (dvInput) {
                syncDisbursementVoucherAmounts(dvInput.closest('[data-dv-form]'));
                return;
            }

            const abstractInput = event.target.closest('[data-abstract-supplier-name], [data-abstract-bid-amount]');
            if (!abstractInput) {
                return;
            }

            const form = abstractInput.closest('[data-abstract-form]');
            const select = form ? form.querySelector('[data-abstract-selected-supplier]') : null;
            syncAbstractSelection(form, !select || select.dataset.manual !== '1');
        });

        document.addEventListener('change', (event) => {
            const poPaymentMode = event.target.closest('[data-po-payment-mode]');
            if (poPaymentMode) {
                syncPurchaseOrderPaymentModes(poPaymentMode);
                return;
            }

            const dvTaxType = event.target.closest('[data-dv-tax-type]');
            if (dvTaxType) {
                syncDisbursementVoucherAmounts(dvTaxType.closest('[data-dv-form]'));
                return;
            }

            const select = event.target.closest('[data-abstract-selected-supplier]');
            if (!select) {
                return;
            }

            select.dataset.manual = select.value === '' ? '0' : '1';
            syncSelectedSupplierAmount(select);
            syncAbstractSelection(select.closest('[data-abstract-form]'), false);
        });

        document.querySelectorAll('[data-pr-form]').forEach(reindexPurchaseRows);
        setupPurchaseOrderAmounts();
        syncPurchaseOrderPaymentModes();
        setupDisbursementVoucherAmounts();
        document.querySelectorAll('[data-tax-management]').forEach(reindexTaxTypes);

        document.querySelectorAll('[data-abstract-selected-supplier]').forEach(syncSelectedSupplierAmount);

        document.querySelectorAll('[data-abstract-form]').forEach((form) => {
            const select = form.querySelector('[data-abstract-selected-supplier]');
            if (select) {
                const lowestBid = lowestAbstractBid(abstractBidData(form));
                select.dataset.manual = select.value !== '' && lowestBid && select.value !== lowestBid.name ? '1' : '0';
            }
            reindexAbstractRows(form);
            syncAbstractSelection(form, false);
        });


        const sidebar = document.querySelector('.sidebar');
        const sidebarNav = sidebar ? sidebar.querySelector('.side-nav') : null;
        if (sidebarNav) {
            const tooltip = document.createElement('div');
            tooltip.className = 'sidebar-nav-tooltip';
            tooltip.id = 'sidebar-nav-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            document.body.append(tooltip);

            const hideNavTooltip = () => {
                tooltip.classList.remove('is-visible');
                sidebarNav.querySelectorAll('[aria-describedby="sidebar-nav-tooltip"]').forEach((link) => {
                    link.removeAttribute('aria-describedby');
                });
            };
            const showNavTooltip = (link) => {
                if (window.innerWidth < 981 || !document.documentElement.classList.contains('sidebar-is-collapsed')) {
                    hideNavTooltip();
                    return;
                }

                const label = link.getAttribute('aria-label') || link.querySelector('.nav-label')?.textContent.trim();
                if (!label) return;

                tooltip.textContent = label;
                link.setAttribute('aria-describedby', tooltip.id);
                const rect = link.getBoundingClientRect();
                const tooltipWidth = tooltip.getBoundingClientRect().width;
                const left = rect.right + 12 + tooltipWidth <= window.innerWidth
                    ? rect.right + 12
                    : Math.max(8, rect.left - tooltipWidth - 12);
                tooltip.style.left = `${Math.round(left)}px`;
                tooltip.style.top = `${Math.round(rect.top + rect.height / 2)}px`;
                tooltip.classList.add('is-visible');
            };

            sidebarNav.querySelectorAll('a').forEach((link) => {
                link.addEventListener('pointerenter', (event) => {
                    if (event.pointerType !== 'touch') showNavTooltip(link);
                });
                link.addEventListener('pointerleave', hideNavTooltip);
                link.addEventListener('focus', () => showNavTooltip(link));
                link.addEventListener('blur', hideNavTooltip);
            });
            sidebar.addEventListener('scroll', hideNavTooltip, { passive: true });
            window.addEventListener('resize', hideNavTooltip, { passive: true });
        }

        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
        if (sidebarToggle) {
            const syncSidebarToggle = () => {
                const collapsed = document.documentElement.classList.contains('sidebar-is-collapsed');
                sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
                sidebarToggle.title = collapsed ? 'Expand sidebar' : 'Minimize sidebar';
            };
            sidebarToggle.addEventListener('click', () => {
                const collapsed = document.documentElement.classList.toggle('sidebar-is-collapsed');
                try {
                    localStorage.setItem('bts.sidebar.collapsed', collapsed ? '1' : '0');
                } catch (error) {
                    // The control remains usable when storage is unavailable.
                }
                syncSidebarToggle();
            });
            syncSidebarToggle();
        }

        const uiForm = document.querySelector('[data-ui-settings-form]');
        if (uiForm) {
            const logoInput = uiForm.querySelector('[data-logo-upload]');
            const logoPreview = uiForm.querySelector('[data-logo-preview]');
            const logoPreviewStatus = uiForm.querySelector('[data-logo-preview-status]');
            const clearLogoSelection = uiForm.querySelector('[data-logo-clear-selection]');
            if (logoInput && logoPreview && logoPreviewStatus && clearLogoSelection) {
                const originalLogoPreview = logoPreview.innerHTML;
                let logoPreviewUrl = '';
                const revokeLogoPreviewUrl = () => {
                    if (logoPreviewUrl) {
                        URL.revokeObjectURL(logoPreviewUrl);
                        logoPreviewUrl = '';
                    }
                };
                const restoreLogoPreview = () => {
                    revokeLogoPreviewUrl();
                    logoPreview.innerHTML = originalLogoPreview;
                    logoPreview.classList.remove('is-previewing');
                    clearLogoSelection.hidden = true;
                };

                logoInput.addEventListener('change', () => {
                    const file = logoInput.files?.[0];
                    if (!file) {
                        restoreLogoPreview();
                        logoPreviewStatus.textContent = 'Choose a PNG, JPG, or JPEG to preview it before saving.';
                        return;
                    }

                    clearLogoSelection.hidden = false;
                    const extension = file.name.split('.').pop()?.toLowerCase();
                    const acceptedTypes = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' };
                    if (!acceptedTypes[extension] || (file.type && file.type !== acceptedTypes[extension])) {
                        restoreLogoPreview();
                        clearLogoSelection.hidden = false;
                        logoPreviewStatus.textContent = 'Select a PNG, JPG, or JPEG image to see its preview.';
                        return;
                    }
                    if (file.size > 10 * 1024 * 1024) {
                        restoreLogoPreview();
                        clearLogoSelection.hidden = false;
                        logoPreviewStatus.textContent = 'This file is larger than 10 MB. Choose a smaller image.';
                        return;
                    }

                    revokeLogoPreviewUrl();
                    logoPreviewUrl = URL.createObjectURL(file);
                    const image = document.createElement('img');
                    image.alt = 'Selected system logo preview';
                    image.addEventListener('load', () => {
                        logoPreviewStatus.textContent = `${file.name} preview. Save System UI to apply it.`;
                    }, { once: true });
                    image.addEventListener('error', () => {
                        restoreLogoPreview();
                        clearLogoSelection.hidden = false;
                        logoPreviewStatus.textContent = 'This image could not be previewed. Choose a valid PNG, JPG, or JPEG.';
                    }, { once: true });
                    image.src = logoPreviewUrl;
                    logoPreview.replaceChildren(image);
                    logoPreview.classList.add('is-previewing');
                });

                clearLogoSelection.addEventListener('click', () => {
                    logoInput.value = '';
                    logoInput.dispatchEvent(new Event('change', { bubbles: true }));
                    restoreLogoPreview();
                    logoPreviewStatus.textContent = 'Selection cleared. The saved logo is still in use.';
                });

                window.addEventListener('pagehide', revokeLogoPreviewUrl, { once: true });
            }

            const preview = uiForm.querySelector('[data-theme-preview]');
            const primary = uiForm.querySelector('[data-ui-primary]');
            const accent = uiForm.querySelector('[data-ui-accent]');
            const colorChannels = (color) => {
                const value = String(color || '').trim();
                const hex = value.match(/^#([0-9a-f]{6})$/i);
                if (hex) return [0, 2, 4].map((offset) => parseInt(hex[1].slice(offset, offset + 2), 16));
                const rgb = value.match(/^rgba?\(\s*(\d+)\D+(\d+)\D+(\d+)/i);
                return rgb ? rgb.slice(1, 4).map(Number) : [81, 81, 61];
            };
            const shadeColor = (color, amount) => colorChannels(color).map((channel) => Math.max(0, Math.min(255, channel + amount)));
            const contrastColor = (channels) => ((0.299 * channels[0]) + (0.587 * channels[1]) + (0.114 * channels[2])) >= 150 ? '#1B2021' : '#FFFFFF';
            const rgbColor = (channels) => `rgb(${channels.join(', ')})`;
            const rgbaColor = (channels, alpha) => `rgba(${channels.join(', ')}, ${alpha})`;            const syncPreview = () => {
                const selected = uiForm.querySelector('[data-theme-preset]:checked');
                const option = selected ? selected.closest('.palette-option') : null;
                const swatches = option ? option.querySelectorAll('.palette-swatches i') : [];
                const custom = selected && selected.value === 'custom';
                const primaryColor = custom && primary ? primary.value : (swatches[0] ? getComputedStyle(swatches[0]).backgroundColor : primary.value);
                const accentColor = custom && accent ? accent.value : (swatches[1] ? getComputedStyle(swatches[1]).backgroundColor : accent.value);
                const customPanel = uiForm.querySelector('[data-custom-colors]');
                const customStatus = uiForm.querySelector('[data-custom-status]');
                const primaryHex = uiForm.querySelector('[data-primary-hex]');
                const accentHex = uiForm.querySelector('[data-accent-hex]');
                if (customPanel) customPanel.classList.toggle('is-inactive', !custom);
                if (customStatus) customStatus.textContent = custom ? 'Active' : 'Select Custom to use';
                if (primaryHex && primary) primaryHex.value = primary.value.toUpperCase();
                if (accentHex && accent) accentHex.value = accent.value.toUpperCase();
                if (preview) {
                    const sidebarChannels = shadeColor(primaryColor, -24);
                    const sidebarForeground = contrastColor(sidebarChannels);
                    const sidebarForegroundChannels = colorChannels(sidebarForeground);
                    preview.style.setProperty('--preview-primary', primaryColor);
                    preview.style.setProperty('--preview-accent', accentColor);
                    preview.style.setProperty('--preview-sidebar', rgbColor(sidebarChannels));
                    preview.style.setProperty('--preview-sidebar-foreground', sidebarForeground);
                    preview.style.setProperty('--preview-sidebar-muted', rgbaColor(sidebarForegroundChannels, 0.66));
                    preview.style.setProperty('--preview-accent-foreground', contrastColor(colorChannels(accentColor)));
                }
            };
            uiForm.addEventListener('input', syncPreview);
            syncPreview();

            uiForm.querySelectorAll('[data-report-color]').forEach((picker) => {
                const output = picker.closest('.report-color-control')?.querySelector('[data-report-color-hex]');
                const syncReportColor = () => {
                    if (output) output.value = picker.value.toUpperCase();
                };
                picker.addEventListener('input', syncReportColor);
                syncReportColor();
            });
        }

        document.querySelectorAll('[data-toast]').forEach((toast) => {
            window.setTimeout(() => {
                toast.classList.add('is-fading');
            }, 2600);
            window.setTimeout(() => {
                toast.remove();
            }, 3400);
        });

        // Live dashboard clock in the configured Asia/Manila timezone.
        const clockWidget = document.querySelector('[data-live-clock]');
        if (clockWidget) {
            const timeElement = clockWidget.querySelector('[data-clock-time]');
            const dateElement = clockWidget.querySelector('[data-clock-date]');
            const timeFormatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            const dateFormatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });

            const updateClock = () => {
                const now = new Date();
                if (timeElement) {
                    const formattedTime = timeFormatter.format(now);
                    timeElement.textContent = formattedTime;
                }
                if (dateElement) {
                    const dateParts = Object.fromEntries(dateFormatter.formatToParts(now).map((part) => [part.type, part.value]));
                    dateElement.textContent = `${dateParts.weekday}, ${dateParts.month} ${dateParts.day}, ${dateParts.year}`;
                }
            };

            updateClock();
            setInterval(updateClock, 1000);
        }
    });
})();
