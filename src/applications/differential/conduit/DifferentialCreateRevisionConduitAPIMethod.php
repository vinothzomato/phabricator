<?php

final class DifferentialCreateRevisionConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.createrevision';
  }

  public function getMethodDescription() {
    return pht('Create a new Differential revision.');
  }

  protected function defineParamTypes() {
    return array(
      // TODO: Arcanist passes this; prevent fatals after D4191 until Conduit
      // version 7 or newer.
      'user'   => 'ignored',
      'diffid' => 'required diffid',
      'fields' => 'required dict',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF' => pht('Bad diff ID.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getValue('diffid')))
      ->executeOne();
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $reviewers = array();
    if ($viewer->getReviewerPHID()) {
      $reviewer = new DifferentialReviewer(
        $viewer->getReviewerPHID(),
        array(
          'status' => DifferentialReviewerStatus::STATUS_ADDED,
          ));
      if ($reviewer) {
        $reviewers[] = $reviewer;
      }
    }
    $revision = DifferentialRevision::initializeNewRevision($viewer);
    $revision->attachReviewerStatus($reviewers);

    $this->applyFieldEdit(
      $request,
      $revision,
      $diff,
      $request->getValue('fields', array()),
      $message = null);

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
