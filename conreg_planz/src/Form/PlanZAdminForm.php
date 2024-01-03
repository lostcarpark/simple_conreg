<?php

namespace Drupal\conreg_planz\Form;

use Drupal\conreg_planz\PlanZ;
use Drupal\conreg_planz\PlanZUser;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_conreg\FieldOptions;
use Drupal\simple_conreg\Member;

/**
 * Configure simple_conreg settings for this site.
 */
class PlanZAdminForm extends FormBase {
  private PlanZ $planz;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'conreg_planz_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'conreg_planz.admin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    $config = \Drupal::config('simple_conreg.settings.' . $eid . '.planz');
    $this->planz = new PlanZ($config);
    /**
     * Manual member invites.
     */

    $form['info'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="email_members">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Manually add members and send invite emails.'),
    ];

    $form['member_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search for member'),
    ];

    $form['search_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#ajax' => [
        'wrapper' => 'search-results',
        'callback' => [$this, 'callbackSearch'],
        'event' => 'click',
      ],
    ];

    $form['search_results'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="search-results">',
      '#suffix' => '</div>',
    ];

    $form['member_range'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Member Number Range to Invite'),
      '#description' => $this->t('Enter Member Nos to invite. Use commas (,) to separate ranges and hyphens (-) to separate range limits, e.g. "1,3,5-7".'),
    ];

    $form['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override member options - all members in range will be added'),
    ];

    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset passwords for existing members - if checked will update password of members already existing on PlanZ with new random ones (note, we recommend getting users to use password reset on PlanZ rather than setting password centrally)'),
    ];

    $form['dont_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Don\'t email - if checked no emails will be sent, member info will display on page. Only use for members who have difficulty receiving emails.'),
    ];

    $form['manual_add'] = [
      '#type' => 'button',
      '#value' => $this->t('Manual Add to PlanZ'),
      '#ajax' => [
        'wrapper' => 'manual-add-result',
        'callback' => [$this, 'callbackManualAdd'],
        'event' => 'click',
      ],
    ];

    $form['result'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="manual-add-result">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Results will go here.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
  }

  /**
   * Callback for member search. Search ConReg members and check if present on PlanZ/PlanZ.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function callbackSearch(array $form, FormStateInterface $form_state) {
    $vals = $form_state->getValues();
    $eid = $form_state->get('eid');

    $form['search_results']['head'] = [
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => $this->t('Search results for @terms.', ['@terms' => $vals['member_search']]),
    ];

    $connection = \Drupal::database();
    $select = $connection->select('conreg_members', 'm');
    // Select these specific fields for the output.
    $select->addField('m', 'member_no');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->leftJoin('conreg_planz', 'z', 'm.mid = z.mid');
    $select->addField('z', 'badgeid');
    $select->condition('m.eid', $eid);
    $select->condition('m.is_paid', 1);
    $select->condition('m.is_approved', 1);
    // Only include members who aren't deleted.
    $select->condition("is_deleted", FALSE);
    foreach (explode(' ', $vals['member_search']) as $word) {
      // Escape search word to prevent dangerous characters.
      $esc_word = '%' . $connection->escapeLike($word) . '%';
      $likes = $select->orConditionGroup()
        ->condition('m.first_name', $esc_word, 'LIKE')
        ->condition('m.last_name', $esc_word, 'LIKE')
        ->condition('m.badge_name', $esc_word, 'LIKE')
        ->condition('m.email', $esc_word, 'LIKE');
      $select->condition($likes);
    }
    $select->orderBy('m.member_no');
    // Make sure we only get items 0-49, for scalability reasons.
    // $select->range(0, 50);.
    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $rows = [];
    $headers = [
      $this->t('Member No'),
      $this->t('First Name'),
      $this->t('Last Name'),
      $this->t('email'),
      $this->t('PlanZ Badge ID'),
    ];

    foreach ($entries as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\Html::escape', (array) $entry);
    }

    $form['search_results']['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    return $form['search_results'];
  }

  /**
   * Callback for manual add button. Add specified members to PlanZ/PlanZ.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array The updated "Result" form element.
   */
  public function callbackManualAdd(array $form, FormStateInterface $form_state) {
    $eid = $form_state->get('eid');
    $config = \Drupal::config('simple_conreg.settings.' . $eid . '.planz');

    $fieldOptions = FieldOptions::getFieldOptions($eid);
    $optionFields = [];
    foreach ($fieldOptions->options as $option) {
      if ($config->get('option_fields.' . $option->optionId) ?: FALSE) {
        $optionFields[$option->optionId] = TRUE;
      }
    }

    $output = [];
    $vals = $form_state->getValues();
    if (preg_match('/^([0-9]+(\-[0-9]+)?,)*[0-9]+(\-[0-9]+)?$/', $vals['member_range']) == 1) {
      foreach (explode(',', $vals['member_range']) as $range) {
        $output[] = "<p>$range</p>";
        [$min, $max] = array_pad(explode('-', $range), 2, '');
        if (empty($max)) {
          // If no max set, range is single number in min.
          $output[] = $this->addMemberToPlanZ($eid, $min, $vals['override'], $vals['reset'], $vals['dont_email'], $optionFields);
        }
        else {
          for ($num = $min; $num <= $max; $num++) {
            $output[] = $this->addMemberToPlanZ($eid, $num, $vals['override'], $vals['reset'], $vals['dont_email'], $optionFields);
          }
        }
        // Log an event to show a member check occurred.
        // \Drupal::logger('conreg_planz')->info("Manual Add pressed.");.
      }
      $form['result']['#markup'] = implode("\n", $output);
    }
    else {
      $form['result']['#markup'] = $this->t('Member numbers not in correct format.');
    }

    return $form['result'];
  }

  /**
   * Add a member to the PlanZ/PlanZ database.
   *
   * @param int $eid
   *   The event ID.
   * @param int $memberNo
   *   The member number being added.
   * @param bool $override
   *   If true, add member even if they don't meet criteria.
   * @param bool $reset
   *   If true, reset the user's password.
   * @param bool $dontEmail
   *   If true, don't send email notification.
   * @param array $optionFields
   *
   * @return string Returns the details of the newly added member.
   */
  private function addMemberToPlanZ(int $eid, int $memberNo, bool $override, bool $reset, bool $dontEmail, array $optionFields): string {
    $member = Member::loadMemberByMemberNo($eid, $memberNo);
    if (is_null($member)) {
      return "<p>Member: $memberNo does not exist.</p>";
    }
    $match = FALSE;
    foreach ($optionFields as $optId => $optVal) {
      if ($optId) {
        if (isset($member?->options[$optId]?->isSelected)) {
          $match = TRUE;
        }
      }
    }
    if ($match || $override) {

      $user = new PlanZUser($this->planz);
      $user->load($member->mid);
      $user->save($member, $reset);

      if (!$dontEmail) {
        // Send email to user.
        $this->planz->sendInviteEmail($user);
      }

      return $this->t('<p>Member: @first_name @last_name<br />' .
                      'Badge id: @badgeid.<br />' .
                      'Password: @password<br />' .
                      'URL: @url</p>',
                      [
                        '@first_name' => $member->first_name,
                        '@last_name' => $member->last_name,
                        '@badgeid' => $user->badgeId,
                        '@password' => $user->password ?? '',
                        '@url' => $this->planz->planZUrl,
                      ]);
    }

    // If this point reached, member not added, so return empty string.
    return "<p>Member: $memberNo not added.</p>";
  }

}
