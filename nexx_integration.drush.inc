<?php

/**
 * @file
 *   Drush commands for the nexx_integration module
 */

/**
 * Implements hook_drush_commands().
 */
function nexx_integration_drush_command() {
  $items = array();

  $items['nexx-export-taxonomy'] = array(
    'description' => "Export taxonomy terms to omnia. This inserts all terms into Omnia, it cannot check if they already exist.",
    'arguments' => array(
      'source_vocabulary' => 'Drupal Vocabulary to export',
      'destination' => 'omnia destination to export to',
    ),
    'examples' => array(
      'drush -l http://example.com nexx-export-taxonomy category channel' => 'Export drupal categories vocabulary to omnia channel',
      'drush -l http://example.com nexx-export-taxonomy people actors' => 'Export drupal people vocabulary to omnia actors',
    )
  );

  return $items;
}


/**
 * Implements the nexx-export-taxonomy drush command.
 */
function drush_nexx_integration_nexx_export_taxonomy($source_vocabulary, $destination) {
  $container = \Drupal::getContainer();
  $type_manager = \Drupal::entityTypeManager();
  $nexx_notification = $container->get('nexx_integration.notification');


  $vocabulary = $type_manager->getStorage("taxonomy_vocabulary")->load($source_vocabulary);
  if(empty($vocabulary)){
    drush_set_error(dt("Given vocabulary !vocabulary does not exist.", array('!vocabulary' => $source_vocabulary)));
    exit;
  }

  $terms = $type_manager
    ->getStorage("taxonomy_term")
    ->loadTree($source_vocabulary);

  foreach($terms as $term) {
    $nexx_notification->insert($destination, $term->tid, $term->name);
  }
}
