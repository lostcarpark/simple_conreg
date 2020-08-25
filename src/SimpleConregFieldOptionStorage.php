<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregFieldOptionStorage
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregFieldOptionStorage {


  public static function insertMemberOptions($mid, $options)
  {
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
  public static function getMemberOptions($eid, $mid)
  {
    $select = db_select('conreg_member_options', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'optid');
    $select->addField('m', 'option_detail');
    $select->condition('m.mid', $mid);
    $select->condition('m.is_selected', 1);
    
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
    
    $memberOptions = [];
    foreach ($entries as $entry) {
      $memberOptions[$entry['optid']] = $entry['option_detail'];
    }

    return $memberOptions;
  }


  /*
   * Function to return a list of members who have ticked specified option.
   *
   * select m.first_name, m.last_name, m.email, o.is_selected, o.option_detail
   * from conreg_members m inner join conreg_member_options o on m.mid=o.mid
   * where m.eid=1 and m.is_paid=1 and m.is_deleted=0 and o.optid=1;
   */
  public static function adminOptionMemberListLoad($eid, $optid)
  {
    $select = db_select('conreg_members', 'm');
    $select->join('conreg_member_options', 'o', 'm.mid=o.mid');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('o', 'optid');
    $select->addField('o', 'is_selected');
    $select->addField('o', 'option_detail');
    $select->condition('m.eid', $eid);
    if (is_array($optid)) {
      $or_group = $select->orConditionGroup();
      foreach ($optid as $curOpt)
        $or_group->condition('o.optid', $curOpt);
      $select->condition($or_group);
    }
    else {
      $select->condition('o.optid', $optid);
    }
    $select->condition("is_paid", TRUE); //Only include members have paid.
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->orderby('m.mid', 'ASC');    
    $select->orderby('o.optid', 'ASC');    

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}

