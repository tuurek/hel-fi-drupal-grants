(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.GrantsHandlerApplicationSeachBehavior = {
    attach: function (context, settings) {
      var draftListOptions = {
        valueNames: [ 'application-list-item--name', 'application-list-item--status', 'application-list-item--number', 'application-list-item--submitted' ]
      };
      var draftList = new List('applications__list', draftListOptions);
      $('select.sort').change(function(){
        var selection = $(this).val();
        draftList.sort(selection);
      });

      $('button.sort').click(function() {
        draftList.sort($(this).data('sort'));
      });


    }
  };
})(jQuery, Drupal, drupalSettings);
