
/* Javascript for WCTR PRO Admin */

jQuery(function ($) {

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

            case 'product_variation':
                ruleElement.find('.value-multiselect').show();
                ruleElement.find('.value-input, .value-select').hide();

                var selectElement = ruleElement.find('.multiselect-value');
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

    // Event listener for changing item-selector
    $(document).on('change', '.item-selector', function () {
        var ruleElement = $(this).closest('.rule-row');
        setValue(ruleElement);
    });

    $('#wctrpro-save-tyrules').on('click', function () {
        var data = {
            rules: []
        };

        $('.rule-group').each(function () {
            var groupUrl = $(this).find('.group-url').val();
            var rules = [];

            $(this).find('.rule-row').each(function () {
                var item = $(this).find('.item-selector').val();
                var operator = $(this).find('.operator-selector').val();
                var value = '';
                var condition = $(this).find('.condition-selector').val();

                switch (item) {
                    case 'user_role':
                    case 'user_status':
                    case 'product_category':
                    case 'product_variation':
                    case 'product_tag':
                    case 'product_name':
                        value = $(this).find('.multiselect-value').val();
                        break;

                    default:
                        value = $(this).find('.input-value').val();
                }

                rules.push({
                    item: item,
                    operator: operator,
                    value: value,
                    condition: condition
                });
            });

            data.rules.push({
                groupUrl: groupUrl,
                rules: rules
            });
        });

        $.ajax({
            type: "POST",
            url: wctr_config.restApiBase + 'tyrules/save',
            data: data,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wctr_config.nonce);
            },
            success: function (response) {
                toastr.success('Rules have been updated!', 'Success');
                window.onbeforeunload = null;
                $(window).off("beforeunload");
            },
            error: function (jqXHR, textStatus, errorThrown) {
                toastr.error('Some error occurred, please try again in some time!', 'Error!');
            }
        });
    });

});