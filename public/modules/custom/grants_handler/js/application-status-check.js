(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.GrantsHandlerApplicationStatusCheck = {
    attach: function (context, settings) {
      $('.applicationStatusCheckable').on('click', function () {
        var applicationNumber = $(this).data('application-number');
        var requestUrl = drupalSettings.grants_handler.site_url + '/grants-metadata/status-check/' + applicationNumber;

        $.ajax({
          url: requestUrl,
        }).done(function (data) {
          console.log(data)
        });
      })
    }
  };
})(jQuery, Drupal, drupalSettings);
