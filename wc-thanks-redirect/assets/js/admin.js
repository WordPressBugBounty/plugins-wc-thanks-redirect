
/* Javascript for WCTR PRO Admin */

jQuery(function ($) {

    // Sandbox/Live mode toggle: update the hint badge's color and text
    // immediately, without waiting for the settings form to be saved.
    var $modeSection = $('#wctr-mode-section');

    if ($modeSection.length) {
        var $modeHint     = $('#wctr-mode-hint');
        var $modeHintText = $('#wctr-mode-hint-text');

        $modeSection.on('change', '#wctr-mode-select', function () {
            var isLive = 'live' === $(this).val();

            $modeHint
                .removeClass('wctr-badge-live wctr-badge-sandbox')
                .addClass(isLive ? 'wctr-badge-live' : 'wctr-badge-sandbox');

            $modeHintText.text($modeSection.data(isLive ? 'hintLive' : 'hintSandbox'));
        });
    }

    // Global Thank You Page toggle: dim the URL fields below while it's off,
    // purely visual — the fields stay in the form and still save either way.
    var $globalToggle = $('#wctr_global');
    var $globalFields  = $('#wctr-global-fields');

    if ($globalToggle.length && $globalFields.length) {
        $globalToggle.on('change', function () {
            $globalFields.toggleClass('wctr-fields-disabled', !this.checked);
        });
    }

    $('#group-container').sortable({
        items: '.rule-group',
        placeholder: 'ui-state-highlight',
    }).disableSelection();

    function fetchSavedRules() {
        return $.ajax({
            url: wctr_config.restApiBase + 'tyrules/fetch',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                $('#group-container').html('<p id="loading-message" class="text-center"><em>Loading</em> <i class="fa fa-circle-notch fa-spin"></i></p>');
            },
            method: 'GET',
            dataType: 'json',
            complete: function () {
                $('#loading-message').hide();
            }
        });
    }

    // Load the UI with saved data
    function loadSavedData(rules) {
        // Clear existing container content
        $('#group-container').html('');

        // Loop through saved rules and populate UI
        $.each(rules, function (index, group) {
            var groupElement = addGroup();
            groupElement.find('.group-url').val(group.groupUrl);

            // Add rules to the group
            $.each(group.rules, function (ruleIndex, rule) {
                var ruleElement = addRule(groupElement);

                // Set rule fields
                ruleElement.find('.item-selector').val(rule.item);
                ruleElement.find('.operator-selector').val(rule.operator);
                setValue(ruleElement, rule.value);
                ruleElement.find('.condition-selector').val(rule.condition);
            });
        });

        toggleRemoveButtons();
        hideConditionIfLastRule();
    }

    // Helper function to add a new group
    function addGroup(isClicked = false) {
        var groupHtml = $('#group-template').html();
        var groupElement = $(groupHtml);
        $('#group-container').append(groupElement);

        if (!groupElement.find('.rule-row').length && isClicked) {
            addRule(groupElement);
        }

        toggleRemoveButtons();
        hideConditionIfLastRule();
        return groupElement;
    }

    // Helper function to add a rule to a group
    function addRule(groupElement) {
        var ruleHtml = $('#rule-template').html();
        var ruleElement = $(ruleHtml);
        groupElement.find('.rule-list').append(ruleElement);
        toggleRemoveButtons();
        hideConditionIfLastRule();
        return ruleElement;
    }

    // Helper function to set the value input field dynamically based on type
    function setValue(ruleElement, value) {
        var inputType = ruleElement.find('.item-selector').val();

        switch (inputType) {
            case 'product_name':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options
                var savedProductIds = value;

                // Initialize Select2 for product_name with AJAX search
                selectElement.select2({
                    placeholder: "Search for products",
                    minimumInputLength: 2,
                    ajax: {
                        url: wctr_config.restApiBase + 'tyrules/search-product',
                        dataType: 'json',
                        delay: 250,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                        },
                        data: function (params) {
                            return { search: params.term };
                        },
                        processResults: function (data) {
                            return {
                                results: $.map(data, function (product) {
                                    return { id: product.id, text: product.text };
                                })
                            };
                        },
                        cache: true
                    }
                });

                if (savedProductIds) {

                    if (Array.isArray(savedProductIds)) {
                        // Manually trigger the loading of saved product details and set them as selected options
                        var selectElement = ruleElement.find('.multiselect-value');

                        // Fetch product details for the saved product IDs
                        $.ajax({
                            url: wctr_config.restApiBase + 'tyrules/products/details',
                            method: 'POST',
                            dataType: 'json',
                            data: { ids: savedProductIds },
                            beforeSend: function (xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                            },
                            success: function (response) {
                                if (response && response.data) {
                                    var selectedOptions = $.map(response.data, function (product) {
                                        return { id: product.id, text: product.text };
                                    });

                                    // Append the selected options and set them as selected
                                    selectedOptions.forEach(function (option) {
                                        var newOption = new Option(option.text, option.id, true, true);
                                        selectElement.append(newOption).trigger('change');
                                    });
                                }
                            }
                        });
                    }

                }

                break;

            case 'payment_method':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                // Initialize Select2 multiselect for payment_method using localized data
                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options

                // Add each payment gateway option from the localized data
                $.each(wctr_config.lists.payment_gateways, function (index, gateway) {
                    var option = $('<option></option>')
                        .val(gateway.id)
                        .text(gateway.text);

                    selectElement.append(option);
                });

                // Initialize Select2 for the multiselect
                selectElement.select2({
                    placeholder: "Select payment gateways",
                    allowClear: false,
                    multiple: true,
                });

                // Set the saved value if provided (expects an array for multiselect)
                if (value && Array.isArray(value)) {
                    selectElement.val(value).trigger('change');
                }
                break;

            case 'product_variation':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options
                var savedProductIds = value;

                // Initialize Select2 for product_name with AJAX search
                selectElement.select2({
                    placeholder: "Search for variations",
                    minimumInputLength: 2,
                    ajax: {
                        url: wctr_config.restApiBase + 'tyrules/search-product',
                        dataType: 'json',
                        delay: 250,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                        },
                        data: function (params) {
                            return { search: params.term, variations_only: 1 };
                        },
                        processResults: function (data) {
                            return {
                                results: $.map(data, function (product) {
                                    return { id: product.id, text: product.text };
                                })
                            };
                        },
                        cache: true
                    }
                });

                if (savedProductIds) {

                    if (Array.isArray(savedProductIds)) {
                        // Manually trigger the loading of saved product details and set them as selected options
                        var selectElement = ruleElement.find('.multiselect-value');

                        // Fetch product details for the saved product IDs
                        $.ajax({
                            url: wctr_config.restApiBase + 'tyrules/products/details',
                            method: 'POST',
                            dataType: 'json',
                            data: { ids: savedProductIds },
                            beforeSend: function (xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                            },
                            success: function (response) {
                                if (response && response.data) {
                                    var selectedOptions = $.map(response.data, function (product) {
                                        return { id: product.id, text: product.text };
                                    });

                                    // Append the selected options and set them as selected
                                    selectedOptions.forEach(function (option) {
                                        var newOption = new Option(option.text, option.id, true, true);
                                        selectElement.append(newOption).trigger('change');
                                    });
                                }
                            }
                        });
                    }

                }

                break;

            case 'product_category':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options
                var savedProductIds = value;

                // Initialize Select2 for product_name with AJAX search
                selectElement.select2({
                    placeholder: "Search for category",
                    minimumInputLength: 2,
                    ajax: {
                        url: wctr_config.restApiBase + 'tyrules/search-category',
                        dataType: 'json',
                        delay: 250,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                        },
                        data: function (params) {
                            return { search: params.term, post_type: 'product' };
                        },
                        processResults: function (data) {
                            return {
                                results: $.map(data, function (product) {
                                    return { id: product.id, text: product.text };
                                })
                            };
                        },
                        cache: true
                    }
                });

                if (savedProductIds) {

                    if (Array.isArray(savedProductIds)) {
                        // Manually trigger the loading of saved product details and set them as selected options
                        var selectElement = ruleElement.find('.multiselect-value');

                        // Fetch product details for the saved product IDs
                        $.ajax({
                            url: wctr_config.restApiBase + 'tyrules/post/terms',
                            method: 'POST',
                            dataType: 'json',
                            data: { ids: savedProductIds, taxonomy: 'product_cat' },
                            beforeSend: function (xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                            },
                            success: function (response) {
                                if (response && response.data) {
                                    var selectedOptions = $.map(response.data, function (product) {
                                        return { id: product.id, text: product.text };
                                    });

                                    // Append the selected options and set them as selected
                                    selectedOptions.forEach(function (option) {
                                        var newOption = new Option(option.text, option.id, true, true);
                                        selectElement.append(newOption).trigger('change');
                                    });
                                }
                            }
                        });
                    }

                }

                break;

            case 'product_tag':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options
                var savedProductIds = value;

                // Initialize Select2 for product_name with AJAX search
                selectElement.select2({
                    placeholder: "Search for tag",
                    minimumInputLength: 2,
                    ajax: {
                        url: wctr_config.restApiBase + 'tyrules/search-tag',
                        dataType: 'json',
                        delay: 250,
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                        },
                        data: function (params) {
                            return { search: params.term, post_type: 'product' };
                        },
                        processResults: function (data) {
                            return {
                                results: $.map(data, function (product) {
                                    return { id: product.id, text: product.text };
                                })
                            };
                        },
                        cache: true
                    }
                });

                if (savedProductIds) {

                    if (Array.isArray(savedProductIds)) {
                        // Manually trigger the loading of saved product details and set them as selected options
                        var selectElement = ruleElement.find('.multiselect-value');

                        // Fetch product details for the saved product IDs
                        $.ajax({
                            url: wctr_config.restApiBase + 'tyrules/post/terms',
                            method: 'POST',
                            dataType: 'json',
                            data: { ids: savedProductIds, taxonomy: 'product_tag' },
                            beforeSend: function (xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
                            },
                            success: function (response) {
                                if (response && response.data) {
                                    var selectedOptions = $.map(response.data, function (product) {
                                        return { id: product.id, text: product.text };
                                    });

                                    // Append the selected options and set them as selected
                                    selectedOptions.forEach(function (option) {
                                        var newOption = new Option(option.text, option.id, true, true);
                                        selectElement.append(newOption).trigger('change');
                                    });
                                }
                            }
                        });
                    }

                }

                break;

            case 'user_role':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                // Initialize Select2 multiselect for payment_method using localized data
                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options

                // Add each payment gateway option from the localized data
                $.each(wctr_config.lists.user_roles, function (index, gateway) {
                    var option = $('<option></option>')
                        .val(gateway.id)
                        .text(gateway.text);

                    selectElement.append(option);
                });

                // Initialize Select2 for the multiselect
                selectElement.select2({
                    placeholder: "Select user roles",
                    allowClear: false,
                    multiple: true,
                });

                // Set the saved value if provided (expects an array for multiselect)
                if (value && Array.isArray(value)) {
                    selectElement.val(value).trigger('change');
                }
                break;

            case 'shipping_method':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                // Initialize Select2 multiselect for payment_method using localized data
                var selectElement = ruleElement.find('.multiselect-value');
                selectElement.empty(); // Clear existing options

                // Add each payment gateway option from the localized data
                $.each(wctr_config.lists.shipping_methods, function (index, shipping) {
                    var option = $('<option></option>')
                        .val(shipping.id)
                        .text(shipping.text);

                    selectElement.append(option);
                });

                // Initialize Select2 for the multiselect
                selectElement.select2({
                    placeholder: "Select shipping method",
                    allowClear: false,
                    multiple: true,
                });

                // Set the saved value if provided (expects an array for multiselect)
                if (value && Array.isArray(value)) {
                    selectElement.val(value).trigger('change');
                }
                break;

            default:
                ruleElement.find('.value-input').show();
                ruleElement.find('.input-value').val(value);
                ruleElement.find('.value-select, .value-multiselect').hide();
                break;
        }
    }

    // Fetch saved rules via AJAX
    fetchSavedRules().done(function (response) {
        if (response && response.data.length) {
            loadSavedData(response.data);
        } else {
            addGroup();
        }
    }).always(function () {
        $('#loading-message').remove();
        $('#button-container').show();
    }).fail(function () {
        $('#group-container').html('<p class="text-center"><span class="badge bg-warning">Failed to load rules. Please try again.</span></p>');
        addGroup();
    });

    // Prevent removing the last group or last rule in a group
    function toggleRemoveButtons() {
        $('.remove-group-btn').show();
        $('.remove-rule-btn').show();

        // Hide delete button for the last group if it's the only group
        if ($('.rule-group').length === 1) {
            $('.remove-group-btn').hide();
        }

        // Hide delete button for the last rule if it's the only rule in the group
        $('.rule-group').each(function () {
            if ($(this).find('.rule-row').length === 1) {
                $(this).find('.remove-rule-btn').hide();
            }
        });
    }

    // Hide condition selector for the last rule in each group
    function hideConditionIfLastRule() {
        $('.rule-list').each(function () {
            var rules = $(this).find('.rule-row');
            rules.find('.condition-selector').show();
            rules.last().find('.condition-selector').hide();
        });
    }

    // Event listener for adding a new group
    $('#add-group-btn').click(function () {
        addGroup(true);
    });

    // Event listener for adding a new rule to a group
    $(document).on('click', '.add-rule-btn', function () {
        var groupElement = $(this).closest('.rule-group');
        addRule(groupElement);
    });

    // Event listener for removing a group
    $(document).on('click', '.remove-group-btn', function () {
        var groupElement = $(this).closest('.rule-group');
        if ($('.rule-group').length > 1) {
            groupElement.remove();
        }
        toggleRemoveButtons();
        hideConditionIfLastRule();
    });

    // Event listener for removing a rule
    $(document).on('click', '.remove-rule-btn', function () {
        var groupElement = $(this).closest('.rule-group');
        var ruleElement = $(this).closest('.rule-row');
        if (groupElement.find('.rule-row').length > 1) {
            ruleElement.remove();
        }
        toggleRemoveButtons();
        hideConditionIfLastRule();
    });

    // Event listener for url dropdown   
    $(document).on('click', '.url-dropdown', (event) => {
        const $dropdown = $(event.currentTarget);
        const selectedValue = $dropdown.val();
        $dropdown.parent().find('.group-url').val(selectedValue);
    });

    $(document).on('change', '#wctr_thanks_redirect_page', function () {
        $('#wctr_thanks_redirect_url').val($(this).val());
    })

    // Upgrade modal: accessible dialog shown whenever a free user touches
    // anything Rules-related, since Rules doesn't actually save in this
    // free build. Built once, content swapped per trigger. Defaults to a
    // no-op so every caller below can call it unconditionally even when
    // upsell UI is switched off via WCTR_SHOW_UPSELL.
    var openUpsellModal = function () {};
    var $upsellModal = $('#wctr-upsell-modal');

    if ($upsellModal.length && wctr_config.showUpsell) {
        var $upsellPanel = $upsellModal.find('.wctr-modal__panel');
        var $upsellTitle = $('#wctr-upsell-modal-title');
        var $upsellDesc = $('#wctr-upsell-modal-desc');
        var $upsellPrimary = $('#wctr-upsell-modal-primary');
        var upsellLastFocused = null;

        var closeUpsellModal = function () {
            $upsellModal.prop('hidden', true);
            $(document).off('keydown.wctrUpsellModal');

            if (upsellLastFocused && typeof upsellLastFocused.focus === 'function') {
                upsellLastFocused.focus();
            }
        };

        var onUpsellModalKeydown = function (event) {
            if (event.key === 'Escape') {
                closeUpsellModal();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var focusable = $upsellPanel.find('a[href], button').filter(':visible');
            if (!focusable.length) {
                return;
            }

            var first = focusable.first()[0];
            var last = focusable.last()[0];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        openUpsellModal = function (condition) {
            var upsell = wctr_config.upsell || {};
            var conditions = upsell.conditions || {};
            var benefits = upsell.benefits || {};

            var conditionLabel = condition && conditions[condition] ? conditions[condition] : '';
            var benefit = (condition && benefits[condition]) ? benefits[condition] : benefits.generic;

            $upsellTitle.text(
                conditionLabel
                    ? conditionLabel + ' rules are a PRO feature'
                    : 'Thank You Page Rules are a PRO feature'
            );
            $upsellDesc.text(benefit || '');

            if (upsell.trialUrl) {
                $upsellPrimary.attr('href', upsell.trialUrl).text('Start 7-day free trial');
            } else {
                $upsellPrimary.attr('href', upsell.upgradeUrl).text('Upgrade to PRO');
            }

            upsellLastFocused = document.activeElement;

            $upsellModal.prop('hidden', false);
            $upsellPanel.find('.wctr-modal__close').trigger('focus');

            $(document).on('keydown.wctrUpsellModal', onUpsellModalKeydown);
        };

        $upsellModal.on('click', '[data-wctr-modal-close]', closeUpsellModal);
    }

    // Event listener for changing item-selector: this free build's Rules
    // builder is preview-only (nothing here is ever actually saved), so
    // picking any condition surfaces the upgrade modal alongside the
    // existing value-field swap.
    $(document).on('change', '.item-selector', function () {
        var ruleElement = $(this).closest('.rule-row');
        setValue(ruleElement);

        var condition = $(this).val();
        if (condition) {
            openUpsellModal(condition);
        }
    });

    // "Save Rules" never calls the save endpoint in this free build —
    // Rules is preview-only here, so Save always surfaces the upgrade
    // modal instead.
    $(document).on('click', '#save-rules-btn', function () {
        openUpsellModal();
    });

    // Starter template cards: prefill a group+rule for the matching
    // condition, then show the upgrade modal for it.
    var STARTER_TEMPLATE_DEFAULTS = {
        payment_method: { operator: 'in' },
        product_category: { operator: 'in' },
        user_role: { operator: 'in' },
        shipping_method: { operator: 'in' }
    };

    $(document).on('click', '[data-wctr-template-condition]', function () {
        var condition = $(this).data('wctr-template-condition');
        var groupElement = addGroup(true);
        var ruleElement = groupElement.find('.rule-row').first();

        if (!ruleElement.length) {
            ruleElement = addRule(groupElement);
        }

        ruleElement.find('.item-selector').val(condition);
        setValue(ruleElement);

        var defaults = STARTER_TEMPLATE_DEFAULTS[condition];
        if (defaults && defaults.operator) {
            ruleElement.find('.operator-selector').val(defaults.operator);
        }

        openUpsellModal(condition);
    });

});