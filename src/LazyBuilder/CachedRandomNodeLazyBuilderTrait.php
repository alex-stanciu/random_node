<?php

namespace Drupal\random_node\LazyBuilder;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Trait CachedRandomNodeLazyBuilderTrait.
 *
 * @see \Drupal\random_node\LazyBuilder\CachedRandomNodeLazyBuilderInterface
 *   Classes using this trait should implement the above interface.
 *
 * @package Drupal\random_node\LazyBuilder
 */
trait CachedRandomNodeLazyBuilderTrait {

  /**
   * Settings property name, without the leading '#' character.
   *
   * @var string
   *
   * @see getSettingsProperty()
   *   IMPORTANT: Lazy builders using this trait should overwrite this value in
   *   their constructor.
   */
  protected $settingsPropertyName = 'random_node_settings';

  /**
   * Gets the logger channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   Logger channel instance.
   */
  abstract protected function getLogger();

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  abstract protected function getEntityTypeManager();

  /**
   * {@inheritdoc}
   */
  public function &getSettingsProperty(array &$element) {
    if ($this->settingsPropertyName) {
      $property_key = '#' . $this->settingsPropertyName;

      if (!array_key_exists($property_key, $element)) {
        $element[$property_key] = [];
      }

      return $element[$property_key];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRandomNodes(int $count, array $cache_keys = [], int $cache_max_age = 600, $view_mode = 'teaser') {
    $element = [
      '#pre_render' => [
        [$this, 'getRandomNodes'],
        [$this, 'buildRandomNodesContent'],
      ],
    ];

    if (($settings_property = &$this->getSettingsProperty($element)) !== NULL) {
      $settings_property = [
        'count' => $count,
        'cache' => [
          'keys' => $cache_keys,
          'max_age' => $cache_max_age,
        ],
        'view_mode' => $view_mode,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @see getCachedRandomNodes()
   */
  public function getRandomNodes(array $element) {
    $conditions = (
      (($settings_property = &$this->getSettingsProperty($element)) !== NULL)
      && (!isset($settings_property['nodes']))
    );
    if ($conditions) {
      $cached_nodes = $this->getCachedRandomNodes($settings_property['count'],
        $settings_property['cache']['keys'],
        $settings_property['cache']['max_age']);

      $settings_property['nodes'] = [];
      if ($cached_nodes) {
        // Merge the random nodes' cacheability metadata with the render array's
        // cacheability metadata, so the render array's cache invalidates when
        // the random nodes' cache expires.
        $element_cache_meta = CacheableMetadata::createFromRenderArray($element);
        $element_cache_meta->addCacheableDependency($cached_nodes);
        $element_cache_meta->applyTo($element);

        if (($nodes = $cached_nodes->getData()) !== FALSE) {
          $settings_property['nodes'] = $nodes;
        }
        else {
          $this->getLogger()->error("Failed to retrieve the random nodes from cache.");
        }
      }
      else {
        $this->getLogger()->error("Failed to init the random nodes cache.");
      }
    }

    return $element;
  }

  /**
   * Retrieves random nodes from cache.
   *
   * @param int $count
   *   The number of nodes to get.
   * @param array $cache_keys
   *   (optional) An array of one or more keys that identify a set of random
   *   nodes. Defaults to empty array.
   * @param int $cache_max_age
   *   (optional) Randomization cache max age (in seconds). Zero seconds means
   *   it is not cacheable. \Drupal\Core\Cache\Cache::PERMANENT means it is
   *   cacheable forever. No new random nodes are selected until expired.
   *   Defaults to \Drupal\Core\Cache\Cache::PERMANENT.
   *
   * @return null|\Drupal\cache_enhancements\CacheableDataInterface
   *   Random nodes cache object, or NULL if failed to instantiate.
   *
   * @see \Drupal\Core\Render\RendererInterface::render()
   *   Cache keys and max-age definitions.
   */
  abstract protected function getCachedRandomNodes(int $count, array $cache_keys, int $cache_max_age);

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\node\NodeViewBuilder::buildMultiple()
   *   The logic this function is based on.
   */
  public function buildRandomNodesContent(array $element) {
    $conditions = (
      (($settings_property = &$this->getSettingsProperty($element)) !== NULL)
      && (!empty($settings_property['nodes']))
    );
    if ($conditions) {
      $element['nodes'] = $this->getEntityTypeManager()
        ->getViewBuilder('node')
        ->viewMultiple($settings_property['nodes'],
          $settings_property['view_mode']);
    }

    return $element;
  }

}
