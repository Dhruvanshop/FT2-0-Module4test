<?php

namespace Drupal\expose_student_data\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class StudentApiController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a StudentApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * Returns a list of students in JSON format,filtered by various fields.
   */
  public function listStudents() {
    $request = \Drupal::request();
    $params = $request->query->all();
    $students = [];

    // Get the current request.
    $request = $this->requestStack->getCurrentRequest();

    // Convert stream name to term ID.
    if (!empty($params['student_stream'])) {
      $stream_name = $params['field_stream'];
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->condition('vid', 'stream')
        ->condition('name', $stream_name)
        ->accessCheck(TRUE)
        ->execute();

      if (!empty($query)) {
        $tid = reset($query);
        $params['field_stream'] = $tid;
      }
      else {
        $params['field_stream'] = NULL;
      }
    }

    // Load student users with optional filters.
    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $query->condition('status', 1);
    $query->condition('roles', 'student');
    $query->accessCheck(FALSE);

    // Apply filters if parameters are provided.
    if ($params['field_stream']) {
      $query->condition('field_stream.target_id', $params['student_stream']);
    }

    if ($params['joining_year']) {
      $query->condition('field_joining_year.value', $params['joining_year']);
    }

    if ($params['passing_year']) {
      $query->condition('field_passing_year.value', $params['passing_year']);
    }

    if ($params['name']) {
      $query->condition('name', '%' . $params['name'] . '%', 'LIKE');
    }

    if ($params['email']) {
      $query->condition('mail', '%' . $params['email'] . '%', 'LIKE');
    }

    if ($params['uid']) {
      $query->condition('uid', '%' . $params['uid'] . '%', 'LIKE');
    }

    $uids = $query->execute();

    if (!empty($uids)) {
      $users = User::loadMultiple($uids);

      foreach ($users as $user) {
        $students[] = [
          'id' => $user->id(),
          'name' => $user->getDisplayName(),
          'email' => $user->getEmail(),
          'stream' => $this->getTermLabel($user->get('field_stream')->target_id),
          'joining_year' => $user->get('field_joining_year')->value,
          'passing_year' => $user->get('field_passing_year')->value,
          'mobile_number' => $user->get('field_mobile_number')->value,
        ];
      }
    }

    return new JsonResponse($students);
  }

  /**
   * Helper function to get the label of a taxonomy term.
   */
  private function getTermLabel($term_id) {
    if ($term_id) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      return $term ? $term->label() : '';
    }
    return '';
  }

}
