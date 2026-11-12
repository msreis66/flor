<?php
namespace Drupal\bootstrap_ui_kit\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * UI Kit Controller.
 */
class BootstrapUiKitController extends ControllerBase {

  /**
   * Returns the user interface kit designed by IXM.
   *
   * @return array
   *   A simple renderable array.
   */
  public function content() {
    return array (
      '#theme' => [
        'bootstrap_ui_kit',
      ],
    );
  }
}
