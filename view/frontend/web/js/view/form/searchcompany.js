define([
    'jquery',
    'ko',
    'Magento_Ui/js/form/element/abstract',
    'Magento_Checkout/js/model/url-builder',
    'vesource_kvk'
], function ($,ko,Abstract,urlBuilder) {

    'use strict';
    
    ko.bindingHandlers.autoComplete = {
        init: function (element, valueAccessor) {            
            var options = valueAccessor().options;
            
            //own widget
            $(element).kvk({autoCompleteOptions:options});              
        }
    };
    
    return Abstract.extend({
        defaults: {
            isApiError:ko.observable(false),
            modules:{
                street:'${"checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset"}.street',
            }
        },
        initialize: function () {
            this._super()
                ._setClasses();
        
//            var self = this;            
//            registry.async(this.provider)(function () {
                this.initModules();
                this.initObservable();
//            });
            
            this.isApiError.subscribe(function (newValue) {
                if(newValue===true){
                    //set warn
                }else{
                    this.warn(false);//
                }
            }, this);
            
            return this;
        },
        initObservable:function(){            
            this._super().observe(['visible','error','warn']);
            
            return this;
        },        
        processStreet:function(street,houseNumber,houseNumberAddition){
            var streetData = {};
            var arrStreet = [];
            
            street?arrStreet.push(street):arrStreet.push('');
            houseNumber?arrStreet.push(houseNumber):arrStreet.push('');
            
            if(houseNumberAddition){
                arrStreet.push(houseNumberAddition);
            }
                                                                                    
            var minLength = Math.min(this.street().elems().length,arrStreet.length);
            
            for(var i=0;i<minLength;i++){
                streetData[i] = arrStreet[i];
            }
                                    
            //append if we not have enough street field
                        
            if(arrStreet.length > minLength){
                //push to last street field
                streetData[minLength-1] = arrStreet.slice(minLength-1, arrStreet.length).join(' ');
            }
            
            return streetData;
        },        
        update:function(arr){
            var updateData = {};
            
            if(arr.shortBusinessName){
                updateData['company'] = arr.shortBusinessName;
            }

            if(arr.city){
                updateData['city'] = arr.city;
            }

            if(arr.postalCode){
                updateData['postcode'] = arr.postalCode;
            }

            updateData['street'] = this.processStreet(arr.street,arr.houseNumber,arr.houseNumberAddition);
            
            this.source.set(this.customScope,$.extend(true, {}, this.source.get(this.customScope), updateData));
        },
        getOptions:function(element){            
            var _that = this;
                                                                        
            var options = {
                url: function(phrase) {
                    var protocol = window.location.protocol;                   
                    var mode = phrase.length===8&&$.isNumeric(phrase.substring(0,8))?'BY_KVKNUMBER':'BY_COMPANYNAME';
                    
                    return protocol.replace("\/\/","")+"\/\/"+window.location.host+'/'+urlBuilder.createUrl('/kvk/information', {})+"?str=" + phrase+ "&mode=" + mode;
                }, 
                getValue: "shortBusinessName",
                ajaxSettings: {
                    dataType: "json",
                    method: "GET",
                    data: {
                        dataType: "json"
                    },
                    converters: {
                        'text json': function(result) {
                            if(result){
                                var obj = $.parseJSON($.parseJSON(result));
                                var items = obj.items || [];
                                var mode = obj.mode?obj.mode:'BY_COMPANYNAME';
                                                                
                                if(items.length&&items.length>0){
                                    if(mode === 'BY_KVKNUMBER' && items.length === 1){
                                        // hitting enter when find exact kvknumber                                                                                
                                        // hide list after show (to click)
                                        setTimeout(function(){
                                            $(element).closest('.easy-autocomplete').find('ul').show().find('.eac-item').eq(0).click();
                                            $(element).closest('.easy-autocomplete').find('ul').hide();
                                        },200);
                                    }
                                    
                                    if(_that.isApiError()===true){
                                        _that.set('isApiError',false);
                                    }
                                }
                                
                                return items;
                            }else{
                                if(_that.isApiError()===false){
                                    _that.set('isApiError',true);
                                } 
                            }
                        }
                   },
                    statusCode: {
                        404: function() {
                            if(_that.isApiError()===false){
                                _that.set('isApiError',true);
                            } 
                        }
                    },
                    error: function (request, status, error) {
                        if(_that.isApiError()===false){
                            _that.set('isApiError',true);
                        } 
                    },
                },
                requestDelay: 500,
                template: {
                    type: "description",
                    fields: {
                        description: "kvk"
                    }

                }, 
                list: {
                    maxNumberOfElements: 10,
                    onSelectItemEvent: function() {
                        var selectedItemData = $(element).getSelectedItemData();
                        
                        if (selectedItemData !== -1){
                            _that.update(selectedItemData);
                        }
                    }
                },

                theme: "blue-light"
            };

            return options;                                                            
        }
              
    });
});