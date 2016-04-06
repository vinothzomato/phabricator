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
    if ($request->isFormPost()) {

      $repository_tokenizer = $request->getArr(
        id(new DifferentialRepositoryField())->getFieldKey());
      if ($repository_tokenizer) {
        $repository_phid = reset($repository_tokenizer);
      }

      $v_repo = $request->getStr('repo');
      $v_base = $request->getStr('base');
      $v_head = $request->getStr('head');

      if ($v_repo && $v_base && $v_head) {

        $authorGithubUser = new GithubApiUser();
        $authorGithubUser->setUsername($viewer->getGithubUsername());
        $authorGithubUser->setToken($viewer->getGithubAccessToken());

        $repos_json = $authorGithubUser->getAllRepos();
        $repos = json_decode($repos_json, true);
        $repos_urls = ipull($repos, 'html_url');

        $diff_response = $authorGithubUser->getDiff($repos_urls[intval($v_repo)],$v_base,$v_head);
        if ($diff_response) {
          $diff = $diff_response;
        }
      }

      if (!strlen($diff)) {
        $errors[] = pht(
          'You can not create an empty diff. Paste a diff or upload a '.
          'file containing a diff.');
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
              'repo' => $v_repo,
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
    if ($diff) {
      $form
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Raw Diff'))
          ->setName('diff')
          ->setValue($diff)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setError($e_diff));
    }
    else{
      $authorGithubUser = new GithubApiUser();
      $authorGithubUser->setUsername($viewer->getGithubUsername());
      $authorGithubUser->setToken($viewer->getGithubAccessToken());
      $repos_json = $authorGithubUser->getAllRepos();
      $repos = json_decode($repos_json, true);
      $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Remote Repository'))
          ->setName('repo')
          ->setValue($v_repo)
          ->setOptions(ipull($repos, 'html_url')))
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
    }

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
