window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function () {
    $.form.manage('/pay/api/config', '#payForm');

    window.pageOnUnLoad = function () {
    };

    return false;
};
