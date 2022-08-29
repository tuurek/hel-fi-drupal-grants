(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.GrantsHandlerApplicationSearchBehavior = {
    attach: function (context, settings) {



      var fullListOptions = {
        valueNames: [ 'application-list-item--name', 'application-list-item--status', 'application-list-item--number', 'application-list-item--submitted' ]
      };
      var fullList = new List('applications__list', fullListOptions);
      $('#applications__list .application-list__count-value').html(fullList.update().matchingItems.length);

      fullList.on('searchComplete', function(){
        $('#applications__list .application-list__count-value').html(fullList.update().matchingItems.length);
      });

      $('select.sort').change(function(){
        var selection = $(this).val();
        fullList.sort(selection);
      });

      $('button.sort').click(function() {
        fullList.sort($(this).data('sort'));
      });


    }
  };
})(jQuery, Drupal, drupalSettings);
