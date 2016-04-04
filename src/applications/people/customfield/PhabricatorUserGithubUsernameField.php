<?php

final class PhabricatorUserGithubUsernameField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:githubUsername';
  }

  public function getFieldName() {
    return pht('Github Username');
  }

  public function getFieldDescription() {
    return pht('Stores the github username of the user.');
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $this->value = $object->getGithubUserName();
  }

  public function getOldValueForApplicationTransactions() {
    return $this->getObject()->getGithubUserName();
  }

  public function getNewValueForApplicationTransactions() {
    if (!$this->isEditable()) {
      return $this->getObject()->getGithubUserName();
    }
    return $this->value;
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $this->getObject()->setGithubUsername($xaction->getNewValue());
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getFieldKey());
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setName($this->getFieldKey())
      ->setValue($this->value)
      ->setLabel($this->getFieldName())
      ->setDisabled(!$this->isEditable());
  }

  private function isEditable() {
    return false;
  }

}
