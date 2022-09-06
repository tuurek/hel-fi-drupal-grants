<?php

namespace Drupal\grants_attachments;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\file\Entity\File;
use Drupal\grants_attachments\Plugin\WebformElement\GrantsAttachments;
use Drupal\grants_profile\GrantsProfileService;
use Drupal\helfi_atv\AtvDocument;
use Drupal\helfi_atv\AtvDocumentNotFoundException;
use Drupal\helfi_atv\AtvFailedToConnectException;
use Drupal\helfi_atv\AtvService;
use Drupal\webform\Entity\WebformSubmission;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Handle attachment related things.
 */
class AttachmentHandler {

  /**
   * The grants_attachments.attachment_uploader service.
   *
   * @var \Drupal\grants_attachments\AttachmentUploader
   */
  protected AttachmentUploader $attachmentUploader;

  /**
   * The grants_attachments.attachment_remover service.
   *
   * @var \Drupal\grants_attachments\AttachmentRemover
   */
  protected AttachmentRemover $attachmentRemover;

  /**
   * Field names for attachments.
   *
   * @var string[]
   *
   * @todo get field names from form where field type is attachment.
   */
  protected static array $attachmentFieldNames = [
    'vahvistettu_tilinpaatos' => 43,
    'vahvistettu_toimintakertomus' => 4,
    'vahvistettu_tilin_tai_toiminnantarkastuskertomus' => 5,
    'vuosikokouksen_poytakirja' => 8,
    'toimintasuunnitelma' => 1,
    'talousarvio' => 2,
    'muu_liite' => 0,
  ];

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * Show messages messages.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * ATV access.
   *
   * @var \Drupal\helfi_atv\AtvService
   */
  protected AtvService $atvService;

  /**
   * Grants profile access.
   *
   * @var \Drupal\grants_profile\GrantsProfileService
   */
  protected GrantsProfileService $grantsProfileService;

  /**
   * Attached file id's.
   *
   * @var array
   */
  protected array $attachmentFileIds;

  /**
   * Debug status.
   *
   * @var bool
   */
  protected bool $debug;

  /**
   * Constructs an AttachmentHandler object.
   *
   * @param \Drupal\grants_attachments\AttachmentUploader $grants_attachments_attachment_uploader
   *   Uploader.
   * @param \Drupal\grants_attachments\AttachmentRemover $grants_attachments_attachment_remover
   *   Remover.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerChannelFactory
   *   Logger.
   * @param \Drupal\helfi_atv\AtvService $atvService
   *   Atv access.
   * @param \Drupal\grants_profile\GrantsProfileService $grantsProfileService
   *   Profile service.
   */
  public function __construct(
    AttachmentUploader $grants_attachments_attachment_uploader,
    AttachmentRemover $grants_attachments_attachment_remover,
    Messenger $messenger,
    LoggerChannelFactory $loggerChannelFactory,
    AtvService $atvService,
    GrantsProfileService $grantsProfileService,
  ) {

    $this->attachmentUploader = $grants_attachments_attachment_uploader;
    $this->attachmentRemover = $grants_attachments_attachment_remover;

    $this->messenger = $messenger;
    $this->logger = $loggerChannelFactory->get('grants_attachments_handler');

    $this->atvService = $atvService;
    $this->grantsProfileService = $grantsProfileService;

    $this->attachmentFileIds = [];

    $this->debug = FALSE;

  }

  /**
   * If debug is on or not.
   *
   * @return bool
   *   TRue or false depending on if debug is on or not.
   */
  public function isDebug(): bool {
    return $this->debug;
  }

  /**
   * Set debug.
   *
   * @param bool $debug
   *   True or false.
   */
  public function setDebug(bool $debug): void {
    $this->debug = $debug;
  }

  /**
   * Get file fields.
   *
   * @return string[]
   *   Attachment fields.
   */
  public static function getAttachmentFieldNames($preventKeys = FALSE): array {
    if ($preventKeys) {
      return self::$attachmentFieldNames;
    }
    return array_keys(self::$attachmentFieldNames);
  }

