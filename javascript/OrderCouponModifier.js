(function () {
    'use strict';

    var DiscountCoupon = {
        formID: '#OrderCouponModifier_Form_ModifierForm',
        fieldID: '#CouponCode input',
        loadingClass: 'loading',

        submitForm: function (form) {
            var applyChanges =
                window.Cart && typeof window.Cart.setChanges === 'function'
                    ? window.Cart.setChanges
                    : window.SilverShop && typeof window.SilverShop.applyCartAjaxChanges === 'function'
                        ? window.SilverShop.applyCartAjaxChanges
                        : null;

            var action = form.getAttribute('action');
            var url = action && action !== '' ? action : window.location.href;

            form.classList.add(DiscountCoupon.loadingClass);

            var fd = new FormData(form);
            fetch(url, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        if (!response.ok) {
                            throw new Error(response.statusText || 'Request failed');
                        }
                        return body;
                    });
                })
                .then(function (data) {
                    form.classList.remove(DiscountCoupon.loadingClass);
                    if (applyChanges) {
                        applyChanges(data);
                    }
                })
                .catch(function () {
                    form.classList.remove(DiscountCoupon.loadingClass);
                });
        },

        init: function () {
            var form = document.querySelector(DiscountCoupon.formID);
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            var actions = form.querySelector('.Actions');
            if (actions) {
                actions.style.display = 'none';
            }

            var field = document.querySelector(DiscountCoupon.fieldID);
            if (!field) {
                return;
            }

            field.removeAttribute('disabled');
            field.addEventListener('change', function () {
                DiscountCoupon.submitForm(form);
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        DiscountCoupon.init();
    });
})();
