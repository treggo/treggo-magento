define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    '../../model/shipping-rates-validator/treggoshippingmethod',
    '../../model/shipping-rates-validation-rules/treggoshippingmethod'
], function (Component,
             defaultShippingRatesValidator,
             defaultShippingRatesValidationRules,
             customShippingRatesValidator,
             customShippingRatesValidationRules) {
    'use strict';

    defaultShippingRatesValidator.registerValidator('treggoshippingmethod', customShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('treggoshippingmethod', customShippingRatesValidationRules);

    return Component;
});
