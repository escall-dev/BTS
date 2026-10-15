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

        const closeModal = () => {
            modal.hidden = true;
        };

        document.addEventListener('click', (event) => {
            const startButton = event.target.closest('[data-start-workflow]');
            if (startButton) {
                form.action = startButton.dataset.startAction || form.action;
                modal.hidden = false;
                const proceedButton = modal.querySelector('button[type="submit"]');
                if (proceedButton) {
                    proceedButton.focus();
                }
                return;
            }

            if (event.target.closest('[data-start-modal-close]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    function setupDeleteConfirmation() {
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-confirm-delete]');
            if (!form) {
                return;
            }

            if (!window.confirm(form.dataset.confirmDelete || 'Delete this saved report process?')) {
                event.preventDefault();
            }
        });
    }
    function setupArchiveFilters() {
        const form = document.querySelector('[data-archive-filter-form]');
        if (!form) {
            return;
        }

        let searchTimer;
        const submitFilters = () => form.requestSubmit();

        form.querySelectorAll('[data-archive-filter-control]').forEach((control) => {
            control.addEventListener('change', submitFilters);
        });

        const search = form.querySelector('[data-archive-process-search]');
        if (search) {
            search.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(submitFilters, 450);
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
        setupHoverNotes();
        setupStartModal();
        setupDeleteConfirmation();
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
    });
})();
