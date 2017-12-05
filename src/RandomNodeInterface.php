<?php

namespace Drupal\random_node;

/**
 * Interface RandomEntityInterface.
 *
 * @package Drupal\random_entity
 */
interface RandomNodeInterface {

  /**
   * Gets random nodes.
   *
   * @param int $amount
   *   The amount to get. If amount is a negative integer then all nodes will
   *   be loaded.
   * @param array|null $bundles
   *   Content types from which get.
   * @param array|null $options
   *   Filter nodes based on its properties.
   *
   * @return \Drupal\node\NodeInterface[]
   *   List of nodes.
   */
  public function getRandomNodes($amount, array $bundles = NULL, array $options = []);

}
