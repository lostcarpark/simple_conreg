<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregUpgradeStorage
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\devel;

class SimpleConregUpgradeStorage {

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
  public static function insert($entry) {
    $return_value = NULL;
    $connection = \Drupal::database();
    try {
      $return_value = $connection->insert('conreg_upgrades')
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
      $count = $connection->update('conreg_upgrades')
          ->fields($entry)
          ->condition('upgid', $entry['upgid'])
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
  public static function updateByLeadMid($entry) {
    $connection = \Drupal::database();
    try {
      // $connection->update()...->execute() returns the number of rows updated.
      $count = $connection->update('conreg_upgrades')
          ->fields($entry)
          ->condition('lead_mid', $entry['lead_mid'])
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
    $connection->delete('conreg_upgrades')
        ->condition('upgid', $entry['upgid'])
        ->execute();
  }

  public static function deleteUnpaidByMid($mid) {
    $connection = \Drupal::database();
    $connection->delete('conreg_upgrades')
        ->condition('mid', $mid)
        ->condition('is_paid', 0)
        ->execute();
  }

  public static function deleteUnpaidByLeadMid($lead_mid) {
    $connection = \Drupal::database();
    $connection->delete('conreg_upgrades')
        ->condition('lead_mid', $lead_mid)
        ->condition('is_paid', 0)
        ->execute();
  }

  /**
   * Read from the database using a filter array.
   *
   */
  public static function load($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_upgrades table.
    $select = $connection->select('conreg_upgrades', 'upgrades');
    $select->fields('upgrades');

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
    // Read all fields from the conreg_upgrades table.
    $select = $connection->select('conreg_upgrades', 'upgrades');
    $select->fields('upgrades');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}
