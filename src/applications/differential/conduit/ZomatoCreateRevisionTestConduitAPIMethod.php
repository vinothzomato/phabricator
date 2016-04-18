<?php

final class ZomatoCreateRevisionTestConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'zomato.createrevisiontest';
  }

  public function getMethodDescription() {
    return pht('Create a new differential revision.');
  }

  protected function defineParamTypes() {
    $status_const = $this->formatStringConstants(
      array(
        'none',
        'skip',
        'okay',
        'warn',
        'fail',
        ));
    $vcs_const = $this->formatStringConstants(
      array(
        'git'
        ));
    return array(
      'fields' => 'required dict',
      'repo' => 'required string',
      'base' => 'required string',
      'head' => 'required string',
      'repoId' => 'required string',
      'projectId' => 'required string',
      'sourceMachine' => 'required string',
      'sourcePath' => 'required string',
      'branch' => 'required string',
      'bookmark' => 'optional string',
      'sourceControlSystem' => 'required '.$vcs_const,
      'sourceControlPath'  => 'required string',
      'sourceControlBaseRevision' => 'required string',
      'creationMethod' => 'optional string',
      'lintStatus' => 'required '.$status_const,
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
      'ERR_NO_REVIEWERS' => pht('No reviewers found in your project. Please contact infra@zomato.com'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $viewer = $request->getUser();
    $fields = $request->getValue('fields', array());

    $repo = $request->getValue('repo');
    $base = $request->getValue('base');
    $head = $request->getValue('head');


    $repoId = $request->getValue('repoId');
    $repository = id(new PhabricatorRepositoryQuery())
    ->setViewer($viewer)
    ->withIDs(array($repoId))
    ->executeOne();
    if (!$repository) {
      throw new ConduitException('ERR_REPO_NOT_FOUND');
    } 
    $fields['repository'] = array($repository->getPHID());

    $projectId = $request->getValue('projectId');
    $project = id(new PhabricatorProjectQuery())
    ->setViewer($viewer)
    ->withIDs(array($projectId))
    ->needReviewers(true)
    ->executeOne();
    if (!$project) {
      throw new ConduitException('ERR_PROJECT_NOT_FOUND');
    }      
    if ($project->getIsPullRequest()) {
      $head = $viewer->getGithubUsername().':'.$head;
    }
    $fields['projects'] = array($project->getPHID());

    $reviewers = $project->getReviewerPHIDs();
    if (!$reviewers || empty($reviewers)) {
      throw new ConduitException('ERR_NO_REVIEWERS');
    }

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

    if (!$newDiff) {
      $changes = $parser->parseDiff($diff);
      foreach ($changes as $key => $change) {
        // Remove "message" changes, e.g. from "git show".
        if ($change->getType() == ArcanistDiffChangeType::TYPE_MESSAGE) {
          unset($changes[$key]);
        }
      }
      $diff_spec = array(
        'changes' => mpull($changes, 'toDictionary'),
        'lintStatus' => $request->getValue('lintStatus'),
        'unitStatus' => 'skip',
        'sourceMachine' => $request->getValue('sourceMachine'),
        'sourcePath' => $request->getValue('sourcePath'),
        'branch' => $request->getValue('branch'),
        'bookmark' => $request->getValue('bookmark'),
        'sourceControlSystem' => $request->getValue('sourceControlSystem'),
        'sourceControlPath'  => $request->getValue('sourceControlPath'),
        'sourceControlBaseRevision' => $request->getValue('sourceControlBaseRevision'),
        'creationMethod' => $request->getValue('creationMethod'),
        );
      $call = new ConduitCall(
        'differential.creatediff',
        array(
          //'diff' => $diff,
          'repositoryPHID' => $repository->getPHID(),
          'repo' => $repo,
          'base' => $base,
          'head' => $head,
          'viewPolicy' => 'users',
          ) + $diff_spec);
      // $call = new ConduitCall(
      //   'differential.createrawdiff',
      //   array(
      //     'diff' => $diff,
      //     'repositoryPHID' => $repository->getPHID(),
      //     'repo' => $repo,
      //     'base' => $base,
      //     'head' => $head,
      //     'viewPolicy' => 'users',
      //     ));
      $call->setUser($viewer);
      $result = $call->execute();

      $diff_id = $result['id'];

      $newDiff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($diff_id))
      ->executeOne();
    }

    $fields['reviewerPHIDs'] = $reviewers;
    $fields['ccPHIDs'] = $reviewers;

    $call = new ConduitCall(
     'differential.createrevision',
     array(
      'fields' => $fields,
      'projectId' => $projectId,
      'diffid' => $newDiff->getID(),
      ));
    $call->setUser($viewer);
    $result = $call->execute();

    return $result;
  }

}
