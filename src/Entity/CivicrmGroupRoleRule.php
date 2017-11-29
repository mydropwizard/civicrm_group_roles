<?php

namespace Drupal\civicrm_group_roles\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Civicrm group role rule entity.
 *
 * @ConfigEntityType(
 *   id = "civicrm_group_role_rule",
 *   label = @Translation("Association Rule"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\civicrm_group_roles\CivicrmGroupRolesListBuilder",
 *     "form" = {
 *       "add" = "Drupal\civicrm_group_roles\Form\CivicrmGroupRoleRuleForm",
 *       "edit" = "Drupal\civicrm_group_roles\Form\CivicrmGroupRoleRuleForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "civicrm_group_role_rule",
 *   admin_permission = "access civicrm group role setting",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/civicrm/civicrm-group-roles/rule/{civicrm_group_role_rule}",
 *     "add-form" = "/admin/config/civicrm/civicrm-group-roles/rule/add",
 *     "edit-form" = "/admin/config/civicrm/civicrm-group-roles/rule/{civicrm_group_role_rule}/edit",
 *     "delete-form" = "/admin/config/civicrm/civicrm-group-roles/rule/{civicrm_group_role_rule}/delete",
 *     "collection" = "/admin/config/civicrm/civicrm-group-roles"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "group",
 *     "role",
 *   }
 * )
 */
class CivicrmGroupRoleRule extends ConfigEntityBase implements CivicrmGroupRoleRuleInterface {

  /**
   * Load rules applicable to a set of roles.
   *
   * @param array $roles
   *   An array of role names.
   *
   * @return \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule[]
   *   The applicable rules.
   */
  public static function loadByRoles(array $roles) {
    return array_filter(static::loadMultiple(), function ($rule) use ($roles) {
      return in_array($rule->role, $roles);
    });
  }

  /**
   * Load rules applicable to a group.
   *
   * @param int $groupId
   *   A group ID.
   *
   * @return \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule[]
   *   The applicable rules.
   */
  public static function loadByGroup($groupId) {
    return static::loadByGroups([$groupId]);
  }

  /**
   * Load rules applicable to a group.
   *
   * @param array $groupIds
   *   A group ID.
   *
   * @return \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule[]
   *   The applicable rules.
   */
  public static function loadByGroups(array $groupIds) {
    return array_filter(static::loadMultiple(), function ($rule) use ($groupIds) {
      return in_array($rule->group, $groupIds);
    });
  }

}
