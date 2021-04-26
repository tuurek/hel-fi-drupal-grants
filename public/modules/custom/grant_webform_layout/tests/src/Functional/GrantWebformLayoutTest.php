<?php

namespace Drupal\Tests\grant_webform_layout\Functional;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests for webform example element.
 *
 * @group grant_webform_layout
 */
class GrantWebformLayoutTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['grant_webform_layout'];

  /**
   * Tests webform example element.
   */
  public function testGrantWebformLayout() {
    $webform = Webform::load('grant_webform_layout');

    // Check form element rendering.
    $this->drupalGet('/webform/grant_webform_layout');
    // NOTE:
    // This is a very lazy but easy way to check that the element is rendering
    // as expected.
    $this->assertRaw('<div class="js-form-item form-item js-form-type-grant-webform-layout form-item-grant-webform-layout js-form-item-grant-webform-layout">');
    $this->assertRaw('<label for="edit-grant-webform-layout">Webform Example Element</label>');
    $this->assertRaw('<input data-drupal-selector="edit-grant-webform-layout" type="text" id="edit-grant-webform-layout" name="grant_webform_layout" value="" size="60" class="form-text grant-webform-layout" />');

    // Check webform element submission.
    $edit = [
      'grant_webform_layout' => '{Test}',
      'grant_webform_layout_multiple[items][0][_item_]' => '{Test 01}',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $webform_submission = WebformSubmission::load($sid);
    $this->assertEqual($webform_submission->getElementData('grant_webform_layout'), '{Test}');
    $this->assertEqual($webform_submission->getElementData('grant_webform_layout_multiple'), ['{Test 01}']);
  }

}
