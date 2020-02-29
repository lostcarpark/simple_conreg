<?php

/**
 * @file
 * Contains \Drupal\simple_conreg\DBTNExampleAddForm
 */

namespace Drupal\simple_conreg;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Drupal\devel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class SimpleConregCheckoutForm extends FormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, $payid = NULL, $key = NULL, $return = '') {

    // Load payment details.
    if (is_numeric($payid) && is_numeric($key) && SimpleConregPaymentStorage::checkPaymentKey($payid, $key)) {
      $payment = SimpleConregPayment::load($payid);      
    } else {
      $form['message'] = array(
        '#markup' => $this->t('Invalid payment credentials. Please return to <a href="@url">registration page</a> and complete membership details.', array("@url" => "/members/register"))
      );
      return $form;
    }
    
    // Get event ID to fetch Stripe keys.
    // If payment has MID, get event from member. If not, assume event 1 (will come up with a better long term solution). 
    if (isset($payment) && isset($payment->paymentLines[0]) && !empty($payment->paymentLines[0]->mid)) {
      $member = SimpleConregStorage::load(array("mid"=>$payment->paymentLines[0]->mid));
      $eid = $member['eid'];
      $email = $member['email'];
    }
    else {
      $eid = 1;
      $email = '';
    }
    $config = $this->config('simple_conreg.settings.'.$eid);

    // Set your secret key: remember to change this to your live secret key in production
    // See your keys here: https://dashboard.stripe.com/account/apikeys
    \Stripe\Stripe::setApiKey($config->get('payments.private_key'));

    $this->processStripeMessages($config);

    // Stripe messages processed, so we need to load the payment again, as it may have been completed.
    $payment = SimpleConregPayment::load($payid);

    // Check if payment date populated. If so, payment is complete, thank you message can be displayed.
    if ($payment->paidDate) {
      $form['#title'] = $this->t('Thank You');
      $form['message'] = array(
        '#markup' => $this->t('Your payment has been received and your registration is complete. Your payment reference is @pay_ref.', array("@pay_ref" => $payment->paymentRef))
      );
      return $form;
    }

    // Set up payment lines on Stripe.
    $items = [];
    foreach ($payment->paymentLines as $line) {
      // Only add member to payment if price greater than zero...
      if ($line->amount > 0) {
        $items[] = [
          'name' => $line->lineDesc,
          'description' => $line-lineDesc,
          'amount' => $line->amount * 100,
          'currency' => $config->get('payments.currency'),
          'quantity' => 1,
        ];
      }
    }
    
    // Set up return URLs.
    $success = Url::fromRoute("simple_conreg_checkout", ["payid" => $payment->payId,"key" => $payment->randomKey], ['absolute' => TRUE])->toString();
    $cancel = Url::fromRoute("simple_conreg_register", ["eid" => $eid], ['absolute' => TRUE])->toString();

    // Set up Stripe Session.
    $session = \Stripe\Checkout\Session::create([
      'payment_method_types' => ['card'],
      'customer_email' => $email,
      'line_items' => $items,
      'success_url' => $success,
      'cancel_url' => $cancel,
    ]);
    
    // Update the payment with the session ID.
    $payment->sessionId = $session->id;
    $payment->save();

    $from['#title'] = $this->t("Transferring to Stripe");

    // Attach the Javascript library and set up parameters.
    $form['#attached'] = [
      'library' => ['simple_conreg/conreg_checkout'],
      'drupalSettings' => ['simple_conreg' => ['checkout' => ['public_key' => $config->get('payments.public_key'), 'session_id' => $session->id]]]
    ];

    $form['security_message'] = array(
      '#markup' => $this->t('You will be transferred to Stripe to securely accept your payment. Your browser will return after payment processed.'),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    );

