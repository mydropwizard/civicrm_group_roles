<?php

namespace Drupal\civicrm_group_roles;

use CRM_Contact_BAO_SubscriptionHistory;
use CRM_Contact_DAO_GroupContact;
use Drupal\civicrm\Civicrm;
use Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Class CivicrmGroupRoles.
 */
class CivicrmGroupRoles {

  /**
   * The CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * CivicrmGroupRoles constructor.
   *
   * @param \Drupal\civicrm\Civicrm $civicrm
   *   The CiviCRM service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(Civicrm $civicrm, ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $loggerFactory) {
    $this->civicrm = $civicrm;
    $this->configFactory = $configFactory;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Add a user to Civi groups depending on their roles upon creation.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function addGroupsOnCreate(UserInterface $account) {
    $this->civicrm->initialize();

    if (!$contactId = $this->getContactId($account->id())) {
      return;
    }

    /** @var \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule[] $rules */
    $rules = CivicrmGroupRoleRule::loadByRoles($account->getRoles());

    foreach ($rules as $rule) {
      $this->addGroupContact($rule->group, $contactId);
    }
  }

  /**
   * Adds groups to a user based on roles.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function userAddGroups(UserInterface $account) {
    $roles = array_diff($account->getRoles(), ['anonymous', 'authenticated']);
    if (!$roles) {
      return;
    }

    $logger = $this->loggerFactory->get('civicrm_group_roles');

    if (!$contactId = $this->getContactId($account->id())) {
      $logger->error('CiviCRM contact not found for Drupal user ID %id', [
        '%id' => $account->id(),
      ]);
      return;
    }

    $userGroups = $this->getContactGroupIds($contactId);

    foreach ($roles as $role) {
      $rules = $this->validateGroups(CivicrmGroupRoleRule::loadByRoles([$role]));
      foreach ($rules as $rule) {
        if ($rule->role == $role && !in_array($rule->group, $userGroups)) {
          $this->addGroupContact($rule->group, $contactId);
          // @TODO: add debugging/logging.
        }
      }
    }
  }

  /**
   * Removes groups from a user based on roles.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param array $removedRoles
   *   Roles removed from the user.
   */
  public function userRemoveGroups(UserInterface $account, array $removedRoles) {
    $logger = $this->loggerFactory->get('civicrm_group_roles');

    if (!$contactId = $this->getContactId($account->id())) {
      $logger->error('CiviCRM contact not found for Drupal user ID %id', [
        '%id' => $account->id(),
      ]);
      return;
    }

    $userGroups = $this->getContactGroupIds($contactId);

    foreach ($removedRoles as $role) {
      $rules = $this->validateGroups(CivicrmGroupRoleRule::loadByRoles([$role]));
      foreach ($rules as $rule) {
        if ($rule->role == $role && in_array($rule->group, $userGroups)) {
          $this->deleteGroupContact($rule->group, $contactId);
          // @TODO: add debugging/logging.
        }
      }
    }
  }

  /**
   * Remove roles from a user based on group membership.
   */
  public function syncRoles(UserInterface $account) {
    $config = $this->configFactory->get('civicrm_group_roles.settings');
    $logger = $this->loggerFactory->get('civicrm_group_roles');

    if (!$contactId = $this->getContactId($account->id())) {
      $logger->error('CiviCRM contact not found for Drupal user ID %id', [
        '%id' => $account->id(),
      ]);
      return;
    }

    $userGroups = $this->getContactGroupIds($contactId);

    // Find which roles need to be added and removed.
    $addRoles = $removeRoles = [];
    foreach (CivicrmGroupRoleRule::loadMultiple() as $rule) {
      if (in_array($rule->group, $userGroups)) {
        $addRoles[] = $rule->role;
      }
      else {
        $removeRoles[] = $rule->role;
      }
    }

    $userRoles = $account->getRoles();

    $addRoles = array_unique($addRoles);
    $removeRoles = array_unique($removeRoles);
    $removeRoles = array_diff($removeRoles, $addRoles);
    $removeRoles = array_intersect($removeRoles, $userRoles);
    $addRoles = array_diff($addRoles, $userRoles);

    // Add/remove the roles as we've determined.
    foreach ($addRoles as $role) {
      $account->addRole($role);
    }
    foreach ($removeRoles as $role) {
      $account->removeRole($role);
    }

    if ($config->get('debugging')) {
      $params = [
        '%initial' => print_r($userRoles, TRUE),
        '%add' => print_r($addRoles, TRUE),
        '%remove' => print_r($removeRoles, TRUE),
        '%final' => print_r($account->getRoles(), TRUE),
      ];
      $logger->info('Initial roles: %initial, roles to add: %add, roles to remove: %remove, final roles: %final', $params);
    }

    // If there were changes, save the user.
    if ($account->getRoles() != $userRoles) {
      $account->save();
    }
  }

  /**
   * Get the user's CiviCRM contact ID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return int|null
   *   The contact ID, or NULL if not found.
   */
  public function getContactId($uid) {
    $this->civicrm->initialize();

    try {
      $result = civicrm_api3('UFMatch', 'getsingle', ['uf_id' => $uid]);
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $result['contact_id'];
  }

  /**
   * Get the user for a contact.
   *
   * @param int $contactId
   *   The contact ID.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user corresponding to the specified contact, or NULL if not found.
   */
  public function getContactUser($contactId) {
    $this->civicrm->initialize();

    try {
      $result = civicrm_api3('UFMatch', 'getsingle', [
        'contact_id' => $contactId,
      ]);
    }
    catch (\Exception $e) {
      return NULL;
    }

    return User::load($result['uf_id']);
  }

  /**
   * Loads a group by ID.
   *
   * @param int $groupId
   *   The group ID.
   *
   * @return array|null
   *   Group data.
   */
  public function getGroup($groupId) {
    $this->civicrm->initialize();

    try {
      $result = civicrm_api3('Group', 'getsingle', ['group_id' => $groupId]);
    }
    catch (\Exception $e) {
      return NULL;
    }

    return $result;
  }

  /**
   * Manipulate the user's roles based on the added group.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule $rule
   *   The group role rule.
   */
  public function userAddRole(UserInterface $account, CivicrmGroupRoleRule $rule) {
    $account->addRole($rule->role);
  }

  /**
   * Manipulate the user's roles based on the removed group.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule $rule
   *   The group role rule.
   */
  public function userRemoveRole(UserInterface $account, CivicrmGroupRoleRule $rule) {
    // See if the user has role provided via another group.
    $userGroupIds = $this->getContactGroupIds($this->getContactId($account->id()));

    $roleRules = array_filter(CivicrmGroupRoleRule::loadByRoles([$rule->role]), function ($roleRule) use ($userGroupIds) {
      return in_array($roleRule->group, $userGroupIds);
    });

    if (!$roleRules) {
      $account->removeRole($rule->role);
    }
  }

  /**
   * Get a contact's groups.
   *
   * @param int $contactId
   *   The contact ID.
   *
   * @return array
   *   Group data.
   */
  public function getContactGroupIds($contactId) {
    $this->civicrm->initialize();

    // Get groups applicable to the rules.
    $ruleGroups = array_map(function ($rule) {
      return $rule->group;
    }, CivicrmGroupRoleRule::loadMultiple());

    $groups = [];

    // Check each group to see if the contact is a member. This is necessary for
    // the case of "smart groups" which are not able to be queried via the
    // GroupContact API.
    foreach ($ruleGroups as $group_id) {
      $params = [
        'filter.group_id' => $group_id,
        'id' => $contactId,
        'version'  => 3,
      ];
      $result = civicrm_api3('contact', 'get', $params);
      if ($result['count'] > 0) {
        $groups[] = $group_id;
      }
    }

    return $groups;
  }

  /**
   * Filters out rules with invalid groups.
   *
   * @param \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule[] $rules
   *   Group assignment rules.
   *
   * @return \Drupal\civicrm_group_roles\Entity\CivicrmGroupRoleRule[]
   *   Rules with valid groups.
   */
  protected function validateGroups(array $rules) {
    $config = $this->configFactory->get('civicrm_group_roles.settings');
    $logger = $this->loggerFactory->get('civicrm_group_roles');

    return array_filter($rules, function ($rule) use ($config, $logger) {
      // Ensure valid group.
      if (!$group = $this->getGroup($rule->group)) {
        $message = 'Cannot add contact to nonexistent group (ID %groupId)';
        $logger->error($message, ['%groupId' => $rule->group]);
        return FALSE;
      }

      // Exclude smart groups, see CRM-11161.
      if (!empty($group['saved_search_id'])) {
        if ($config->get('debugging')) {
          $message = 'Group ID %groupId is a smart group, so the user was not added to it statically.';
          $logger->info($message, ['%groupId' => $rule->group]);
        }
        return FALSE;
      }

      return TRUE;
    });
  }

  /**
   * Adds a contact to a group.
   *
   * @param int $groupId
   *   The group ID.
   * @param int $contactId
   *   The contact ID.
   *
   * @return bool
   *   Indicates success.
   */
  protected function addGroupContact($groupId, $contactId) {
    $groupContact = new CRM_Contact_DAO_GroupContact();
    $groupContact->group_id = $groupId;
    $groupContact->contact_id = $contactId;

    if ($groupContact->find(TRUE)) {
      return TRUE;
    }

    // Add the contact to group.
    $historyParams = [
      'contact_id' => $contactId,
      'group_id' => $groupId,
      'method' => 'API',
      'status' => 'Added',
      'date' => date('YmdHis'),
      'tracking' => NULL,
    ];
    CRM_Contact_BAO_SubscriptionHistory::create($historyParams);
    $groupContact->status = 'Added';
    $groupContact->save();
    return TRUE;
  }

  /**
   * Removes a contact from a group.
   *
   * @param int $groupId
   *   The group ID.
   * @param int $contactId
   *   The contact ID.
   *
   * @return bool
   *   Indicates success.
   */
  protected function deleteGroupContact($groupId, $contactId) {
    $groupContact = new CRM_Contact_DAO_GroupContact();
    $groupContact->group_id = $groupId;
    $groupContact->contact_id = $contactId;

    if (!$groupContact->find(TRUE)) {
      return TRUE;
    }

    return $groupContact->delete() !== FALSE;
  }

}
