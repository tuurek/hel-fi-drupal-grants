(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.GrantsHandlerApplicationStatusCheck = {
    attach: function (context, settings) {
      $('.applicationStatusCheckable').on('click', function() {
        var applicationNumber = $(this).data('application-number');
        $.ajax({
          url: "https://hel-fi-drupal-grant-applications.docker.so//grants-metadata/status-check/" + applicationNumber,
        }).done(function(data) {
          console.log(data)
        });
      })
    }
  };
})(jQuery, Drupal, drupalSettings);
