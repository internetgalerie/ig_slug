/**
 * Module: Ig/IgSlug/Confirm
 * JavaScript for Confirt
 * @exports Ig/IgSlug/Confirm
 */
define(['jquery'], function($) {

        // Confirmation box
    var Confirm = {
    };
    // Form submit on changes
    $('select.form-ig-slug-filter').change(function(){$(this).parents('form').submit()});

    Confirm.init = function() {
     // do init stuff
    };
    Confirm.dialog = function(title, message, url) {
                top.TYPO3.Modal.confirm(title, message).on('button.clicked', function(e) {
                        if (e.target.name == 'ok') {
                                document.location.href = url;
                        }
                        top.TYPO3.Modal.dismiss();
                });
                return false;
    };


    TYPO3.Confirm=Confirm
    return Confirm;
});
