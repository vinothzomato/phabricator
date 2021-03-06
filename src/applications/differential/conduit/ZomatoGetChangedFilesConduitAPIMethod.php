<?php

final class ZomatoGetChangedFilesConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'zomato.getchangedfiles';
  }

  public function getMethodDescription() {
    return pht('Get changed file list.');
  }

  protected function defineParamTypes() {
    return array(
      'repo' => 'required string',
      'base' => 'required string',
      'head' => 'required string',
      'repoId' => 'required string',
      'projectId' => 'required string'
    );
  }

  protected function defineReturnType() {
    return 'nonempty string';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_REPO_NOT_FOUND' => pht('Repository was not found.'),
      'ERR_PROJECT_NOT_FOUND' => pht('Project was not found.'),
      'ERR_NO_CHANGES' => pht('No changes found between base and head.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $viewer = $request->getUser();

    $repo = $request->getValue('repo');
    $base = $request->getValue('base');
    $head = $request->getValue('head');

    $repoId = $request->getValue('repoId');
    $projectId = $request->getValue('projectId');
    $repository = id(new PhabricatorRepositoryQuery())
    ->setViewer($viewer)
    ->withIDs(array($repoId))
    ->executeOne();
    if (!$repository) {
      throw new ConduitException('ERR_REPO_NOT_FOUND');
    } 
    $fields['repository'] = array($repository->getPHID());

    $project = id(new PhabricatorProjectQuery())
    ->setViewer($viewer)
    ->withIDs(array($projectId))
    ->executeOne();
    if (!$project) {
      throw new ConduitException('ERR_PROJECT_NOT_FOUND');
    }      
    if ($project->getIsPullRequest()) {
      $head = $viewer->getGithubUsername().':'.$head;
    }
    $fields['projects'] = array($project->getPHID());

    $authorGithubUser = new GithubApiUser();
    $authorGithubUser->setUsername($viewer->getGithubUsername());
    $authorGithubUser->setToken($viewer->getGithubAccessToken());

    $diff = $authorGithubUser->getDiff($repo,$base,$head);

    if (!$diff) {
      throw new ConduitException('ERR_NO_CHANGES');
    }

    $prev_diff = id(new DifferentialDiff())->loadOneWhere(
      'repo = %s AND base = %s AND head = %s ORDER BY id DESC limit 1',
      $repo,$base,$head);

    $newDiff = null;

    if ($prev_diff) {
      id(new DifferentialChangesetQuery())
      ->setViewer($this->getViewer())
      ->withDiffs(array($prev_diff))
      ->needAttachToDiffs(true)
      ->needHunks(true)
      ->execute();

      $raw_changes = $prev_diff->buildChangesList();
      $changes = array();
      foreach ($raw_changes as $changedict) {
        $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
      }

      $loader = id(new PhabricatorFileBundleLoader())
      ->setViewer($viewer);

      $bundle = ArcanistBundle::newFromChanges($changes);
      $bundle->setLoadFileDataCallback(array($loader, 'loadFileData'));
      $raw_diff = $bundle->toGitPatch();

      $parser = new ArcanistDiffParser();
      $diff_changes = $parser->parseDiff($diff);

      $loader = id(new PhabricatorFileBundleLoader())
      ->setViewer($viewer);

      $bundle = ArcanistBundle::newFromChanges($diff_changes);
      $bundle->setLoadFileDataCallback(array($loader, 'loadFileData'));
      $new_diff = $bundle->toGitPatch();

      if ($raw_diff === $new_diff) {
        if ($prev_diff->getRevisionID()) {
          $diff_revision = id(new DifferentialRevision())->load($prev_diff->getRevisionID());
          if (!$diff_revision->isClosed()) {
            throw new ConduitException('Already a active revision exists with same diff. Please visit '.PhabricatorEnv::getURI('/D'.$diff_revision->getID()));
          }
        }
        else{
          $newDiff = $prev_diff;
        }
      }
    }
    return array();
  }
}
