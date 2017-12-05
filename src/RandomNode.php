<?php

namespace Drupal\random_node;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class RandomEntity.
 *
 * @package Drupal\random_entity
 */
class RandomNode implements RandomNodeInterface {

  /**
   * Node IDs that were already random-ed.
   *
   * @var array
   */
  protected $drawnIds = [];

  /**
   * Connection object for queries.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity type manager to load entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * RandomEntity constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Used to acquire random node id's.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Used to load entities.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager interface.
   */
  public function __construct(Connection $database,
                              EntityTypeManagerInterface $entityTypeManager,
                              LanguageManagerInterface $languageManager) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   *
   * @see node_query_node_access_alter()
   */
  public function getRandomNodes($amount, array $bundles = NULL, array $options = []) {
    $default_options = [
      // By default, get published nodes.
      'status' => 1,
      // Make sure we have a langcode set so that there is no chance on getting
      // same random ID in one query.
      'langcode' => $this->languageManager->getCurrentLanguage()->getId()
    ];
    $options += $default_options;

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $ids = $this->getRandomNodeIds($amount, $bundles, $options);

    return $nodeStorage->loadMultiple($ids);
  }

  /**
   * Gets random node IDs.
   *
   * @param int $amount
   *   Total amount to get.
   * @param array $bundles
   *   Bundles to select from.
   * @param array $options
   *   Query conditions.
   *
   * @return array
   *   Array of node IDs.
   */
  protected function getRandomNodeIds($amount, array $bundles = [], array $options = []) {
    $hasStringKeys = count(array_filter(array_keys($bundles), 'is_string')) > 0;
    $query = $this->getBaseQuery($options);
    $ids = [];

    if (!$hasStringKeys) {
      // Only select from the provided bundles if any.
      if ($bundles) {
        $query->condition('n.type', $bundles, 'IN');
      }

      if ($amount > 0) {
        $query->range(0, $amount);
      }

      $ids = $this->acquireRandomIds($query);
    }
    else {
      $undefinedAmountBundles = [];

      // Separate the defined amount for bundles and the bundles for which
      // amount to be random-ed is not defined.
      foreach ($bundles as $bundleOrKey => $bundleOrAmount) {
        if (is_int($bundleOrAmount)) {
          $bundleQuery = clone $query;
          $queryAmount = $amount >= $bundleOrAmount ? $bundleOrAmount : $bundleOrAmount - $amount;

          $bundleQuery->condition('n.type', $bundleOrKey)
            ->range(0, $queryAmount);

          $ids = array_merge($this->acquireRandomIds($bundleQuery), $ids);
          $amount -= $queryAmount;
        }
        else {
          $undefinedAmountBundles[] = $bundleOrAmount;
        }
      }

      if (count($undefinedAmountBundles) && $amount > 0) {
        // Only select from the provided bundles if any.
        $query->condition('n.type', $undefinedAmountBundles, 'IN')
          ->range(0, $amount);

        $ids = array_merge($this->acquireRandomIds($query), $ids);
      }

      // Because of multiple separate queries we might end up with most of the
      // random same type of nodes near each other. Shuffle..
      shuffle($ids);
    }

    return $ids;
  }

  /**
   * Acquire random IDs from built query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The build random query.
   *
   * @return array
   *   The random-ed ids.
   */
  protected function acquireRandomIds(SelectInterface $query) {
    $ids = [];
    foreach ($query->execute()->fetchAll() as $result) {
      $ids[] = $result->nid;
    }

    // Save the IDs we random-ed so next random call won't get the same ones.
    // We do this only per request at the moment.
    $this->drawnIds = array_unique(array_merge($this->drawnIds, $ids));
    return $ids;
  }

  /**
   * Get base random node query.
   *
   * @param array $options
   *   The query options.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The base query.
   */
  protected function getBaseQuery(array $options) {
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->database->select('node', 'n')
      ->fields('n', ['nid'])
      ->orderRandom()
      ->addTag('node_access');

    // Filter by node properties.
    if (!empty($options)) {
      $query->innerJoin('node_field_data', 'nfd', 'nfd.nid = n.nid');
      foreach ($options as $option => $value) {
        $query->condition('nfd.' . $option, $value);
      }
    }

    // Prevent selecting nodes that already have been selected for better
    // randomization.
    if (!empty($this->drawnIds)) {
      $query->condition('n.nid', $this->drawnIds, 'NOT IN');
    }

    return $query;
  }

}
