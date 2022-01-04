<?php

namespace Drupal\grants_handler;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class LinkModifierService.
 */
class LinkModifierService implements InboundPathProcessorInterface {

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var RequestStack
   */
  protected RequestStack $requestStack;
  /**
   * Constructs a new DefaultService object.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request = NULL) {
    if (str_starts_with($path, '/yleisavustushakemus/')) {
      $sid = str_replace('/yleisavustushakemus/', '', $path);
      $path = "/admin/structure/webform/manage/yleisavustushakemus/submission/" . $sid;
    }
    return $path;
  }
}
