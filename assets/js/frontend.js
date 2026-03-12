/**
 * 3task Glossary - Frontend JavaScript v2.3.0
 * Print Functionality
 *
 * @package 3Task_Glossary
 * @since 2.3.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initPrint();
    });

    /**
     * Print functionality
     */
    function initPrint() {
        var printBtns = document.querySelectorAll('.azgl-print-btn');

        printBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.print();
            });
        });
    }

})();
