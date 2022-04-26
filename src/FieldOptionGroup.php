<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\FieldOptionGroup.
 */

namespace Drupal\simple_conreg;

/**
 * Class to represent a group of options.
 */
class FieldOptionGroup {

  public $groupId;
  public $fieldType;
  public $fieldName;
  public $title;
  public $options;
  public $global;
  public $public;

  /**
   * Constructs a new FieldOption object.
   */
  public function __construct()
  {
    $this->fieldOptions = [];
  }

  public function parseGroup($groupLine)
  {
    list($this->groupId, $this->fieldType, $this->fieldName, $this->title, $this->global, $this->public) = array_pad(explode('|', $groupLine), 5, '');
  }

  /*
   * Make a copy of the group, but not its options.
   */
  public function cloneGroup()
  {
    $group = new FieldOptionGroup();
    $group->groupId = $this->groupId;
    $group->fieldType = $this->fieldType;
    $group->fieldName = $this->fieldName;
    $group->title = $this->title;
    $group->global = $this->global;
    $group->public = $this->public;
    return $group;
  }
  
  public function addOption(FieldOption &$option)
  {
    $this->options[$option->optionId] = $option;
  }

  public function groupForm(&$member, $requireMandatory = TRUE)
  {
    $options = [
      '#type' => 'fieldset',
      '#title' => t($this->title),
      '#attributes' => [
        'class' => ['field-option-group'],
      ],
    ];
    switch ($this->fieldType) {
      case 'checkboxes':
        return $this->groupCheckBoxes($options);
        break;
      case 'textfields':
        return $this->groupTextFields($options);
        break;
      default:
        return $this->groupCheckBoxes($options);
    }
  }

  private function groupCheckBoxes($options)
  {
    foreach ($this->options as $option) {
      // Create a div to contain the option.
      $options[$option->optionId] = [
        '#prefix' => '<div class="option_'.$option->optionId.'">',
        '#suffix' => '</div>',
      ];
      // Add the option to the form.
      $options[$option->optionId]['option'] = [
        '#type' => 'checkbox',
        '#title' => $option->title,
        '#attributes' => [
          'class' => ['field-option'],
        ],
      ];
      if (isset($option->mustSelect) && $option->mustSelect == 1 && $requireMandatory) {
        //$options[$option->optionId]['option']['#required'] = TRUE;
        $options[$option->optionId]['option']['#attributes']['class'][] = 'must-select';
      }
      // If option has detail, add the detail.
      if (!empty($option->detailTitle)) {
        $options[$option->optionId]['option']['#attributes']['class'][] = 'field-option-has-detail';
        $options[$option->optionId]['detail'] = [
          '#type' => 'textfield',
          '#title' => $option->detailTitle,
          '#weight' => $option->weight,
          '#attributes' => [
            'class' => ['field-option-detail'],
          ],
        ];
        if ($option->detailRequired) {
          //$options[$option->optionId]['detail']['#required'] = TRUE;
          $options[$option->optionId]['detail']['#attributes']['class'][] = 'detail-required';
        }
      }
      // If member data for option, add the values.
      if (isset($member) && isset($member->options) && isset($member->options[$option->optionId])) {
        $options[$option->optionId]['option']['#default_value'] = $member->options[$option->optionId]->isSelected;
        if (isset($member->options[$option->optionId]->optionDetail)) {
          $options[$option->optionId]['detail']['#default_value'] = $member->options[$option->optionId]->optionDetail;
        }
      }
    }
    return $options;
  }

  private function groupTextFields($options)
  {
    foreach ($this->options as $option) {
      // Create a div to contain the option.
      $options[$option->optionId] = [
        '#prefix' => '<div class="option_'.$option->optionId.'">',
        '#suffix' => '</div>',
      ];
      // If option has detail, add the detail.
      $options[$option->optionId]['detail'] = [
        '#type' => 'textfield',
        '#title' => $option->detailTitle,
        '#weight' => $option->weight,
        '#attributes' => [
          'class' => ['field-option-detail'],
        ],
      ];
      if ($option->detailRequired) {
        //$options[$option->optionId]['detail']['#required'] = TRUE;
        $options[$option->optionId]['detail']['#attributes']['class'][] = 'detail-required';
      }
      // If member data for option, add the values.
      if (isset($member->options[$option->optionId]->optionDetail)) {
        $options[$option->optionId]['detail']['#default_value'] = $member->options[$option->optionId]->optionDetail;
      }
    }
    return $options;
  }

  public static function newGroup($groupLine)
  {
    if (!empty($groupLine)) {
      $group = new FieldOptionGroup();
      $group->parseGroup($groupLine);
      return $group;
    }
    return FALSE;
  }
}
