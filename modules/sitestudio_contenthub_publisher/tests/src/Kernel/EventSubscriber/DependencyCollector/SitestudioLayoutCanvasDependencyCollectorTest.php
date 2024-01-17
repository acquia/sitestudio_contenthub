<?php

namespace Drupal\Tests\sitestudio_contenthub_publisher\Kernel\EventSubscriber\DependencyCollector;

use Drupal\cohesion_elements\Entity\Component;
use Drupal\cohesion_elements\Entity\ComponentCategory;
use Drupal\cohesion_elements\Entity\ComponentContent;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\sitestudio_contenthub_publisher\EventSubscriber\DependencyCollector\SitestudioLayoutCanvasDependencyCollector;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;

/**
 * Tests for CohesionLayout entity dependency collection.
 *
 * @group Cohesion
 *
 * @package Drupal\Tests\sitestudio_contenthub_publisher\Kernel\EventSubscriber\DependencyCollector
 *
 * @covers \Drupal\sitestudio_contenthub_publisher\EventSubscriber\DependencyCollector\SitestudioLayoutCanvasDependencyCollector::onCalculateDependencies
 */
class SitestudioLayoutCanvasDependencyCollectorTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'acquia_contenthub',
    'cohesion',
    'cohesion_elements',
    'cohesion_templates',
    'sitestudio_contenthub_subscriber',
    'sitestudio_contenthub_publisher',
    'entity_reference_revisions',
    'file',
    'node',
  ];

  const JSON_VALUES = '{"canvas":[{"uid":"cpt_test_component","type":"component","title":"Test Component","enabled":true,"category":"category-10","componentId":"cpt_test_component","componentType":"link","uuid":"d23a59d5-c8d7-49b8-9cda-61fa01aadf55","parentUid":"root","children":[]},{"uid":"cpt_test_component","type":"component","title":"Test Component","enabled":true,"category":"category-10","componentId":"cpt_test_component","componentType":"link","uuid":"19c96fe4-db43-4f2f-8c91-8081b7a948fb","parentUid":"root","children":[]},{"uid":"cpt_test_component","type":"component","title":"Test Component","enabled":true,"category":"category-10","componentId":"cpt_test_component","componentType":"link","uuid":"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c","parentUid":"root","children":[]},{"uid":"cpt_links_pattern_repeater","type":"component","title":"Links pattern repeater","enabled":true,"category":"category-4","componentId":"cpt_links_pattern_repeater","componentType":"component-pattern-repeater","uuid":"e7d1a1ac-abef-4971-9273-9d25bcc8db79","parentUid":"root","children":[]}],"mapper":{},"model":{"d23a59d5-c8d7-49b8-9cda-61fa01aadf55":{"settings":{"title":"Test Component"},"4793e582-fe13-490c-99d2-badcce843df7":"node::64"},"19c96fe4-db43-4f2f-8c91-8081b7a948fb":{"settings":{"title":"Test Component"},"4793e582-fe13-490c-99d2-badcce843df7":"node::56"},"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c":{"settings":{"title":"Test Component"},"4793e582-fe13-490c-99d2-badcce843df7":"https:\/\/www.drupal.org\/"},"e7d1a1ac-abef-4971-9273-9d25bcc8db79":{"settings":{"title":"Links pattern repeater"},"d1653d6c-68ef-4b15-aabb-fdf3daf5064e":[{"f1a9f274-f030-47d5-a831-79a23da8a59e":"External","abdc6059-1b0d-498e-ac6a-daa58cbb85fb":"https:\/\/www.drupal.org\/"},{"f1a9f274-f030-47d5-a831-79a23da8a59e":"Node 1","abdc6059-1b0d-498e-ac6a-daa58cbb85fb":"node::4"},{"f1a9f274-f030-47d5-a831-79a23da8a59e":"Node 2","abdc6059-1b0d-498e-ac6a-daa58cbb85fb":"node::3"}]}},"previewModel":{"d23a59d5-c8d7-49b8-9cda-61fa01aadf55":{},"19c96fe4-db43-4f2f-8c91-8081b7a948fb":{},"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c":{},"e7d1a1ac-abef-4971-9273-9d25bcc8db79":{}},"variableFields":{"d23a59d5-c8d7-49b8-9cda-61fa01aadf55":[],"19c96fe4-db43-4f2f-8c91-8081b7a948fb":[],"ebafa6a9-b0b8-49cb-a481-d8c123c52e1c":[],"e7d1a1ac-abef-4971-9273-9d25bcc8db79":[]},"meta":{"fieldHistory":[]}}';
  const NODE_UUIDS = [
    '3' => 'a48e537a-6ec9-4191-b518-ed5e6d559a12',
    '4' => 'f8c3512b-aa9e-4008-8bf3-c03193ca3afa',
    '56' => 'ae85e706-809b-4c6f-9ef5-0a0f6779d6c8',
    '64' => 'dd871855-28df-475a-9d93-6cb4cba62d27',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('cohesion', ['coh_usage']);
    $this->installEntitySchema('cohesion_layout');
    $this->installEntitySchema('component_content');
    $this->installConfig('node');
    $this->installConfig('field');
    $this->installSchema('node', 'node_access');
    \Drupal::moduleHandler()->loadInclude('acquia_contenthub_subscriber', 'install');
    \Drupal::moduleHandler()->loadInclude('acquia_contenthub_publisher', 'install');
    \Drupal::moduleHandler()->loadInclude('cohesion', 'install');
    \Drupal::moduleHandler()->loadInclude('cohesion_elements', 'install');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // Create a content type.
    NodeType::create([
      'type' => 'test',
      'name' => 'test',
    ])->save();

    foreach (self::NODE_UUIDS as $id => $uuid) {
      $values = [
        'title' => 'test title ' . $id,
        'nid' => $id,
        'uuid' => $uuid,
        'type' => 'test'
        ];
      Node::create($values)->save();
    }

    $category_storage = $this->entityTypeManager->getStorage('cohesion_component_category');
    \Drupal::service('cohesion_elements.category_relationships')->createUncategorized($category_storage, ComponentCategory::DEFAULT_CATEGORY_ID);
    $component_category = ComponentCategory::load(ComponentCategory::DEFAULT_CATEGORY_ID);

    $component = Component::create([
      'id' => 'test_component',
      'json_values' => '{}',
      'category' => $component_category->id(),
    ]);
    $component->save();

    // Create a new component content.
    $component_content = ComponentContent::create([
      'title' => 'Test Component',
      'component' => 'test_component',
      ]);
    $component_content->save();

    $this->cohesion_layout = CohesionLayout::create([
      'json_values' => self::JSON_VALUES,
      'uuid' => '3a68e76a-d224-4fff-9737-a13cfa481165',
      'id' => '113',
      'parent_type' => $component_content->getEntityTypeId(),
      'parent_field_name' => 'field_layout_canvas',
    ]);
    $this->cohesion_layout->save();
  }

  public function testOnCalculateDependencies() {
    $subscriber = new SitestudioLayoutCanvasDependencyCollector(\Drupal::entityTypeManager());

    $wrapper = new DependentEntityWrapper($this->cohesion_layout);
    $stack = new DependencyStack();
    $event = new CalculateEntityDependenciesEvent($wrapper, $stack);

    $this->assertEquals([], $event->getDependencies());

    $subscriber->onCalculateDependencies($event);

    $dependencies = $event->getDependencies();
    foreach (self::NODE_UUIDS as $node_uuid) {
      $this->assertArrayHasKey($node_uuid, $dependencies);
      $this->assertInstanceOf(DependentEntityWrapper::class, $dependencies[$node_uuid]);
    }
  }


}
