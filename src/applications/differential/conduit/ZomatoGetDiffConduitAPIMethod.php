<?php

final class ZomatoGetDiffConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'zomato.getdiff';
  }

  public function getMethodDescription() {
    return pht('Get diff between base and head.');
  }

  protected function defineParamTypes() {
    return array(
      'repo' => 'required string',
      'base' => 'required string',
      'head' => 'required string',
      );
  }

  protected function defineReturnType() {
    return 'nonempty string';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NO_CHANGES' => pht('No changes found between base and head.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $viewer = $request->getUser();

    $repo = $request->getValue('repo');
    $base = $request->getValue('base');
    $head = $request->getValue('head');

    $authorGithubUser = new GithubApiUser();
    $authorGithubUser->setUsername($viewer->getGithubUsername());
    $authorGithubUser->setToken($viewer->getGithubAccessToken());

    $diff = $authorGithubUser->getDiff($repo,$base,$head);

    if (!$diff) {
      throw new ConduitException('ERR_NO_CHANGES');
    }

    return array('diff' => $diff);;
  }

}
