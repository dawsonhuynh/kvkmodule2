define([
    "jquery",   
    "jquery.easyautocomplete"//
], function ($) {

    $.widget('vesource.kvk', {
        options: {
            autoCompleteCompany:false,
            autoCompleteOptions:{},
            url: '',
            loaderIconUrl: '',
            ajax: null,
            fieldWrapHtml: "" +
                "<div class='field'>" +
                "<label class='label'>%label%</label>" +
                "<div class='control'>" +
                "%inputHtml%" +
                "</div>" +
                "</div> ",
            addFields: {},
            hideFields: {},
            showFields: {}
        },                
        _create: function () {
            if( !$.isEmptyObject(this.options.autoCompleteOptions )){
              this.applyAutoComplete(this.options.autoCompleteOptions);
           }
//            this._initObservers();
        },

        _initObservers: function () {
            this._addFields();
            this._hideFields();
        },

        _addFields: function () {

            var fieldset = 'test';

            $('div.field.street').before(fieldset);
        },

        _hideFields: function () {
            $('div.field.street').hide();
            $('div.field.city').hide();
            $('div.field.region').hide();
        },

        _showFields: function () {

        },

        _save: function (observerData) {

            var self = this;

            this._loader(observerData.reload, 'show');

            var data = {};

            if (this.ajax) {
                this.ajax.abort();
            }

            this.ajax = $.ajax({
                type: "POST",
                url: this.options.url,
                data: data,
                success: function (response) {
                    console.log(response);

                    if (observerData.reload !== undefined) {
                        self._updateContent(response.content);
                    }

                    if (observerData.redirect !== undefined) {
                        window.location.href = observerData.redirect;
                    }

                    self._loader(observerData.reload, 'hide');
                },
            });

        },

        _updateContent: function (content) {

        },

        _loader: function (selectors, action) {
            var loaderClassName = 'vesource-kvk-loader';
            $.each(selectors, function (index, selector) {
                var element = $('#' + selector);
                if (action == 'show') {
                    element.append('<div class="' + loaderClassName + '">reloading</div>');
                } else {
                    element.find('.' + loaderClassName).remove();
                }
            });
        },
        applyAutoComplete: function(options){
            console.log('easyAutocomplete');
            //or use id
            $(this.element[0]).easyAutocomplete(options);
        }
    });

    return $.vesource.kvk;
});
