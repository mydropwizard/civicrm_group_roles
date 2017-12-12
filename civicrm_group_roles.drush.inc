<?php

/**
 * @file
 * CiviGroup Roles Sync Drush integration.
 */

use Drupal\user\Entity\User;

/**
 * Implements hook_drush_command().
 */
function civicrm_group_roles_drush_command() {
  $commands['civicrm-group-role-sync'] = [
    'description' => 'Run CiviCRM Group Role Sync.',
    'examples' => [
      'drush civicrm-member-role-sync --uid=8',
      'drush civicrm-member-role-sync --contact_id=89',
    ],
    'options' => [
      'uid' => 'Optional User ID',
      'contact_id' => 'Optional Contact ID',
    ],
    'aliases' => ['cgrs'],
  ];

  return $commands;
}

/**
 * Drush callback for civicrm-member-group-sync.
 */
function drush_civicrm_group_roles_civicrm_group_role_sync() {
  /** @var \Drupal\civicrm_group_roles\CivicrmGroupRoles $civicrmGroupRoles */
  $civicrmGroupRoles = \Drupal::service('civicrm_group_roles');

  if ($uid = drush_get_option('uid')) {
    if (!$account = User::load($uid)) {
      $message = dt('Unable to load user @uid', ['@uid' => $uid]);
      return drush_set_error('CIVICRM_GROUP_ROLES_USER_NOT_FOUND', $message);
    }

    $civicrmGroupRoles->userAddGroups($account);
    $civicrmGroupRoles->syncRoles($account);
    return;
  }

  if ($cid = drush_get_option('contact_id')) {
    // Lookup contact and account.
    if (!$uid = $civicrmGroupRoles->getContactUser($cid)) {

    }

    if (!$account = User::load($uid)) {
      $message = dt('Unable to load user for contact @cid', ['@cid' => $cid]);
      return drush_set_error('CIVICRM_GROUP_ROLES_CONTACT_USER_NOT_FOUND', $message);
    }

    $civicrmGroupRoles = \Drupal::service('civicrm_group_roles');
    $civicrmGroupRoles->userAddGroups($account);
    $civicrmGroupRoles->syncRoles($account);
    return;
  }

  drush_log(dt('Syncing CiviGroup roles.'), 'ok');
  /** @var \Drupal\civicrm_group_roles\Batch\Sync $sync */
  $sync = \Drupal::service('civicrm_group_roles.batch.sync');
  $batch = $sync->getBatch();
  $batch['progressive'] = FALSE;
  batch_set($batch);
  drush_backend_batch_process();
}
