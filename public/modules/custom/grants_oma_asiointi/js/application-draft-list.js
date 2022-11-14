(function ($, Drupal, drupalSettings) {
Drupal.behaviors.omaAsiointiFront = {
  attach: function (context, settings) {
    if ($("#oma-asiointi__sent")[0]) {
      var sentListOptions = {
        valueNames: ['application-list__item--name', 'application-list__item--status', 'application-list__item--number', 'application-list__item--submitted']
      };
      var sentList = new List('oma-asiointi__sent', sentListOptions);
      $('#oma-asiointi__sent .application-list__count-value').html(sentList.update().matchingItems.length);

      sentList.on('searchComplete', function () {
        $('#oma-asiointi__sent .application-list__count-value').html(sentList.update().matchingItems.length);
      });

      $('select.sort').change(function () {
        selectionArray = $(this).val().split(' ');
        var selection = selectionArray[1];
        var direction = selectionArray[0]
        sentList.sort(selection, {order: direction});
      });

      $('button.sort').click(function () {
        sentList.sort($(this).data('sort'));
      });
      sentList.sort('application-list__item--submitted', {order: 'desc'});

    }
  }
};
})(jQuery, Drupal, drupalSettings);
