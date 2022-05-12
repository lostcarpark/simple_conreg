<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\FieldOption.
 */

namespace Drupal\simple_conreg;

/**
 * Class to represent a field options.
 */
class FieldOption {

  public $optionId;
  public $groupId;
  public $group;
  public $title;
  public $detailTitle;
  public $detailRequired;
  public $weight;
  public $inMemberClasses;
  public $mustSelect;

  /**
   * Constructs a new FieldOption object.
   */
  public function __construct()
  {
    $inMemberClasses = [];
  }

  public function parseOption($optionLine) {
    $optionFields = array_pad(explode('|', trim($optionLine)), 8, '');
    list($this->optionId, $this->groupId, $this->title, $this->detailTitle, $this->detailRequired, $this->weight, $belongsIn, $this->mustSelect) = $optionFields;
    $inMemberClasses = [];
    foreach (explode(',', trim($belongsIn)) as $inClass) {
      $this->inMemberClasses[] = $inClass;
    }
  }
  
  public function setGroup(FieldOptionGroup &$optionGroup) {
    $this->group = &$optionGroup;
  }
  
  public static function newOption($optionLine) {
    if (!empty($optionLine)) {
      $option = new FieldOption();
      $option->parseOption($optionLine);
      return $option;
    }
    return FALSE;
  }
}
