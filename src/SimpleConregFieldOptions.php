<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregFieldOptions.
 */

namespace Drupal\simple_conreg;

/**
 * List options for Simple Convention Registration.
 */
class SimpleConregFieldOptions {


  /**
   * Fetch field options from config.
   *
   * Parameters: Event ID, Config, Fieldset.
   */
  private static function parseFieldOptions($eid, $config)
  {
    if (is_null($config)) {
      $config = SimpleConregConfig::getConfig($eid);
    }

    // Initialise results arrays.
    $fieldGroups = [];
    $fieldOptions = [];

    // Get options and split into lines.
    $optionGroups = explode("\n", $config->get('simple_conreg_options.option_groups')); // One option group per line.
    $options = explode("\n", $config->get('simple_conreg_options.options')); // One option group per line.

    foreach ($optionGroups as $group) {
      if (!empty($group)) {
        // Get 
        $groupFields = array_pad(explode('|', $group), 3, '');
        list($grpid, $fieldName, $groupTitle) = $groupFields;
        $fieldGroups[$grpid] = ['field' => $fieldName, 'title' => $groupTitle, 'options' => []];
      }
    }

    foreach ($options as $option) {
      if (!empty($option)) {
        $optionFields = array_pad(explode('|', $option), 8, '');
        list($optid, $grpid, $optionTitle, $detailTitle, $required, $weight, $belongsIn, $mustSelect) = $optionFields;
        $fieldSets = [];
        foreach (explode(',', trim($belongsIn)) as $fieldSet) {
          //if (!empty($fieldSet)) {
            $fieldSets[] = $fieldSet;
          //}
        }
        $fieldOption = ['grpid' => $grpid, 'title' => $optionTitle, 'detail' => $detailTitle, 'required' => $required, 'weight' => $weight, 'fieldSets' => $fieldSets, 'mustSelect' => $mustSelect]; 
        $fieldOptions[$optid] = $fieldOption;
        $fieldGroups[$grpid]['options'][$optid] = $fieldOption;
      }
    }
    return ['groups' => $fieldGroups, 'options' => $fieldOptions];
  }
  

