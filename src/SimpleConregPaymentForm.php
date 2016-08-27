<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\DBTNExampleAddForm
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregPaymentForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a new EmailExampleGetFormPage.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.mail'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_conreg_payment';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mid = NULL, $key = NULL) {
    $config = $this->config('simple_conreg.settings');

    $form['#attached'] = array(
      'library' => array('simple_conreg/conreg_payment'),
      'drupalSettings' => array('simple_conreg' => array('payments' => array('public_key' => $config->get('payments.public_key')))),
    );

    $form['members'] = array(
      '#prefix' => '<div id="payment-errors">',
      '#suffix' => '</div>',
    );

    if (is_numeric($mid) && is_numeric($key) && SimpleConregStorage::checkMemberKey($mid, $key)) {
      $member = SimpleConregStorage::load(array("mid"=>$mid));
    } else {
      $form['message'] = array(
        '#markup' => $this->t('Invalid payment credentials. Please return to <a href="@url">registration page</a> and complete membership details.', array("@url" => "/members/register"))
      );
      return $form;
    }

    if ($member->is_paid) {
      $form['message'] = array(
        '#markup' => $this->t('Your payment has been completed. Thank you for joining.')
      );
      return $form;
    }

    $form_state->set('mid', $mid);
    $amount = $member["payment_amount"];
    $form_state->set('payment_amount', $amount);

    // Callback function to be called after the page is built. This prevents card details from being sent to our server.
    $form['#after_build'][] = array($this, 'afterBuild');

    $form['intro'] = array(
      '#markup' => $config->get('payment_intro'),
    );
    
    $form['message'] = array(
      '#markup' => $this->t('Amount to pay: @currency@amount.', array('@currency'=>$config->get('payments.symbol'), '@amount'=>$amount)),
    );
    
    $form['card_number'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Card Number'),
      '#size' => 20,
      '#maxlength' => 16,
      '#attributes' => array('class' => array("card-number")),
    );
    
    $form['cvc'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('CVC'),
      '#size' => 4,
      '#maxlength' => 4,
      '#attributes' => array('class' => array("card-cvc")),
    );
    
    $months = array();
    for ($m=1; $m<=12; $m++) {
      $months[$m] = $m;
    }
    $form['expiry_month'] = array(
      '#type' => 'select',
      '#title' => $this->t('Expiry (MM)'),
      '#options' => $months,
      '#attributes' => array('class' => array("card-expiry-month")),
    );

    $years = array();
    for ($y=2016; $y<2026; $y++) {
      $years[$y] = $y;
    }
    $form['expiry_year'] = array(
      '#type' => 'select',
      '#title' => $this->t('Expiry (YYYY)'),
      '#options' => $years,
      '#attributes' => array('class' => array("card-expiry-year")),
    );
    
    $form['stripeToken'] = array(
      '#type' => 'hidden',
      '#attributes' => array('id' => "stripeToken"),
    );

    $form['security_message'] = array(
      '#markup' => $this->t('Your credit card details are sent directly and securely to our payment processor, Stripe. Your details are never received by or stored on our webserver.'),
    );
    

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Pay now'),
      '#attributes' => array('id' => "submitBtn"),
    );

    return $form;
  }

  public function afterBuild(array $form, FormStateInterface $form_state) {
    unset($form['card_number']['#name']);
    unset($form['cvc']['#name']);
    unset($form['expiry_month']['#name']);
    unset($form['expiry_year']['#name']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('simple_conreg.settings');
    $form_values = $form_state->getValues();
    $mid = $form_state->get('mid');
    $amount = $form_state->get('payment_amount');

    try {
      // Stripe should now be autoloaded by Composer.
      //require_once('stripe/init.php');

		  // set your secret key: remember to change this to your live secret key in production
		  // see your keys here https://manage.stripe.com/account
		  \Stripe\Stripe::setApiKey($config->get('payments.private_key'));

      // Charge the order:
      $charge = \Stripe\Charge::create(array(
        "amount" => $amount * 100, // amount in cents, again
        "currency" => $config->get('payments.currency'),
        "source" => $form_values["stripeToken"],
        "description" => "Member ID ".$mid,
      ));

      // Check that it was paid:
      if ($charge->paid == true) {

        $payment_id = $charge->id;
        drupal_set_message($this->t("Your payment has been accepted. Thank you for joining. Your payment confirmation ID is @payment", array('@payment' => $payment_id)));
        
        // First update the lead member (in case for some reason the lead_mid update failed).
        $entry = array('mid' => $mid, 'is_paid' => 1, 'payment_id' => $payment_id, 'payment_method' => 'Stripe');
        $return = SimpleConregStorage::update($entry);

        // Update all members in group using lead_mid.
        $entry = array('lead_mid' => $mid, 'is_paid' => 1, 'payment_id' => $payment_id, 'payment_method' => 'Stripe');
        $return = SimpleConregStorage::updateByLeadMid($entry);

        // Load the lead member to send confirmation email.
        $member = SimpleConregStorage::load(array("mid"=>$mid));

        // Set up parameters for receipt email.
        $params = (array)$member;
        $module = "simple_conreg";
        $key = "payment_message";
        $to = $member["email"];
        $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
        $params['from'] = $config->get('confirmation.from_name').' <'.$config->get('confirmation.from_email').'>';
        $send_now = TRUE;
        // Send receipt email to member.
        $result = $this->mailManager->mail($module, $key, $to, $language_code, $params);

        // If copy_us checkbox checked, send a copy to us.
        if ($config->get('confirmation.copy_us')) {
          $key = "organiser_payment_message";
          $to = $config->get('confirmation.from_email');
          $result = $this->mailManager->mail($module, $key, $to, $language_code, $params);
        }

        // If copy email to field provided, send an extra copy to us.
        if (!empty($config->get('confirmation.copy_email_to'))) {
          $key = "organiser_payment_message";
          $to = $config->get('confirmation.copy_email_to');
          $result = $this->mailManager->mail($module, $key, $to, $language_code, $params);
        }

      } else { // Charge was not paid!
        $form_state->setErrorByName('card_number', $this->t("Payment System Error! Your payment could NOT be processed (i.e., you have not been charged) because the payment system rejected the transaction. You can try again or use another card."));
      }
    } catch (\Stripe\Error\Card $e) {
      // Card was declined.
      $e_json = $e->getJsonBody();
      $err = $e_json['error'];
      $form_state->setErrorByName('card_number', $err['message']);
    } catch (\Stripe\Error\ApiConnection $e) {
      // Network problem, perhaps try again.
      $form_state->setErrorByName('card_number', $this->t("A network error occurred processing the payment. Your card has not been charged. Please try again later."));
    } catch (\Stripe\Error\InvalidRequest $e) {
      // You screwed up in your programming. Shouldn't happen!
      $form_state->setErrorByName('card_number', $this->t("Something has gone wrong. You have not been charged. We've very sorry. Please try again later."));
    } catch (\Stripe\Error\Api $e) {
      // Stripe's servers are down!
      $form_state->setErrorByName('card_number', $this->t("Our card processor's servers are currently down. You have not been charged. Please try again later."));
    } catch (\Stripe\Error\Base $e) {
      // Something else that's not the customer's fault.
      $form_state->setErrorByName('card_number', $this->t("Something has gone wrong. You have not been charged. We've very sorry. Please try again later."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Redirect to payment form.
    $form_state->setRedirect('simple_conreg_thanks');
  }
}    

