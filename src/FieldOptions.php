<?php

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class FieldOptions {

  public $groups;
  public $options;
  public $memberClasses;

  /**
   * Fetch field options from config.
   *
   * Parameters: Event ID, Config, Fieldset.
   */
  public function __construct($eid, $config = NULL) {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    // Initialise results arrays.
    $this->groups = [];
    $this->options = [];
    $this->memberClasses = [];

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
        // Add the field option into each required class.
        foreach ($fieldOption->inMemberClasses as $class) {
          if (!isset($this->memberClasses[$class])) {
            $this->memberClasses[$class] = [];
          }
          if (!isset($this->memberClasses[$class][$fieldOption->groupId])) {
            $newGroup = $this->groups[$fieldOption->groupId]->cloneGroup();
            $this->memberClasses[$class][$fieldOption->groupId] = $newGroup;
          }
          $this->memberClasses[$class][$fieldOption->groupId]->options[] = $fieldOption;
        }
      }
    }
  }

  /**
   * Static function to get Field Options.
   *
   * Get from cache if possible. If not in cache, process from settings.
   *
   * @param int $eid
   *   The event ID.
   * @param bool $reset
   *   True to reset cached values.
   *
   * @return FieldOptions
   *   Object structure contatining available options.
   */
  public static function getFieldOptions($eid, $reset = FALSE) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $cid = 'simple_conreg:fieldOptions_' . $eid . '_' . $language;

    // Check if field options previously cached.
    if (!$reset && $cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }

    // Not cached, so create new field options, and cache that.
    $fieldOptions = new FieldOptions($eid);
    \Drupal::cache()->set($cid, $fieldOptions);

    return $fieldOptions;
  }

  /**
   * Fetch field option titles from config.
   *
   * @param int $eid
   * @param object $config
   *
   * @return array The array of option titles.
   */
  public static function getFieldOptionsTitles($eid, $config = NULL) {
    // If event config not passed in, load it.
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    // Get the list of options, and put titles in an array.
    $optionTitles = [];
    foreach (explode("\n", $config->get('simple_conreg_options.options')) as $optionLine) {
      [$optid, $grpid, $optionTitle] = explode('|', $optionLine);
      $optionTitles[$optid] = $optionTitle;
    }
    return $optionTitles;
  }

  /**
   * Fetch field options selected by member.
   *
   * @param int @mid The member ID.
   *
   * @return array The list of options.
   */
  public function getMemberOptions($mid) {
    // Get member's options from database.
    $entries = FieldOptionStorage::getMemberOptions($mid);

    // Initialise return array.
    $memberOptions = [];
    // Loop through option groups.
    foreach ($this->groups as $optionGroup) {
      // Loop through options within group.
      foreach ($optionGroup->options as $optid => $option) {
        // Check if option selected by member.
        if (array_key_exists($optid, $entries)) {
          // Get field to attach option to.
          $fieldName = $optionGroup->fieldName;
          // Get title from option group.
          $memberOptions[$fieldName]['title'] = $optionGroup->title;
          // Get option title.
          $memberOptions[$fieldName]['options'][$optid]['option_title'] = $option->title;
          $memberOptions[$fieldName]['options'][$optid]['detail_title'] = $option->detailTitle;
          // Get member's detail from database result.
          $memberOptions[$fieldName]['options'][$optid]['option_detail'] = $entries[$optid]['option_detail'];
        }
      }
    }

    return $memberOptions;
  }

  /**
   * Fetch field options selected by member.
   *
   * @param int $mid
   * @param bool $selected
   *
   * @return array Array of member's options.
   */
  public static function getMemberOptionValues($mid, $selected = TRUE) {
    // Get member's options from database.
    return FieldOptionStorage::getMemberOptions($mid, $selected);
  }

  /**
   * Add field options to member form.
   *
   * @param string $classRef
   *   The member class for the member type (determines which options to show)
   * @param array $memberForm
   *   Form to add to options to.
   * @param Member $member
   *   Member object containing saved member.
   * @param bool $showGlobal
   *   True to show global options.
   * @param bool $showPrivate
   *   True to show private (admin only) options.
   * @param bool $requireMandatory
   */
  public function addOptionFields($classRef, &$memberForm, Member $member = NULL, $showGlobal = NULL, $showPrivate = FALSE, $requireMandatory = TRUE) {
    // Loop through each field option.
    foreach ($this->memberClasses[$classRef] as $group) {
      // Check if field should be displayed -- IF $global is null (meaning display both global and member options) or $global matches the group value AND $public is false (meaning show public and private) or the group is public.
      if ((is_null($showGlobal) || $showGlobal == $group->global) && ($showPrivate || $group->public)) {
        // If the field exists on the form, save its value.
        if (array_key_exists($group->fieldName, $memberForm)) {
          $field = $memberForm[$group->fieldName];
        }
        else {
          // Make sure there isn't a stored value from a previous field.
          unset($field);
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
        $memberForm[$group->fieldName]['options'] = $group->groupForm($member, $requireMandatory);
      }
    }
  }

  /**
   * Process field options from submitted member form.
   *
   * Parameters: Event ID, Fieldset, Form vals for member, and reference to array to return option values.
   */
  public function validateOptionFields(&$memberVals, &$optionVals) {
    // To do: add checks for mandatory options.
  }

  /**
   * Process field options from submitted member form.
   *
   * @param string $classRef
   *   Not currently used - consider removing.
   * @param array &$memberVals
   *   Array of form values for member.
   * @param int $mid
   *   The member ID.
   * @param array &$memberOptions
   *   An array of member options to be saved.
   */
  public function procesOptionFields($classRef, array &$memberVals, $mid, array &$memberOptions) {
    // Loop through each field on the member form.
    foreach ($this->groups as $group) {
      // Check if fieldname exists in submitted values.
      if (!empty($memberVals) && array_key_exists($group->fieldName, $memberVals)) {
        switch ($group->fieldType) {
          case 'checkboxes':
            $this->processOptionCheckBoxes($memberVals[$group->fieldName], $mid, $memberOptions);
            break;

          case 'textfields':
            $this->processOptionTextFields($memberVals[$group->fieldName], $mid, $memberOptions);
            break;

          default:
            $this->processOptionCheckBoxes($memberVals[$group->fieldName], $mid, $memberOptions);
        }
      }
      // If the option is attached to a regular field, put the field value back
      // in its parent, so the values look like they would if options hadn't
      // been added.
      if (isset($memberVals[$group->fieldName][$group->fieldName])) {
        $memberVals[$group->fieldName] = $memberVals[$group->fieldName][$group->fieldName];
      }
    }
  }

  /**
   * Process field options of type CheckBox from submitted member form.
   *
   * Parameters: Form vals for option group, member ID, and reference to array to return option values.
   */
  public function processOptionCheckBoxes(&$groupVals, $mid, &$memberOptions) {
    foreach ($groupVals['options'] as $optid => $optionInfo) {
      // We only want to store the option if it is already part of the member, or it has been set on the form.
      if (isset($memberOptions[$optid]) || $optionInfo['option']) {
        $optionDetail = $optionInfo['detail'] ?? '';
        $memberOptions[$optid] = new MemberOption($mid, $optid, $optionInfo['option'], $optionDetail);
      }
    }
  }

  /**
   * Process field options of type TextField from submitted member form.
   *
   * Parameters: Form vals for option group, member ID, and reference to array to return option values.
   */
  public function processOptionTextFields(&$groupVals, $mid, &$memberOptions) {
    foreach ($groupVals['options'] as $optid => $optionInfo) {
      // We only want to store the option if it is already part of the member, or it has been set on the form.
      if (isset($memberOptions[$optid]) || $optionInfo['detail']) {
        $optionDetail = $optionInfo['detail'] ?? '';
        $optionVal = empty($optionDetail) ? FALSE : TRUE;
        $memberOptions[$optid] = new MemberOption($mid, $optid, $optionVal, $optionDetail);
      }
    }
  }

  /**
   * Save field options from submitted member form.
   *
   * @param int $mid
   *   Member ID.
   * @param array $options
   *   Array of option fields.
   */
  public static function insertOptionFields($mid, array &$options) {
    FieldOptionStorage::insertMemberOptions($mid, $options);
  }

  /**
   * Save field options from previously saved member form.
   *
   * @param int $mid
   *   Member ID.
   * @param array $options
   *   Array of option fields.
   */
  public static function updateOptionFields($mid, array &$options) {
    FieldOptionStorage::updateMemberOptions($mid, $options);
  }

  /**
   * Get a list of all Options.
   *
   * @return array
   *   Options array.
   */
  public function getFieldOptionList(): array {
    // Initialise results array.
    $fieldOptions = [];
    foreach ($this->options as $optid => $option) {
      $fieldOptions[] = ['optid' => $optid, 'option_title' => $option->title];
    }
    return $fieldOptions;
  }

  /**
   * Get a list of all Options.
   *
   * @return array
   *   Options array.
   */
  public function getFieldOptionGroupedList(): array {
    // Initialise results array.
    $fieldOptions = [];
    foreach ($this->groups as $grpid => $group) {
      $fieldOptions[] = ['grpid' => $grpid, 'group_title' => $group->title];
      foreach ($group->options as $optid => $option) {
        $fieldOptions[] = [
          'grpid' => $grpid,
          'optid' => $optid,
          'option_title' => $option->title,
        ];
      }
    }
    return $fieldOptions;
  }

}
