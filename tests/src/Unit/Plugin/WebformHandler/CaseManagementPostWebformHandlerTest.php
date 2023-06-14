<?php

namespace Drupal\Tests\bhcc_case_management\Unit\Plugin\WebformHandler;

use Drupal\bhcc_case_management\Plugin\WebformHandler\CaseManagementPostWebformHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformMessageManagerInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformTokenManagerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Unit tests for the CaseManagementPostWebformHandler class.
 *
 * @coversDefaultClass Drupal\bhcc_case_management\Plugin\WebformHandler\CaseManagementPostWebformHandler
 * @group bhcc_case_management
 */
class CaseManagementPostWebformHandlerTest extends UnitTestCase {

  /**
   * This is what we are testing.
   *
   * @var Drupal\bhcc_case_management\Plugin\WebformHandler\CaseManagementPostWebformHandler
   */
  protected $testTarget;

  /**
   * Webform configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * Plugin Definition.
   *
   * @var mixed
   */
  protected $pluginDefinition;

  /**
   * Logger factory.
   *
   * @var Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Conditions validator.
   *
   * @var Drupal\webform\WebformSubmissionConditionsValidatorInterface
   */
  protected $conditionsValidator;

  /**
   * Http client.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Message manager.
   *
   * @var Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager;

  /**
   * Webform token manager.
   *
   * @var Drupal\webform\WebformTokenManagerInterface
   */
  protected $webformTokenManager;

  /**
   * Entity field manager.
   *
   * @var Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Set up mock objects.
   */
  public function setUp(): void {

    $this->mockServicesContainer();

    $this->configuration = [];
    $this->pluginId = 'bhcc_case_management';
    $this->pluginDefinition = NULL;
    $this->loggerFactory = $this->basicMockObject(LoggerChannelFactoryInterface::class);
    $this->configFactory = $this->basicMockObject(ConfigFactoryInterface::class);
    $this->entityTypeManager = $this->basicMockObject(EntityTypeManagerInterface::class);
    $this->conditionsValidator = $this->basicMockObject(WebformSubmissionConditionsValidatorInterface::class);
    $this->httpClient = $this->basicMockObject(ClientInterface::class);
    $this->messageManager = $this->basicMockObject(WebformMessageManagerInterface::class);

    $this->testTarget = new CaseManagementPostWebformHandler($this->configuration, $this->pluginId, $this->pluginDefinition, $this->loggerFactory, $this->configFactory, $this->entityTypeManager, $this->conditionsValidator, $this->httpClient, $this->messageManager);
  }

