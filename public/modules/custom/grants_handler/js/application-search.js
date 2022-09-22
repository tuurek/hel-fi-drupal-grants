(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.GrantsHandlerApplicatiosSearchBehavior = {
    attach: function (context, settings) {
      var fullListOptions = null;
      var fullList = null;
      if ($("#applications__list")[0]) {
        fullListOptions = {
          valueNames: ['application-list__item--name', 'application-list__item--status', 'application-list__item--number', 'application-list__item--submitted']
        };

        fullList = new List('applications__list', fullListOptions);
        $('#applications__list .application-list__count-value').html(fullList.update().matchingItems.length);

        fullList.on('searchComplete', function () {
          $('#applications__list .application-list__count-value').html(fullList.update().matchingItems.length);
        });

        $('select.sort').change(function () {
          var selection = $(this).val();
          fullList.sort(selection);
          console.log(fullList);
          console.log(selection);
        });

        $('button.sort').click(function () {
          fullList.sort($(this).data('sort'));
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
