<?php

namespace Drupal\simple_conreg;

use Drupal\Core\Database\Query\SelectInterface;

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregStorage.
 */

/**
 * Storage class for conreg_members.
 */
class SimpleConregStorage {

  /**
   * Save an entry in the database.
   *
   * The underlying DBTNG function is $connection->insert().
   *
   * Exception handling is shown in this example. It could be simplified
   * without the try/catch blocks, but since an insert will throw an exception
   * and terminate your application if the exception is not handled, it is best
   * to employ try/catch.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int
   *   The number of updated rows.
   *
   * @throws \Exception
   *   When the database insert fails.
   *
   * @see $connection->insert()
   */
  public static function insert(array $entry): int|NULL {
    $connection = \Drupal::database();
    $return_value = NULL;
    try {
      $return_value = $connection->insert('conreg_members')
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('$connection->insert failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]), 'error');
    }
    return $return_value;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   *
   * @see $connection->update()
   */
  public static function update(array $entry): int|NULL {
    $connection = \Drupal::database();
    try {
      // $connection->update()...->execute() returns the number of rows updated.
      $count = $connection->update('conreg_members')
        ->fields($entry)
        ->condition('mid', $entry['mid'])
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('$connection->update failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]), 'error');
    }
    return $count;
  }

  /**
   * Update an entry in the database, using the lead_mid as key.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   *
   * @see $connection->update()
   */
  public static function updateByLeadMid(array $entry): int|NULL {
    $connection = \Drupal::database();
    try {
      // $connection->update()...->execute() returns the number of rows updated.
      $count = $connection->update('conreg_members')
        ->fields($entry)
        ->condition('lead_mid', $entry['lead_mid'])
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('$connection->update failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]), 'error');
    }
    return $count;
  }

  /**
   * Delete an entry from the database.
   *
   * @param array $entry
   *   An array containing at least the person identifier 'pid' element of the
   *   entry to delete.
   *
   * @see $connection->delete()
   */
  public static function delete(array $entry): void {
    $connection = \Drupal::database();
    $connection->delete('conreg_members')
      ->condition('mid', $entry['mid'])
      ->execute();
  }

  /**
   * Read from the database using a filter array.
   *
   * @param array $entry
   *   Array of fields to filter on.
   *
   * @return array|bool
   *   Values read from conreg_members or false if no result.
   */
  public static function load(array $entry = []):array|bool {
    $connection = \Drupal::database();
    // Read all fields from the conreg_members table.
    $select = $connection->select('conreg_members', 'members');
    $select->fields('members');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Unless mid specified, exclude deleted members.
    if (!array_key_exists("mid", $entry)) {
      $select->condition("is_deleted", FALSE);
    }
    // Return the result in associative array format.
    return $select->execute()->fetchAssoc();
  }

  /**
   * Read from the database and return multiple rows using a filter array.
   *
   * @param array $entry
   *   Array of fields to filter on.
   *
   * @return array
   *   Associative array of fields.
   */
  public static function loadAll(array $entry = []): array {
    $connection = \Drupal::database();
    // Read all fields from the conreg_members table.
    $select = $connection->select('conreg_members', 'members');
    $select->fields('members');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Unless mid specified, only fetch members who don't have deleted flag set.
    if (!array_key_exists("mid", $entry)) {
      $select->condition("is_deleted", FALSE);
    }
    // Return the result in associative array format.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /**
   * Check if valid mid and key combo.
   *
   * @param int $mid
   *   Member ID.
   * @param int $key
   *   Key value.
   *
   * @return bool
   *   TRUE if key checks out.
   */
  public static function checkMemberKey(int $mid, int $key): bool {
    $connection = \Drupal::database();
    // Read all fields from the conreg_members table.
    $select = $connection->select('conreg_members', 'members');
    $select->fields('members');
    $select->condition("mid", $mid);
    $select->condition("random_key", $key);
    // Only include members who aren't deleted.
    $select->condition("is_deleted", FALSE);

    // Return the result in object format.
    if ($select->countQuery()->execute()->fetchField() > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Load the public members list.
   *
   * @param int $eid
   *   The event ID.
   *
   * @return array
   *   Array of members.
   */
  public static function adminPublicListLoad($eid) {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'badge_type');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'display');
    $select->addField('m', 'country');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_approved', 1);
    // Only include members who aren't deleted.
    $select->condition("is_deleted", FALSE);
    $select->orderBy('m.member_no');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /**
   * Add conditions to select for Admin page.
   *
   * @param int $eid
   *   The event ID.
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The select object to add the conditions to.
   * @param string $condition
   *   Text to select the condition to add.
   * @param string|null $search
   *   Entry for text searches.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Modified selection criteris.
   */
  private static function adminMemberListCondition(int $eid, SelectInterface $select, string $condition, string|NULL $search): SelectInterface {
    $connection = \Drupal::database();
    $select->condition('m.eid', $eid);
    switch ($condition) {
      case 'approval':
        $select->condition('m.is_paid', 1);
        $select->condition('m.is_approved', 0);
        $select->condition("m.is_deleted", FALSE);
        break;

      case 'approved':
        $select->condition('m.is_paid', 1);
        $select->condition('m.is_approved', 1);
        $select->condition("m.is_deleted", FALSE);
        break;

      case 'unpaid':
        $select->condition('m.is_paid', 0);
        $select->condition("m.is_deleted", FALSE);
        break;

      case 'all':
        // All members.
        $select->condition("m.is_deleted", FALSE);
        break;

      case 'custom':
        $words = explode(' ', trim($search));
        foreach ($words as $word) {
          if ($word != '') {
            // Escape search word to prevent dangerous characters.
            $esc_word = '%' . $connection->escapeLike($word) . '%';
            $likes = $select->orConditionGroup()
              ->condition('m.member_no', $esc_word, 'LIKE')
              ->condition('m.first_name', $esc_word, 'LIKE')
              ->condition('m.last_name', $esc_word, 'LIKE')
              ->condition('m.badge_name', $esc_word, 'LIKE')
              ->condition('m.email', $esc_word, 'LIKE')
              ->condition('m.payment_id', $esc_word, 'LIKE')
              ->condition('m.comment', $esc_word, 'LIKE');
            $select->condition($likes);
          }
        }
        // Only include members who aren't deleted.
        $select->condition("m.is_deleted", FALSE);
    }
    return $select;
  }

  /**
   * Load the member list for the admin page.
   *
   * @param int $eid
   *   Event ID.
   * @param string $condition
   *   Type of list from drop-down list.
   * @param string|null $search
   *   Search string for custom search.
   * @param int $page
   *   Page number.
   * @param int $pageSize
   *   Rows per page.
   * @param string $order
   *   Field to order by.
   * @param string $direction
   *   Whether sort is ascending or descending.
   *
   * @return array
   *   Associative array of members.
   */
  public static function adminMemberListLoad(int $eid,
    string $condition,
    string|NULL $search,
    int $page = 1,
    int $pageSize = 10,
    string $order = 'm.mid',
    string $direction = 'ASC'): array {

    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'display');
    $select->addField('m', 'member_type');
    $select->addField('m', 'days');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'is_approved');
    $select->addField('m', 'member_no');
    $select->addField('m', 'phone');
    $select->addField('m', 'join_date');
    $select->addField('m', 'lead_mid');
    $select->addExpression("concat(l.first_name, ' ', l.last_name)", 'registered_by');
    $select->addField('l', 'email', 'lead_email');
    $select->join('conreg_members', 'l', 'm.lead_mid = l.mid');
    // Add selection criteria.
    $select = SimpleConregStorage::adminMemberListCondition($eid, $select, $condition, $search);
    // Sort by specified field and direction.
    $select->orderby($order, $direction);
    $select->orderby('m.mid', $direction);
    // Make sure we only get items 0-49, for scalability reasons.
    $select->range(($page - 1) * $pageSize, $pageSize);

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Run query to get total count.
    $select = $connection->select('conreg_members', 'm');
    $select->addField('m', 'mid');
    $select->condition('m.eid', $eid);
    $select = SimpleConregStorage::adminMemberListCondition($eid, $select, $condition, $search);
    $count = $select->countQuery()->execute()->fetchField();
    $pages = (int) (($count - 1) / $pageSize) + 1;

    return [$pages, $entries];
  }

  /**
   * Apply conditions to select for check-in page.
   *
   * @param int $eid
   *   Event ID.
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The select object to add the conditions to.
   * @param string $search
   *   Text to search for.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Modified selection criteria.
   */
  private static function adminMemberCheckInListCondition(int $eid, SelectInterface $select, string $search): SelectInterface {
    $connection = \Drupal::database();
    $select->condition('m.eid', $eid);
    $words = explode(' ', trim($search));
    foreach ($words as $word) {
      if ($word != '') {
        // Escape search word to prevent dangerous characters.
        $esc_word = '%' . $connection->escapeLike($word) . '%';
        $likes = $select->orConditionGroup()
          ->condition('m.member_no', $esc_word, 'LIKE')
          ->condition('l.member_no', $esc_word, 'LIKE')
          ->condition('m.first_name', $esc_word, 'LIKE')
          ->condition('l.first_name', $esc_word, 'LIKE')
          ->condition('m.last_name', $esc_word, 'LIKE')
          ->condition('l.last_name', $esc_word, 'LIKE')
          ->condition('m.badge_name', $esc_word, 'LIKE')
          ->condition('l.badge_name', $esc_word, 'LIKE')
          ->condition('m.email', $esc_word, 'LIKE')
          ->condition('l.email', $esc_word, 'LIKE')
          ->condition('m.payment_id', $esc_word, 'LIKE')
          ->condition('l.payment_id', $esc_word, 'LIKE')
          ->condition('m.comment', $esc_word, 'LIKE')
          ->condition('l.comment', $esc_word, 'LIKE');
        $select->condition($likes);
      }
    }
    $select->condition('m.is_paid', 1);
    $select->condition("m.is_deleted", FALSE);

    return $select;
  }

  /**
   * Get member list for check in listing.
   *
   * @param int $eid
   *   The event ID.
   * @param string $search
   *   The text to search for.
   *
   * @return array
   *   Associative array of members.
   */
  public static function adminMemberCheckInListLoad(int $eid, string $search): array {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'member_type');
    $select->addField('m', 'days');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'comment');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'is_checked_in');
    $select->addExpression("concat(l.first_name, ' ', l.last_name)", 'registered_by');
    $select->join('conreg_members', 'l', 'm.lead_mid = l.mid');
    // Add selection criteria.
    $select = SimpleConregStorage::adminMemberCheckInListCondition($eid, $select, $search);
    // Sort by specified field and direction.
    $select->orderby('m.mid', 'ASC');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /*
   * Get unpaid member list for bottom pane of check in listing.
   */
  public static function adminMemberUnpaidListLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'member_type');
    $select->addField('m', 'days');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'comment');
    $select->addField('m', 'display');
    $select->addField('m', 'communication_method');
    $select->addField('m', 'member_total');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'is_checked_in');
    // Add selection criteria.
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 0);
    $select->condition('m.is_checked_in', 0);
    $select->condition("m.is_deleted", FALSE); //Only include members who aren't deleted.
    // Sort by specified field and direction.
    $select->orderby('m.mid', 'ASC');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /*
   * Get member list for Member Portal listing.
   */
  public static function adminMemberPortalListLoad($eid, $email, $is_paid = NULL)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'member_type');
    $select->addField('m', 'days');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'comment');
    $select->addField('m', 'member_price');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'is_checked_in');
    $select->addExpression("concat(l.first_name, ' ', l.last_name)", 'registered_by');
    $select->join('conreg_members', 'l', 'm.lead_mid = l.mid');
    // Add selection criteria.
    $select->condition('m.eid', $eid);
    $likes = $select->orConditionGroup()
      ->condition('m.email', $email, 'LIKE')
      ->condition('l.email', $email, 'LIKE');
    $select->condition($likes);
    // If "is paid" specified, add it as a condition.
    if (!is_null($is_paid)) {
      $select->condition('m.is_paid', $is_paid);
    }
    $select->condition("m.is_deleted", FALSE); //Only include members who aren't deleted.
    // Sort by specified field and direction.
    $select->orderby('m.mid', 'ASC');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }


  public static function loadAllMemberNos($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'member_no');
    $select->addField('m', 'is_approved');
    $select->addField('m', 'is_checked_in');
    $select->condition('m.eid', $eid);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $members = array();
    //Turn numeric array into associative array by mid.
    foreach ($entries as $member) {
      $members[$member["mid"]] = $member;
    }

    return $members;
  }

  public static function loadMaxMemberNo($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addExpression('MAX(m.member_no)');
    $select->condition('m.eid', $eid);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->condition('m.is_approved', 1);
    // Make sure we only get items 0-49, for scalability reasons.
    //$select->range(0, 50);

    $max = $select->execute()->fetchField();
    if (empty($max)) {
      $max = 0;
    }

    return $max;
  }


  public static function adminPaidMemberListLoad($eid, $direction = 'ASC', $order = 'm.member_no')
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_type');
    $select->addField('m', 'days');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'street');
    $select->addField('m', 'street2');
    $select->addField('m', 'city');
    $select->addField('m', 'county');
    $select->addField('m', 'postcode');
    $select->addField('m', 'country');
    $select->addField('m', 'phone');
    $select->addField('m', 'birth_date');
    $select->addField('m', 'age');
    $select->addField('m', 'display');
    $select->addField('m', 'communication_method');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'member_price');
    $select->addField('m', 'comment');
    $select->addField('m', 'is_approved');
    $select->addField('m', 'mid');
    $select->addExpression('from_unixtime(join_date)', 'joined');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->orderby($order, $direction);
    // Make sure we only get items 0-49, for scalability reasons.
    //$select->range(0, 50);

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /**
   * Return a list of member details for badge printing.
   *
   * @param int $eid
   *   The event identifier.
   * @param int $max_num_badges
   *   Maximum number of badges to return.
   * @param array $options
   *   Array of selection options.
   *
   * @return array
   *   List of member members.
   */
  public static function adminMemberBadges(int $eid, int $max_num_badges = 0, array $options = []): array {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'days');
    $select->addField('m', 'country');
    $select->addField('m', 'member_type');
    $select->addField('m', 'mid');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition('m.is_approved', 1);
    // Only include members who aren't deleted.
    $select->condition("is_deleted", FALSE);
    if (!empty($options['member_no_from'])) {
      $select->condition('m.member_no', $options['member_no_from'], '>=');
    }
    if (!empty($options['member_no_to'])) {
      $select->condition('m.member_no', $options['member_no_to'], '<=');
    }
    if (!empty($options['member_range'])) {
      $orGroup = $select->orConditionGroup();
      foreach (explode(',', $options['member_range']) as $range) {
        $output[] = "<p>$range</p>";
        [$min, $max] = array_pad(explode('-', $range), 2, '');
        if (empty($max)) {
          // If no max set, range is single number in min.
          $orGroup->condition('member_no', $min);
        }
        else {
          $orGroup->condition('member_no', [$min, $max], 'BETWEEN');
        }
      }
      $select->condition($orGroup);
    }
    if (!empty($options['member_types'])) {
      $orGroup = $select->orConditionGroup();
      foreach ($options['member_types'] as $typeCode => $type) {
        $orGroup->condition('member_type', $typeCode);
      }
      $select->condition($orGroup);
    }
    if (!empty($options['badge_types'])) {
      $orGroup = $select->orConditionGroup();
      foreach ($options['badge_types'] as $badgeCode => $badge) {
        $orGroup->condition('badge_type', $badgeCode);
      }
      $select->condition($orGroup);
    }
    if (!empty($options['communication_methods'])) {
      $orGroup = $select->orConditionGroup();
      foreach ($options['communication_methods'] as $methodCode => $methodName) {
        $orGroup->condition('communication_method', $methodCode);
      }
      $select->condition($orGroup);
    }
    if (!empty($options['field_options'])) {
      $select->join('conreg_member_options', 'o', 'm.mid = o.mid');
      $select->condition('o.is_selected', 1);
      $orGroup = $select->orConditionGroup();
      foreach ($options['field_options'] as $optionCode => $option) {
        $orGroup->condition('o.optid', $optionCode);
      }
      $select->condition($orGroup);
    }
    if (!empty($options['update_since'])) {
      $select->condition('m.update_date', $options['update_since'], '>=');
    }
    $select->orderby('m.member_no');
    // If maximum number of badges specified, select that range.
    if ($max_num_badges) {
      $select->range(0, $max_num_badges);
    }

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_type');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.member_type');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberBadgeSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'badge_type');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.badge_type');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberDaysSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'days');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.days');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberPaymentMethodSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'payment_method');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.payment_method');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberAmountPaidSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_price');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.member_price');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberAmountPaidByTypeSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_type');
    $select->addField('m', 'member_price');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.member_type');
    $select->groupby('m.member_price');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberByDateSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addExpression('year(from_unixtime(m.join_date))', 'year');
    $select->addExpression('month(from_unixtime(m.join_date))', 'month');
    $select->addExpression('COUNT(1)', 'num');
    $select->addExpression('SUM(m.member_price)', 'total_paid');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('year');
    $select->groupby('month');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberCheckInSummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'is_checked_in');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.is_checked_in');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberAddOns($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'add_on');
    $select->addField('m', 'add_on_info');
    $select->addField('m', 'add_on_price');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->condition("add_on_price", 0, ">"); // Only list members if they have an add-on.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberChildMembers($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'member_type');
    $select->addField('m', 'age');
    $select->addField('p', 'first_name');
    $select->addField('p', 'last_name');
    $select->addField('p', 'email');
    $select->join('conreg_members', 'p', 'm.lead_mid = p.mid');
    $select->condition('m.member_type', ['C', 'I'], 'IN');
    $select->condition('m.is_paid', 1);
    $select->condition("m.is_deleted", FALSE); //Only include members who aren't deleted.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminMemberCountrySummaryLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'country');
    $select->addExpression('COUNT(m.mid)', 'num');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->groupby('m.country');
    $select->orderby('num', $direction = 'DESC');
    // Make sure we only get items 0-49, for scalability reasons.
    //$select->range(0, 50);

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminZZ9MemberListLoad($eid, $condition)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'street');
    $select->addField('m', 'city');
    $select->addField('m', 'county');
    $select->addField('m', 'postcode');
    $select->addField('m', 'country');
    $select->addField('m', 'phone');
    $select->addField('m', 'birth_date');
    $select->addField('m', 'add_on');
    $select->addField('m', 'add_on_info');
    $select->addField('m', 'extra_flag1');
    $select->addField('m', 'extra_flag2');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition('m.add_on', "No thanks!", "!=");
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->orderBy('m.add_on');
    // Make sure we only get items 0-49, for scalability reasons.
    //$select->range(0, 50);

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminProgrammeMemberListLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_type');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'is_approved');
    $select->condition('m.eid', $eid);
    $select->condition('m.extra_flag1', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function adminVolunteerMemberListLoad($eid)
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_type');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'is_paid');
    $select->addField('m', 'is_approved');
    $select->condition('m.eid', $eid);
    $select->condition('m.extra_flag2', 1);
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /*
   * Function to return a list of members and communications methods for integration with Simplenews module.
   */
  public static function adminMailoutListLoad($eid, $methods, $languages)
  {
    $connection = \Drupal::database();
    // Run this query: select email, min(communication_method) from conreg_members where email is not null and email<>'' and communication_method is not null group by email;
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'communication_method)');
    $select->addField('m', 'language)');
    $select->condition('m.eid', $eid);
    $select->isNotNull('m.email');
    $select->condition('m.email', '', '<>');
    $select->condition("is_paid", 1); //Only include paid members.
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->condition('m.communication_method', $methods, 'IN');
    $select->condition('m.language', $languages, 'IN');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /*
   * Function to return a list of members and communications methods for integration with Simplenews module.
   */
  public static function adminSimplenewsSubscribeListLoad($eid)
  {
    $connection = \Drupal::database();
    // Run this query: select email, min(communication_method) from conreg_members where email is not null and email<>'' and communication_method is not null group by email;
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'email');
    $select->addField('m', 'communication_method)');
    $select->condition('m.eid', $eid);
    $select->isNotNull('m.email');
    $select->condition('m.email', '', '<>');
    $select->condition("is_deleted", FALSE); //Only include members who aren't deleted.
    $select->isNotNull('m.communication_method');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}
