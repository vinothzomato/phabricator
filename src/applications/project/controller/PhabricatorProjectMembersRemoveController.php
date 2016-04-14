<?php

final class PhabricatorProjectMembersRemoveController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $type = $request->getURIData('type');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needReviewers(true)
      ->needWatchers(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    if ($type == 'watchers') {
      $is_watcher = true;
      $is_reviewer = false;
      $edge_type = PhabricatorObjectHasWatcherEdgeType::EDGECONST;
    } else if ($type == 'reviewers') {
      if (!$project->supportsEditMembers()) {
        return new Aphront404Response();
      }
      $is_reviewer = true;
      $is_watcher = false;
      $edge_type = PhabricatorProjectProjectHasReviewerEdgeType::EDGECONST;
    }
    else {
      if (!$project->supportsEditMembers()) {
        return new Aphront404Response();
      }

      $is_watcher = false;
      $is_reviewer = false;
      $edge_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
    }

    $members_uri = $this->getApplicationURI('members/'.$project->getID().'/');
    $reviewers_uri = $this->getApplicationURI('reviewers/'.$project->getID().'/');
    $remove_phid = $request->getStr('phid');

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(
          array(
            '-' => array($remove_phid => $remove_phid),
          ));

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      if ($is_reviewer) {
        return id(new AphrontRedirectResponse())
        ->setURI($reviewers_uri);
      }  
      return id(new AphrontRedirectResponse())
        ->setURI($members_uri);
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($remove_phid))
      ->executeOne();

    $target_name = phutil_tag('strong', array(), $handle->getName());
    $project_name = phutil_tag('strong', array(), $project->getName());

    if ($is_watcher) {
      $title = pht('Remove Watcher');
      $body = pht(
        'Remove %s as a watcher of %s?',
        $target_name,
        $project_name);
      $button = pht('Remove Watcher');
    } else if ($is_reviewer) {
      $title = pht('Remove Reviewer');
      $body = pht(
        'Remove %s as a reviewer of %s?',
        $target_name,
        $project_name);
      $button = pht('Remove Reviewer');
    } else {
      $title = pht('Remove Member');
      $body = pht(
        'Remove %s as a project member of %s?',
        $target_name,
        $project_name);
      $button = pht('Remove Member');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('phid', $remove_phid)
      ->appendParagraph($body)
      ->addCancelButton($members_uri)
      ->addSubmitButton($button);
  }

}
