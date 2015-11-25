<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Unit\RuleTest.
 */

namespace Drupal\Tests\rules\Unit;

use Drupal\rules\Context\ContextDefinition;
use Drupal\rules\Engine\ExpressionInterface;
use Drupal\rules\Engine\ExpressionManagerInterface;
use Drupal\rules\Engine\RulesComponent;
use Drupal\rules\Engine\RulesStateInterface;
use Drupal\rules\Plugin\RulesExpression\Rule;
use Drupal\rules\Plugin\RulesExpression\RulesAnd;
use Drupal\rules\Plugin\RulesExpression\RulesOr;
use Drupal\rules\Plugin\RulesExpression\ActionSet;
use Prophecy\Argument;
use Prophecy\Prophecy\MethodProphecy;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\RulesExpression\Rule
 * @group rules
 */
class RuleTest extends RulesUnitTestBase {

  /**
   * The rules expression plugin manager.
   *
   * @var \Drupal\rules\Engine\ExpressionManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $expressionManager;

  /**
   * The rule being tested.
   *
   * @var \Drupal\rules\Plugin\RulesExpression\RuleInterface
   */
  protected $rule;

  /**
   * The primary condition container of the rule.
   *
   * @var \Drupal\rules\Engine\ConditionExpressionContainerInterface
   */
  protected $conditions;

  /**
   * The primary action container of the rule.
   *
   * @var \Drupal\rules\Engine\ActionExpressionContainerInterface
   */
  protected $actions;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->expressionManager = $this->prophesize(ExpressionManagerInterface::class);

    $this->conditions = new RulesAnd([], 'rules_and', [], $this->expressionManager->reveal());
    $this->expressionManager->createInstance('rules_and', [])->willReturn($this->conditions);

    $this->actions = new ActionSet([], 'rules_action_set', [], $this->expressionManager->reveal());
    $this->expressionManager->createInstance('rules_action_set', [])->willReturn($this->actions);

    $this->rule = new Rule([], 'rules_rule', [], $this->expressionManager->reveal());
  }

  /**
   * Tests that a rule is constructed with condition and action containers.
   *
   * @covers ::__construct
   */
  public function testContainersOnConstruct() {
    $this->assertSame($this->conditions, $this->rule->getConditions());
    $this->assertSame($this->actions, $this->rule->getActions());
  }

  /**
   * Tests the condition container setter and getter.
   *
   * @covers ::setConditions
   * @covers ::getConditions
   */
  public function testSetConditionsGetConditions() {
    $or = new RulesOr([], 'rules_or', [], $this->expressionManager->reveal());
    $this->rule->setConditions($or);
    $this->assertSame($or, $this->rule->getConditions());

    $and = new RulesAnd([], 'rules_and', [], $this->expressionManager->reveal());
    $this->rule->setConditions($and);
    $this->assertSame($and, $this->rule->getConditions());
  }

  /**
   * Tests the condition container setter and getter.
   *
   * @covers ::setActions
   * @covers ::getActions
   */
  public function testSetActionsGetActions() {
    $action_set = new ActionSet([], '', [], $this->expressionManager->reveal());
    $this->rule->setActions($action_set);
    $this->assertSame($action_set, $this->rule->getActions());
  }

  /**
   * Tests that an action fires if a condition passes.
   *
   * @covers ::execute
   */
  public function testActionExecution() {
    // The method on the test action must be called once.
    $this->testActionExpression->executeWithState(
      Argument::type(RulesStateInterface::class))->shouldBeCalledTimes(1);

    $this->rule
      ->addExpressionObject($this->trueConditionExpression->reveal())
      ->addExpressionObject($this->testActionExpression->reveal())
      ->execute();
  }

  /**
   * Tests that an action does not fire if a condition fails.
   *
   * @covers ::execute
   */
  public function testConditionFails() {
    // The execute method on the action must never be called.
    $this->testActionExpression->executeWithState(
      Argument::type(RulesStateInterface::class))->shouldNotBeCalled();

    $this->rule
      ->addExpressionObject($this->falseConditionExpression->reveal())
      ->addExpressionObject($this->testActionExpression->reveal())
      ->execute();
  }

  /**
   * Tests that an action fires if a condition passes.
   *
   * @covers ::execute
   */
  public function testTwoConditionsTrue() {
    // The method on the test action must be called once.
    $this->testActionExpression->executeWithState(
      Argument::type(RulesStateInterface::class))->shouldBeCalledTimes(1);

    $this->rule
      ->addExpressionObject($this->trueConditionExpression->reveal())
      ->addExpressionObject($this->trueConditionExpression->reveal())
      ->addExpressionObject($this->testActionExpression->reveal())
      ->execute();
  }

  /**
   * Tests that an action does not fire if a condition fails.
   *
   * @covers ::execute
   */
  public function testTwoConditionsFalse() {
    // The execute method on the action must never be called.
    $this->testActionExpression->executeWithState(
      Argument::type(RulesStateInterface::class))->shouldNotBeCalled();

    $this->rule
      ->addExpressionObject($this->trueConditionExpression->reveal())
      ->addExpressionObject($this->falseConditionExpression->reveal())
      ->addExpressionObject($this->testActionExpression->reveal())
      ->execute();
  }

  /**
   * Tests that nested rules are properly executed.
   *
   * @covers ::execute
   */
  public function testNestedRules() {
    $this->testActionExpression->executeWithState(
      Argument::type(RulesStateInterface::class))->shouldBeCalledTimes(1);

    $nested = new Rule([], 'rules_rule', [], $this->expressionManager->reveal());
    // We need to replace the action and conditon container to not have the same
    // instances as in the outer rule.
    $nested->setConditions(new RulesAnd([], 'rules_and', [], $this->expressionManager->reveal()));
    $nested->setActions(new ActionSet([], 'rules_action_set', [], $this->expressionManager->reveal()));

    $nested->addExpressionObject($this->trueConditionExpression->reveal())
      ->addExpressionObject($this->testActionExpression->reveal());

    $this->rule
      ->addExpressionObject($this->trueConditionExpression->reveal())
      ->addExpressionObject($nested)
      ->execute();
  }

  /**
   * Tests executing a rule given some context.
   */
  public function testRuleExecutionWithContext() {
    $rule = new Rule([], 'rules_rule', [], $this->expressionManager->reveal());
    $expression = $this->prophesize(ExpressionInterface::class);
    $prophecy = $expression->executeWithState(Argument::any());
    /** @var $prophecy \Prophecy\Prophecy\MethodProphecy */
    $prophecy
      ->shouldBeCalledTimes(1);
    $rule->addExpressionObject($expression->reveal());
    $entity = $this->prophesize(EntityInterface::class);

    RulesComponent::create($rule)
      ->addContextDefinition('node', ContextDefinition::create('entity:node')
        ->setLabel('node'))
      ->setContextValue('node', $entity)
      ->execute();
  }

}
