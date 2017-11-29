<?php

namespace Drupal\civicrm_group_roles;

use Drupal\civicrm\Civicrm;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CivicrmGroupRolesListBuilder.
 */
class CivicrmGroupRolesListBuilder extends ConfigEntityListBuilder {

  /**
   * CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $builder = parent::createInstance($container, $entity_type);
    /** @var static $builder */
    return $builder->setCivicrm($container->get('civicrm'));
  }

  /**
   * Set the CiviCRM service.
   *
   * @param \Drupal\civicrm\Civicrm $civicrm
   *   CiviCRM service.
   *
   * @return $this
   */
  public function setCivicrm(Civicrm $civicrm) {
    $this->civicrm = $civicrm;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Rule'),
      'group' => $this->t('Group'),
      'role' => $this->t('Role'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [
      'label' => $entity->label() . ' (' . $entity->id . ')',
      'group' => $this->getGroupLabel($entity->group),
      'role' => $this->getRoleLabel($entity->role),
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * Gets the group label.
   *
   * @param int $groupId
   *   The group ID.
   *
   * @return string
   *   The group name, or
   */
  protected function getGroupLabel($groupId) {
    $this->civicrm->initialize();
    try {
      $result = civicrm_api3('Group', 'getsingle', ['group_id' => $groupId]);
    }
    catch (\Exception $e) {
      return $this->t('N/A');
    }

    return $result['title'];
  }

  /**
   * Gets the role label.
   *
   * @param string $rid
   *   The role ID.
   *
   * @return string|null
   *   The role label,  "N/A" if role not found.
   */
  protected function getRoleLabel($rid) {
    if ($role = Role::load($rid)) {
      return $role->label();
    }
    return $this->t('N/A');
  }

}
