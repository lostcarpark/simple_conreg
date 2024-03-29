<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregStorage
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\devel;

class SimpleConregEventStorage {

  /**
   * Save an event in the database.
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
      $return_value = $connection->insert('conreg_events')
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
   * Update an event in the database.
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
      $count = $connection->update('conreg_events')
          ->fields($entry)
          ->condition('eid', $entry['eid'])
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
   * Delete an event from the database.
   *
   * @param array $entry
   *   An array containing at least the person identifier 'pid' element of the
   *   entry to delete.
   *
   * @see $connection->delete()
   */
  public static function delete($entry) {
    $connection = \Drupal::database();
    $connection->delete('conreg_events')
        ->condition('eid', $entry['eid'])
        ->execute();
  }

  /**
   * Read event(s) from the database using a filter array.
   *
   */
  public static function load($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_events table.
    $select = $connection->select('conreg_events', 'events');
    $select->fields('events');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in object format.
    return $select->execute()->fetchAssoc();
  }

  /**
   * Read from the database and return multiple rows using a filter array.
   *
   */
  public static function loadAll($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_events table.
    $select = $connection->select('conreg_events', 'events');
    $select->fields('events');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  
  /**
   * Read all event(s) from the database and return associative array for option list.
   *
   */
  public static function eventOptions() {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_events', 'e');
    // Select these specific fields for the output.
    $select->addField('e', 'eid');
    $select->addField('e', 'event_name');
    $select->orderBy('e.event_name');

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}
