(function () {
    'use strict';

    function doList() {
        var wrap = document.querySelector('#ModelClassSelector');
        if (!wrap) {
            return;
        }

        var currentModel = wrap.querySelector('select');
        if (!(currentModel instanceof HTMLSelectElement)) {
            return;
        }

        var currentModelName = currentModel.value || '';
        var formSelector = '#Form_SearchForm' + currentModelName.replace('Form', '');
        var form = document.querySelector(formSelector);
        if (form instanceof HTMLFormElement) {
            form.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('change', function (e) {
            var t = e.target;
            if (!(t instanceof Element)) {
                return;
            }

            if (t.closest('#ModelClassSelector')) {
                doList();
            }
        });

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!(t instanceof Element)) {
                return;
            }

            if (t.matches('button[name=action_clearsearch]')) {
                e.preventDefault();
                doList();
            }

            if (t.closest('#list_view')) {
                doList();
            }
        });

        if (document.querySelector('#list_view_loading')) {
            doList();
        }
    });
})();
