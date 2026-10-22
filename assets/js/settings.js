/**
 * Settings option, credential, and save-state interactions.
 *
 * Builder: Shashi
 */
(function($) {
    'use strict';

    $(function() {
        $('.idlm-capture-tabs').on('click', '[role="tab"]', function() {
            var $tab = $(this), panelId = $tab.attr('aria-controls');
            $('.idlm-capture-tabs [role="tab"]').removeClass('active').attr({'aria-selected': 'false', 'tabindex': '-1'});
            $tab.addClass('active').attr({'aria-selected': 'true', 'tabindex': '0'});
            $('#idlm-capture-meta, #idlm-capture-webhook').prop('hidden', true);
            $('#' + panelId).prop('hidden', false);
            $('#idlm-capture-tab-input').val(panelId === 'idlm-capture-webhook' ? 'webhook' : 'meta');
        }).on('keydown', '[role="tab"]', function(event) {
            var $tabs = $('.idlm-capture-tabs [role="tab"]'), index = $tabs.index(this);
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') index = (index + 1) % $tabs.length;
            else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') index = (index - 1 + $tabs.length) % $tabs.length;
            else if (event.key === 'Home') index = 0;
            else if (event.key === 'End') index = $tabs.length - 1;
            else return;
            event.preventDefault(); $tabs.eq(index).trigger('click').trigger('focus');
        });
    });

    $(function() {
        var $root = $('[data-idlm-facebook-connect]');
        if (!$root.length) { return; }
        var $status = $root.find('[data-idlm-facebook-status]');
        var $pages = $root.find('[data-idlm-facebook-pages]');
        var $forms = $root.find('[data-idlm-facebook-forms]');
        var pageCursor = '', formCursor = '', formRequest = 0, activeRequests = 0;
        function call(operation, data) {
            activeRequests++;
            $root.attr('aria-busy', 'true');
            return $.ajax({url: idlm_vars.ajax_url, method: 'POST', data: $.extend({action: 'idlm_facebook_connect', nonce: idlm_vars.nonce, operation: operation}, data || {})})
                .always(function() { activeRequests--; if (!activeRequests) { $root.removeAttr('aria-busy'); } });
        }
        function error(xhr) { $status.text(xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Could not connect to Facebook. Please try again.'); }
        function result(response) { if (!response || !response.success) { $status.text(response && response.data ? response.data.message : 'Facebook connection failed.'); return null; } return response.data; }
        function loadPages(after) {
            $status.text('Loading your Facebook Pages…');
            $root.find('[data-idlm-facebook-more-pages]').prop('disabled', true);
            call('pages', {after: after || ''}).done(function(response) {
                var data = result(response); if (!data) { return; }
                $root.find('[data-idlm-facebook-picker]').prop('hidden', false);
                (data.pages || []).forEach(function(page) { if (!$pages.find('option').filter(function() { return this.value === page.id; }).length) { $('<option>').val(page.id).text(page.name).appendTo($pages); } });
                pageCursor = data.after || '';
                $root.find('[data-idlm-facebook-more-pages]').prop('hidden', !pageCursor);
                $status.text($pages.find('option').length > 1 ? 'Choose a Page to see its lead forms.' : 'No Pages were returned. Connect again and grant access to the Pages you want to sync.');
            }).fail(error).always(function() { $root.find('[data-idlm-facebook-more-pages]').prop('disabled', false); });
        }
        function loadForms(after) {
            var page = $pages.val(), request = ++formRequest;
            if (!page) { return; }
            $status.text('Loading individual lead forms…');
            $root.find('[data-idlm-facebook-more-forms]').prop('disabled', true);
            call('forms', {page_id: page, after: after || ''}).done(function(response) {
                if (request !== formRequest || page !== $pages.val()) { return; }
                var data = result(response); if (!data) { return; }
                $root.find('[data-idlm-facebook-form-fieldset]').prop('hidden', false);
                (data.forms || []).forEach(function(form) {
                    if ($forms.find('input').filter(function() { return this.value === form.id; }).length) { return; }
                    var $label = $('<label>').addClass('idlm-form-group');
                    $('<input>', {type: 'checkbox', value: form.id, 'data-idlm-facebook-form': ''}).appendTo($label);
                    $label.append(document.createTextNode(' ' + form.name)).appendTo($forms);
                });
                formCursor = data.after || '';
                $root.find('[data-idlm-facebook-more-forms]').prop('hidden', !formCursor);
                $status.text($forms.find('input').length ? 'Select the forms you want to sync, then click Start syncing.' : 'This Page has no active lead forms available to this connection.');
            }).fail(function(xhr) { if (request === formRequest) { error(xhr); } }).always(function() { $root.find('[data-idlm-facebook-more-forms]').prop('disabled', false); });
        }
        $root.on('click', '[data-idlm-facebook-start]', function() {
            if ($root.attr('data-idlm-facebook-ready') !== '1') {
                $status.text('Facebook sign-in is unavailable until the CRM provider configures the shared connection service and a public HTTPS address.');
                $root.find('[data-idlm-facebook-setup]').trigger('focus');
                return;
            }
            var $button = $(this); $button.prop('disabled', true); $status.text('Opening Facebook permissions…');
            call('start', {return_url: window.location.href.split('#')[0]}).done(function(response) { var data = result(response); if (data && data.authorization_url) { window.location.assign(data.authorization_url); } }).fail(error).always(function() { $button.prop('disabled', false); });
        });
        $pages.on('change', function() { formRequest++; $forms.empty(); formCursor = ''; $root.find('[data-idlm-facebook-form-fieldset]').prop('hidden', true); $root.find('[data-idlm-facebook-save]').prop('disabled', true); loadForms(''); });
        $root.on('change', '[data-idlm-facebook-form]', function() { $root.find('[data-idlm-facebook-save]').prop('disabled', $forms.find('input:checked').length < 1); });
        $root.on('click', '[data-idlm-facebook-more-pages]', function() { loadPages(pageCursor); });
        $root.on('click', '[data-idlm-facebook-more-forms]', function() { loadForms(formCursor); });
        $root.on('click', '[data-idlm-facebook-save]', function() {
            var forms = $forms.find('input:checked').map(function() { return this.value; }).get(), $button = $(this);
            if (!forms.length || forms.length > 20) { $status.text('Choose between one and twenty forms.'); return; }
            $button.prop('disabled', true); $pages.prop('disabled', true); $status.text('Connecting the selected forms…');
            call('select', {page_id: $pages.val(), forms: forms}).done(function(response) {
                if (result(response)) { var url = new URL(window.location.href); url.searchParams.delete('idlm_fb_return'); url.hash = 'tab-webhooks'; window.location.assign(url.toString()); }
            }).fail(error).always(function() { $button.prop('disabled', false); $pages.prop('disabled', false); });
        });
        $root.on('click', '[data-idlm-facebook-disconnect]', function() {
            var $button = $(this); $button.prop('disabled', true); $status.text('Disconnecting Facebook…');
            call('disconnect').done(function(response) { if (result(response)) { window.location.reload(); } }).fail(error).always(function() { $button.prop('disabled', false); });
        });
        var current = new URL(window.location.href);
        if (current.searchParams.get('idlm_fb_return') === '1') {
            current.searchParams.delete('idlm_fb_return'); current.hash = 'tab-webhooks'; window.history.replaceState(null, '', current.toString());
            var tab = document.getElementById('idlm-settings-tab-webhooks'); if (tab) { tab.click(); }
            loadPages('');
        }
    });


    function tokenValues($editor) {
        var editor = $editor.get(0);
        if (!editor) {
            return [];
        }
        return Array.prototype.map.call(editor.querySelectorAll('[data-idlm-token-list] .idlm-token-editor__item'), function(item) {
            var value = item.querySelector('[data-idlm-token-value]');
            return value ? value.value.trim() : '';
        }).filter(Boolean);
    }

    function syncTokenSource($editor) {
        var editor = $editor.get(0);
        var source = editor ? editor.querySelector('[data-idlm-token-source]') : null;
        if (!source) {
            return;
        }
        source.value = tokenValues($editor).join(',');
        source.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function updateTokenOrderControls($editor) {
        var $items = $editor.find('[data-idlm-token-list] .idlm-token-editor__item');
        $items.each(function(index) {
            var $item = $(this);
            $item.find('[data-idlm-token-move="up"]').prop('disabled', index === 0);
            $item.find('[data-idlm-token-move="down"]').prop('disabled', index === $items.length - 1);
            $item.find('.idlm-token-editor__position').text(index + 1);
        });
    }

    function appendPipelineTotalControl($item, value) {
        var controlId = 'idlm-stage-total-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
        $('<label>', {'class': 'screen-reader-text', text: 'Header total for ' + value})
            .attr('for', controlId)
            .appendTo($item);
        var $select = $('<select>', {
            id: controlId,
            name: 'kanban_stage_totals[' + encodeURIComponent(value) + ']',
            'class': 'idlm-select idlm-pipeline-stage-editor__total',
            'aria-label': 'Header total for ' + value,
            'data-idlm-stage-total': ''
        });
        $('<option>', {value: 'none', text: 'Hidden'}).appendTo($select);
        $('<option>', {value: 'quote_value', text: 'Quote value'}).appendTo($select);
        $('<option>', {value: 'deal_value', text: 'Deal value'}).appendTo($select);
        $select.appendTo($item);
    }

    function clearTokenError($editor) {
        $editor.removeClass('has-error');
        $editor.find('[data-idlm-token-entry]').removeAttr('aria-invalid');
        $editor.find('[data-idlm-token-error]').text('').prop('hidden', true);
    }

    function showTokenError($editor, message) {
        $editor.addClass('has-error');
        $editor.find('[data-idlm-token-entry]').attr('aria-invalid', 'true');
        $editor.find('[data-idlm-token-error]').text(message).prop('hidden', false);
    }

    function addToken($editor) {
        var $entry = $editor.find('[data-idlm-token-entry]');
        var value = $.trim($entry.val());
        var values = tokenValues($editor);
        var duplicate = values.some(function(existing) {
            return existing.toLocaleLowerCase() === value.toLocaleLowerCase();
        });

        clearTokenError($editor);
        if (!value) {
            showTokenError($editor, 'Enter an option before adding it.');
            $entry.trigger('focus');
            return;
        }
        if (value.indexOf(',') !== -1) {
            showTokenError($editor, 'Add one option at a time without commas.');
            $entry.trigger('focus');
            return;
        }
        if (duplicate) {
            showTokenError($editor, 'That option is already in the list.');
            $entry.trigger('focus');
            return;
        }

        var isPipeline = $editor.is('[data-idlm-pipeline-editor]');
        var $item = $('<li>', {'class': 'idlm-token-editor__item idlm-token-editor__item--movable' + (isPipeline ? ' idlm-pipeline-stage-editor__item' : '')})
            .attr('draggable', 'true');
        $('<span>', {'class': 'idlm-token-editor__position' + (isPipeline ? ' idlm-pipeline-stage-editor__position' : ''), 'aria-hidden': 'true'}).appendTo($item);
        $('<span>', {'class': 'dashicons dashicons-menu idlm-token-editor__drag', 'aria-hidden': 'true'}).appendTo($item);
        $('<span>', {'class': 'idlm-token-editor__value'}).text(value).appendTo($item);
        $('<input>', {
            type: 'hidden',
            name: $editor.find('[data-idlm-token-source]').attr('name') + '_values[]',
            value: value,
            'data-idlm-token-value': ''
        }).appendTo($item);
        $('<input>', {
            type: 'text',
            name: 'value_labels[' + $editor.find('[data-idlm-token-source]').attr('name') + '][' + encodeURIComponent(value) + ']',
            value: value,
            'class': 'idlm-input idlm-token-editor__alias',
            'aria-label': 'Display label for ' + value
        }).appendTo($item);
        if (isPipeline) {
            appendPipelineTotalControl($item, value);
        }
        if (!isPipeline) {
            $('<button>', {
                type: 'button',
                'class': 'idlm-token-editor__move',
                'aria-label': 'Move ' + value + ' up',
                'data-idlm-token-move': 'up'
            }).append($('<span>', {
                'class': 'dashicons dashicons-arrow-up-alt2',
                'aria-hidden': 'true'
            })).appendTo($item);
            $('<button>', {
                type: 'button',
                'class': 'idlm-token-editor__move',
                'aria-label': 'Move ' + value + ' down',
                'data-idlm-token-move': 'down'
            }).append($('<span>', {
                'class': 'dashicons dashicons-arrow-down-alt2',
                'aria-hidden': 'true'
            })).appendTo($item);
        }
        $('<button>', {
            type: 'button',
            'class': 'idlm-token-editor__remove',
            'aria-label': 'Remove ' + value,
            'data-idlm-token-remove': ''
        }).append($('<span>', {
            'class': 'dashicons dashicons-no-alt',
            'aria-hidden': 'true'
        })).appendTo($item);
        $editor.find('[data-idlm-token-list]').append($item);
        updateTokenOrderControls($editor);
        $entry.val('').trigger('focus');
        $editor.find('[data-idlm-token-status]').text(value + ' added.');
        syncTokenSource($editor);
    }

    function moveToken($button) {
        var $editor = $button.closest('[data-idlm-token-editor]');
        var $item = $button.closest('.idlm-token-editor__item');
        var direction = $button.attr('data-idlm-token-move');
        var $adjacent = direction === 'up' ? $item.prev('.idlm-token-editor__item') : $item.next('.idlm-token-editor__item');
        var value = $.trim($item.find('[data-idlm-token-value]').val());

        if (!$adjacent.length) {
            updateTokenOrderControls($editor);
            return;
        }

        if (direction === 'up') {
            $item.insertBefore($adjacent);
        } else {
            $item.insertAfter($adjacent);
        }
        syncTokenSource($editor);
        updateTokenOrderControls($editor);
        $editor.find('[data-idlm-token-status]').text(value + ' moved ' + direction + '.');
        $button.trigger('focus');
    }

    function validateTokenEditors($form) {
        var valid = true;
        var $firstInvalid = $();

        $form.find('[data-idlm-token-editor]').each(function() {
            var $editor = $(this);
            clearTokenError($editor);
            if ($editor.attr('data-idlm-required') === 'true' && tokenValues($editor).length === 0) {
                showTokenError($editor, 'Add at least one option before saving.');
                valid = false;
                if (!$firstInvalid.length) {
                    $firstInvalid = $editor.find('[data-idlm-token-entry]');
                }
            }
        });

        if ($firstInvalid.length) {
            $firstInvalid.trigger('focus');
        }
        return valid;
    }

    function enhanceSecretField(field) {
        var $field = $(field);
        if ($field.parent().hasClass('idlm-secret-control')) {
            return;
        }

        var fieldId = $field.attr('id');
        var labelText = fieldId ? $.trim($('label[for="' + fieldId + '"]').first().text()) : 'secret';
        var saved = $field.attr('data-idlm-secret-configured') === '1';
        var configured = saved || String($field.val() || '').length > 0;
        var $control = $('<div>', {'class': 'idlm-secret-control'});
        var $actions = $('<div>', {'class': 'idlm-secret-control__actions'});
        var $reveal = $('<button>', {
            type: 'button',
            'class': 'idlm-btn idlm-btn-secondary idlm-secret-control__button idlm-secret-control__reveal',
            'aria-pressed': 'false',
            'aria-label': 'Show ' + labelText
        }).append($('<span>', {
            'class': 'dashicons dashicons-visibility',
            'aria-hidden': 'true'
        }));
        var $copy = $('<button>', {
            type: 'button',
            'class': 'idlm-btn idlm-btn-secondary idlm-secret-control__button',
            text: 'Copy',
            'aria-label': 'Copy ' + labelText
        });
        var $state = $('<span>', {
            'class': 'idlm-secret-control__state ' + (configured ? 'is-configured' : 'is-empty'),
            text: saved ? 'Saved securely' : (configured ? 'Configured' : 'Not configured')
        });
        var $statusRow = $('<div>', {'class': 'idlm-secret-control__status-row'}).append($state);
        var $status = $('<span>', {'class': 'screen-reader-text', 'aria-live': 'polite'});

        $field.wrap($control);
        $actions.append($reveal, $copy);
        $field.after($actions, $statusRow, $status);
        // Keep any credential-specific destructive action paired with the
        // configured-state indicator instead of leaving it below the control.
        $statusRow.append($field.closest('.idlm-secret-control').next('.idlm-action-link-danger'));

        $reveal.on('click', function() {
            var revealing = $field.attr('type') === 'password';
            $field.attr('type', revealing ? 'text' : 'password');
            $reveal.attr('aria-pressed', revealing ? 'true' : 'false');
            $reveal.attr('aria-label', (revealing ? 'Hide ' : 'Show ') + labelText);
            $reveal.find('.dashicons').toggleClass('dashicons-visibility', !revealing).toggleClass('dashicons-hidden', revealing);
        });

        $copy.on('click', function() {
            var value = String($field.val() || '');
            if (!value) {
                $status.text(saved ? 'The saved value is hidden and cannot be copied. Paste a replacement to change it.' : labelText + ' is not configured.');
                return;
            }
            var copied = function() {
                $status.text(labelText + ' copied.');
                $copy.text('Copied');
                window.setTimeout(function() { $copy.text('Copy'); }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(copied, function() {
                    $field.trigger('select');
                    if (document.execCommand('copy')) copied();
                });
            } else {
                $field.trigger('select');
                if (document.execCommand('copy')) copied();
            }
        });

        $field.on('input', function() {
            var entered = String($field.val() || '').length > 0;
            configured = saved || entered;
            $state.toggleClass('is-configured', configured).toggleClass('is-empty', !configured).text(entered ? 'Ready to save' : (saved ? 'Saved securely' : 'Not configured'));
        });
    }

    function setSaveState($form, state, message) {
        var $bar = $form.closest('.idlm-settings-page').find('.idlm-settings-savebar');
        var $status = $bar.find('[data-idlm-save-status]');
        var $button = $bar.find('.idlm-save-settings-btn');
        $bar.removeClass('is-dirty is-saving is-saved').addClass('is-' + state);
        $status.text(message);
        $button.prop('disabled', state === 'saving').attr('aria-busy', state === 'saving' ? 'true' : 'false');
        $button.find('[data-idlm-save-label]').text(state === 'saving' ? 'Saving…' : 'Save settings');
    }

    function normalizeHex(value) {
        value = String(value || '').trim().toUpperCase();
        return /^#[0-9A-F]{6}$/.test(value) ? value : '';
    }

    function contrastRatio(hex) {
        hex = normalizeHex(hex);
        if (!hex) return 0;
        var channels = [1, 3, 5].map(function(index) {
            var value = parseInt(hex.slice(index, index + 2), 16) / 255;
            return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
        });
        var luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
        return 1.05 / (luminance + 0.05);
    }

    function updateBrandColour($control) {
        var $value = $control.find('[data-idlm-brand-colour-value]');
        var normalized = normalizeHex($value.val());
        var $contrast = $control.find('[data-idlm-brand-contrast]');
        $control.removeClass('has-error has-valid-colour');
        $value.removeAttr('aria-invalid');
        if (!normalized) {
            $control.addClass('has-error');
            $value.attr('aria-invalid', 'true');
            $contrast.text('Enter a six-digit hexadecimal colour.');
            return false;
        }
        $value.val(normalized);
        $control.find('[data-idlm-brand-colour-picker]').val(normalized);
        if ($control.attr('data-contrast-required') === 'true') {
            var ratio = contrastRatio(normalized);
            var passes = ratio >= 4.5;
            $contrast.text((passes ? 'Pass' : 'Does not pass') + ': ' + ratio.toFixed(2) + ':1 contrast with white text.');
            $control.toggleClass('has-error', !passes).toggleClass('has-valid-colour', passes);
            $value.attr('aria-invalid', passes ? 'false' : 'true');
            return passes;
        }
        $control.addClass('has-valid-colour');
        return true;
    }

    function updateBrandPreview($root) {
        var preview = $root.find('[data-idlm-brand-preview]').get(0);
        if (!preview) return;
        var values = {};
        $root.find('[data-idlm-brand-field]').each(function() {
            var $field = $(this);
            var key = $field.attr('data-idlm-brand-field');
            values[key] = $field.is(':checkbox') ? ($field.prop('checked') ? '1' : '0') : $field.val();
        });
        preview.style.setProperty('--idlm-action', normalizeHex(values.primary_color) || '#7A5017');
        preview.style.setProperty('--idlm-brand-gold', normalizeHex(values.accent_color) || '#C99644');
        preview.style.setProperty('--idlm-sidebar', normalizeHex(values.sidebar_color) || '#111A24');
        Object.keys(values).forEach(function(key) {
            $root.find('[data-idlm-preview-text="' + key + '"]').text(values[key] || '');
        });
        $root.find('[data-idlm-preview-attribution]').prop('hidden', values.show_plugin_attribution !== '1');
    }

    function renderBrandAsset($control, attachment) {
        var $preview = $control.find('[data-idlm-brand-asset-preview]');
        var $input = $control.find('[data-idlm-brand-asset-input]');
        var field = $input.attr('data-idlm-brand-field');
        var url = attachment && attachment.url ? attachment.url : '';
        var id = attachment && attachment.id ? attachment.id : 0;
        $input.val(id).trigger('change');
        $preview.empty();
        if (url) {
            $('<img>', {src: url, alt: ''}).appendTo($preview);
            $control.find('[data-idlm-brand-asset-select]').text('Replace');
            $control.find('[data-idlm-brand-asset-remove]').prop('hidden', false);
            $control.find('[data-idlm-brand-asset-status]').text('Image selected. Save settings to publish it.');
        } else {
            $('<span>', {'class': 'dashicons dashicons-format-image', 'aria-hidden': 'true'}).appendTo($preview);
            $('<span>').text('No image selected').appendTo($preview);
            $control.find('[data-idlm-brand-asset-select]').text('Choose image');
            $control.find('[data-idlm-brand-asset-remove]').prop('hidden', true);
            $control.find('[data-idlm-brand-asset-status]').text('Image removed. Save settings to publish this change.');
        }
        var $previewImage = $control.closest('[data-idlm-white-label]').find('[data-idlm-preview-image="' + field + '"]');
        if ($previewImage.length) {
            $previewImage.empty();
            if (url) {
                $('<img>', {src: url, alt: ''}).appendTo($previewImage);
            } else if (field === 'logo_id') {
                $('<span>', {'data-idlm-preview-text': 'brand_name'}).text($control.closest('[data-idlm-white-label]').find('[data-idlm-brand-field="brand_name"]').val() || '').appendTo($previewImage);
            } else {
                $('<span>', {'class': 'dashicons dashicons-groups', 'aria-hidden': 'true'}).appendTo($previewImage);
            }
        }
    }

    function validateWhiteLabel($root) {
        var valid = true;
        var $firstInvalid = $();
        $root.find('[data-idlm-brand-colour]').each(function() {
            if (!updateBrandColour($(this))) {
                valid = false;
                if (!$firstInvalid.length) $firstInvalid = $(this).find('[data-idlm-brand-colour-value]');
            }
        });
        if ($firstInvalid.length) $firstInvalid.trigger('focus');
        return valid;
    }

    /**
     * Promote WordPress notices created by the settings screen into a compact,
     * fixed toast stack. Individual notices retain their own live-region role;
     * the stack deliberately is not a live region to avoid announcing twice.
     */
    function enhanceSettingsNotices($page) {
        var stack = document.createElement('div');
        stack.className = 'idlm-settings-toast-stack';
        stack.setAttribute('aria-label', 'Settings notifications');
        document.body.appendChild(stack);

        $page.find('.notice, [data-idlm-settings-toast]').each(function() {
            var $notice = $(this);
            if ($notice.data('idlmToastEnhanced')) {
                return;
            }

            $notice.data('idlmToastEnhanced', true).addClass('idlm-settings-toast');
            // WordPress normally injects .notice-dismiss via common.js. Settings
            // pages can load without that script, so guarantee one accessible
            // dismissal control without creating duplicates. The icon is an
            // inline SVG rather than a WordPress icon-font glyph, which keeps
            // it consistent even when admin styles load in a different order.
            var $dismissButtons = $notice.find('.notice-dismiss');
            if (!$dismissButtons.length && $notice.is('.is-dismissible')) {
                $dismissButtons = $('<button>', {
                    type: 'button',
                    class: 'notice-dismiss',
                    'aria-label': 'Dismiss notification'
                }).appendTo($notice);
            }
            if ($dismissButtons.length > 1) {
                $dismissButtons.slice(1).remove();
                $dismissButtons = $dismissButtons.first();
            }
            $dismissButtons.each(function() {
                var button = this;
                var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                var firstStroke = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                var secondStroke = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                icon.setAttribute('class', 'idlm-settings-toast__dismiss-icon');
                icon.setAttribute('viewBox', '0 0 16 16');
                icon.setAttribute('aria-hidden', 'true');
                icon.setAttribute('focusable', 'false');
                firstStroke.setAttribute('d', 'M4 4L12 12');
                secondStroke.setAttribute('d', 'M12 4L4 12');
                icon.appendChild(firstStroke);
                icon.appendChild(secondStroke);
                button.type = 'button';
                button.setAttribute('aria-label', 'Dismiss notification');
                button.textContent = '';
                button.appendChild(icon);
            });
            if (!$notice.attr('role')) {
                $notice.attr({
                    role: $notice.is('.notice-error, .notice-warning') ? 'alert' : 'status',
                    'aria-live': $notice.is('.notice-error, .notice-warning') ? 'assertive' : 'polite',
                    'aria-atomic': 'true'
                });
            }
            stack.appendChild($notice.get(0));

            var timer = null;
            var startedAt = 0;
            var remaining = 4000;
            var closing = false;

            function removeNotice() {
                if (closing) {
                    return;
                }
                closing = true;
                if (timer) {
                    window.clearTimeout(timer);
                    timer = null;
                }
                $notice.addClass('is-closing');
                window.setTimeout(function() {
                    $notice.remove();
                    if (!stack.children.length) {
                        stack.remove();
                    }
                }, window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180);
            }

            // Capture the click before WordPress's document-level dismissal
            // handler. This makes removal reliable for both injected and
            // fallback controls, while retaining native Enter/Space activation.
            $dismissButtons.each(function() {
                var button = this;
                if (button._idlmToastDismissHandler) {
                    button.removeEventListener('click', button._idlmToastDismissHandler, true);
                }
                button._idlmToastDismissHandler = function(event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    removeNotice();
                };
                button.addEventListener('click', button._idlmToastDismissHandler, true);
            });

            var requiresAction = $notice.find('a, button:not(.notice-dismiss), input, select, textarea').length > 0;
            var hasExplicitAutoClose = $notice.is('[data-idlm-toast-autoclose]')
                && $notice.attr('data-idlm-toast-autoclose') !== 'false';
            var autoClose = hasExplicitAutoClose || ($notice.is('.notice-success, .notice-info')
                && !$notice.is('.notice-error, .notice-warning')
                && !requiresAction
                && $notice.attr('data-idlm-toast-autoclose') !== 'false');
            if (!autoClose) {
                return;
            }

            var delay = parseInt($notice.attr('data-idlm-toast-autoclose'), 10);
            delay = delay >= 3000 && delay <= 5000 ? delay : 4000;
            remaining = delay;

            function pauseTimer() {
                if (!timer) {
                    return;
                }
                window.clearTimeout(timer);
                timer = null;
                remaining -= Date.now() - startedAt;
            }

            function resumeTimer() {
                if (closing || timer || remaining <= 0) {
                    return;
                }
                startedAt = Date.now();
                timer = window.setTimeout(removeNotice, remaining);
            }

            $notice.on('mouseenter focusin', pauseTimer);
            $notice.on('mouseleave focusout', function(event) {
                if (event.type === 'focusout' && $notice.has(event.relatedTarget).length) {
                    return;
                }
                resumeTimer();
            });
            resumeTimer();
        });
    }

    window.IDLMSettingsActionStatus = function(button, message, type) {
        var $button = $(button);
        var $region = $button.closest('.idlm-table-actions');
        var $status = $region.find('[data-idlm-action-status]');
        if (!$status.length) {
            $status = $('<p>', {
                'class': 'idlm-settings-action-status',
                'role': type === 'error' ? 'alert' : 'status',
                'aria-live': type === 'error' ? 'assertive' : 'polite',
                'data-idlm-action-status': ''
            }).appendTo($region);
        }
        $status.removeClass('is-success is-error').addClass(type === 'error' ? 'is-error' : 'is-success');
        $status.text(message + ' Tested ' + new Date().toLocaleString() + '.');
    };

    $(function() {
        var $page = $('.idlm-settings-page');
        var $form = $('#idlm-settings-form');
        if (!$page.length || !$form.length) {
            return;
        }

        enhanceSettingsNotices($page);

        $page.on('click', '[data-idlm-manual-next]', function() {
            var $setup = $(this).closest('[data-idlm-manual-setup]');
            var $next = $setup.find('[data-idlm-manual-step="' + $(this).attr('data-idlm-manual-next') + '"]');
            $setup.find('[data-idlm-manual-step]').prop('open', false);
            $next.prop('open', true).children('summary').attr('tabindex', '-1').trigger('focus');
        });

        $page.find('input[type="password"]').each(function() {
            enhanceSecretField(this);
        });

        $page.on('click', '[data-idlm-token-add]', function() {
            addToken($(this).closest('[data-idlm-token-editor]'));
        });
        $page.on('keydown', '[data-idlm-token-entry]', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addToken($(this).closest('[data-idlm-token-editor]'));
            }
        });
        $page.on('input', '[data-idlm-token-entry]', function() {
            clearTokenError($(this).closest('[data-idlm-token-editor]'));
        });
        $page.on('click', '[data-idlm-token-remove]', function() {
            var $button = $(this);
            var $editor = $button.closest('[data-idlm-token-editor]');
            var $item = $button.closest('.idlm-token-editor__item');
            var value = $.trim($item.find('.idlm-token-editor__value').text());
            var removeToken = function() {
                $item.remove();
                clearTokenError($editor);
                syncTokenSource($editor);
                updateTokenOrderControls($editor);
                $editor.find('[data-idlm-token-status]').text(value + ' removed.');
                $editor.find('[data-idlm-token-entry]').trigger('focus');
            };

            if (!$editor.is('[data-idlm-pipeline-editor]')) {
                removeToken();
                return;
            }

            window.IDLMDialog.confirm({
                title: 'Remove pipeline stage?',
                message: '“' + value + '” will no longer be available as a pipeline stage. Leads already in this stage will retain their stored stage until moved. This takes effect after you save settings.',
                confirmLabel: 'Remove stage',
                trigger: $button.get(0)
            }).then(function(confirmed) {
                if (confirmed) removeToken();
            });
        });
        var draggedToken = null;
        $page.on('dragstart', '[data-idlm-sortable-editor] .idlm-token-editor__item', function(event) {
            draggedToken = this;
            $(this).addClass('is-dragging');
            event.originalEvent.dataTransfer.effectAllowed = 'move';
        }).on('dragover', '[data-idlm-sortable-editor] .idlm-token-editor__item', function(event) {
            if (!draggedToken || draggedToken === this) return;
            event.preventDefault();
            var rect = this.getBoundingClientRect();
            this.parentNode.insertBefore(draggedToken, event.originalEvent.clientY < rect.top + rect.height / 2 ? this : this.nextSibling);
        }).on('dragend', '[data-idlm-sortable-editor] .idlm-token-editor__item', function() {
            var $editor = $(this).closest('[data-idlm-token-editor]');
            $(this).removeClass('is-dragging');
            draggedToken = null;
            syncTokenSource($editor);
            updateTokenOrderControls($editor);
            $editor.find('[data-idlm-token-status]').text($editor.is('[data-idlm-pipeline-editor]') ? 'Pipeline stage order updated.' : 'Option order updated.');
        });
        $page.on('click', '[data-idlm-token-move]', function() {
            moveToken($(this));
        });

        $page.find('[data-idlm-token-editor]').each(function() {
            var $editor = $(this);
            syncTokenSource($editor);
            updateTokenOrderControls($editor);
        });

        var $whiteLabel = $page.find('[data-idlm-white-label]');
        if ($whiteLabel.length) {
            $whiteLabel.find('[data-idlm-brand-colour]').each(function() {
                updateBrandColour($(this));
            });
            updateBrandPreview($whiteLabel);

            $whiteLabel.on('input change', '[data-idlm-brand-field]', function() {
                updateBrandPreview($whiteLabel);
            });
            $whiteLabel.on('input', '[data-idlm-brand-colour-value]', function() {
                var $control = $(this).closest('[data-idlm-brand-colour]');
                var normalized = normalizeHex($(this).val());
                if (normalized) {
                    $control.find('[data-idlm-brand-colour-picker]').val(normalized);
                    updateBrandColour($control);
                }
                updateBrandPreview($whiteLabel);
            });
            $whiteLabel.on('blur', '[data-idlm-brand-colour-value]', function() {
                updateBrandColour($(this).closest('[data-idlm-brand-colour]'));
                updateBrandPreview($whiteLabel);
            });
            $whiteLabel.on('input change', '[data-idlm-brand-colour-picker]', function() {
                var $control = $(this).closest('[data-idlm-brand-colour]');
                $control.find('[data-idlm-brand-colour-value]').val(String($(this).val()).toUpperCase()).trigger('change');
                updateBrandColour($control);
                updateBrandPreview($whiteLabel);
            });
            $whiteLabel.on('click', '[data-idlm-brand-asset-select]', function() {
                if (!window.wp || !window.wp.media) {
                    window.IDLMDialog.notify('The WordPress media library is not available on this screen.', 'error');
                    return;
                }
                var $button = $(this);
                var $control = $button.closest('[data-idlm-brand-asset]');
                var frame = window.wp.media({
                    title: $button.attr('data-title') || 'Choose brand image',
                    button: {text: 'Use image'},
                    library: {type: ['image/png', 'image/jpeg', 'image/webp']},
                    multiple: false
                });
                frame.on('select', function() {
                    renderBrandAsset($control, frame.state().get('selection').first().toJSON());
                });
                frame.open();
            });
            $whiteLabel.on('click', '[data-idlm-brand-asset-remove]', function() {
                renderBrandAsset($(this).closest('[data-idlm-brand-asset]'), null);
            });
            $whiteLabel.on('click', '[data-idlm-brand-reset]', function() {
                var $button = $(this);
                window.IDLMDialog.confirm({
                    title: 'Restore default branding?',
                    message: 'This resets the branding fields and preview. Choose Save settings to publish the reset.',
                    confirmLabel: 'Restore defaults',
                    trigger: $button.get(0)
                }).then(function(confirmed) {
                    if (!confirmed) return;
                    var defaults = $whiteLabel.data('defaults') || {};
                    Object.keys(defaults).forEach(function(key) {
                        var $field = $whiteLabel.find('[data-idlm-brand-field="' + key + '"]');
                        if ($field.is(':checkbox')) {
                            $field.prop('checked', String(defaults[key]) === '1');
                        } else if ($field.is('[data-idlm-brand-asset-input]')) {
                            renderBrandAsset($field.closest('[data-idlm-brand-asset]'), null);
                        } else {
                            $field.val(defaults[key]);
                        }
                    });
                    $whiteLabel.find('[data-idlm-brand-colour]').each(function() { updateBrandColour($(this)); });
                    updateBrandPreview($whiteLabel);
                    $whiteLabel.find('[data-idlm-brand-field]').first().trigger('change').trigger('focus');
                });
            });
        }
        var initialState = $form.serialize();
        $form.on('input change', ':input', function() {
            if ($form.serialize() !== initialState) {
                setSaveState($form, 'dirty', 'Unsaved changes');
            } else {
                setSaveState($form, 'clean', 'No unsaved changes');
            }
        });
        $form.on('submit', function(event) {
            if (!validateTokenEditors($form) || ($whiteLabel.length && !validateWhiteLabel($whiteLabel))) {
                event.preventDefault();
                setSaveState($form, 'dirty', 'Fix the highlighted settings before saving.');
                return;
            }
            setSaveState($form, 'saving', 'Saving settings…');
        });

        var notice = document.querySelector('[data-idlm-settings-saved]');
        if (notice) {
            setSaveState($form, 'saved', 'Settings saved');
        }
        var whiteLabelErrors = document.querySelector('[data-idlm-white-label-errors]');
        if (whiteLabelErrors) {
            whiteLabelErrors.focus();
        }
    });
})(jQuery);
