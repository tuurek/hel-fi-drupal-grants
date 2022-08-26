(function ($, Drupal, drupalSettings) {
Drupal.behaviors.omaAsiointiFront = {
  attach: function (context, settings) {
    var draftListOptions = {
      valueNames: [ 'application-list-item--name', 'application-list-item--status', 'application-list-item--number', 'application-list-item--submitted' ]
    };
    var draftList = new List('oma-asiointi__drafts', draftListOptions);
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
