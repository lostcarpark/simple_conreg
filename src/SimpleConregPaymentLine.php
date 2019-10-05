<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\SimpleConregPaymentLine
 */

use Drupal\devel;

namespace Drupal\simple_conreg;

class SimpleConregPaymentLine
{
  var $payId;
  var $payLineId;
  var $mid;
  var $type;
  var $lineDesc;
  var $amount;

  // Construct upgrade manager. Store event ID and initialise array.
  public function __construct($mid=NULL, $type=NULL, $lineDesc=NULL, $amount=NULL)
  {
    $this->mid = $mid;
    $this->type = $type;
    $this->lineDesc = $lineDesc;
    $this->amount = $amount;
  }
  
  public function save($payId=NULL)
  {
    $payLine = ['payid' => $payId,
                'mid' => $this->mid,
                'payment_type' => $this->type,
                'line_desc' => $this->lineDesc,
                'amount' => $this->amount,
                ];
    // If we have a Line ID, we are updating an existing payment line.
    if (isset($this->payLineId)) {
      $payLine['lineid'] = $this->payLineId;
      SimpleConregPaymentStorage::updateLine($payLine);
      return $this->payLineId;
    }
    // No Line ID, so inserting a new line.
    else {
      $this->payLineId = SimpleConregPaymentStorage::insertLine($payLine);
      return $this->payLineId;
    }
  }
  
  public static function load($lineId)
  {
    if ($payLine = SimpleConregPaymentStorage::loadLine(['lineid' => $lineId])) {
      $line = new SimpleConregPaymentLine($payLine['mid'], $payLine['payment_type'], $payLine['line_desc'], $payLine['amount']);
      $line->payId = $payLine['payid'];
      $line->payLineId = $lineId;
      return $line;
    }
    else
      return NULL;
  }

  public static function loadLines($payId)
  {
    $lines = [];
    if ($payLines = SimpleConregPaymentStorage::loadAllLines(['payid' => $payId])) {
      foreach ($payLines as $payLine) {
        $line = new SimpleConregPaymentLine($payLine['mid'], $payLine['payment_type'], $payLine['line_desc'], $payLine['amount']);
        $line->payId = $payLine['payid'];
        $line->payLineId = $payLine['lineid'];
        $lines[] = $line;
      }
      return $lines;
    }
    else
      return NULL;
  }
}