  /**
   * Mock a basic (null) object.
   *
   * @param Class $objectClass
   *   A class / interface to mock.
   *
   * @return Object
   *   Mock object.
   */
  protected function basicMockObject($objectClass) {
    return $this->getMockBuilder($objectClass)
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Mock Drupal service container.
   */
  protected function mockServicesContainer() {

    // Create a new container object.
    $container = new ContainerBuilder();

    // Add basic webform.token_manager service to the container.
    $this->webformTokenManager = $this->basicMockObject(WebformTokenManagerInterface::class);
    $this->entityFieldManager = $this->basicMockObject(EntityFieldManagerInterface::class);
    $this->entityFieldManager->expects($this->any())
      ->method('getBaseFieldDefinitions')
      ->willReturn([]);
    $container->set('webform.token_manager', $this->webformTokenManager);
    $container->set('entity_field.manager', $this->entityFieldManager);

    // Let Drupal use the mock container.
    \Drupal::setContainer($container);
  }

  /**
   * Mock Webform submission class.
   *
   * @param array $submission_data
   *   Webform submission array in form key => value.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   Webform submission mock object.
   */
  protected function mockWebformSubmission(array $submission_data = []) {
    $webform_submission = $this->basicMockObject(WebformSubmissionInterface::class);
    $webform_submission->expects($this->once())
      ->method('getData')
      ->willReturn($submission_data);

    return $webform_submission;
  }

  /**
   * Mock Webform class.
   *
   * @param string $id
   *   Webform ID.
   * @param string $title
   *   Webform title.
   * @param string $path
   *   Webform path.
   * @param string $category
   *   Webform category.
   *
   * @return \Drupal\webform\WebformInterface
   *   Mock webform object.
   */
  protected function mockWebform($id, $title, $path, $category) {

    // Return the form url from url class.
    $url = $this->basicMockObject(Url::class);
    $url->expects($this->once())
      ->method('toString')
      ->willReturn($path);

    // Mock the webform class.
    $webform = $this->basicMockObject(WebformInterface::class);
    $webform->expects($this->once())
      ->method('id')
      ->willReturn($id);
    $webform->expects($this->once())
      ->method('label')
      ->willReturn($title);
    $webform->expects($this->once())
      ->method('toUrl')
      ->willReturn($url);
    $webform->expects($this->once())
      ->method('get')
      ->with('category')
      ->willReturn($category);

    return $webform;
  }

  /**
   * Mock Webform element.
   *
   * @param array $webform_elements
   *   Webform elements array, asscoiative array as the webform tree would be.
   * @param \Drupal\webform\WebformInterface &$webform
   *   (By reference) Existing webform mock object to add the elements to.
   */
  protected function mockWebformElement(array $webform_elements, &$webform) {
    $map = [];
    foreach ($webform_elements as $key => $element) {
      // Need to include false paremter here as used in the original class.
      $map[] = [$key, FALSE, $element];
    }
    $webform->expects($this->any())
      ->method('getElement')
      ->will($this->returnValueMap($map));
  }

  /**
   * Tests for hasDocumentFiles.
   *
   * We provide a webform that has a webform_document_file,
   * and expect to return TRUE with casekey intact.
   */
  public function testHasDocumentFiles() {

    // Set up casekey.
    $case_key = $this->randomMachineName(16);

    // Basic submission data.
    $submission_data = [
      'uploaded_document' => '1',
      'casekey' => $case_key,
    ];

    // Mock the webform class.
    $webform = $this->basicMockObject(WebformInterface::class);

    // Mock webform elements.
    $webform_elements = [
      'uploaded_document' => [
        '#type' => 'webform_document_file',
      ],
      'casekey' => [
        '#type' => 'value',
      ],
    ];
    $this->mockWebformElement($webform_elements, $webform);

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'hasDocumentFiles');
    $method->setAccessible(TRUE);
    // Use invokeArgs as need to pass webform_values by refernece.
    // See :http://nibralab.github.io/reflection-and-call-by-reference/
    $result = $method->invokeArgs(
        $this->testTarget,
        [
          &$submission_data,
          $webform,
        ]);

    // Assert that this is a simple case.
    $this->assertTrue($result);

    // Assert casekey is still present.
    $this->assertArrayHasKey('casekey', $submission_data);
    $this->assertEquals($case_key, $submission_data['casekey']);
  }

  /**
   * Tests for hasDocumentFiles.
   *
   * We provide a webform that does not have a webform_document_file,
   * and expect to return FASLE with casekey removed.
   */
  public function testHasNoDocumentFiles() {

    // Set up casekey.
    $case_key = $this->randomMachineName(16);

    // Basic submission data.
    $submission_data = [
      'not_an_uploaded_document' => '1',
      'casekey' => $case_key,
    ];

    // Mock the webform class.
    $webform = $this->basicMockObject(WebformInterface::class);

    // Mock webform elements.
    $webform_elements = [
      'not_an_uploaded_document' => [
        '#type' => 'webform_not_a_document_file',
      ],
      'casekey' => [
        '#type' => 'value',
      ],
    ];
    $this->mockWebformElement($webform_elements, $webform);

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'hasDocumentFiles');
    $method->setAccessible(TRUE);
    // Use invokeArgs as need to pass webform_values by refernece.
    // See :http://nibralab.github.io/reflection-and-call-by-reference/
    $result = $method->invokeArgs(
      $this->testTarget,
      [
        &$submission_data,
        $webform,
      ]);

    // Assert that this is a simple case.
    $this->assertFalse($result);

