(function($, Symphony) {
    //Symphony.View.add('/extension/url_connector/url-connections/:action:/:id:/:status:', function(action, id, status) {
    Symphony.View.add('/extension/url_connector/url_connections/:action:/:id:/:status:', function(action, id, status) {
        var legend, expand, collapse, toggle;

        var duplicator = $('#parameters-duplicator');
        legend = $('#parameters-legend');

        // Create toggle controls
        expand = $('<a />', {
            'class': 'expand',
            'text': Symphony.Language.get('Expand all')
        });
        collapse = $('<a />', {
            'class': 'collapse',
            'text': Symphony.Language.get('Collapse all')
        });
        toggle = $('<p />', {
            'class': 'help toggle'
        });

        // Add toggle controls
        toggle.append(expand).append('<br />').append(collapse).insertAfter(legend);

        // Toggle fields
        toggle.on('click.admin', 'a.expand, a.collapse', function toggleFields() {

            // Expand
            if ($(this).is('.expand')) {
                duplicator.trigger('expandall.collapsible');
            }

            // Collapse
            else {
                duplicator.trigger('collapseall.collapsible');
            }
        });

        // Affix for toggle
        //$('fieldset.settings > legend + .help').symphonyAffix();

        // Initialise field editor
        duplicator.symphonyDuplicator({
            orderable: true,
            collapsible: true,
            preselect: 'name'
        });

        // Focus first input
        duplicator.on('constructshow.duplicator expandstop.collapsible', '.instance', function() {
            var item = $(this);
            if (!item.hasClass('js-animate-all')) {
                $(this).find('input:visible:first').trigger('focus.admin');
            }
        });

        // Update name
        duplicator.on('blur.admin input.admin', '.instance input[name*="[name]"]', function() {
            var label = $(this),
                value = label.val();

            // Empty label
            if(value === '') {
                value = Symphony.Language.get('Untitled Parameter');
            }

            // Update title
            label.parents('.instance').find('.frame-header strong').text(value);
        });

        $('input[name="fields[include_php]"]').change(function() {
            if (this.checked) {
                $('#php-box').slideDown();
            } else {
                $('#php-box').slideUp();
            }
        });

        // Restore collapsible states for new sections
        if (status === 'created') {
            var fields = duplicator.find('.instance'),
                storageId = Symphony.Context.get('context-id');

            storageId = storageId.split('.');
            storageId.pop();
            storageId = 'symphony.collapsible.' + storageId.join('.') + '.0.collapsed';

            if(Symphony.Support.localStorage === true && window.localStorage[storageId]) {
                $.each(window.localStorage[storageId].split(','), function(index, value) {
                    var collapsed = duplicator.find('.instance').eq(value);
                    if(collapsed.has('.invalid').length == 0) {
                        collapsed.trigger('collapse.collapsible', [0]);
                    }
                });

                window.localStorage.removeItem(storageId);
            }
        }
    });

})(window.jQuery, window.Symphony);
