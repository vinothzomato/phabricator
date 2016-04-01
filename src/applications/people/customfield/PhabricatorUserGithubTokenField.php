<?php

final class PhabricatorUserGithubTokenField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:githubToken';
  }

  public function getFieldName() {
    return pht('Github Access Token');
  }

  public function getFieldDescription() {
    return pht('Stores the github access token of the user.');
  }

  public function canDisableField() {
    return true;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $this->value = $object->getGithubToken();
  }

  public function getOldValueForApplicationTransactions() {
    return $this->getObject()->getGithubToken();
  }

  public function getNewValueForApplicationTransactions() {
    if (!$this->isEditable()) {
      return $this->getObject()->getGithubToken();
    }
    return $this->value;
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $this->getObject()->setGithubToken($xaction->getNewValue());
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
    return false;//PhabricatorEnv::getEnvConfig('account.editable');
  }

}
