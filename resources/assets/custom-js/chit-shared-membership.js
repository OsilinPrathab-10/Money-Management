/**
 * Shared Membership UI — mutual exclusive Independent vs Sharing Option,
 * toggle, dynamic co-owner rows, % total = 100, share amount calc.
 */
(function () {
    function formatMoney(n) {
        var v = Number(n) || 0;
        return '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getChitValue(block) {
        var fromData = parseFloat(block.getAttribute('data-chit-value') || '0');
        if (fromData > 0) return fromData;

        var form = block.closest('form');
        if (!form) return 0;
        var groupSelect = form.querySelector('select[name="group_id"]');
        if (!groupSelect) return 0;
        var opt = groupSelect.options[groupSelect.selectedIndex];
        return parseFloat((opt && opt.getAttribute('data-chit-value')) || '0') || 0;
    }

    function reindexRows(block) {
        var rows = block.querySelectorAll('.share-rows .share-row');
        rows.forEach(function (row, i) {
            var client = row.querySelector('.share-client');
            var pct = row.querySelector('.share-pct');
            if (client) client.name = 'shares[' + i + '][client_id]';
            if (pct) pct.name = 'shares[' + i + '][ownership_percentage]';
            var label = row.querySelector('.form-label');
            if (label && label.textContent.indexOf('Customer') === 0) {
                label.textContent = i === 0 ? 'Customer (Primary)' : 'Customer';
            }
            var removeBtn = row.querySelector('.remove-share-row');
            if (removeBtn) removeBtn.disabled = rows.length <= 2;
        });
    }

    function getSeatInstallment(block) {
        var form = block.closest('form');
        var indepPct = 100;
        if (form) {
            var sharePctInput = form.querySelector('.share-percentage-input:not([disabled])');
            if (sharePctInput) {
                indepPct = parseFloat(sharePctInput.value || '100') || 100;
            }
        }

        var preview = (form && form.querySelector('.share-percentage-preview-block'))
            || document.querySelector('.share-percentage-preview-block');
        var baseInst = preview
            ? (parseFloat(preview.getAttribute('data-base-installment') || '0') || 0)
            : 0;

        if (form) {
            var groupSelect = form.querySelector('select[name="group_id"]');
            if (groupSelect && groupSelect.selectedIndex >= 0) {
                var opt = groupSelect.options[groupSelect.selectedIndex];
                var dataInst = parseFloat((opt && opt.getAttribute('data-installment')) || '0') || 0;
                if (dataInst > 0) {
                    baseInst = dataInst;
                }
            }
        }

        indepPct = Math.max(0, indepPct);
        return baseInst > 0 ? (baseInst * indepPct / 100) : 0;
    }

    function refreshAmounts(block) {
        var seatInstallment = getSeatInstallment(block);
        var total = 0;
        block.querySelectorAll('.share-rows .share-row').forEach(function (row) {
            var pctInput = row.querySelector('.share-pct');
            var amtInput = row.querySelector('.share-amount');
            var pct = parseFloat(pctInput && pctInput.value ? pctInput.value : '0') || 0;
            total += pct;
            if (amtInput) {
                amtInput.value = seatInstallment > 0
                    ? formatMoney(seatInstallment * pct / 100)
                    : '—';
            }
        });
        total = Math.round(total * 100) / 100;
        var totalEl = block.querySelector('.share-total-pct');
        var statusEl = block.querySelector('.share-total-status');
        if (totalEl) totalEl.textContent = total.toFixed(2);
        if (statusEl) {
            if (Math.abs(total - 100) < 0.01) {
                statusEl.innerHTML = '<span class="text-success fw-semibold">Valid (100%)</span>';
            } else {
                statusEl.innerHTML = '<span class="text-danger fw-semibold">Must equal 100%</span>';
            }
        }
    }

    function setSharedMode(block, enabled) {
        var shared = block.querySelector('.shared-owners-fields');
        if (shared) shared.style.display = enabled ? '' : 'none';
        block.querySelectorAll('.share-client, .share-pct').forEach(function (el) {
            el.required = enabled;
            el.disabled = !enabled;
        });
        refreshAmounts(block);
    }

    /**
     * Independent Sharing ↔ Sharing Option are mutually exclusive.
     * Independent is default (100%). Edit can switch either way.
     */
    function applySharingMode(form, mode) {
        if (!form) return;
        var isShared = mode === 'shared';

        var indepSection = form.querySelector('.independent-sharing-section');
        var sharingSection = form.querySelector('.sharing-option-section');
        var clientSection = form.querySelector('.independent-client-section');
        var shareInput = form.querySelector('.share-percentage-input');
        var shareHidden = form.querySelector('.share-percentage-hidden');
        var toggle = form.querySelector('.shared-membership-toggle');
        var isSharedOff = form.querySelector('.is-shared-off');
        var primaryClient = form.querySelector('.independent-primary-client, .shared-primary-client');

        if (indepSection) indepSection.style.display = isShared ? 'none' : '';
        if (sharingSection) sharingSection.style.display = isShared ? '' : 'none';
        if (clientSection) clientSection.style.display = isShared ? 'none' : '';

        if (shareInput) {
            shareInput.disabled = isShared;
            shareInput.required = false;
            if (!isShared && (!shareInput.value || shareInput.value === '' || parseFloat(shareInput.value) <= 0)) {
                shareInput.value = '100';
            }
        }
        if (shareHidden) {
            shareHidden.disabled = !isShared;
            shareHidden.value = '100';
        }

        if (toggle) {
            toggle.checked = isShared;
            toggle.disabled = false;
        }
        if (isSharedOff) {
            isSharedOff.disabled = isShared;
        }

        if (primaryClient) {
            primaryClient.disabled = isShared;
            primaryClient.required = !isShared;
        }

        var block = form.querySelector('.shared-membership-block');
        if (block) {
            setSharedMode(block, isShared);
        }

        updateSharePctPreviews();
    }

    function initSharingMode(form) {
        if (!form || form._sharingModeInit) return;
        form._sharingModeInit = true;

        var radios = form.querySelectorAll('.sharing-mode-radio');
        if (!radios.length) return;

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!radio.checked) return;
                applySharingMode(form, radio.getAttribute('data-sharing-mode') || radio.value);
            });
        });

        var checked = form.querySelector('.sharing-mode-radio:checked');
        var mode = checked
            ? (checked.getAttribute('data-sharing-mode') || checked.value)
            : 'independent';
        applySharingMode(form, mode);
    }

    function initBlock(block) {
        if (block._sharedInit) return;
        block._sharedInit = true;

        var form = block.closest('form');
        if (form) {
            initSharingMode(form);
        }

        var toggle = block.querySelector('.shared-membership-toggle');

        block.addEventListener('click', function (e) {
            var addBtn = e.target.closest('.add-share-row');
            if (addBtn) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                if (addBtn._isAdding) return;
                addBtn._isAdding = true;
                setTimeout(function () { addBtn._isAdding = false; }, 300);

                var tpl = block.querySelector('.share-row-template');
                var container = block.querySelector('.share-rows');
                if (!tpl || !container) return;
                var index = container.querySelectorAll('.share-row').length;
                var html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
                container.insertAdjacentHTML('beforeend', html);
                reindexRows(block);
                refreshAmounts(block);
                return;
            }

            var removeBtn = e.target.closest('.remove-share-row');
            if (removeBtn && !removeBtn.disabled) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                if (removeBtn._isRemoving) return;
                removeBtn._isRemoving = true;
                setTimeout(function () { removeBtn._isRemoving = false; }, 300);

                var row = removeBtn.closest('.share-row');
                var rows = block.querySelectorAll('.share-rows .share-row');
                if (row && rows.length > 2) {
                    row.remove();
                    reindexRows(block);
                    refreshAmounts(block);
                }
            }
        });

        block.addEventListener('input', function (e) {
            if (e.target.classList.contains('share-pct')) {
                refreshAmounts(block);
            }
        });

        if (form) {
            var groupSelect = form.querySelector('select[name="group_id"]');
            if (groupSelect && !groupSelect._sharedGroupBound) {
                groupSelect._sharedGroupBound = true;
                groupSelect.addEventListener('change', function () {
                    var opt = groupSelect.options[groupSelect.selectedIndex];
                    var val = (opt && opt.getAttribute('data-chit-value')) || '0';
                    block.setAttribute('data-chit-value', val);
                    refreshAmounts(block);
                });
            }

            if (!form._sharedSubmitBound) {
                form._sharedSubmitBound = true;
                form.addEventListener('submit', function (e) {
                if (!toggle || !toggle.checked) return;
                var total = 0;
                var clients = [];
                var dup = false;
                block.querySelectorAll('.share-rows .share-row').forEach(function (row) {
                    var c = row.querySelector('.share-client');
                    var p = row.querySelector('.share-pct');
                    var cid = c && c.value ? c.value : '';
                    if (cid) {
                        if (clients.indexOf(cid) !== -1) dup = true;
                        clients.push(cid);
                    }
                    total += parseFloat(p && p.value ? p.value : '0') || 0;
                });
                total = Math.round(total * 100) / 100;
                if (clients.length < 2) {
                    e.preventDefault();
                    alert('Shared membership requires at least 2 customers.');
                    return;
                }
                if (dup) {
                    e.preventDefault();
                    alert('Duplicate customers are not allowed in the same membership.');
                    return;
                }
                if (Math.abs(total - 100) > 0.01) {
                    e.preventDefault();
                    alert('Ownership percentages must total exactly 100%. Current: ' + total + '%');
                }
            });
            }
        }

        refreshAmounts(block);
    }

    function updateSharePctPreviews() {
        document.querySelectorAll('.share-percentage-input').forEach(function (input) {
            if (input.disabled) return;
            var val = parseFloat(input.value || '100') || 100;
            var container = input.closest('.share-percentage-preview-block')
                || input.closest('.card-body, .modal-body, form, div');
            if (!container) return;

            var form = input.closest('form');
            var baseInst = parseFloat(container.getAttribute('data-base-installment') || '0') || 0;
            var basePayout = parseFloat(container.getAttribute('data-base-payout') || '0') || 0;

            if (form) {
                var groupSelect = form.querySelector('select[name="group_id"]');
                if (groupSelect && groupSelect.selectedIndex >= 0) {
                    var opt = groupSelect.options[groupSelect.selectedIndex];
                    var dataInst = parseFloat(opt.getAttribute('data-installment') || '0') || 0;
                    var dataPayout = parseFloat(opt.getAttribute('data-payout') || '0') || 0;
                    var chitVal = parseFloat(opt.getAttribute('data-chit-value') || '0') || 0;
                    var totalM = parseInt(opt.getAttribute('data-total-months') || '0', 10) || 0;

                    if (dataInst > 0) {
                        baseInst = dataInst;
                    } else if (chitVal > 0 && totalM > 0) {
                        baseInst = chitVal / totalM;
                    }

                    if (dataPayout > 0) {
                        basePayout = dataPayout;
                    } else if (chitVal > 0) {
                        basePayout = chitVal * 0.95;
                    }
                }
            }

            var prefix = input.id.replace('sharePercentageInput', '');
            var instEl = document.getElementById('previewInstallment' + prefix);
            var payoutEl = document.getElementById('previewPayout' + prefix);
            var splitEl = document.getElementById('previewSplitAmount' + prefix);
            var splitLabelEl = document.getElementById('previewSplitLabel' + prefix);
            var freqSelect = document.getElementById('collectionFrequency' + prefix)
                || (form && form.querySelector('.collection-frequency-select'));
            var freq = (freqSelect && freqSelect.value) ? freqSelect.value : 'monthly';
            var monthlyShare = baseInst * val / 100;
            var parts = freq === 'daily' ? 30 : (freq === 'weekly' ? 4 : 1);
            var splitAmount = parts > 1 ? (monthlyShare / parts) : monthlyShare;

            if (instEl && baseInst > 0) {
                instEl.textContent = formatMoney(monthlyShare);
            }
            if (splitEl && baseInst > 0) {
                splitEl.textContent = formatMoney(splitAmount);
            }
            if (splitLabelEl) {
                if (freq === 'daily') {
                    splitLabelEl.textContent = 'Daily Collection (÷30):';
                } else if (freq === 'weekly') {
                    splitLabelEl.textContent = 'Weekly Collection (÷4):';
                } else {
                    splitLabelEl.textContent = 'Collection Amount:';
                }
            }
            if (payoutEl && basePayout > 0) {
                payoutEl.textContent = formatMoney(basePayout * val / 100);
            }

            if (form) {
                form.querySelectorAll('.shared-membership-block').forEach(refreshAmounts);
            }
        });
    }

    function boot() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.querySelector('.sharing-mode-radio')) {
                initSharingMode(form);
            }
        });
        document.querySelectorAll('.shared-membership-block').forEach(initBlock);
        document.querySelectorAll('.share-percentage-input').forEach(function (input) {
            if (input._sharePctBound) return;
            input._sharePctBound = true;
            input.addEventListener('input', updateSharePctPreviews);
            input.addEventListener('change', updateSharePctPreviews);
        });
        document.querySelectorAll('.collection-frequency-select').forEach(function (select) {
            if (select._freqBound) return;
            select._freqBound = true;
            select.addEventListener('change', updateSharePctPreviews);
        });

        document.querySelectorAll('select[name="group_id"]').forEach(function (select) {
            if (select._groupBound) return;
            select._groupBound = true;
            select.addEventListener('change', updateSharePctPreviews);
        });

        updateSharePctPreviews();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('shown.bs.modal', function () {
        boot();
    });
})();
