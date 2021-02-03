define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    '../model/upsfreightshipping-validatior',
    '../model/upsfreightshipping-rules'
],function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        shippingRatesValidator,
        shippingRatesValidationRules
    ) {
        'use strict';
        defaultShippingRatesValidator.registerValidator('upsfreightshipping', shippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('upsfreightshipping', shippingRatesValidationRules);
        return Component;
    }
);