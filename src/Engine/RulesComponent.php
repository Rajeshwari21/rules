<?php

/**
 * @file
 * Contains \Drupal\rules\Engine\RulesComponent.
 */

namespace Drupal\rules\Engine;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;

/**
 * Handles executable Rules components.
 */
class RulesComponent {

  /**
   * The rules execution state.
   *
   * @var \Drupal\rules\Engine\RulesStateInterface
   */
  protected $state;

  /**
   * Definitions for the context used by the component.
   *
   * @var \Drupal\rules\Context\ContextDefinitionInterface[]
   */
  protected $contextDefinitions;

  /**
   * Definitions for the context provided by the component.
   *
   * @var \Drupal\rules\Context\ContextDefinitionInterface[]
   */
  protected $providedContextDefinitions;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The expression.
   *
   * @var \Drupal\rules\Engine\ExpressionInterface
   */
  protected $expression;

  /**
   * Constructs the object.
   *
   * @param \Drupal\rules\Engine\ExpressionInterface $expression
   *   The expression of the component.
   *
   * @return static
   */
  public static function create(ExpressionInterface $expression) {
    return new static($expression);
  }

  /**
   * Constructs the object.
   *
   * @param \Drupal\rules\Engine\ExpressionInterface $expression
   *   The expression of the component.
   */
  protected function __construct($expression) {
    $this->state = RulesState::create();
    $this->typedDataManager = \Drupal::typedDataManager();
    $this->expression = $expression;
  }

  /**
   * Adds a context definition.
   *
   * @param $name
   *   The name of the context to add.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The definition to add.
   *
   * @return $this
   */
  public function addContextDefinition($name, ContextDefinitionInterface $definition) {
    $this->contextDefinitions[$name] = $definition;
    return $this;
  }

  /**
   * Sets the value of a context.
   *
   * @param string $name
   *   The name.
   * @param mixed $value
   *   The context value.
   *
   * @throws \LogicException
   *   Thrown if the passed context is not defined.
   *
   * @return $this
   */
  public function setContextValue($name, $value) {
    if (!isset($this->contextDefinitions[$name])) {
      throw new \LogicException("The specified context '$name' is not defined.");
    }
    $data = $this->typedDataManager->create(
      $this->contextDefinitions[$name]->getDataDefinition(),
      $value
    );
    $this->state->addVariable($name, $data);
    return $this;
  }

  /**
   * Executes the component with the previously set context.
   *
   * @return mixed[]
   *   The array of provided context values.
   *
   * @throws \Drupal\rules\Exception\RulesEvaluationException
   *   Thrown if the Rules expression triggers errors during execution.
   */
  public function execute() {
    $result = $this->expression->executeWithState($this->state);
    $this->state->autoSave();
    return $result;
  }

  /**
   * Executes the component with the given values.
   *
   * @return mixed[]
   *   The array of arguments; i.e., an array keyed by name of the defined
   *   context and the context value as argument.
   *
   * @throws \LogicException
   *   Thrown if the context is not defined.
   * @throws \Drupal\rules\Exception\RulesEvaluationException
   *   Thrown if the Rules expression triggers errors during execution.
   */
  public function executeWithArguments(array $arguments) {
    $this->state = RulesState::create();
    foreach ($arguments as $name => $value) {
      $this->setContextValue($name, $value);
    }
    return $this->execute();
  }

}
