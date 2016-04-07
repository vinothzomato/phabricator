<?php

final class ZomatoUpdateRevisionConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'zomato.updaterevision';
  }

  public function getMethodDescription() {
    return pht('Update the differential revision.');
  }

  protected function defineParamTypes() {
    return array(
      'repo' => 'required string',
      'base' => 'required string',
      'head' => 'required string',
      'message' => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty string';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NO_CHANGES' => pht('No changes found between base and head.'),
      'ERR_NO_DIFF' => pht('No diff found. Please create a new revision using arc z --create .'),
      'ERR_REVISION_CLOSED' => pht('Revision closed. Please create a new revision using arc z --create .'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $repo = $request->getValue('repo');
    $base = $request->getValue('base');
    $head = $viewer->getGithubUsername().':'.$request->getValue('head');
    $message = $request->getValue('message');

    $authorGithubUser = new GithubApiUser();
    $authorGithubUser->setUsername($viewer->getGithubUsername());
    $authorGithubUser->setToken($viewer->getGithubAccessToken());

    $raw_diff = $authorGithubUser->getDiff($repo,$base,$head);

    if (!$raw_diff) {
      throw new ConduitException('ERR_NO_CHANGES');
    }

    $diff = id(new DifferentialDiff())->loadOneWhere(
      'repo = %s AND base = %s AND head = %s ORDER BY id DESC limit 1',
      $repo,$base,$head);

    if (!$diff) {
      throw new ConduitException('ERR_NO_DIFF');
    }

    $revision = id(new DifferentialRevisionQuery())
    ->setViewer($viewer)
    ->withIDs(array($diff->getID()))
    ->needReviewerStatus(true)
    ->needActiveDiffs(true)
    ->requireCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
        ))
    ->executeOne();

    if ($revision->getStatus() == ArcanistDifferentialRevisionStatus::CLOSED) {
      throw new ConduitException('ERR_REVISION_CLOSED');
    }


    $call = new ConduitCall(
      'differential.createrawdiff',
      array(
        'diff' => $raw_diff,
        'repositoryPHID' => $revision->getRepositoryPHID(),
        'repo' => $repo,
        'base' => $base,
        'head' => $head,
        'viewPolicy' => 'users',
        ));
    $call->setUser($viewer);
    $result = $call->execute();

    $diff_id = $result['id'];

    $newDiff = id(new DifferentialDiffQuery())
    ->setViewer($viewer)
    ->withIDs(array($diff_id))
    ->executeOne();

    $this->applyFieldEdit(
      $request,
      $revision,
      $newDiff,
      array(),
      $message);

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
      );
  }

?> 