  /**
   * Validate single attachment field.
   *
   * @param string $fieldName
   *   Name of the field in validation.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $fieldTitle
   *   Field title for errors.
   * @param string $triggeringElement
   *   Triggering element.
   */
  public static function validateAttachmentField(
    string $fieldName,
    FormStateInterface $form_state,
    string $fieldTitle,
    string $triggeringElement
  ) {
    // Get value.
    $values = $form_state->getValue($fieldName);

    $args = [];
    if (isset($values[0]) && is_array($values[0])) {
      $args = $values;
    }
    else {
      $args[] = $values;
    }

    foreach ($args as $value) {
      // Muu liite is optional.
      if ($fieldName !== 'muu_liite' && ($value === NULL || empty($value))) {
        $form_state->setErrorByName($fieldName, t('@fieldname field is required', [
          '@fieldname' => $fieldTitle,
        ]));
      }

      if ($value !== NULL && !empty($value)) {
        // If attachment is uploaded, make sure no other field is selected.
        if (isset($value['attachment']) && is_int($value['attachment'])) {
          if ($value['isDeliveredLater'] === "1") {
            $form_state->setErrorByName("[" . $fieldName . "][isDeliveredLater]", t('@fieldname has file added, it cannot be added later.', [
              '@fieldname' => $fieldTitle,
            ]));
          }
          if ($value['isIncludedInOtherFile'] === "1") {
            $form_state->setErrorByName("[" . $fieldName . "][isIncludedInOtherFile]", t('@fieldname has file added, it cannot belong to other file.', [
              '@fieldname' => $fieldTitle,
            ]));
          }
        }
        else {
          if ($fieldName !== 'muu_liite') {
            if ((!empty($value) && !isset($value['attachment']) && ($value['attachment'] === NULL && $value['attachmentName'] === ''))) {
              if (empty($value['isDeliveredLater']) && empty($value['isIncludedInOtherFile'])) {
                $form_state->setErrorByName("[" . $fieldName . "][isDeliveredLater]", t('@fieldname has no file uploaded, it must be either delivered later or be included in other file.', [
                  '@fieldname' => $fieldTitle,
                ]));
              }
            }
          }
        }
      }
    }
  }

