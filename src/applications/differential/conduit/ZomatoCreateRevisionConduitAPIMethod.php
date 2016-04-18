<?php

final class ZomatoCreateRevisionConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'zomato.createrevision';
  }

  public function getMethodDescription() {
    return pht('Create a new differential revision.');
  }

  protected function defineParamTypes() {
    $vcs_const = $this->formatStringConstants(
      array(
        'git'
        ));
    $status_const = $this->formatStringConstants(
      array(
        'none',
        'skip',
        'okay',
        'warn',
        'fail',
        ));
    return array(
      'changes' => 'optional list<dict>',
      'diff' => 'optional string',
      'fields' => 'required dict',
      'repo' => 'required string',
      'base' => 'required string',
      'head' => 'required string',
      'repoId' => 'required string',
      'projectId' => 'required string',
      'sourceMachine'             => 'optional string',
      'sourcePath'                => 'optional string',
      'branch'                    => 'optional string',
      'bookmark'                  => 'optional string',
      'sourceControlSystem'       => 'optional '.$vcs_const,
      'sourceControlPath'         => 'optional string',
      'sourceControlBaseRevision' => 'optional string',
      'creationMethod'            => 'optional string',
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
    $change_data = $request->getValue('changes');
    $diff_data = $request->getValue('diff');

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

    if ($diff_data) {
      $diff = $diff_data;
    }
    else{
      $authorGithubUser = new GithubApiUser();
      $authorGithubUser->setUsername($viewer->getGithubUsername());
      $authorGithubUser->setToken($viewer->getGithubAccessToken());

      $diff = $authorGithubUser->getDiff($repo,$base,$head);

      if (!$diff) {
        throw new ConduitException('ERR_NO_CHANGES');
      }
    }

    $prev_diff = id(new DifferentialDiff())->loadOneWhere(
      'repo = %s AND base = %s AND head = %s ORDER BY id DESC limit 1',
      $repo,$base,$head);

    $newDiff = null;

    if ($prev_diff) {
      $prev_diff = id(new DifferentialDiffQuery())
      ->withIDs(array($prev_diff->getID()))
      ->setViewer($viewer)
      ->needChangesets(true)
      ->executeOne();

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
      $diff_changes = $parser->parseDiff($raw_diff);

      $loader = id(new PhabricatorFileBundleLoader())
      ->setViewer($viewer);

      $bundle = ArcanistBundle::newFromChanges($diff_changes);
      $bundle->setLoadFileDataCallback(array($loader, 'loadFileData'));
      $raw_diff = $bundle->toGitPatch();

      $parser = new ArcanistDiffParser();
      $diff_changes = $parser->parseDiff($diff);

      $loader = id(new PhabricatorFileBundleLoader())
      ->setViewer($viewer);

      $bundle = ArcanistBundle::newFromChanges($diff_changes);
      $bundle->setLoadFileDataCallback(array($loader, 'loadFileData'));
      $new_diff = $bundle->toGitPatch();

      if ($diff_data) {
        return array('old' => $raw_diff, 'new'=>$new_diff);
      }

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
      if ($change_data) {
       $diff_spec = array(
        'changes' => $change_data,
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
          'repositoryPHID' => $repository->getPHID(),
          'repo' => $repo,
          'base' => $base,
          'head' => $head,
          'viewPolicy' => 'users',
          ) + $diff_spec);
       $call->setUser($viewer);
       $result = $call->execute();

       $diff_id = $result['diffid'];
       $diffid = $diff_id;
      }
      else{
        $call = new ConduitCall(
          'differential.createrawdiff',
          array(
            'diff' => $diff,
            'repositoryPHID' => $repository->getPHID(),
            'repo' => $repo,
            'base' => $base,
            'head' => $head,
            'viewPolicy' => 'users',
            ));
        $call->setUser($viewer);
        $result = $call->execute();

        $diff_id = $result['id'];
        $diffid = $diff_id;

        $newDiff = id(new DifferentialDiffQuery())
        ->setViewer($viewer)
        ->withIDs(array($diff_id))
        ->executeOne();

        switch ($request->getValue('lintStatus')) {
          case 'skip':
          $lint_status = DifferentialLintStatus::LINT_SKIP;
          break;
          case 'okay':
          $lint_status = DifferentialLintStatus::LINT_OKAY;
          break;
          case 'warn':
          $lint_status = DifferentialLintStatus::LINT_WARN;
          break;
          case 'fail':
          $lint_status = DifferentialLintStatus::LINT_FAIL;
          break;
          case 'none':
          default:
          $lint_status = DifferentialLintStatus::LINT_NONE;
          break;
        }
        $newDiff->setLintStatus($lint_status);
        $newDiff->save();
      }
    }
    else{
      $diffid = $newDiff->getID();
    }

    $fields['reviewerPHIDs'] = $reviewers;
    $fields['ccPHIDs'] = $reviewers;

    $call = new ConduitCall(
     'differential.createrevision',
     array(
      'fields' => $fields,
      'projectId' => $projectId,
      'diffid' => $diffid,
      ));
    $call->setUser($viewer);
    $result = $call->execute();

    return $result;
  }

}
