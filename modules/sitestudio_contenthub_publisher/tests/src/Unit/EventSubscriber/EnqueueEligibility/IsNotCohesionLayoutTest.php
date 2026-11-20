<?php

namespace Drupal\Tests\sitestudio_contenthub_publisher\Unit\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\sitestudio_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsNotCohesionLayout;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for directly enqueing CohesionLayout entities.
 *
 * @group Cohesion
 *
 * @package Drupal\Tests\acquia_contenthub_publisher\Unit\EventSubscriber\EntityEligibility
 *
 * @covers \Drupal\sitestudio_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsNotCohesionLayout::onEnqueueCandidateEntity
 */
#[Group('Cohesion')]
#[CoversMethod(\Drupal\sitestudio_contenthub_publisher\EventSubscriber\EnqueueEligibility\IsNotCohesionLayout::class, 'onEnqueueCandidateEntity')]
class IsNotCohesionLayoutTest extends UnitTestCase {

  public function testCohesionConfigEligibility() {
    // Setup our files for testing.
    $config = $this->prophesize(CohesionLayout::class);

    // This is the thing we're actually going to test.
    $subscriber = new IsNotCohesionLayout();

    // Test insert.
    $event = new ContentHubEntityEligibilityEvent($config->reveal(), 'insert');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertFalse($event->getEligibility());

    // Test update.
    $event = new ContentHubEntityEligibilityEvent($config->reveal(), 'update');
    $subscriber->onEnqueueCandidateEntity($event);
    $this->assertFalse($event->getEligibility());
  }

}
