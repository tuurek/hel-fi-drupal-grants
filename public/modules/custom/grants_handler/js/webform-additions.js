(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.GrantsHandlerBehavior = {
        attach: function (context, settings) {
            $("#edit-account-number-select").change(function () {
                $("#edit-account-number").val($(this).val())
            });
        }
    };
})(jQuery, Drupal, drupalSettings);
