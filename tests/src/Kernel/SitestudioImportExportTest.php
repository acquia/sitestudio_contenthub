<?php

namespace Drupal\Tests\sitestudio_contenthub\Kernel;

/**
 * Class CohesionImportExportTest.
 *
 * @group Cohesion
 *
 * @requires module cohesion
 * @requires module acquia_contenthub
 * @requires module sitestudio_contenthub_publisher
 * @requires module sitestudio_contenthub_subscriber
 *
 * @package Drupal\Tests\sitestudio_contenthub\Kernel
 */
class SitestudioImportExportTest extends SitestudioContentHubImportExportTest {

  const ENTITY_REFERENCE_TYPES = [
    'file',
    'entity_reference',
    'entity_reference_revisions',
    'cohesion_entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected $fixtures = [
    [
      'cdf' => 'sitestudio/component_content.json',
      'expectations' => 'expectations/sitestudio/component_content.php',
    ],
  ];

  protected static $configSchemaCheckerExclusions = [
    'cohesion_templates.cohesion_content_templates.user_user_compact',
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'node',
    'field',
    'cohesion',
    'acquia_contenthub',
    'cohesion_elements',
    'sitestudio_contenthub_subscriber',
    'sitestudio_contenthub_publisher',
    'file',
    'entity_reference_revisions',
    'token',
    'cohesion_templates',
    'image',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->installEntitySchema('cohesion_layout');
    $this->installEntitySchema('component_content');
    $this->installSchema('cohesion', ['coh_usage']);
    \Drupal::moduleHandler()->loadInclude('cohesion', 'install');
    \Drupal::moduleHandler()->loadInclude('cohesion_elements', 'install');
    \Drupal::moduleHandler()->loadInclude('acquia_contenthub_subscriber', 'install');
    \Drupal::moduleHandler()->loadInclude('acquia_contenthub_publisher', 'install');
    \Drupal::moduleHandler()->loadInclude('sitestudio_contenthub_publisher', 'install');
    \Drupal::moduleHandler()->loadInclude('sitestudio_contenthub_subscriber', 'install');
  }

  /**
   * Tests sitestudio.
   *
   * @param mixed $args
   *   Data.
   *
   * @dataProvider sitestudioDataProvider
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSitestudio(...$args) {

    $uuids = [
      '90626630-68d9-4c22-951d-8559a7eca2a7',
    ];
    $count_uuid = 0;

    $current_uuid = $this->container->get('uuid');

    $uuid = $this->createMock('Drupal\Component\Uuid\Php');
    $uuid->expects($this->any())
      ->method('generate')
      ->will($this->returnCallback(function () use ($uuids, &$count_uuid, $current_uuid) {

        $backtrace = debug_backtrace();
        foreach ($backtrace as $trace) {
          if ($trace['function'] == 'resetElementsUUIDs') {
            $uuid = $uuids[$count_uuid];
            $count_uuid++;
            return $uuid;
          }
        }

        return $current_uuid->generate();
      }));

    \Drupal::service('file_system')->mkdir(COHESION_TEMPLATE_PATH, 0777, FALSE);

    $this->container->set('uuid', $uuid);
    \Drupal::setContainer($this->container);
    parent::contentEntityImportExport(...$args);
  }

  /**
   * Data provider for testSitestudio.
   */
  public function sitestudioDataProvider() {
    yield [
      0,
      [
        [
          'type' => 'component_content',
          'uuid' => 'ced8e818-f68a-4f7d-8408-6fbf61166874',
        ],
        [
          'type' => 'cohesion_layout',
          'uuid' => 'c255b7e7-5fef-4e24-83cf-fe0a777c5cfb',
        ],
      ],
      'component_content',
      'ced8e818-f68a-4f7d-8408-6fbf61166874',
    ];
  }

}
