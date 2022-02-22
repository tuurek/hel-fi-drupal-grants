(function ($) {

    $("#edit-account-number-select").change(function () {
        $("#edit-account-number").val($(this).val())
    });

})(jQuery);