    return $form;
  }

  // Function to process payments coming back from Stripe.
  private function processStripeMessages($config) {
    // Check events on Stripe.
    $events = \Stripe\Event::all([
      'type' => 'checkout.session.completed',
      'created' => [
        // Check for events created in the last 24 hours.
        'gte' => time() - 24 * 60 * 60,
      ],
    ]);

    // Loop through received events and mark payments complete.
    foreach ($events->autoPagingIterator() as $event) {
      $session = $event->data->object;
      // Update the payment record.
      $payment = SimpleConregPayment::loadBySessionId($session->id);
      if (isset($payment)) {
        // Only update payment if not already paid.
        if (empty($payment->paidDate)) {
          $payment->paidDate = time();
          $payment->paymentMethod = "Stripe";
          $payment->paymentRef = $session->payment_intent;
          $payment->save();
        }
        
        SimpleConregAddons::markPaid($payment->getId(), $session->payment_intent);
        
        // Process the payment lines.
        foreach ($payment->paymentLines as $line) {
          switch ($line->type) {
            case "member":
              // Only update member if not already paid.
              $member = SimpleConregStorage::load(['mid' => $line->mid, 'is_paid' => 0, 'is_deleted' => 0]);
              if (isset($member)) {
                $update['mid'] = $line->mid;
                $update['is_paid'] = 1;
                $update['payment_id'] = $session->payment_intent;
                $update['payment_method'] = 'Stripe';
                $result = SimpleConregStorage::update($update);
                // If email address populated, send confirmation email.
                if (!empty($member['email']))
                  $this->sendConfirmationEmail($config, $member);
              }
              break;
            case "upgrade":
              $member = SimpleConregStorage::load(['mid' => $line->mid, 'is_deleted' => 0]);
              if (isset($member)) {
                $mgr = new SimpleConregUpgradeManager($member['eid']);
                if ($mgr->loadUpgrades($line->mid, 0)) {
                  $mgr->completeUpgrades($payment->paymentAmount, $payment->paymentMethod, $payment->paymentRef);
                }
              }
              break;
          }
        }
      }
    }
  }

  private function sendConfirmationEmail($config, $member)
  {
    // Set up parameters for receipt email.
    $params = ['eid' => $member['eid'], 'mid' => $member['mid']];
    $params['subject'] = $config->get('confirmation.template_subject');
    $params['body'] = $config->get('confirmation.template_body');
    $params['format'] = $config->get('confirmation.template_format');
    $module = "simple_conreg";
    $key = "template";
    $to = $member["email"];
    $language_code = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $send_now = TRUE;
    // Send confirmation email to member.
    if (!empty($member["email"]))
      $result = \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $language_code, $params);

    // If copy_us checkbox checked, send a copy to us.
    if ($config->get('confirmation.copy_us')) {
      $params['subject'] = $config->get('confirmation.notification_subject');
      $to = $config->get('confirmation.from_email');
      $result = $this->mailManager->mail($module, $key, $to, $language_code, $params);
    }

    // If copy email to field provided, send an extra copy to us.
    if (!empty($config->get('confirmation.copy_email_to'))) {
      $params['subject'] = $config->get('confirmation.notification_subject');
      $to = $config->get('confirmation.copy_email_to');
      $result = $this->mailManager->mail($module, $key, $to, $language_code, $params);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $eid = $form_state->get('eid');
    $mid = $form_state->get('mid');
    $return = $form_state->get('return');

    switch ($return) {
      case 'checkin':
        // Redirect to check-in page.
        $form_state->setRedirect('simple_conreg_admin_checkin', ['eid' => $eid, 'lead_mid' => $mid]);
        break;
      case 'fantable':
        // Redirect to fan table page.
        $form_state->setRedirect('simple_conreg_admin_fantable', ['eid' => $eid, 'lead_mid' => $mid]);
        break;
      case 'portal':
        // Redirect to portal.
        $form_state->setRedirect('simple_conreg_portal', ['eid' => $eid]);
        break;
      default:
        // Redirect to payment form.
        $form_state->setRedirect('simple_conreg_thanks', ['eid' => $eid]);
    }
  }
}    

