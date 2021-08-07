<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregAddonStorage
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\devel;

class SimpleConregAddonStorage
{

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
  public static function insert($entry)
  {
    $return_value = NULL;
    $connection = \Drupal::database();
    try {
      $return_value = $connection->insert('conreg_member_addons')
          ->fields($entry)
          ->execute();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('$connection->insert failed. Message = %message, query= %query', array(
            '%message' => $e->getMessage(),
            '%query' => $e->query_string,
          )), 'error');
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
  public static function update($entry) {
    $connection = \Drupal::database();
    try {
      // $connection->update()...->execute() returns the number of rows updated.
      $count = $connection->update('conreg_member_addons')
          ->fields($entry)
          ->condition('addonid', $entry['addonid'])
          ->execute();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('$connection->update failed. Message = %message, query= %query', array(
            '%message' => $e->getMessage(),
            '%query' => $e->query_string,
          )), 'error');
    }
    return $count;
  }


  public static function updateByPayId($entry) {
    $connection = \Drupal::database();
    try {
      // $connection->update()...->execute() returns the number of rows updated.
      $count = $connection->update('conreg_member_addons')
          ->fields($entry)
          ->condition('payid', $entry['payid'])
          ->execute();
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('$connection->update failed. Message = %message, query= %query', array(
            '%message' => $e->getMessage(),
            '%query' => $e->query_string,
          )), 'error');
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
  public static function delete($entry) {
    $connection = \Drupal::database();
    $connection->delete('conreg_member_addons')
        ->condition('addonid', $entry['addonid'])
        ->execute();
  }


  /**
   * Read from the database using a filter array.
   *
   */
  public static function load($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_addons table.
    $select = $connection->select('conreg_member_addons', 'addons');
    $select->fields('addons');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    return $select->execute()->fetchAssoc();
  }


  /**
   * Read from the database and return multiple rows using a filter array.
   *
   */
  public static function loadAll($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_addons table.
    $select = $connection->select('conreg_member_addons', 'addons');
    $select->fields('addons');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function loadAddOnReport($eid, $addOn) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_addons table.
    $select = $connection->select('conreg_members', 'm');
    $select->join('conreg_member_addons', 'a', 'm.mid = a.mid');
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('a', 'addon_name');
    $select->addField('a', 'addon_option');
    $select->addField('a', 'addon_info');
    $select->addField('a', 'addon_amount');
    $select->addField('a', 'payment_ref');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition("m.is_deleted", FALSE); //Only include members who aren't deleted.
    $select->condition('a.is_paid', 1);
    if (!empty($addOn))
      $select->condition('a.addon_name', $addOn);

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}

