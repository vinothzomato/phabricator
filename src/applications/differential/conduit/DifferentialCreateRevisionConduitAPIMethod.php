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
      'projectId'   => 'required string',
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
      'ERR_PROJECT_NOT_FOUND' => pht('Project was not found.'),
      'ERR_NO_REVIEWERS' => pht('No reviewers found in your project. Please contact infra@zomato.com'),
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

    $projectId = $request->getValue('projectId');
    $project = id(new PhabricatorProjectQuery())
    ->setViewer($viewer)
    ->withIDs(array($projectId))
    ->needReviewers(true)
    ->executeOne();
    if (!$project) {
      throw new ConduitException('ERR_PROJECT_NOT_FOUND');
    }   

    $reviewers = $project->getReviewerPHIDs();
    if (!$reviewers || empty($reviewers)) {
      throw new ConduitException('ERR_NO_REVIEWERS');
    }

    $diff_reviewers = array();
    foreach ($reviewers as $reviewerPHID) {
      $reviewer = new DifferentialReviewer(
        $reviewerPHID,
        array(
          'status' => DifferentialReviewerStatus::STATUS_ADDED,
          ));
      if ($reviewer) {
        $reviewers[] = $reviewer;
      }
    }

    $revision = DifferentialRevision::initializeNewRevision($viewer);
    $revision->attachReviewerStatus($diff_reviewers);

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
