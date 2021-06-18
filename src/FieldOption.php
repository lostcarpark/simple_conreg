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
  public $fieldSets;
  public $mustSelect;

  /**
   * Constructs a new FieldOption object.
   */
  public function __construct()
  {
    $fieldSets = [];
  }

  public function parseOption($optionLine) {
    $optionFields = array_pad(explode('|', $optionLine), 8, '');
    list($this->optionId, $this->groupId, $this->title, $this->detailTitle, $this->detailRequired, $this->weight, $belongsIn, $this->mustSelect) = $optionFields;
    $fieldSets = [];
    foreach (explode(',', trim($belongsIn)) as $fieldSet) {
      $this->fieldSets[] = $fieldSet;
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
