(function ($, Drupal, drupalSettings) {
Drupal.behaviors.omaAsiointiFront = {
  attach: function (context, settings) {
    if ($("#oma-asiointi__drafts")[0]) {
      var draftListOptions = {
        valueNames: ['application-list-item--name', 'application-list-item--status', 'application-list-item--number', 'application-list-item--submitted']
      };
      var draftList = new List('oma-asiointi__drafts', draftListOptions);
      $('#oma-asiointi__drafts .application-list__count-value').html(draftList.update().matchingItems.length);

      draftList.on('searchComplete', function () {
        $('#oma-asiointi__drafts .application-list__count-value').html(draftList.update().matchingItems.length);
      });

      $('select.sort').change(function () {
        var selection = $(this).val();
        draftList.sort(selection);
      });

      $('button.sort').click(function () {
        draftList.sort($(this).data('sort'));
      });
    }
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
        var selection = $(this).val();
        sentList.sort(selection);
      });

      $('button.sort').click(function () {
        sentList.sort($(this).data('sort'));
      });
    }
  }
};
})(jQuery, Drupal, drupalSettings);
