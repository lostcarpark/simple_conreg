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
   * Add field options to member form.
   *
   * Parameters: Event ID, Fieldset, Form to add to.
   */
  public static function addOptionFields($eid, $fieldset, &$memberForm, &$memberVals, &$optionCallbacks, $callback, $memberNo = NULL) {
    // Read the option field from the database.
    $fieldOptions = SimpleConregFieldOptionStorage::getFieldOptions($eid, $fieldset);
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
        $callbackKey = "members[member$memberNo][$key][$key]";
        $optionCallbacks[$callbackKey] = ['group', $memberNo, $key];
      }
      // If linked field is not a checkbox, or it is and it's been checked, display the options as checkboxes.
      if (!isset($field) || $field['#type'] != 'checkbox' || $memberVals[$key][$key]) {
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
            '#ajax' => [
              'wrapper' => $id,
              'callback' => $callback,
              'event' => 'change',
            ],
          ];
          $memberForm[$key]['options']['container_'.$optid] = [
            '#prefix' => '<div id="'.$id.'">',
            '#suffix' => '</div>',
          ];
          if (!empty($optionDetails['detail']) && $memberVals[$key]['options'][$optid]) {
            $memberForm[$key]['options']['container_'.$optid]['detail_'.$optid] = [
              '#type' => 'textfield',
              '#title' => $optionDetails['detail'],
            ];
            if ($optionDetails['required']) {
              $memberForm[$key]['options']['container_'.$optid]['detail_'.$optid]['#required'] = TRUE;
            }
          }
          $callbackKey = "members[member$memberNo][$key][options][$optid]";
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
  public static function procesOptionFields($eid, $fieldset, &$memberVals, &$optionVals) {
    // Read the option field from the database.
    $fieldOptions = SimpleConregFieldOptionStorage::getFieldOptions($eid, $fieldset);
    // Loop through each field on the member form.
    foreach ($memberVals as $key=>$fieldVal) {
      if (array_key_exists($key, $fieldOptions)) {
        // Put the field value back in its parent, so the values look like they would if options hadn't been added.
        $memberVals[$key] = $fieldVal[$key];
        foreach ($fieldOptions[$key]['options'] as $optid=>$optionDetails) {
          $optionVals[$optid] = [
            'option' => $fieldVal['options'][$optid],
            'detail' => $fieldVal['options']['container_'.$optid]['detail_'.$optid],
          ];
        }
      }
    }
  }

  /**
   * Save field options from submitted member form.
   *
   * Parameters: Event ID, Fieldset, Form vals for member, and reference to array to return option values.
   */
  public static function insertOptionFields($mid, $options) {
    SimpleConregFieldOptionStorage::insertMemberOptions($mid, $options);
  }

}