    // Assert casekey is not present.
    $this->assertArrayNotHasKey('casekey', $submission_data);
  }

  /**
   * Tests for handleElementSimpleCase() with simple array.
   *
   * We provide a simple array of answers (eg. ticked checkboxes)
   * and expect them to be transformed into newline seperated values.
   */
  public function testHandleElementSimpleCaseWithSimpleArray() {
    $webform_value = [
      'Answer one',
      'Answer two',
      'Answer three',
      'Answer four',
      'Answer_five',
    ];
    $expected_transform = "Answer one\nAnswer two\nAnswer three\nAnswer four\nAnswer_five";

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'handleElementSimpleCase');
    $method->setAccessible(TRUE);
    // Use invokeArgs as need to pass webform_values by refernece.
    // See :http://nibralab.github.io/reflection-and-call-by-reference/
    $result = $method->invokeArgs($this->testTarget, [&$webform_value]);

    // Assert that this is a simple case.
    $this->assertTrue($result);

    // Assert the webform value was changed.
    $this->assertEquals($expected_transform, $webform_value);
  }

  /**
   * Tests for handleElementSimpleCase() with multidimensional array.
   *
   * We provide a multidimensional array (like composite forms)
   * and expect that this is not transformed and not flagged as a simple case.
   */
  public function testHandleElementSimpleCaseWithMultidimensionalArray() {

    // Should we also test if the array has 0 as a key?
    $webform_value = [
      'key one' => 'Answer one',
      'key two' => 'Answer two',
      'key three' => ['Answer three', 'Answer four', 'Answer_five'],
    ];
    $expected_transform = $webform_value;

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'handleElementSimpleCase');
    $method->setAccessible(TRUE);
    $result = $method->invokeArgs($this->testTarget, [&$webform_value]);

    // Assert that this is a simple case.
    $this->assertFalse($result);

    // Assert the webform value was not changed.
    $this->assertEquals($expected_transform, $webform_value);
  }

  /**
   * Tests for handleElementSpecialCase() with single date.
   *
   * We provide a date array with the defined element types
   * and expect it to be transformed to a date string.
   */
  public function testHandleElementSpecialCaseDate() {
    $webform_value = [
      'day' => '01',
      'month' => '02',
      'year' => '1980',
    ];
    $expected_transform = '1980-02-01';
    $webform_element = [
      '#type' => 'bhcc_webform_date',
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'handleElementSpecialCase');
    $method->setAccessible(TRUE);
    $result = $method->invokeArgs($this->testTarget, [
      &$webform_value,
      $webform_element,
    ]);

    // Assert that this is a special case.
    $this->assertTrue($result);

    // Assert the webform value was changed.
    $this->assertEquals($webform_value, $expected_transform);

    // Also test with bhcc_webform_date_of_birth.
    $webform_value_dob = [
      'day' => '01',
      'month' => '02',
      'year' => '1980',
    ];
    $expected_transform_dob = '1980-02-01';
    $webform_element_dob = [
      '#type' => 'bhcc_webform_date_of_birth',
    ];
    $result = $method->invokeArgs(
      $this->testTarget,
      [
        &$webform_value_dob,
        $webform_element_dob,
      ]);

    // Assert that this is a special case.
    $this->assertTrue($result);

    // Assert the webform value was changed.
    $this->assertEquals($expected_transform_dob, $webform_value_dob);

  }

  /**
   * Tests for handleElementSpecialCase() with multiple dates.
   *
   * We provide a multidimensional date array with the defined element type
   * and expect it to be transformed to a newline seperated date string.
   */
  public function testHandleElementSpecialCaseMultipleDates() {
    $webform_value = [
      [
        'day' => '01',
        'month' => '02',
        'year' => '1980',
      ],
      [
        'day' => '03',
        'month' => '04',
        'year' => '1990',
      ],
      [
        'day' => '05',
        'month' => '06',
        'year' => '2000',
      ],
    ];
    $expected_transform = "1980-02-01\n1990-04-03\n2000-06-05";
    $webform_element = [
      '#type' => 'bhcc_webform_date',
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'handleElementSpecialCase');
    $method->setAccessible(TRUE);
    $result = $method->invokeArgs(
      $this->testTarget,
      [
        &$webform_value,
        $webform_element,
      ]);

    // Assert that this is a special case.
    $this->assertTrue($result);

    // Assert the webform value was changed.
    $this->assertEquals($expected_transform, $webform_value);

  }

  /**
   * Tests for handleElementSpecialCase() when not a special case.
   *
   * We provide a multidimensional array with a non special element type
   * and expect it to not be transformed and flagged as a special case.
   */
  public function testHandleElementSpecialCaseNotSpecial() {
    $webform_value = [
      'Key one' => 'answer one',
      'Key two' => 'answer two',
      'Key three' => 'answer three',
    ];
    $webform_element = [
      '#type' => 'not_special',
    ];
    $expected_value = $webform_value;

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'handleElementSpecialCase');
    $method->setAccessible(TRUE);
    $result = $method->invokeArgs(
      $this->testTarget,
      [
        &$webform_value,
        $webform_element,
      ]);

    // Assert that this is not a special case.
    $this->assertFalse($result);

    // Assert the webform value was not changed.
    $this->assertEquals($expected_value, $webform_value);
  }

  /**
   * Tests for prepareResponsePayload with a simple answer.
   *
   * We provide the question and answer
   * and expect the assembled payload array.
   * Testing this first as composite uses this method for individual answers.
   */
  public function testPrepareResponsePayloadSimpleAnswer() {
    $question = 'Question one';
    $answer = 'Answer one';
    $machine_name = 'question_one';
    $webform_element = [];

    $expected = [
      'question'     => 'Question one',
      'answer'       => 'Answer one',
      'machine_name' => 'question_one',
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'prepareResponsePayload');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $question, $answer, $machine_name, $webform_element);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for prepareResponsePayload with multiple answers.
   *
   * We provide the question and a numerical keyed array answer
   * and expect the assembled payload array with each answer newline seperated.
   * This simulates answers from checkboxes.
   */
  public function testPrepareResponsePayloadMultipleAnswer() {
    $question = 'Question one';
    $answer = ['Answer one', 'Answer two', 'Answer three'];
    $machine_name = 'question_one';
    $webform_element = [];

    $expected = [
      'question'     => 'Question one',
      'answer'       => "Answer one\nAnswer two\nAnswer three",
      'machine_name' => 'question_one',
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'prepareResponsePayload');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $question, $answer, $machine_name, $webform_element);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for prepareResponsePayload with date answer.
   *
   * We provide the question and a bhcc_date array answer
   * and expect the assembled payload array with each answer / seperated.
   */
  public function testPrepareResponsePayloadDateAnswer() {
    $question = 'Question one';
    $answer = [
      'day' => '01',
      'month' => '02',
      'year' => '1980',
    ];
    $machine_name = 'question_one';
    $webform_element = [
      '#type' => 'bhcc_webform_date',
    ];

    $expected = [
      'question'     => 'Question one',
      'answer'       => '1980-02-01',
      'machine_name' => 'question_one',
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'prepareResponsePayload');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $question, $answer, $machine_name, $webform_element);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for prepareCompositePayload with a simple composite answer.
   *
   * We provide a named keyed array and the webform element
   * and expect the formatted composite payload.
   */
  public function testPrepareCompositePayloadSimpleComposite() {
    $values = [
      'simple_question_one'   => 'Simple answer one',
      'simple_question_two'   => 'Simple answer two',
      'simple_question_three' => 'Simple answer three',
    ];
    $webform_element = [
      '#webform_composite_elements' => [
        'simple_question_one' => [
          '#title' => 'Simple question one',
        ],
        'simple_question_two' => [
          '#title' => 'Simple question two',
        ],
        'simple_question_three' => [
          '#title' => 'Simple question three',
        ],
      ],
    ];

    $expected = [
      [
        [
          'question'     => 'Simple question one',
          'answer'       => 'Simple answer one',
          'machine_name' => 'simple_question_one',
        ],
        [
          'question'     => 'Simple question two',
          'answer'       => 'Simple answer two',
          'machine_name' => 'simple_question_two',
        ],
        [
          'question'     => 'Simple question three',
          'answer'       => 'Simple answer three',
          'machine_name' => 'simple_question_three',
        ],
      ],
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'prepareCompositePayload');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $values, $webform_element);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for prepareCompositePayload with a multiple delta composite answer.
   *
   * We provide a multidimensional array of answers and the webform element
   * and expect the formatted composite payload.
   */
  public function testPrepareCompositePayloadMultipleComposite() {
    $values = [
      [
        'simple_question_one'   => 'Simple answer one group one',
        'simple_question_two'   => 'Simple answer two group one',
        'simple_question_three' => 'Simple answer three group one',
      ],
      [
        'simple_question_one'   => 'Simple answer one group two',
        'simple_question_two'   => 'Simple answer two group two',
        'simple_question_three' => 'Simple answer three group two',
      ],
      [
        'simple_question_one'   => 'Simple answer one group three',
        'simple_question_two'   => 'Simple answer two group three',
        'simple_question_three' => 'Simple answer three group three',
      ],
    ];
    $webform_element = [
      '#webform_composite_elements' => [
        'simple_question_one' => [
          '#title' => 'Simple question one',
        ],
        'simple_question_two' => [
          '#title' => 'Simple question two',
        ],
        'simple_question_three' => [
          '#title' => 'Simple question three',
        ],
      ],
    ];

    $expected = [
      [
        [
          'question'     => 'Simple question one',
          'answer'       => 'Simple answer one group one',
          'machine_name' => 'simple_question_one',
        ],
        [
          'question'     => 'Simple question two',
          'answer'       => 'Simple answer two group one',
          'machine_name' => 'simple_question_two',
        ],
        [
          'question'     => 'Simple question three',
          'answer'       => 'Simple answer three group one',
          'machine_name' => 'simple_question_three',
        ],
      ],
      [
        [
          'question'     => 'Simple question one',
          'answer'       => 'Simple answer one group two',
          'machine_name' => 'simple_question_one',
        ],
        [
          'question'     => 'Simple question two',
          'answer'       => 'Simple answer two group two',
          'machine_name' => 'simple_question_two',
        ],
        [
          'question'     => 'Simple question three',
          'answer'       => 'Simple answer three group two',
          'machine_name' => 'simple_question_three',
        ],
      ],
      [
        [
          'question'     => 'Simple question one',
          'answer'       => 'Simple answer one group three',
          'machine_name' => 'simple_question_one',
        ],
        [
          'question'     => 'Simple question two',
          'answer'       => 'Simple answer two group three',
          'machine_name' => 'simple_question_two',
        ],
        [
          'question'     => 'Simple question three',
          'answer'       => 'Simple answer three group three',
          'machine_name' => 'simple_question_three',
        ],
      ],
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'prepareCompositePayload');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $values, $webform_element);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for handleCompositeElementSpecialCase() with address lookup field.
   *
   * We provide an addresslookup array with the defined element types
   * and expect a composite to be returned like using an address field.
   *
   * @todo move this to the address_lookup element, and test it is invoked.
   */
  public function testHandleElementSpecialCaseAddressLookup() {
    $webform_value = [
      'address_1' => 'Flat 1',
      'address_2' => '123 Fake St',
      'town_city' => 'Brighton',
      'postcode' => 'BN1 1AA',
    ];
    $expected_transform = [
      [
        'question'     => 'Address 1',
        'answer'       => 'Flat 1',
        'machine_name' => 'address_1',
      ],
      [
        'question'     => 'Address 2',
        'answer'       => '123 Fake St',
        'machine_name' => 'address_2',
      ],
      [
        'question'     => 'Town/City',
        'answer'       => 'Brighton',
        'machine_name' => 'town_city',
      ],
      [
        'question'     => 'Postcode',
        'answer'       => 'BN1 1AA',
        'machine_name' => 'postcode',
      ],
    ];
    $webform_element = [
      '#type' => 'bhcc_central_hub_webform_uk_address',
      '#webform_composite_elements' => [
        'address_lookup' => [
          '#title' => 'Address Lookup',
        ],
        'address_entry' => [
          '#title' => 'Address Entry',
          'address_1' => [
            '#title' => 'Address 1',
          ],
          'address_2' => [
            '#title' => 'Address 2',
          ],
          'town_city' => [
            '#title' => 'Town/City',
          ],
          'postcode' => [
            '#title' => 'Postcode',
          ],
        ],
      ],
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'handleCompositeElementSpecialCase');
    $method->setAccessible(TRUE);
    $result = $method->invokeArgs(
      $this->testTarget,
      [
        &$webform_value,
        $webform_element,
      ]);

    // Assert that this is a special case.
    $this->assertTrue($result);

    // Assert the webform value was changed.
    $this->assertEquals($expected_transform, $webform_value);

  }

  /**
   * Tests for prepareResponsePayload with a composite answer.
   *
   * We provide a composite question and answer
   * and expect the assembled payload array.
   * This should use prepareCompositePayload which in terms calls this method
   * recursvley to assmeble the payload response.
   */
  public function testPrepareResponsePayloadCompositeAnswer() {
    $question = 'Composite Question';
    $answer = [
      [
        'simple_question_one'   => 'Simple answer one group one',
        'simple_question_two'   => 'Simple answer two group one',
        'simple_question_three' => 'Simple answer three group one',
      ],
      [
        'simple_question_one'   => 'Simple answer one group two',
        'simple_question_two'   => 'Simple answer two group two',
        'simple_question_three' => 'Simple answer three group two',
      ],
      [
        'simple_question_one'   => 'Simple answer one group three',
        'simple_question_two'   => 'Simple answer two group three',
        'simple_question_three' => 'Simple answer three group three',
      ],
    ];
    $machine_name = 'composite_question';
    $webform_element = [
      '#type' => 'custom_composite',
      '#webform_composite_elements' => [
        'simple_question_one' => [
          '#title' => 'Simple question one',
        ],
        'simple_question_two' => [
          '#title' => 'Simple question two',
        ],
        'simple_question_three' => [
          '#title' => 'Simple question three',
        ],
      ],
    ];

    $expected = [
      'question'         => 'Composite Question',
      'composite_answer' => [
        [
          [
            'question'     => 'Simple question one',
            'answer'       => 'Simple answer one group one',
            'machine_name' => 'simple_question_one',
          ],
          [
            'question'     => 'Simple question two',
            'answer'       => 'Simple answer two group one',
            'machine_name' => 'simple_question_two',
          ],
          [
            'question'     => 'Simple question three',
            'answer'       => 'Simple answer three group one',
            'machine_name' => 'simple_question_three',
          ],
        ],
        [
          [
            'question'     => 'Simple question one',
            'answer'       => 'Simple answer one group two',
            'machine_name' => 'simple_question_one',
          ],
          [
            'question'     => 'Simple question two',
            'answer'       => 'Simple answer two group two',
            'machine_name' => 'simple_question_two',
          ],
          [
            'question'     => 'Simple question three',
            'answer'       => 'Simple answer three group two',
            'machine_name' => 'simple_question_three',
          ],
        ],
        [
          [
            'question'     => 'Simple question one',
            'answer'       => 'Simple answer one group three',
            'machine_name' => 'simple_question_one',
          ],
          [
            'question'     => 'Simple question two',
            'answer'       => 'Simple answer two group three',
            'machine_name' => 'simple_question_two',
          ],
          [
            'question'     => 'Simple question three',
            'answer'       => 'Simple answer three group three',
            'machine_name' => 'simple_question_three',
          ],
        ],
      ],
      'machine_name'     => 'composite_question',
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'prepareResponsePayload');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $question, $answer, $machine_name, $webform_element);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for caseManagementPrepare - With no service.
   *
   * We provide a basic webform subission,
   * that does not have a service with serviceselector set.
   * We expect back a formatted array for case management.
   */
  public function testCaseManagementPrepareNoService() {

    // Return the correct submission data from the webform submission class.
    $webform_submission = $this->mockWebformSubmission();

    // Mock the webform class.
    $webform = $this->mockWebform('test_webform_no_service', 'Test Webform with No Service', '/form/test-webform-no-service', 'test');

    $expected = [
      'id'        => 'test_webform_no_service',
      'title'     => 'Test Webform with No Service',
      'url'       => '/form/test-webform-no-service',
      'citizenId' => NULL,
      'category'  => 'test',
      'service'   => NULL,
      'payload'   => [],
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'caseManagementPrepare');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $webform, $webform_submission);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for caseManagementPrepare - with a service.
   *
   * We provide a basic webform submission
   * with a service selected via serviceselector.
   * We expect back an array for Case Management,
   * with the service set and the title amended with the the service name.
   */
  public function testCaseManagementPrepareWithService() {

    // Basic submission data.
    $submission_data = ['serviceselector' => 'With Service'];

    // Return the correct submission data from the webform submission class.
    $webform_submission = $this->mockWebformSubmission($submission_data);

    // Mock the webform class.
    $webform = $this->mockWebform('test_webform_with_service', 'Test Webform', '/form/test-webform-with-service', 'test');

    // Mock webform elements.
    $webform_elements = [
      'serviceselector' => [
        '#type' => 'select',
        '#title' => 'Service',
        '#options' => [
          'With Service' => ('With Service'),
        ],
      ],
    ];
    $this->mockWebformElement($webform_elements, $webform);

    $expected = [
      'id'        => 'test_webform_with_service',
      'title'     => 'Test Webform - With Service',
      'url'       => '/form/test-webform-with-service',
      'citizenId' => NULL,
      'category'  => 'test',
      'service'   => 'With Service',
      'payload'   => [
        [
          'question'     => 'Service',
          'answer'       => 'With Service',
          'machine_name' => 'serviceselector',
        ],
      ],
    ];

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'caseManagementPrepare');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $webform, $webform_submission);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests for caseManagementPrepare - Full stack test.
   *
   * We provide a full webform submission and webform.
   * We expect back the fully prepared array for Case Management.
   */
  public function testCaseManagementPrepare() {
    $submission_data = $this->getFullTestSubmissionData();
    $expected = $this->getFullTestExpectedResult();

    // Return the correct submission data from the webform submission class.
    $webform_submission = $this->mockWebformSubmission($submission_data);

    // Mock the webform class.
    $webform = $this->mockWebform('test_webform', 'Test Webform', '/form/test-webform', 'test');

    // Mock webform elements.
    $webform_elements = $this->getFullTestWebformElement();
    $this->mockWebformElement($webform_elements, $webform);

    // Turn protected method into public method.
    $method = new \ReflectionMethod(CaseManagementPostWebformHandler::class, 'caseManagementPrepare');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->testTarget, $webform, $webform_submission);

    $this->assertEquals($expected, $result);
  }

  /**
   * Get full test submission data.
   *
   * Data to mock up a webform submission.
   */
  protected function getFullTestSubmissionData() {
    return [
      'simple_question' => 'Simple answer',
      'multiple_question' => [
        'Multiple answer one',
        'Multiple answer two',
        'Multiple answer three',
      ],
      'date_question' => [
        'day'   => '01',
        'month' => '02',
        'year'  => '1980',
      ],
      'composite_question' => [
        'key_one'   => 'Composite answer one',
        'key_two'   => 'Composite answer two',
        'key_three' => 'Composite answer three',
      ],
      'multiple_composite_question' => [
        [
          'key_one'   => 'Composite group one answer one',
          'key_two'   => 'Composite group one answer two',
          'key_three' => 'Composite group one answer three',
        ],
        [
          'key_one'   => 'Composite group two answer one',
          'key_two'   => 'Composite group two answer two',
          'key_three' => 'Composite group two answer three',
        ],
        [
          'key_one'   => 'Composite group three answer one',
          'key_two'   => 'Composite group three answer two',
          'key_three' => 'Composite group three answer three',
        ],
      ],
      'address_question' => [
        'address_1' => 'Flat 1',
        'address_2' => '123 Fake St',
        'town_city' => 'Brighton',
        'postcode' => 'BN1 1AA',
      ],
      'upload_a_document' => '1',
      'serviceselector' => 'Test service',
      'casekey' => 'qwertyuiopasdfghjklzxcvbnm',
      'citizenidtoken' => '0123456789',
    ];
  }

  /**
   * Get full test expected result.
   *
   * The expected result for the webform using getFullTestSubmissionData().
   */
  protected function getFullTestExpectedResult() {
    return [
      'id'        => 'test_webform',
      'title'     => 'Test Webform - Test service',
      'url'       => '/form/test-webform',
      'citizenId' => '0123456789',
      'category'  => 'test',
      'service'   => 'Test service',
      'payload'   => [
        [
          'question'     => 'Simple question',
          'answer'       => 'Simple answer',
          'machine_name' => 'simple_question',
        ],
        [
          'question'     => 'Multiple question',
          'answer'       => "Multiple answer one\nMultiple answer two\nMultiple answer three",
          'machine_name' => 'multiple_question',
        ],
        [
          'question'     => 'Date question',
          'answer'       => '1980-02-01',
          'machine_name' => 'date_question',
        ],
        [
          'question'         => 'Composite question',
          'composite_answer' => [
            [
              [
                'question'     => 'Key one',
                'answer'       => 'Composite answer one',
                'machine_name' => 'key_one',
              ],
              [
                'question'     => 'Key two',
                'answer'       => 'Composite answer two',
                'machine_name' => 'key_two',
              ],
              [
                'question'     => 'Key three',
                'answer'       => 'Composite answer three',
                'machine_name' => 'key_three',
              ],
            ],
          ],
          'machine_name'     => 'composite_question',
        ],
        [
          'question'         => 'Multiple composite question',
          'composite_answer' => [
            [
              [
                'question'     => 'Key one',
                'answer'       => 'Composite group one answer one',
                'machine_name' => 'key_one',
              ],
              [
                'question'     => 'Key two',
                'answer'       => 'Composite group one answer two',
                'machine_name' => 'key_two',
              ],
              [
                'question'     => 'Key three',
                'answer'       => 'Composite group one answer three',
                'machine_name' => 'key_three',
              ],
            ],
            [
              [
                'question'     => 'Key one',
                'answer'       => 'Composite group two answer one',
                'machine_name' => 'key_one',
              ],
              [
                'question'     => 'Key two',
                'answer'       => 'Composite group two answer two',
                'machine_name' => 'key_two',
              ],
              [
                'question'     => 'Key three',
                'answer'       => 'Composite group two answer three',
                'machine_name' => 'key_three',
              ],
            ],
            [
              [
                'question'     => 'Key one',
                'answer'       => 'Composite group three answer one',
                'machine_name' => 'key_one',
              ],
              [
                'question'     => 'Key two',
                'answer'       => 'Composite group three answer two',
                'machine_name' => 'key_two',
              ],
              [
                'question'     => 'Key three',
                'answer'       => 'Composite group three answer three',
                'machine_name' => 'key_three',
              ],
            ],
          ],
          'machine_name'     => 'multiple_composite_question',
        ],
        [
          'question'         => 'Address question',
          'composite_answer' => [
            [
              [
                'question'     => 'Address 1',
                'answer'       => 'Flat 1',
                'machine_name' => 'address_1',
              ],
              [
                'question'     => 'Address 2',
                'answer'       => '123 Fake St',
                'machine_name' => 'address_2',
              ],
              [
                'question'     => 'Town/City',
                'answer'       => 'Brighton',
                'machine_name' => 'town_city',
              ],
              [
                'question'     => 'Postcode',
                'answer'       => 'BN1 1AA',
                'machine_name' => 'postcode',
              ],
            ],
          ],
          'machine_name'     => 'address_question',
        ],
        [
          'question'     => 'Upload a document',
          'answer'       => '1',
          'machine_name' => 'upload_a_document',
        ],
        [
          'question'     => 'Service',
          'answer'       => 'Test service',
          'machine_name' => 'serviceselector',
        ],
        [
          'question'     => 'CaseKey',
          'answer'       => 'qwertyuiopasdfghjklzxcvbnm',
          'machine_name' => 'casekey',
        ],
      ],
    ];
  }

  /**
   * Get the full test webform element.
   *
   * Get the full webform elements needed to match with the submission data
   * provided by getFullTestSubmissionData()
   */
  protected function getFullTestWebformElement() {
    return [
      'simple_question' => [
        '#title' => 'Simple question',
        '#type'  => 'text',
      ],
      'multiple_question' => [
        '#title' => 'Multiple question',
        '#type'  => 'checkboxes',
      ],
      'date_question' => [
        '#title' => 'Date question',
        '#type'  => 'bhcc_webform_date',
      ],
      'composite_question' => [
        '#title' => 'Composite question',
        '#type'  => 'custom_composite',
        '#webform_composite_elements' => [
          'key_one' => [
            '#title' => 'Key one',
          ],
          'key_two' => [
            '#title' => 'Key two',
          ],
          'key_three' => [
            '#title' => 'Key three',
          ],
        ],
      ],
      'multiple_composite_question' => [
        '#title' => 'Multiple composite question',
        '#type'  => 'custom_composite',
        '#webform_composite_elements' => [
          'key_one' => [
            '#title' => 'Key one',
          ],
          'key_two' => [
            '#title' => 'Key two',
          ],
          'key_three' => [
            '#title' => 'Key three',
          ],
        ],
      ],
      'address_question' => [
        '#type' => 'bhcc_central_hub_webform_uk_address',
        '#title' => 'Address question',
        '#webform_composite_elements' => [
          'address_lookup' => [
            '#title' => 'Address Lookup',
          ],
          'address_entry' => [
            '#title' => 'Address Entry',
            'address_1' => [
              '#title' => 'Address 1',
            ],
            'address_2' => [
              '#title' => 'Address 2',
            ],
            'town_city' => [
              '#title' => 'Town/City',
            ],
            'postcode' => [
              '#title' => 'Postcode',
            ],
          ],
        ],
      ],
      'upload_a_document' => [
        '#type' => 'webform_document_file',
        '#title' => 'Upload a document',
      ],
      'serviceselector' => [
        '#type' => 'select',
        '#title' => 'Service',
        '#options' => [
          'Test service' => 'Test service',
          'Test service 2' => 'Test service 2',
          'Test service 3' => 'Test service 3',
        ],
      ],
      'casekey' => [
        '#type' => 'value',
        '#title' => 'CaseKey',
      ],
      'citizenidtoken' => [
        '#type' => 'value',
        '#title' => 'citizenidtoken',
      ],
    ];
  }

}
