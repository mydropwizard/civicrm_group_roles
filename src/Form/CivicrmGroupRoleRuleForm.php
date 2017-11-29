<?php

namespace Drupal\civicrm_group_roles\Form;

use Drupal\civicrm\Civicrm;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CivicrmGroupRoleRuleForm.
 */
class CivicrmGroupRoleRuleForm extends EntityForm {

  /**
   * CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * CivicrmGroupRoleRuleForm constructor.
   *
   * @param \Drupal\civicrm\Civicrm $civicrm
   *   CiviCRM service.
   */
  public function __construct(Civicrm $civicrm) {
    $this->civicrm = $civicrm;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('civicrm'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $rule = $this->entity;

    $form['add_rule'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Association Rule'),
      '#description' => $this->t('Choose a CiviCRM Group and a Drupal Role below.'),
    ];

    $form['add_rule']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $rule->label(),
      '#description' => $this->t('Label for the association rule.'),
      '#required' => TRUE,
    ];

    $form['add_rule']['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $rule->id(),
      '#description' => $this->t('A unique ID for the association rule.'),
      '#machine_name' => [
        'exists' => '\Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule::load',
        'source' => ['add_rule', 'label'],
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ],
    ];

    $form['add_rule']['group'] = [
      '#type' => 'select',
      '#title' => $this->t('CiviCRM Group'),
      '#options' => $this->getGroups(),
      '#required' => TRUE,
    ];

    $form['add_rule']['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Drupal Role'),
      '#options' => $this->getRoles(),
      '#required' => TRUE,
    ];

    // Edit mode.
    if (!$rule->isNew()) {
      $form['add_rule']['label']['#default_value'] = $rule->label;
      $form['add_rule']['id']['#default_value'] = $rule->id;
      $form['add_rule']['group']['#default_value'] = $rule->group;
      $form['add_rule']['role']['#default_value'] = $rule->role;
      $form['add_rule']['id']['#disabled'] = TRUE;
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->entity->save();
    drupal_set_message($this->t('Your association rule has been saved.'));
    $form_state->setRedirect('entity.civicrm_group_role_rule.collection');
  }

  /**
   * Gets CiviCRM group options.
   *
   * @return array
   *   CiviCRM group options.
   */
  protected function getGroups() {
    $groups = [];

    $this->civicrm->initialize();
    $result = civicrm_api3('Group', 'get', ['is_active' => 1]);

    if (empty($result['values'])) {
      return $groups;
    }

    foreach ($result['values'] as $value) {
      $groups[$value['id']] = $value['title'];
    }

    return $groups;
  }

  /**
   * Gets role options.
   *
   * @return array
   *   Role options.
   */
  protected function getRoles() {
    $roles = user_roles(TRUE);
    unset($roles['authenticated']);
    return array_map(function ($role) {
      /** @var \Drupal\user\Entity\Role $role */
      return $role->get('label');
    }, $roles);
  }

}
