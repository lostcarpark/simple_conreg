<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregPayment
 */
namespace Drupal\simple_conreg;

use Drupal\devel;

class SimpleConregPayment
{
  var $payId;
  var $randomKey;
  var $createdDate;
  var $sessionId;
  var $paidDate;
  var $paymentMethod;
  var $paymentAmount;
  var $paymentRef;
  var $paymentLines;

  // Construct upgrade manager. Store event ID and initialise array.
  public function __construct()
  {
    $this->paymentLines = [];
  }
  
  // Add a detail line to the payment.
  public function add(SimpleConregPaymentLine $payLine)
  {
    $this->paymentLines[] = $payLine;
  }

  // Get the payment ID of the payment. If not already created, save the payment to create it.
  public function getId()
  {
    return $this->savePayment();
  }

  // Save the payment details and all payment lines.
  public function save()
  {
    $this->savePayment();
    
    foreach ($this->paymentLines as $line) {
      $line->save($this->payId);
    }
    
    return $this->payId;
  }
  
  private function savePayment()
  {
    // If Random Key not set, generate it.
    if (empty($this->randomKey))
      $this->randomKey = mt_rand();
    if (empty($this->createdDate))
      $this->createdDate = time();
    $pay = ['random_key' => $this->randomKey, 'created_date' => $this->createdDate];
    if (isset($this->paidDate)) $pay['paid_date'] = $this->paidDate;
    if (isset($this->paymentMethod)) $pay['payment_method'] = $this->paymentMethod;
    if (isset($this->paymentAmount)) $pay['payment_amount'] = $this->paymentAmount;
    if (isset($this->paymentRef)) $pay['payment_ref'] = $this->paymentRef;
    
    // If payId is set, updating an existing payment.
    if (!empty($this->payId) && $this->payId>0) {
      $pay['payid'] = $this->payId;
      SimpleConregPaymentStorage::update($pay);
    }
    else {
      $this->payId = SimpleConregPaymentStorage::insert($pay);
    }
    // Save Stripe session ID to payment_sessions table.
    if (!(empty($this->payId) || empty($this->sessionId))) $this->saveSession();
    return $this->payId;
  }

/**
 * Save the session ID to the payment sessions table.
 */
private function saveSession() {
  $connection = \Drupal::database();
  $select = $connection->select('conreg_payment_sessions', 'S');
  $select->addField('S', 'paysessionid');
  $select->condition('S.payid', $this->payId);
  $select->condition('S.session_id', $this->sessionId);
  // We only want to save if not already on table.
  if (empty($select->execute()->fetchField())) {
    $connection->insert('conreg_payment_sessions')->fields(['payid' => $this->payId, 'session_id' => $this->sessionId])->execute();
  }
}

  public static function load(int $payId): SimpleConregPayment|null
  {
    if ($payEntry = SimpleConregPaymentStorage::load(['payid' => $payId])) {
      $payment = new SimpleConregPayment();
      $payment->payId = $payId;
      $payment->randomKey = $payEntry['random_key'];
      $payment->createdDate = $payEntry['created_date'];
      $payment->paidDate = $payEntry['paid_date'];
      $payment->paymentMethod = $payEntry['payment_method'];
      $payment->paymentAmount = $payEntry['payment_amount'];
      $payment->paymentRef = $payEntry['payment_ref'];
      $payment->paymentLines = SimpleConregPaymentLine::loadLines($payId);
      
      return $payment;
    }
    else
      return null;
  }

  /**
   * Look up the payment from the session ID.
   */
  public static function loadBySessionId(string $sessionId): SimpleConregPayment|null
  {
    $connection = \Drupal::database();
    $select = $connection->select('conreg_payment_sessions', 'S');
    $select->addField('S', 'payid');
    $select->condition('S.session_id', $sessionId);
    $payId = $select->execute()->fetchField();
    // Found the payment ID, so load the payment and return it.
    if (!empty($payId)) {
      return self::load($payId);
    }
    // Session ID not found so return null.
    return null;
  }
}



