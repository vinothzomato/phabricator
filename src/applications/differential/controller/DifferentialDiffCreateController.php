<?php

final class DifferentialDiffCreateController extends DifferentialController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // If we're on the "Update Diff" workflow, load the revision we're going
    // to update.
    $revision = null;
    $revision_id = $request->getURIData('revisionID');
    if ($revision_id) {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withIDs(array($revision_id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$revision) {
        return new Aphront404Response();
      }
    }

    $diff = null;
    $v_repo = null;
    $v_repo_url = null;
    // This object is just for policy stuff
    $diff_object = DifferentialDiff::initializeNewDiff($viewer);
    $repository_phid = null;
    $errors = array();
    $e_diff = null;
    $e_file = null;
    $v_base = null;
    $v_head = null;
    $e_base = null;
    $e_head = null;
    $validation_exception = null;

    if ($revision) {
      $diffs = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withRevisionIDs(array($revision_id))
      ->execute();
      $diffs = array_reverse($diffs, $preserve_keys = true);
      $revision_diff = last($diffs);
      $v_repo_url = $revision_diff->getRepo();
      $v_base = $revision_diff->getBase();
      $v_head = $revision_diff->getHead();
      $repository_phid = $revision_diff->getRepositoryPHID();
    }

    if ($request->isFormPost()) {

      $repository_tokenizer = $request->getArr(
        id(new DifferentialRepositoryField())->getFieldKey());
      if ($repository_tokenizer) {
        $repository_phid = reset($repository_tokenizer);
      }

      $v_repo = $request->getStr('repo');
      $v_base = $request->getStr('base');
      $v_head = $request->getStr('head');

      if (strlen($v_base) && $v_base && $v_head) {

        $authorGithubUser = new GithubApiUser();
        $authorGithubUser->setUsername($viewer->getGithubUsername());
        $authorGithubUser->setToken($viewer->getGithubAccessToken());

        $repos_json = $authorGithubUser->getAllRepos();
        $repos = json_decode($repos_json, true);
        $repos_urls = ipull($repos, 'html_url');
        $repo_url = $repos_urls[intval($v_repo)];

        $diff_response = $authorGithubUser->getDiff($repo_url,$v_base,$v_head);
        if ($diff_response) {
          $diff = $diff_response;
          $prev_diff = id(new DifferentialDiff())->loadOneWhere(
            'repo = %s AND base = %s AND head = %s ORDER BY id DESC limit 1',
            $repo_url,$v_base,$v_head);
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
                  return id(new AphrontRedirectResponse())
                  ->setURI('/D'.$prev_diff->getRevisionID().'?id='.$prev_diff->getID());
                }
              }
              else{
                $path = '/differential/diff/'.$prev_diff->getID().'/';
                if ($revision) {
                  $path = $path.'?revisionID='.$revision->getID();
                }
                return id(new AphrontRedirectResponse())
                ->setURI($path);
              }
           }
          }
        }
      }

      if (!strlen($diff)) {
        $errors[] = pht(
          'You can not create an empty diff. No changes found.');
        $e_diff = pht('Required');
        $e_file = pht('Required');
      }

      if (!$errors) {
        try {
          $call = new ConduitCall(
            'differential.createrawdiff',
            array(
              'diff' => $diff,
              'repositoryPHID' => $repository_phid,
              'repo' => $repo_url,
              'base' => $v_base,
              'head' => $v_head,
              'viewPolicy' => $request->getStr('viewPolicy'),
            ));
          $call->setUser($viewer);
          $result = $call->execute();

          $diff_id = $result['id'];

          $uri = $this->getApplicationURI("diff/{$diff_id}/");
          $uri = new PhutilURI($uri);
          if ($revision) {
            $uri->setQueryParam('revisionID', $revision->getID());
          }

          return id(new AphrontRedirectResponse())->setURI($uri);
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $validation_exception = $ex;
        }
      }
    }

    $form = new AphrontFormView();
    $arcanist_href = PhabricatorEnv::getDoclink('Arcanist User Guide');
    $arcanist_link = phutil_tag(
      'a',
      array(
        'href' => $arcanist_href,
        'target' => '_blank',
      ),
      pht('Learn More'));

    $cancel_uri = $this->getApplicationURI();

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($diff_object)
      ->execute();

    $info_view = null;

    if ($revision) {
      $title = pht('Update Diff');
      $header = pht('Update Diff');
      $button = pht('Continue');
      $header_icon = 'fa-upload';
    } else {
      $title = pht('Create Diff');
      $header = pht('Create New Diff');
      $button = pht('Create Diff');
      $header_icon = 'fa-plus-square';
    }

    $form
      ->setEncType('multipart/form-data')
      ->setUser($viewer);

    if ($revision) {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Updating Revision'))
          ->setValue($viewer->renderHandle($revision->getPHID())));
    }

    if ($repository_phid) {
      $repository_value = array($repository_phid);
    } else {
      $repository_value = array();
    }

    $authorGithubUser = new GithubApiUser();
    $authorGithubUser->setUsername($viewer->getGithubUsername());
    $authorGithubUser->setToken($viewer->getGithubAccessToken());
    $repos_json = $authorGithubUser->getAllRepos();
    $repos = json_decode($repos_json, true);
    $options = ipull($repos, 'html_url');

    if ($v_repo_url) {
      $v_repo = array_search($v_repo_url,$options);
    }

    $form
    ->appendChild(
      id(new AphrontFormSelectControl())
      ->setLabel(pht('Remote Repository'))
      ->setName('repo')
      ->setValue($v_repo)
      ->setOptions($options))
    ->appendChild(
      id(new AphrontFormTextControl())
      ->setLabel(pht('Base'))
      ->setName('base')
      ->setValue($v_base)
      ->setError($e_base))
    ->appendChild(
      id(new AphrontFormTextControl())
      ->setLabel(pht('Head'))
      ->setName('head')
      ->setValue($v_head)
      ->setError($e_head));

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName(id(new DifferentialRepositoryField())->getFieldKey())
          ->setLabel(pht('Local Repository'))
          ->setDatasource(new DiffusionRepositoryDatasource())
          ->setValue($repository_value)
          ->setLimit(1))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setName('viewPolicy')
          ->setPolicyObject($diff_object)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($button));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Diff'))
      ->setValidationException($validation_exception)
      ->setForm($form)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setFormErrors($errors);

    $crumbs = $this->buildApplicationCrumbs();
    if ($revision) {
      $crumbs->addTextCrumb(
        $revision->getMonogram(),
        '/'.$revision->getMonogram());
    }
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon($header_icon);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $info_view,
        $form_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
