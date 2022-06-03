<?php

namespace Drupal\grants_handler\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\grants_handler\ApplicationHandler;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\webform\WebformRequestInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Grants Handler routes.
 */
class ApplicationController extends ControllerBase {


  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected WebformRequestInterface $requestHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ApplicationController {
    $instance = parent::create($container);
    $instance->currentUser = $container->get('current_user');

    $instance->entityRepository = $container->get('entity.repository');
    $instance->requestHandler = $container->get('webform.request');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * View single application.
   *
   * @param string $submission_id
   *   Application number for submission.
   *
   * @return array
   *   Build for the page.
   */
  public function view(string $submission_id, $view_mode = 'full', $langcode = 'fi'): array {

    $view_mode = 'default';
    $langcode = 'fi';

    try {
      $webform_submission = ApplicationHandler::submissionObjectFromApplicationNumber($submission_id);

      if ($webform_submission != NULL) {
        $webform = $webform_submission->getWebform();

        // Set webform submission template.
        $build = [
          '#theme' => 'webform_submission',
          '#view_mode' => $view_mode,
          '#webform_submission' => $webform_submission,
          // '#editSubmissionLink' => Link::fromTextAndUrl(t('Edit application'), $url),
        ];

        // Navigation.
        $build['navigation'] = [
          '#type' => 'webform_submission_navigation',
          '#webform_submission' => $webform_submission,
        ];

        // Information.
        $build['information'] = [
          '#type' => 'webform_submission_information',
          '#webform_submission' => $webform_submission,
          '#source_entity' => $webform_submission,
        ];

        $page = $this->entityTypeManager
          ->getViewBuilder($webform_submission->getEntityTypeId())
          ->view($webform_submission, $view_mode);

        // Submission.
        $build['submission'] = $page;

        // Library.
        $build['#attached']['library'][] = 'webform/webform.admin';

        // Add entities cacheable dependency.
        //        $this->renderer->addCacheableDependency($build, $this->currentUser);
        //        $this->renderer->addCacheableDependency($build, $webform);
        //        $this->renderer->addCacheableDependency($build, $webform_submission);.
        return $build;

      }
      else {
        throw new NotFoundHttpException($this->t('Application @number not found.', [
          '@number' => $submission_id,
        ]));
      }

    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | AtvDocumentNotFoundException | GuzzleException $e) {
      throw new NotFoundHttpException($e->getMessage());
    }
    catch (Exception $e) {
      throw new NotFoundHttpException($e->getMessage());
    }
    return [];
  }

  /**
   * View single application.
   *
   * @param string $submission_id
   *   Application number for submission.
   *
   * @return array
   *   Build for the page.
   */
  public function edit(string $submission_id, $view_mode = 'full', $langcode = 'fi'): array {
    try {
      $webform_submission = ApplicationHandler::submissionObjectFromApplicationNumber($submission_id);
      if ($webform_submission == NULL) {
        throw new NotFoundHttpException($this->t('Application @number not found.', [
          '@number' => $submission_id,
        ]));
      }

      $my_form = \Drupal::entityTypeManager()
        ->getStorage('webform')
        ->load($webform_submission->getWebform()->id());

      $my_form->setOriginalId($webform_submission->id());

      $form = $my_form->getSubmissionForm(['data' => $webform_submission->getData()]);

      // $webform = $webform_submission->getWebform();
      //      $webform->entity = $webform_submission;
      //
      // $form = \Drupal::entityTypeManager()
      //        ->getViewBuilder('webform')
      //        ->view($rr);
      $d = 'asdf';

      return [
        '#theme' => 'grants_handler_edit_application',
        '#view_mode' => $view_mode,
        '#submissionObject' => $webform_submission,
        '#submissionId' => $submission_id,
        '#editForm' => $form,
        // '#editForm' => [
        //          '#type' => 'webform',
        //          '#webform' => $webform_submission->getWebform()->id(),
        //          '#default_data' =>
        //        ],
      ];

    }
    catch (InvalidPluginDefinitionException $e) {
      throw new NotFoundHttpException('Application not found');
    }
    catch (PluginNotFoundException $e) {
      throw new NotFoundHttpException('Application not found');
    }
    catch (AtvDocumentNotFoundException $e) {
      throw new NotFoundHttpException('Application not found');
    }
    catch (GuzzleException $e) {
      throw new NotFoundHttpException('Application not found');
    }
    catch (Exception $e) {
      throw new NotFoundHttpException('Application not found');
    }
  }

  /**
   * Returns a page title.
   */
  public function getEditTitle($webform_submission): TranslatableMarkup {
    $applicationNumber = ApplicationHandler::createApplicationNumber($webform_submission);
    return $this->t('Edit application: @submissionId', ['@submissionId' => $applicationNumber]);
  }

  /**
   * Returns a page title.
   */
  public function getTitle($submission_id): TranslatableMarkup {
    return $this->t('View application: @submissionId', ['@submissionId' => $submission_id]);
  }

}
