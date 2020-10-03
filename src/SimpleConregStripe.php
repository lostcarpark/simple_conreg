<?php


namespace Drupal\simple_conreg;


class SimpleConregStripe
{
    // Function to process payments coming back from Stripe.
    public static function processStripeMessages($config)
    {
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
                                $update['update_date'] = time();

                                $result = SimpleConregStorage::update($update);
                                // If email address populated, send confirmation email.
                                if (!empty($member['email']))
                                    SimpleConregEmailer::sendConfirmationEmail($config, $member);
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
}