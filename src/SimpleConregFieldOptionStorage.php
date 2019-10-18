<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregFieldOptionStorage
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregFieldOptionStorage {

  /*
   * Function to return a list of options to add to form display.
   */
  public static function getFieldOptions($eid, $fieldset) {
    // Run this query:
    // select g.grpid, g.field_name, g.group_title, o.optid, o.option_title, o.detail_title, o.detail_is_required
    // from conreg_option_groups g inner join conreg_options o on g.grpid=o.grpid inner join conreg_fieldset_options f on o.optid=f.optid
    // where g.eid=1 and f.fieldset=1 order by g.grpid, o.weight;
    $select = db_select('conreg_option_groups', 'g');
    $select->join('conreg_options', 'o', 'g.grpid=o.grpid');
    $select->join('conreg_fieldset_options', 'f', 'o.optid=f.optid');
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
    $select->orderBy('o.weight');
    
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
    
    $fieldOptions = [];
    foreach ($entries as $entry) {
      $fieldOptions[$entry['field_name']]['title'] = $entry['group_title'];
      $fieldOptions[$entry['field_name']]['options'][$entry['optid']]['option'] = $entry['option_title'];
      $fieldOptions[$entry['field_name']]['options'][$entry['optid']]['detail'] = $entry['detail_title'];
      $fieldOptions[$entry['field_name']]['options'][$entry['optid']]['required'] = $entry['detail_is_required'];
    }

    return $fieldOptions;
  }
  

  public static function insertMemberOptions($mid, $options) {
    foreach ($options as $optid=>$option) {
      // Only save if option set.
      if ($option['option']) {
        db_insert('conreg_member_options')
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
  
  /*
   * Function to return a list of options for specified member.
   */
  public static function getMemberOptions($eid, $mid) {
    // Run this query:
    // select g.field_name, g.group_title, o.option_title, o.detail_title, m.option_detail
    // from conreg_option_groups g inner join conreg_options o on g.grpid=o.grpid inner join conreg_member_options m on o.optid=m.optid
    // where g.eid=1 and m.mid=97 and m.is_selected=1 order by g.grpid, o.weight;
    $select = db_select('conreg_option_groups', 'g');
    $select->join('conreg_options', 'o', 'g.grpid=o.grpid');
    $select->join('conreg_member_options', 'm', 'o.optid=m.optid');
    // Select these specific fields for the output.
    $select->addField('g', 'field_name');
    $select->addField('g', 'group_title');
    $select->addField('o', 'optid');
    $select->addField('o', 'option_title');
    $select->addField('o', 'detail_title');
    $select->addField('m', 'option_detail');
    $select->condition('g.eid', $eid);
    $select->condition('m.mid', $mid);
    $select->condition('m.is_selected', 1);
    if (isset($fieldset))
      $select->condition("f.fieldset", $fieldset);
    $select->orderBy('g.grpid');
    $select->orderBy('o.weight');
    
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
    
    $memberOptions = [];
    foreach ($entries as $entry) {
      $memberOptions[$entry['field_name']]['title'] = $entry['group_title'];
      $memberOptions[$entry['field_name']]['options'][$entry['optid']]['option_title'] = $entry['option_title'];
      $memberOptions[$entry['field_name']]['options'][$entry['optid']]['detail_title'] = $entry['detail_title'];
      $memberOptions[$entry['field_name']]['options'][$entry['optid']]['option_detail'] = $entry['option_detail'];
    }

    return $memberOptions;
  }

  /*
   * Function to return a list of members who have ticked specified option.
   *
   * select * from conreg_options;
   */
  public static function adminOptionListLoad() {
    $select = db_select('conreg_options', 'o');
    // Select these specific fields for the output.
    $select->addField('o', 'optid');
    $select->addField('o', 'option_title');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /*
   * Function to return a list of members who have ticked specified option.
   *
   * select m.first_name, m.last_name, m.email, o.is_selected, o.option_detail
   * from conreg_members m inner join conreg_member_options o on m.mid=o.mid
   * where m.eid=1 and m.is_paid=1 and m.is_deleted=0 and o.optid=1;
   */
  public static function adminOptionMemberListLoad($eid, $optid) {
    $select = db_select('conreg_members', 'm');
    $select->join('conreg_member_options', 'o', 'm.mid=o.mid');
    // Select these specific fields for the output.
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('o', 'is_selected');
    $select->addField('o', 'option_detail');
    $select->condition('m.eid', $eid);
    $select->condition('o.optid', $optid);
    $select->condition("is_paid", TRUE); //Only include members have paid.
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}