  /**
   * Parse attachments from POST.
   *
   * @param array $form
   *   Form in question.
   * @param array $submittedFormData
   *   Submitted form data.
   * @param string $applicationNumber
   *   Generated application number.
   *
   * @return array[]
   *   Parsed attachments.
   */
  public function parseAttachments(
    array $form,
    array $submittedFormData,
    string $applicationNumber): array {

    $attachmentsArray = [];
    $attachmentHeaders = GrantsAttachments::$fileTypes;
    $filenames = [];
    $attachmentFields = self::getAttachmentFieldNames(TRUE);
    foreach ($attachmentFields as $attachmentFieldName => $descriptionKey) {
      $field = $submittedFormData[$attachmentFieldName];

      $descriptionValue = $attachmentHeaders[$descriptionKey];

      $fileType = NULL;

      // Since we have to support multiple field elements, we need to
      // handle all as they were a multifield.
      $args = [];
      if (isset($field[0]) && is_array($field[0])) {
        $args = $field;
      }
      else {
        $args[] = $field;
      }

      // Loop args & create attachement field.
      foreach ($args as $fieldElement) {
        if (is_array($fieldElement)) {

          if (isset($fieldElement["fileType"]) && $fieldElement["fileType"] !== "") {
            $fileType = $fieldElement["fileType"];
          }
          else {
            if (isset($form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#filetype"])) {
              $fileType = $form["elements"]["lisatiedot_ja_liitteet"]["liitteet"][$attachmentFieldName]["#filetype"];
            }
            else {
              $fileType = '0';
            }
          }

          $parsedArray = $this->getAttachmentByFieldValue(
            $fieldElement, $descriptionValue, $fileType);

          if (!empty($parsedArray)) {
            if (!isset($parsedArray['fileName']) || !in_array($parsedArray['fileName'], $filenames)) {
              $attachmentsArray[] = $parsedArray;
              if (isset($parsedArray['fileName'])) {
                $filenames[] = $parsedArray['fileName'];
              }
            }
          }
        }
      }
    }

    if (isset($submittedFormData["account_number"])) {
      try {
        $this->handleBankAccountConfirmation(
          $submittedFormData["account_number"],
          $applicationNumber,
          $filenames,
          $attachmentsArray
        );
      }
      catch (TempStoreException | GuzzleException $e) {
        $this->logger->error($e->getMessage());
      }
    }

    return $attachmentsArray;
  }

  /**
   * Figure out if account confirmation file has been added to application.
   *
   * And if so, attach that file to this application for bank account
   * confirmation.
   *
   * @param string $accountNumber
   *   Bank account in question.
   * @param string $applicationNumber
   *   This application.
   * @param array $filenames
   *   Already added filenames.
   * @param array $attachmentsArray
   *   Full array of attachment information.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function handleBankAccountConfirmation(
    string $accountNumber,
    string $applicationNumber,
    array $filenames,
    array &$attachmentsArray
  ) {

    // If no accountNumber is selected, do nothing.
    if (empty($accountNumber)) {
      return;
    }

    // If we have account number, load details.
    $selectedCompany = $this->grantsProfileService->getSelectedCompany();
    $grantsProfileDocument = $this->grantsProfileService->getGrantsProfile($selectedCompany['identifier']);
    $profileContent = $grantsProfileDocument->getContent();

    $applicationDocument = FALSE;
    try {
      // Search application document from ATV.
      $applicationDocumentResults = $this->atvService->searchDocuments([
        'transaction_id' => $applicationNumber,
      ]);
      $applicationDocument = reset($applicationDocumentResults);
    }
    catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
    }

    $accountConfirmationExists = FALSE;
    $accountConfirmationFile = [];
    // If we have document, look for already added confirmations.
    if ($applicationDocument) {
      $filename = md5($accountNumber);

      $applicationAttachments = $applicationDocument->getAttachments();

      foreach ($applicationAttachments as $attachment) {
        if (str_contains($attachment['filename'], $filename)) {
          $accountConfirmationExists = TRUE;
          $accountConfirmationFile = $attachment;
          break;
        }
        $found = array_filter($filenames, function ($fn) use ($filename) {
          return str_contains($fn, $filename);
        });
        if (!empty($found)) {
          $accountConfirmationExists = TRUE;
          $accountConfirmationFile = $attachment;
          break;
        }
      }

      if (!$accountConfirmationExists) {
        $found = array_filter($attachmentsArray, function ($fn) use ($filename) {
          if (!isset($fn['fileName'])) {
            return FALSE;
          }
          return str_contains($fn['fileName'], $filename);
        });
        if (!empty($found)) {
          $accountConfirmationExists = TRUE;
          $accountConfirmationFile = $found;
        }
      }
    }

    // Find selected account details from profile content.
    $selectedAccount = NULL;
    foreach ($profileContent['bankAccounts'] as $account) {
      if ($account['bankAccount'] == $accountNumber) {
        $selectedAccount = $account;
      }
    }

    if (!$accountConfirmationExists) {
      $selectedAccountConfirmation = FALSE;
      // Get confirmation file from profile.
      if ($selectedAccount['confirmationFile']) {
        $selectedAccountConfirmation = $grantsProfileDocument
          ->getAttachmentForFilename($selectedAccount['confirmationFile']);
      }
      // If found then try to add it to application.
      if ($selectedAccountConfirmation) {
        try {
          // Get file.
          $file = $this->atvService->getAttachment($selectedAccountConfirmation['href']);
          // Add file to attachments for uploading.
          $this->attachmentFileIds[] = $file->id();
        }
        catch (AtvDocumentNotFoundException | AtvFailedToConnectException | GuzzleException $e) {
          $this->logger
            ->error($e->getMessage());
          $this->messenger
            ->addError(t('Bank account confirmation file attachment failed.'));
        }
        // Add account confirmation to attachment array.
        $attachmentsArray[] = [
          'description' => t('Confirmation for account @accountNumber', ['@accountNumber' => $selectedAccount["bankAccount"]])->render(),
          'fileName' => $selectedAccount["confirmationFile"],
          'isNewAttachment' => TRUE,
          'fileType' => 6,
          'isDeliveredLater' => FALSE,
          'isIncludedInOtherFile' => FALSE,
        ];
      }
    }
    else {
      // But if we have accountconfirmation added,
      // make sure it's not added again
      // and also make sure if the attachment is uploaded to add integrationID
      // sometimes this does not work in integration.
      $existingConfirmationForSelectedAccountExists = array_filter($attachmentsArray, function ($fn) use ($selectedAccount, $accountConfirmationFile) {
        if (
          isset($fn['fileName']) &&
          (($fn['fileName'] == $selectedAccount['confirmationFile']) ||
            ($fn['fileName'] == $accountConfirmationFile['filename']))
        ) {
          return TRUE;
        }
        return FALSE;
      });

      if (empty($existingConfirmationForSelectedAccountExists)) {
        // Parse integrationID from uploaded attachment.
        $integrationID = substr(
          $accountConfirmationFile['href'],
          strpos($accountConfirmationFile['href'], '.hel.fi') + 7,
        );

        // If confirmation details are not found from.
        $fileArray = [
          'description' => t('Confirmation for account @accountNumber', ['@accountNumber' => $selectedAccount["bankAccount"]])->render(),
          'fileName' => $selectedAccount["confirmationFile"],
          'isNewAttachment' => FALSE,
          'fileType' => 6,
          'isDeliveredLater' => FALSE,
          'isIncludedInOtherFile' => FALSE,
        ];

        if (!empty($integrationID)) {
          $fileArray['integrationID'] = $integrationID;
        }
        $attachmentsArray[] = $fileArray;
      }
    }
  }

  /**
   * Extract attachments from form data.
   *
   * @param array $field
   *   The field parsed.
   * @param string $fieldDescription
   *   The field description from form element title.
   * @param string $fileType
   *   Filetype id from element configuration.
   *
   * @return \stdClass[]
   *   Data for JSON.
   */
  public function getAttachmentByFieldValue(
    array $field,
    string $fieldDescription,
    string $fileType): array {

    $retval = [
      'description' => (isset($field['description']) && $field['description'] !== "") ? $field['description'] : $fieldDescription,
    ];
    $retval['fileType'] = (int) $fileType;
    // We have uploaded file. THIS time. Not previously.
    if (isset($field['attachment']) && $field['attachment'] !== NULL && !empty($field['attachment'])) {

      $file = File::load($field['attachment']);
      if ($file) {
        // Add file id for easier usage in future.
        $this->attachmentFileIds[] = $field['attachment'];

        $retval['fileName'] = $file->getFilename();
        $retval['isNewAttachment'] = TRUE;
        $retval['isDeliveredLater'] = FALSE;
        $retval['isIncludedInOtherFile'] = FALSE;
      }
    }
    else {
      // If other filetype and no attachment already set, we don't add them to
      // retval since we don't want to fill attachments with empty other files.
      if (($fileType === "0" || $fileType === '6') && empty($field["attachmentName"])) {
        return [];
      }
      // No upload, process accordingly.
      if ($field['fileStatus'] == 'new' || empty($field['fileStatus'])) {
        if (isset($field['isDeliveredLater'])) {
          $retval['isDeliveredLater'] = $field['isDeliveredLater'] === "1";
        }
        if (isset($field['isIncludedInOtherFile'])) {
          $retval['isIncludedInOtherFile'] = $field['isIncludedInOtherFile'] === "1";
        }
      }
      if ($field['fileStatus'] === 'uploaded') {
        if (isset($field['attachmentName'])) {
          $retval['fileName'] = $field["attachmentName"];
          $retval['isNewAttachment'] = FALSE;
        }
        $retval['isDeliveredLater'] = FALSE;
        $retval['isIncludedInOtherFile'] = FALSE;
        $retval['isNewAttachment'] = FALSE;
      }
      if ($field['fileStatus'] === 'otherFile') {
        $retval['isDeliveredLater'] = FALSE;
        $retval['isIncludedInOtherFile'] = TRUE;
        $retval['isNewAttachment'] = FALSE;
      }
      if ($field['fileStatus'] == 'deliveredLater') {
        if ($field['attachmentName']) {
          $retval['fileName'] = $field["attachmentName"];
        }
        if (isset($field['isDeliveredLater'])) {
          $retval['isDeliveredLater'] = $field['isDeliveredLater'] === "1";
        }
        else {
          $retval['isDeliveredLater'] = '0';
        }

        if (isset($field['isIncludedInOtherFile'])) {
          $retval['isIncludedInOtherFile'] = $field['isIncludedInOtherFile'] === "1";
        }
        else {
          $retval['isIncludedInOtherFile'] = '0';
        }
      }

      if (isset($field["integrationID"]) && $field["integrationID"] !== "") {
        $retval['integrationID'] = $field["integrationID"];
        $retval['isNewAttachment'] = FALSE;
      }

    }
    return $retval;
  }

  /**
   * Upload attached files & remove temporary.
   *
   * @param string $applicationNumber
   *   Application identifier.
   * @param \Drupal\webform\Entity\WebformSubmission $webformSubmission
   *   Submission object.
   */
  public function handleApplicationAttachments(
    string $applicationNumber,
    WebformSubmission $webformSubmission
  ) {

    $this->attachmentUploader->setDebug($this->isDebug());
    $attachmentResult = $this->attachmentUploader->uploadAttachments(
      $this->attachmentFileIds,
      $applicationNumber
    );

    foreach ($attachmentResult as $attResult) {
      if ($attResult['upload'] === TRUE) {
        $this->messenger
          ->addStatus(
            t(
              'Attachment (@filename) uploaded',
              [
                '@filename' => $attResult['filename'],
              ]));
      }
      else {
        $this->messenger
          ->addStatus(
            t(
              'Attachment (@filename) upload failed with message: @msg. Event has been logged.',
              [
                '@filename' => $attResult['filename'],
                '@msg' => $attResult['msg'],
              ]));
      }
    }

    $this->attachmentRemover->removeGrantAttachments(
      $this->attachmentFileIds,
      $attachmentResult,
      $applicationNumber,
      $this->isDebug(),
      $webformSubmission->id()
    );

  }

  /**
   * Find out what attachments are uploaded and what are not.
   *
   * @return array
   *   Attachments sorted by upload status.
   */
  public static function attachmentsUploadStatus(AtvDocument $document): array {
    $attachments = $document->getAttachments();
    $content = $document->getContent();

    $contentAttachments = $content["attachmentsInfo"]["attachmentsArray"] ?? [];

    $uploadedByContent = array_filter($contentAttachments, function ($item) {
      foreach ($item as $itemArray) {
        if ($itemArray['ID'] === 'fileName') {
          return TRUE;
        }
      }
      return FALSE;
    });

    $up = [];
    $not = [];

    foreach ($uploadedByContent as $ca) {

      $filesInContent = array_filter($ca, function ($caItem) {
        if ($caItem['ID'] === 'fileName') {
          return TRUE;
        }
        else {
          return FALSE;
        }
      });
      $fn1 = reset($filesInContent);
      $fn = $fn1['value'];

      $attFound = FALSE;

      foreach ($attachments as $k => $v) {
        if (str_contains($v['filename'], $fn)) {
          $attFound = TRUE;
        }
      }

      if ($attFound) {
        $up[] = $fn;
      }
      else {
        $not[] = $fn;
      }
    }

    return [
      'uploaded' => $up,
      'not-uploaded' => $not,
    ];
  }

}
