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
            let isDirty = false;

            const markDirty = () => {
                if (isDirty) {
                    return;
                }
                isDirty = true;
                form.classList.add('is-dirty');
                if (status) {
                    status.textContent = 'Unsaved changes';
                    status.classList.add('is-dirty');
                }
            };

            form.addEventListener('input', markDirty);
            form.addEventListener('change', markDirty);
            form.addEventListener('click', (event) => {
                if (event.target.closest('[data-add-tax-type], [data-remove-tax-type]')) {
                    markDirty();
                }
            });
            form.addEventListener('submit', () => {
                isDirty = false;
                form.classList.remove('is-dirty');
                if (status) {
                    status.textContent = 'Saving changes…';
                    status.classList.remove('is-dirty');
                }
            });

            window.addEventListener('beforeunload', (event) => {
                if (!isDirty) {
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
        const months = Array.from(picker.querySelectorAll('[data-calendar-month]'));
        if (!fromInput || !toInput || !trigger || !clearButton || !dateRangeControl || !label || !popover || months.length !== 2) {
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

        const restoreAppliedRange = () => {
            appliedStart = parseDate(fromInput.value);
            appliedEnd = parseDate(toInput.value);
            selectedStart = appliedStart;
            selectedEnd = appliedEnd;
            visibleMonth = monthStart(appliedStart || appliedEnd || new Date());
            label.textContent = formatRangeLabel(appliedStart, appliedEnd);
            clearButton.hidden = !appliedStart && !appliedEnd;
            dateRangeControl.classList.toggle('has-clear', Boolean(appliedStart || appliedEnd));
            render();
        };

        const updatePreview = () => {
            if (!selectedStart) {
                preview.textContent = 'Select start and end dates';
                applyButton.disabled = true;
                return;
            }
            preview.textContent = selectedEnd
                ? `${formatDate(selectedStart)} → ${formatDate(selectedEnd)}`
                : `${formatDate(selectedStart)} → Choose end date`;
            applyButton.disabled = !selectedEnd;
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
            updatePreview();
        };

        const renderMonth = (monthElement, monthOffset) => {
            const date = monthStart(visibleMonth, monthOffset);
            monthElement.querySelector('[data-calendar-title]').textContent = formatMonth(date);
            const daysContainer = monthElement.querySelector('[data-calendar-days]');
            const firstDay = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
            const mondayOffset = (firstDay.getUTCDay() + 6) % 7;
            const firstCell = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1 - mondayOffset));
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
                if (!selectedStart || selectedEnd) {
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
                    const response = await fetch(`/BTS/archive/suggestions?${parameters}`, {
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
        setupLoginBrandingAnimation();
        setupHoverNotes();
        setupStartModal();
        setupSystemInputSaveState();
        setupSettingsSectionNavigation();
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
    });
})();