  /**
   * Fetch field options from config.
   *
   * Parameters: Event ID, Config, Fieldset.
   */
  public static function getFieldOptions($eid, $config, $fieldset) 
  {
    $options = self::parseFieldOptions($eid, $config);

    // Initialise results array.
    $fieldOptions = [];

    foreach ($options['groups'] as $optionGroup) {
      foreach ($optionGroup['options'] as $optid => $option) {
        if (in_array($fieldset, $option['fieldSets'])) { // Only add to fieldOptions if belonging to requested fieldset.
          $fieldName = $optionGroup['field'];
          $fieldOptions[$fieldName]['title'] = $optionGroup['title'];
          $fieldOptions[$fieldName]['options'][$optid]['option'] = $option['title'];
          $fieldOptions[$fieldName]['options'][$optid]['detail'] = $option['detail'];
          $fieldOptions[$fieldName]['options'][$optid]['required'] = $option['required'];
          $fieldOptions[$fieldName]['options'][$optid]['mustSelect'] = $option['mustSelect'];
        }
      }
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
  public static function getMemberOptions($eid, $config, $mid)
  {
    // Get all options.
    $options = self::parseFieldOptions($eid, $config);

    // Get member's options from database.
    $entries = SimpleConregFieldOptionStorage::getMemberOptions($mid);

    $memberOptions = []; // Initialise return array.
    // Loop through option groups.
    foreach ($options['groups'] as $optionGroup) {
      // Loop through options within group.
      foreach ($optionGroup['options'] as $optid => $option) {
        // Check if option selected by member.
        if (array_key_exists($optid, $entries)) {
          $fieldName = $optionGroup['field']; // Get field to attach option to.
          $memberOptions[$fieldName]['title'] = $optionGroup['title']; // Get title from option group.
          $memberOptions[$fieldName]['options'][$optid]['option_title'] = $option['title']; // Get option title.
          $memberOptions[$fieldName]['options'][$optid]['detail_title'] = $option['detail'];
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
    return SimpleConregFieldOptionStorage::getMemberOptions($mid, $selected);
  }
  
  /**
   * Add field options to member form.
   *
   * Parameters: Event ID, Fieldset, Form to add to.
   */
  public static function addOptionFields($eid, $fieldset, &$memberForm, &$memberVals, &$optionCallbacks, $callback, $memberNo = NULL, Member $member = NULL)
  {
    // Read the option field from the database.
    $fieldOptions = self::getFieldOptions($eid, NULL, $fieldset);
    // Loop through each field option.
    foreach ($fieldOptions as $key=>$fieldOption) {
      // If the field exists on the form, save its value.
      if (array_key_exists($key, $memberForm)) {
        $field = $memberForm[$key];
      } else {
        unset($field);
      }
      // Get ID for group container DIV.
      if (isset($memberNo))
        $id = 'member-'.$memberNo.'-'.$key;
      else
        $id = $key;
      $memberForm[$key] = [
        '#prefix' => '<div id="'.$id.'">',
        '#suffix' => '</div>',
      ];
      // If attachind options to existing field, insert field into container.
      if (isset($field)) {
        // If option is linked to a checkbox field, add Ajax property to checkbox.
        if ($field['#type'] == 'checkbox') {
          $field['#ajax'] = [
            'wrapper' => $id,
            'callback' => $callback,
            'event' => 'change',
          ];
        }
        // Put the original element under the container we added.
        $memberForm[$key][$key] = $field;
        // Store the details in a keyed array for Ajax callbacks.
        if (isset($memberNo))
          $callbackKey = "members[member$memberNo][$key][$key]";
        else
          $callbackKey = "member[$key][$key]";
        $optionCallbacks[$callbackKey] = ['group', $memberNo, $key];
      }
      // If linked field is not a checkbox, or it is and it's been checked, display the options as checkboxes.
      if (!isset($field) || $field['#type'] != 'checkbox' || $memberVals[$key][$key] || (!isset($memberVals[$key][$key]) && isset($member) && isset($member->$key) && $member->$key)) {
        $memberForm[$key]['options'] = [
          '#type' => 'fieldset',
          '#title' => t($fieldOptions[$key]['title']),
        ];
        foreach ($fieldOptions[$key]['options'] as $optid=>$optionDetails) {
          if (isset($memberNo))
            $id = 'member-'.$memberNo.'-option-'.$optid;
          else
            $id = 'option-'.$optid;
          $memberForm[$key]['options'][$optid] = [
            '#type' => 'checkbox',
            '#title' => $optionDetails['option'],
          ];
          if ($optionDetails['mustSelect']) {
            $memberForm[$key]['options'][$optid]['#required'] = TRUE;
          }
          // Check if saved value.
          if (!is_null($member) && is_array($member->options) && array_key_exists($optid, $member->options))
            $memberForm[$key]['options'][$optid]['#default_value'] = TRUE;
          // Check if option can have detail.
          if (isset($optionDetails['detail']) && !empty($optionDetails['detail'])) {
            // Add Ajax property to field to display option when selected.
            $memberForm[$key]['options'][$optid]['#ajax'] = [
              'wrapper' => $id,
              'callback' => $callback,
              'event' => 'change',
            ];
            // Add container placeholder for detail.
            $memberForm[$key]['options']['container_'.$optid] = [
              '#prefix' => '<div id="'.$id.'">',
              '#suffix' => '</div>',
            ];
            // If option selected, put detail in placeholder.
            if ($memberVals[$key]['options'][$optid] || (!isset($memberVals[$key]['options'][$optid]) && !is_null($member) && is_array($member->options) && isset($member->options[$optid]))) {
              $memberForm[$key]['options']['container_'.$optid]['detail_'.$optid] = [
                '#type' => 'textfield',
                '#title' => $optionDetails['detail'],
              ];
              if (!is_null($member) && is_array($member->options) && array_key_exists($optid, $member->options))
                $memberForm[$key]['options']['container_'.$optid]['detail_'.$optid]['#default_value'] = $member->options[$optid]['option_detail'];
              if ($optionDetails['required']) {
                $memberForm[$key]['options']['container_'.$optid]['detail_'.$optid]['#required'] = TRUE;
              }
            }
          }
          if (isset($memberNo))
            $callbackKey = "members[member$memberNo][$key][options][$optid]";
          else
            $callbackKey = "member[$key][options][$optid]";
          $optionCallbacks[$callbackKey] = ['detail', $memberNo, $key, $optid];
        }
      }
    }
  }

  /**
   * Process field options from submitted member form.
   *
   * Parameters: Event ID, Fieldset, Form vals for member, and reference to array to return option values.
   */
  public static function validateOptionFields($eid, $fieldset, &$memberVals, &$optionVals)
  {
    // Read the option field from the database.
    $fieldOptions = self::getFieldOptions($eid, NULL, $fieldset);
  }

  /**
   * Process field options from submitted member form.
   *
   * Parameters: Event ID, Fieldset, Form vals for member, and reference to array to return option values.
   */
  public static function procesOptionFields($eid, $fieldset, &$memberVals, &$optionVals)
  {
    // Read the option field from the database.
    $fieldOptions = self::getFieldOptions($eid, NULL, $fieldset);
    // Loop through each field on the member form.
    foreach ($memberVals as $key=>$fieldVal) {
      if (array_key_exists($key, $fieldOptions)) {
        // Put the field value back in its parent, so the values look like they would if options hadn't been added.
        if (isset($fieldVal[$key]))
          $memberVals[$key] = $fieldVal[$key];
        foreach ($fieldOptions[$key]['options'] as $optid=>$optionDetails) {
          if (isset($fieldVal['options'][$optid]))
            $optionVals[$optid] = [
              'option' => $fieldVal['options'][$optid],
              'detail' => (isset($fieldVal['options']['container_'.$optid]['detail_'.$optid]) ? $fieldVal['options']['container_'.$optid]['detail_'.$optid] : ''),
            ];
        }
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
    SimpleConregFieldOptionStorage::insertMemberOptions($mid, $options);
  }

  /**
   * Save field options from previously saved member form.
   *
   * Parameters: Member ID, array of option fields.
   */
  public static function updateOptionFields($mid, &$options)
  {
    SimpleConregFieldOptionStorage::updateMemberOptions($mid, $options);
  }

  /**
   * Get a list of all Options
   *
   * @return array
   *   Options array.
   */
  public static function getFieldOptionList($eid, $config=NULL)
  {
    $options = self::parseFieldOptions($eid, $config);

    // Initialise results array.
    $fieldOptions = [];
    foreach ($options['options'] as $optid => $option) {
      $fieldOptions[] = ['optid' => $optid, 'option_title' => $option['title']];
    }
    return $fieldOptions;
  }
  
  /**
   * Get a list of all Options
   *
   * @return array
   *   Options array.
   */
  public static function getFieldOptionGroupedList($eid, $config=NULL)
  {
    $options = self::parseFieldOptions($eid, $config);

    // Initialise results array.
    $fieldOptions = [];
    foreach ($options['groups'] as $grpid => $group) {
      $fieldOptions[] = ['grpid' => $grpid, 'group_title' => $group['title']];
      foreach ($group['options'] as $optid => $option) {
        $fieldOptions[] = ['grpid' => $grpid, 'optid' => $optid, 'option_title' => $option['title']];
      }
    }
    return $fieldOptions;
  }  

  /**
   * Get permissions for ConReg field options.
   *
   * @return array
   *   Permissions array.
   */
  public static function permissions()
  {
    $permissions = [];

    $events = SimpleConregEventStorage::eventOptions();
    foreach ($events as $event) {
      foreach (self::getFieldOptionList($event['eid']) as $option) {
        $permissions += [
          'view field option ' . $option['optid'] . ' event ' . $event['eid'] => [
            'title' => t('View data for field option %option for event %event', array('%option' => $option['option_title'], '%event' => $event['event_name'])),
          ]
        ];
      }
    }

    return $permissions;
  }

}

