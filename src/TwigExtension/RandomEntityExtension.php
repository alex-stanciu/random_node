<?php

namespace Drupal\random_node\TwigExtension;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\random_node\RandomNodeInterface;
use Zend\Stdlib\Exception\BadMethodCallException;

/**
 * Twig extension provider for random entity displaying.
 */
class RandomEntityExtension extends \Twig_Extension {

  /**
   * All functions that are supported.
   *
   * The keys represent the function name used in twig and the values are the
   * methods that will be called on this object.
   *
   * @var array
   */
  protected static $functions = [
    'randomNodes' => 'getRandomNodes',
  ];

  /**
   * Random entity provider.
   *
   * @var \Drupal\random_node\RandomNodeInterface
   */
  protected $randomNodeProvider;

  /**
   * Entity type manager to acquire view builders.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Renderer service to render entities.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Marks whether twig is sub-rendering the random entities.
   *
   * @var bool
   */
  protected $isSubRendering = FALSE;

  /**
   * RandomEntityExtension constructor.
   *
   * @param \Drupal\random_node\RandomNodeInterface $randomEntityProvider
   *   Random entity provider.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   */
  public function __construct(RandomNodeInterface $randomEntityProvider, EntityTypeManagerInterface $entityTypeManager, RendererInterface $renderer) {
    $this->randomNodeProvider = $randomEntityProvider;
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    $twigFunctions = [];

    foreach (self::$functions as $twigName => $callback) {
      $twigFunctions[] = new \Twig_SimpleFunction(
        $twigName, [$this, $callback], ['is_safe' => ['html']]
      );
    }

    return $twigFunctions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'random_node.random_node_extension';
  }

  /**
   * Renders a set of entities.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Entity list to render.
   * @param string $viewMode
   *   View mode of the entity.
   * @param int $maxAge
   *   The max age of this random.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered markup of the entities.
   */
  protected function buildEntities(array $nodes, $viewMode, $maxAge) {
    if (empty($nodes)) {
      return '';
    }

    $builder = $this->entityTypeManager->getViewBuilder('node');

    $render = [];
    $i = 0;
    // Get the render arrays for each entity in the provided view mode.
    foreach ($nodes as $entity) {
      $render['random_' . ++$i] = $builder->view($entity, $viewMode);
    }

    $render['#cache']['max-age'] = $maxAge;

    // Prevent cyclic rendering that could happen if the a entity twig
    // requires random entities of the same type.
    $this->isSubRendering = TRUE;
    $result = $this->renderer->render($render);
    $this->isSubRendering = FALSE;

    return $result;
  }

  /**
   * Relay for the random service.
   *
   * @param string $name
   *   The name of the method.
   * @param array $arguments
   *   The arguments to send to method.
   *
   * @return string
   *   Returns rendered entities.
   */
  public function __call($name, array $arguments) {
    // Check if the called method is registered.
    if (!in_array($name, self::$functions)) {
      throw new BadMethodCallException('The method "' . $name . '" is not registered.');
    }

    if ($this->isSubRendering) {
      return '';
    }

    $amount = $arguments[0] ?: 1;
    $bundles = $arguments[1] ?: NULL;
    $viewMode = $arguments[2] ?: 'full';
    $cacheTime = $arguments[3] ?: 30000;
    $options = $arguments[4] ?: NULL;

    // Allow caching that lasts till tomorrow.
    if ($cacheTime == 'today') {
      $cacheTime = strtotime('tomorrow') - time();
    }

    $entities = $this->randomNodeProvider->getRandomNodes($amount, $bundles, $options);
    return $this->buildEntities($entities, $viewMode, $cacheTime);
  }

}
