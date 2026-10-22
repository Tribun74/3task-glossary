/**
 * 3task Glossary: knowledge base "helpful" rating v2.4.0
 * Sends one anonymous vote. The browser remembers it in localStorage; nothing is stored about the visitor on the server.
 *
 * @package 3Task_Glossary
 * @since 2.4.0
 */

(function() {
    'use strict';

    var config = window.azglKb || {};

    function remember(id) {
        try {
            window.localStorage.setItem('azgl_kb_voted_' + id, '1');
        } catch (e) {}
    }

    function voted(id) {
        try {
            return '1' === window.localStorage.getItem('azgl_kb_voted_' + id);
        } catch (e) {
            return false;
        }
    }

    function done(box) {
        box.classList.add('is-done');
        box.querySelector('.azgl-kb-rating-question').textContent = config.thanks || '';
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.azgl-kb-rating').forEach(function(box) {
            var id = box.getAttribute('data-azgl-article');
            if (voted(id)) {
                done(box);
                return;
            }
            box.querySelectorAll('[data-azgl-vote]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var body = new FormData();
                    body.append('article', id);
                    body.append('vote', btn.getAttribute('data-azgl-vote'));
                    remember(id);
                    done(box);
                    if (config.url) {
                        fetch(config.url, { method: 'POST', body: body, credentials: 'omit' });
                    }
                });
            });
        });
    });
})();
