<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregPaymentStorage
 */
namespace Drupal\simple_conreg;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\devel;

class SimpleConregPaymentStorage
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
      $return_value = $connection->insert('conreg_payments')
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

  public static function insertLine($entry) {
    $return_value = NULL;
    $connection = \Drupal::database();
    try {
      $return_value = $connection->insert('conreg_payment_lines')
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
      $count = $connection->update('conreg_payments')
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

  public static function updateLine($entry) {
    $connection = \Drupal::database();
    try {
      // $connection->update()...->execute() returns the number of rows updated.
      $count = $connection->update('conreg_payment_lines')
          ->fields($entry)
          ->condition('lineid', $entry['lineid'])
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
    $connection->delete('conreg_payments')
        ->condition('payid', $entry['payid'])
        ->execute();
  }

  public static function deleteLine($entry) {
    $connection = \Drupal::database();
    $connection->delete('conreg_payment_lines')
        ->condition('lineid', $entry['lineid'])
        ->execute();
  }


  /**
   * Read from the database using a filter array.
   *
   */
  public static function load($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_payments table.
    $select = $connection->select('conreg_payments', 'payments');
    $select->fields('payments');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    return $select->execute()->fetchAssoc();
  }

  public static function loadLine($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_payments table.
    $select = $connection->select('conreg_payment_lines', 'payments');
    $select->fields('payments');

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
    // Read all fields from the conreg_payments table.
    $select = $connection->select('conreg_payments', 'payments');
    $select->fields('payments');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  public static function loadAllLines($entry = array()) {
    $connection = \Drupal::database();
    // Read all fields from the conreg_payments table.
    $select = $connection->select('conreg_payment_lines', 'payments');
    $select->fields('payments');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in associative array format.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

  /**
   * Check if valid payid and key combo.
   */
  public static function checkPaymentKey($payid, $key)
  {
    $connection = \Drupal::database();
    // Read all fields from the conreg_member_payments table.
    $select = $connection->select('conreg_payments', 'payments');
    $select->fields('payments');
    $select->condition("payid", $payid);
    $select->condition("random_key", $key);

    // Return the result in object format.
    if ($select->countQuery()->execute()->fetchField() > 0)
      return TRUE;
    else
      return FALSE;
  }
}
