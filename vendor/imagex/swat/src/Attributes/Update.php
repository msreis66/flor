<?php

namespace Imagex\Swat\Attributes;

use Attribute;

/**
 * Create an attribute for SWAT updates.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Update {

  /**
   * @param string $version The version number as an 8-digit string
   * @param string $description The update description
   * @param bool $required Whether the update is required
   */
  public function __construct(
    public string $version,
    public string $description,
    public bool $required = FALSE,
  ) {}

}
