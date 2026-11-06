<?php

namespace Drupal\Tests\sitestudio_contenthub_publisher\Unit\EventSubscriber\Cdf;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent;
use Drupal\cohesion\LayoutCanvas\LayoutCanvas;
use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\sitestudio_contenthub_publisher\EventSubscriber\Cdf\CohesionLayoutSerializationHandler;
use Drupal\Tests\UnitTestCase;

/**
 * Tests link serialization of CohesionLayout translations.
 *
 * @group Cohesion
 *
 * @covers \Drupal\sitestudio_contenthub_publisher\EventSubscriber\Cdf\CohesionLayoutSerializationHandler
 */
class CohesionLayoutSerializationHandlerTest extends UnitTestCase {

  const NODE_UUIDS = [
    '64' => 'dd871855-28df-475a-9d93-6cb4cba62d27',
    '56' => 'ae85e706-809b-4c6f-9ef5-0a0f6779d6c8',
  ];
  const LINK_PATH = '4793e582-fe13-490c-99d2-badcce843df7';
  const ELEMENT_ONE = 'd23a59d5-c8d7-49b8-9cda-61fa01aadf55';
  const ELEMENT_TWO = '19c96fe4-db43-4f2f-8c91-8081b7a948fb';

  const URL = 'https://example.com';

  /**
   * Builds layout canvas JSON with one link component per value.
   *
   * @param array $links
   *   Link values keyed by element UUID.
   */
  protected function canvasJson(array $links): string {
    $canvas = [];
    $model = [];
    foreach ($links as $uuid => $value) {
      $canvas[] = [
        'uid' => 'cpt_test_component',
        'type' => 'component',
        'title' => 'Test Component',
        'enabled' => TRUE,
        'category' => 'category-10',
        'componentId' => 'cpt_test_component',
        'componentType' => 'link',
        'uuid' => $uuid,
        'parentUid' => 'root',
        'children' => [],
      ];
      $model[$uuid] = [
        'settings' => ['title' => 'Test Component'],
        self::LINK_PATH => $value,
      ];
    }
    return json_encode([
      'canvas' => $canvas,
      'mapper' => new \stdClass(),
      'model' => $model,
      'previewModel' => new \stdClass(),
      'variableFields' => new \stdClass(),
      'meta' => ['fieldHistory' => []],
    ]);
  }

  /**
   * Builds a layout mock, optionally with translations.
   */
  protected function layout(string $json, array $translations = []): CohesionLayout {
    $layout = $this->getMockBuilder(CohesionLayout::class)
      ->disableOriginalConstructor()
      ->getMock();
    $layout->method('getLayoutCanvasInstance')
      ->willReturn(new LayoutCanvas($json));
    $layout->method('getTranslationLanguages')
      ->willReturn(array_fill_keys(array_keys($translations), new \stdClass()));
    $layout->method('getTranslation')
      ->willReturnCallback(fn ($langcode) => $translations[$langcode]);
    return $layout;
  }

  /**
   * Builds the handler with node storage that resolves the known nodes.
   */
  protected function handler(): CohesionLayoutSerializationHandler {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(function ($id) {
      if (!isset(self::NODE_UUIDS[$id])) {
        return NULL;
      }
      $node = $this->createMock(NodeInterface::class);
      $node->method('uuid')->willReturn(self::NODE_UUIDS[$id]);
      return $node;
    });
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturnCallback(fn ($type) => $type === 'node');
    $manager->method('getStorage')->willReturn($storage);
    return new CohesionLayoutSerializationHandler($manager);
  }

  /**
   * Builds the serialize event.
   */
  protected function event(CohesionLayout $layout): SerializeAdditionalMetadataEvent {
    $timestamp = date('c');
    $cdf = new CDFObject('drupal8_content_entity', 'uuid', $timestamp, $timestamp, 'origin', ['languages' => ['en']]);
    return new SerializeAdditionalMetadataEvent($layout, $cdf);
  }

  /**
   * Tests every link in the default language is converted to a UUID.
   */
  public function testDefaultLinksAreConverted() {
    $layout = $this->layout($this->canvasJson([
      self::ELEMENT_ONE => 'node::64',
      self::ELEMENT_TWO => 'node::56',
    ]));
    $event = $this->event($layout);
    $this->handler()->onSerializeCohesionLayout($event);

    $metadata = $event->getCdf()->getMetadata();
    $links = $metadata['additional_data']['processed_links'];
    $this->assertSame(self::NODE_UUIDS['64'], $links[self::ELEMENT_ONE][0]['entity_id']);
    $this->assertSame(self::NODE_UUIDS['56'], $links[self::ELEMENT_TWO][0]['entity_id']);
    $this->assertArrayNotHasKey('processed_links_translations', $metadata['additional_data']);
    $this->assertTrue($event->isPropagationStopped());
  }

  /**
   * Tests a translation-only link is exported under its own key.
   */
  public function testTranslationOnlyLinks() {
    $translation = $this->layout($this->canvasJson([self::ELEMENT_ONE => 'node::56']));
    $layout = $this->layout($this->canvasJson([self::ELEMENT_ONE => self::URL]), ['de' => $translation]);
    $event = $this->event($layout);
    $this->handler()->onSerializeCohesionLayout($event);

    $metadata = $event->getCdf()->getMetadata();
    $this->assertSame([], $metadata['additional_data']['processed_links']);
    $this->assertSame(
      self::NODE_UUIDS['56'],
      $metadata['additional_data']['processed_links_translations']['de'][self::ELEMENT_ONE][0]['entity_id']
    );
    $this->assertTrue($event->isPropagationStopped());
  }

  /**
   * Tests a translation without links is not exported.
   */
  public function testTranslationWithoutLinksIsOmitted() {
    $translation = $this->layout($this->canvasJson([self::ELEMENT_ONE => self::URL]));
    $layout = $this->layout($this->canvasJson([self::ELEMENT_ONE => 'node::64']), ['de' => $translation]);
    $event = $this->event($layout);
    $this->handler()->onSerializeCohesionLayout($event);

    $this->assertArrayNotHasKey('processed_links_translations', $event->getCdf()->getMetadata()['additional_data']);
  }

  /**
   * Tests nothing is written when no language has links.
   */
  public function testNoLinksLeavesEventUntouched() {
    $translation = $this->layout($this->canvasJson([self::ELEMENT_ONE => self::URL]));
    $layout = $this->layout($this->canvasJson([self::ELEMENT_ONE => self::URL]), ['de' => $translation]);
    $event = $this->event($layout);
    $this->handler()->onSerializeCohesionLayout($event);

    $this->assertArrayNotHasKey('additional_data', $event->getCdf()->getMetadata());
    $this->assertFalse($event->isPropagationStopped());
  }

}
