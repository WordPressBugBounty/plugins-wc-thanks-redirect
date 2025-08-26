const settings = window.wc.wcSettings.getSetting('sandboxpaymentgateway-wctr', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('SandBox Payment', 'wc-thanks-redirect');
const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || window.wp.i18n.__('This payment method is only available to administrators for simulating test orders.', 'wc-thanks-redirect'));
};
const WCTR_Gateway = {
    name: 'sandboxpaymentgateway-wctr',
    label: label,
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(WCTR_Gateway);