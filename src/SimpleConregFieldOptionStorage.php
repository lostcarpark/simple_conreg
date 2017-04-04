<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregFieldOptionStorage
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregFieldOptionStorage {

  /*
   * Function to return a list of members and communications methods for integration with Simplenews module.
   */
  public static function getFieldOptions($eid, $fieldset) {
    // Run this query: select email, min(communication_method) from simple_conreg_members where email is not null and email<>'' and communication_method is not null group by email;
    $select = db_select('simple_conreg_option_groups', 'g');
    $select->join('simple_conreg_options', 'o', 'g.grpid=o.grpid');
    $select->join('simple_conreg_fieldset_options', 'f', 'o.optid=f.optid');
    // Select these specific fields for the output.
    $select->addField('g', 'grpid');
    $select->addField('g', 'field_name');
    $select->addField('g', 'group_title');
    $select->addField('o', 'optid');
    $select->addField('o', 'option_title');
    $select->addField('o', 'detail_title');
    $select->addField('o', 'detail_is_required');
    $select->condition('g.eid', $eid);
    if (isset($fieldset))
      $select->condition("f.fieldset", $fieldset);
    $select->orderBy('g.grpid');
    
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
    
    $fieldOptions = [];
    foreach ($entries as $entry) {
      $fieldOptions[$entry['field_name']]['title'] = $entry['group_title'];
      $fieldOptions[$entry['field_name']]['options'][$entry['optid']]['option'] = $entry['option_title'];
      $fieldOptions[$entry['field_name']]['options'][$entry['optid']]['detail'] = $entry['detail_title'];
      $fieldOptions[$entry['field_name']]['options'][$entry['optid']]['required'] = $entry['detail_is_required'];
    }
    //dpm($fieldOptions);

    return $fieldOptions;
  }
  
  public static function insertMemberOptions($mid, $options) {
    foreach ($options as $optid=>$option) {
      // Only save if option set.
      if ($option['option']) {
        db_insert('simple_conreg_member_options')
          ->fields([
            'mid' => $mid,
            'optid' => $optid,
            'is_selected' => $option['option'],
            'option_detail' => $option['detail'],
          ])
          ->execute();
      }
    }
  }

}

