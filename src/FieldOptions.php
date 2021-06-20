<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\FieldOptions.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class FieldOptions {

  public $groups;
  public $options;
  public $fieldSets;

  /**
   * Fetch field options from config.
   *
   * Parameters: Event ID, Config, Fieldset.
   */
  public function __construct($eid, $config = NULL)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    // Initialise results arrays.
    $this->groups = [];
    $this->options = [];
    $this->fieldSets = [];

    // Get option groups and split into lines.
    foreach (explode("\n", $config->get('simple_conreg_options.option_groups')) as $group) {
      $optionGroup = FieldOptionGroup::newGroup($group);
      if ($optionGroup) {
        $this->groups[$optionGroup->groupId] = $optionGroup;
      }
    }

    // Get options and split into lines.
    foreach (explode("\n", $config->get('simple_conreg_options.options')) as $option) {
      $fieldOption = FieldOption::newOption($option);
      if ($fieldOption) {
        $this->options[$fieldOption->optionId] = $fieldOption;
        if (isset($fieldOption->groupId) && isset($this->groups[$fieldOption->groupId])) {
          $fieldOption->setGroup($this->groups[$fieldOption->groupId]);
          $this->groups[$fieldOption->groupId]->addOption($fieldOption);
        }
        // Add the field option into each required fieldset.
        foreach ($fieldOption->fieldSets as $fieldSet) {
          if (!isset($this->fieldSets[$fieldSet])) {
            $this->fieldSets[$fieldSet] = [];
          }
          if (!isset($this->fieldSets[$fieldSet][$fieldOption->groupId])) {
            $newGroup = $this->groups[$fieldOption->groupId]->cloneGroup();
            $this->fieldSets[$fieldSet][$fieldOption->groupId] = $newGroup;
          }
          $this->fieldSets[$fieldSet][$fieldOption->groupId]->options[] = $fieldOption;
        }
      }
    }
    return $this;
  }
  
  /*
   * Static function to get Field Options from cache if possible. If not in cache, process from settings.
   */
  public static function getFieldOptions($eid)
  {
    $cid = 'simple_conreg:fieldOptions_' . $eid;
    
    $fieldOptions = NULL;
    if ($cache = \Drupal::cache()->get($cid)) {
      $fieldOptions = $cache->data;
    }
    else {
      $fieldOptions = new FieldOptions($eid);
      \Drupal::cache()->set($cid, $fieldOptions);
    }
    return $fieldOptions;
  }

  /**
   * Fetch field option titles from config.
   *
   * Parameters: Event ID, Config, Fieldset.
   */
  public static function getFieldOptionsTitles($eid, $config=NULL) 
  {
    // If event config not passed in, load it.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    // Get the list of options, and put titles in an array.
    $optionTitles = [];
    foreach (explode("\n", $config->get('simple_conreg_options.options')) as $optionLine) {
      list($optid, $grpid, $optionTitle) = explode('|', $optionLine);
      $optionTitles[$optid] = $optionTitle;
    }
    return $optionTitles;
  }

  /**
   * Fetch field options selected by member.
   *
   * Parameters: Event ID, Config, member ID.
   */
  public function getMemberOptions($mid)
  {
    // Get member's options from database.
    $entries = FieldOptionStorage::getMemberOptions($mid);

    $memberOptions = []; // Initialise return array.
    // Loop through option groups.
    foreach ($this->groups as $optionGroup) {
      // Loop through options within group.
      foreach ($optionGroup->options as $optid => $option) {
        // Check if option selected by member.
        if (array_key_exists($optid, $entries)) {
          $fieldName = $optionGroup->fieldName; // Get field to attach option to.
          $memberOptions[$fieldName]['title'] = $optionGroup->title; // Get title from option group.
          $memberOptions[$fieldName]['options'][$optid]['option_title'] = $option->title; // Get option title.
          $memberOptions[$fieldName]['options'][$optid]['detail_title'] = $option->detailTitle;
          $memberOptions[$fieldName]['options'][$optid]['option_detail'] = $entries[$optid]['option_detail']; // Get member's detail from database result.
        }
      }
    }

    return $memberOptions;
  }
  
  /**
   * Fetch field options selected by member.
   *
   * Parameters: Event ID, Config, member ID.
   */
  public static function getMemberOptionValues($mid, $selected = TRUE)
  {
    // Get member's options from database.
    return FieldOptionStorage::getMemberOptions($mid, $selected);
  }
  
  /**
   * Add field options to member form.
   *
   * Parameters:
   * $eid - Event ID
   * $fieldset - the set of fields for the member type (determines which options to show)
   * $memberForm - Form to add to options to.
   * $memberVals - Form values.
   * $optionCallbacks - Array to be populated with callbacks.
   * $callback- Name of callback function.
   * $memberNo - >=1 only show member options. =0 only show Global options. =-1 show global and member options.
   * $member - Member object containing saved member.
   */
  public function addOptionFields($fieldSet, &$memberForm, Member $member = NULL, $showGlobal = NULL, $showPrivate = FALSE)
  {
    // Loop through each field option.
    foreach ($this->fieldSets[$fieldSet] as $group) {
      // Check if field should be displayed -- IF $global is null (meaning display both global and member options) or $global matches the group value AND $public is false (meaning show public and private) or the group is public.
      if ((is_null($showGlobal) || $showGlobal == $group->global) && ($showPrivate || $group->public)) {
        // If the field exists on the form, save its value.
        if (array_key_exists($group->fieldName, $memberForm)) {
          $field = $memberForm[$group->fieldName];
        } else {
          unset($field); // Make sure there isn't a stored value from a previous field.
        }
        // Create a Div for our option field. Avoid having unique ID to simplify JavaScript.
        $memberForm[$group->fieldName] = [
          '#prefix' => '<div class="optionGroup">',
          '#suffix' => '</div>',
        ];
        // If attachind options to existing field, insert field into container.
        if (isset($field)) {
          // Put the original element under the container we added.
          $memberForm[$group->fieldName][$group->fieldName] = $field;
          $memberForm[$group->fieldName][$group->fieldName]['#attributes']['class'][] = 'field-has-options';
        }
        $memberForm[$group->fieldName]['options'] = $group->groupForm($member);
      }
    }
  }

  /**
   * Process field options from submitted member form.
   *
   * Parameters: Event ID, Fieldset, Form vals for member, and reference to array to return option values.
   */
  public function validateOptionFields(&$memberVals, &$optionVals)
  {
    // To do: add checks for mandatory options.
  }

  /**
   * Process field options from submitted member form.
   *
   * Parameters: Event ID, Fieldset, Form vals for member, and reference to array to return option values.
   */
  public function procesOptionFields($fieldset, &$memberVals, $mid, &$memberOptions)
  {
    // Loop through each field on the member form.
    foreach ($this->groups as $group) {
      // Check if fieldname exists in 
      if (array_key_exists($group->fieldName, $memberVals)) {
        foreach ($memberVals[$group->fieldName]['options'] as $optid => $optionInfo) {
          // We only want to store the option if it is already part of the member, or it has been set on the form.
          if (isset($memberOptions[$optid] ) || $optionInfo['option']) {
            $memberOptions[$optid] = new MemberOption($mid, $optid, $optionInfo['option'], $optionInfo['detail']);
          }
        }
        // If the option is attached to a regular field, put the field value back in its parent, so the values look like they would if options hadn't been added.
        if (isset($memberVals[$group->fieldName][$group->fieldName]))
          $memberVals[$group->fieldName] = $memberVals[$group->fieldName][$group->fieldName];
      }
    }
  }

  /**
   * Save field options from submitted member form.
   *
   * Parameters: Member ID, array of option fields.
   */
  public static function insertOptionFields($mid, &$options)
  {
    FieldOptionStorage::insertMemberOptions($mid, $options);
  }

  /**
   * Save field options from previously saved member form.
   *
   * Parameters: Member ID, array of option fields.
   */
  public static function updateOptionFields($mid, &$options)
  {
    FieldOptionStorage::updateMemberOptions($mid, $options);
  }

  /**
   * Get a list of all Options
   *
   * @return array
   *   Options array.
   */
  public function getFieldOptionList()
  {
    // Initialise results array.
    $fieldOptions = [];
    foreach ($this->options as $optid => $option) {
      $fieldOptions[] = ['optid' => $optid, 'option_title' => $option->title];
    }
    return $fieldOptions;
  }
  
  /**
   * Get a list of all Options
   *
   * @return array
   *   Options array.
   */
  public function getFieldOptionGroupedList()
  {
    // Initialise results array.
    $fieldOptions = [];
    foreach ($this->groups as $grpid => $group) {
      $fieldOptions[] = ['grpid' => $grpid, 'group_title' => $group->title];
      foreach ($group->options as $optid => $option) {
        $fieldOptions[] = ['grpid' => $grpid, 'optid' => $optid, 'option_title' => $option->title];
      }
    }
    return $fieldOptions;
  }  

}

