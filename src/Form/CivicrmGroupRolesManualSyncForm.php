<?php

namespace Drupal\civicrm_group_roles\Form;

use Drupal\civicrm_group_roles\Batch\Sync;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CivicrmGroupRolesManualSyncForm.
 */
class CivicrmGroupRolesManualSyncForm extends FormBase {

  /**
   * Sync batch service.
   *
   * @var \Drupal\civicrm_group_roles\Batch\Sync
   */
  protected $batch;

  /**
   * CivicrmGroupRolesManualSyncForm constructor.
   *
   * @param \Drupal\civicrm_group_roles\Batch\Sync $batch
   *   Sync batch service.
   */
  public function __construct(Sync $batch) {
    $this->batch = $batch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('civicrm_group_roles.batch.sync'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'civicrm_group_roles_manual_sync';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['manual_sync'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Manual Synchronization'),
      '#description' => $this->t('Manually synchronize CiviCRM group membership and Drupal roles according to the current association rules. This process may take a long time.'),
    ];
    $form['manual_sync']['manual_sync_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Synchronize now'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = $this->batch->getBatch();
    batch_set($batch);
  }

}
