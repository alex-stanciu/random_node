<?php

namespace Drupal\random_node\LazyBuilder;

/**
 * Interface CachedRandomNodeLazyBuilderInterface.
 *
 * @see \Drupal\random_node\LazyBuilder\CachedRandomNodeLazyBuilderTrait
 *   Classes implementing this interface should make use of the above trait.
 *
 * @package Drupal\random_node\LazyBuilder
 */
interface CachedRandomNodeLazyBuilderInterface {

  /**
   * Get the lazy built element's settings property.
   *
   * Useful when deferring the content's building to #pre_render callbacks and
   * need to prevent conflict on the built element's properties, as two or more
   * similar of these callbacks, using properties named the same way would
   * interfere with each other.
   *
   * @param array $element
   *   The lazy built element.
   *
   * @return array
   *   Array by reference containing the settings for the lazy built element.
   */
  public function &getSettingsProperty(array &$element);

  /**
   * Builds the specified amount of random nodes.
   *
   * @param int $count
   *   The number of nodes to build.
   * @param string[] $cache_keys
   *   (optional) An array of one or more keys that identify a set of random
   *   nodes. Defaults to empty array.
   * @param int $cache_max_age
   *   (optional) Randomization cache max age (in seconds). Zero seconds means
   *   it is not cacheable. \Drupal\Core\Cache\Cache::PERMANENT means it is
   *   cacheable forever. No new random nodes are selected until expired.
   *   Defaults to 10 minutes.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the nodes.
   *   Defaults to 'teaser'.
   *
   * @return array
   *   The random nodes render array.
   *
   * @see \Drupal\Core\Render\RendererInterface::render()
   *   Cache keys and max-age definitions.
   * @see \Drupal\Core\Entity\EntityViewBuilderInterface::view()
   *   View mode definition.
   * @see getRandomNodes()
   * @see buildRandomNodesContent()
   */
  public function buildRandomNodes(int $count, array $cache_keys = [], int $cache_max_age = 600, $view_mode = 'teaser');

  /**
   * Pre-render callback; retrieves cached random nodes.
   *
   * @param array $element
   *   The elements array to pre-render.
   *
   * @return array
   *   The pre-rendered elements array.
   *
   * @see buildRandomNodes()
   *   Assigns this function as a #pre_render callback.
   */
  public function getRandomNodes(array $element);

  /**
   * Pre-render callback; builds the content for the random nodes.
   *
   * @param array $element
   *   The elements array to pre-render.
   *
   * @return array
   *   The pre-rendered elements array.
   *
   * @see buildRandomNodes
   *   Assigns this function as a #pre_render callback after the dependency
   *   #pre_render callback(s).
   * @see getRandomNodes()
   *   The #pre_render callback which populates the random nodes property this
   *   function depends on.
   */
  public function buildRandomNodesContent(array $element);

}
