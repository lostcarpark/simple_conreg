<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregPayment
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

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
    if (isset($this->sessionId)) $pay['session_id'] = $this->sessionId;
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
    return $this->payId;
  }

  public static function load($payId)
  {
    if ($payEntry = SimpleConregPaymentStorage::load(['payid' => $payId])) {
      $payment = new SimpleConregPayment();
      $payment->payId = $payId;
      $payment->randomKey = $payEntry['random_key'];
      $payment->createdDate = $payEntry['created_date'];
      $payment->sessionId = $payEntry['session_id'];
      $payment->paidDate = $payEntry['paid_date'];
      $payment->paymentMethod = $payEntry['payment_method'];
      $payment->paymentAmount = $payEntry['payment_amount'];
      $payment->paymentRef = $payEntry['payment_ref'];
      $payment->paymentLines = SimpleConregPaymentLine::loadLines($payId);
      
      return $payment;
    }
    else
      return NULL;
  }

  public static function loadBySessionId($sessionId)
  {
    if ($payEntry = SimpleConregPaymentStorage::load(['session_id' => $sessionId])) {
      $payment = new SimpleConregPayment();
      $payment->payId = $payEntry['payid'];
      $payment->randomKey = $payEntry['random_key'];
      $payment->createdDate = $payEntry['created_date'];
      $payment->sessionId = $payEntry['session_id'];
      $payment->paidDate = $payEntry['paid_date'];
      $payment->paymentMethod = $payEntry['payment_method'];
      $payment->paymentAmount = $payEntry['payment_amount'];
      $payment->paymentRef = $payEntry['payment_ref'];
      $payment->paymentLines = SimpleConregPaymentLine::loadLines($payment->payId);
      
      return $payment;
    }
    else
      return NULL;
  }
}



