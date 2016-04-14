<?php

final class PhabricatorProjectReviewersAddController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $is_admin = $viewer->getIsAdmin();
    $id = $request->getURIData('id');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW
          ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    if (!$is_admin) {
      return new Aphront404Response();
    }

    $this->setProject($project);

    if (!$project->supportsEditMembers()) {
      return new Aphront404Response();
    }

    $done_uri = "/project/reviewers/{$id}/";

    if ($request->isFormPost()) {
      $reviewer_phids = $request->getArr('reviewerPHIDs');

      $type_reviewer = PhabricatorProjectProjectHasReviewerEdgeType::EDGECONST;

      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_reviewer)
        ->setNewValue(
          array(
            '+' => array_fuse($reviewer_phids),
          ));

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($done_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('reviewerPHIDs')
          ->setLabel(pht('Reviewers'))
          ->setDatasource(new PhabricatorPeopleDatasource()));

    return $this->newDialog()
      ->setTitle(pht('Add Reviewers'))
      ->appendForm($form)
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Add Reviewers'));
  }

}
