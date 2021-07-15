// eslint-disable-next-line no-unused-vars
((Drupal, drupalSettings) => {
  Drupal.behaviors.grants_webform_summation_fieldAccessData = {
    attach: function attach() {
      alert(drupalSettings.myname);
    },
  };
  // eslint-disable-next-line no-undef
})(Drupal, drupalSettings);
