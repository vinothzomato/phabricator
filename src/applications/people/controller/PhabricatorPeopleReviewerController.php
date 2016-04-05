<?php

final class PhabricatorPeopleReviewerController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $done_uri = $this->getApplicationURI("manage/{$id}/");

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $done_uri);

    $errors = array();

    $v_reviewerPHID = $user->getReviewerPHID();
    $is_reviewer = $v_reviewerPHID === $viewer->getPHID();
    $e_reviewerPHID = true;
    if ($request->isFormPost()) {
      if ($is_reviewer) {
        id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->changeReviewer($user, '');
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
      id(new PhabricatorUserEditor())
      ->setActor($viewer)
      ->changeReviewer($user, $viewer->getPHID());
      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $inst1 = $is_reviewer ? pht(
      'Remove user from your review list!') : 
    pht(
      'Add user to your review list!');

    $form = id(new AphrontFormView())
    ->setUser($viewer)
    ->appendChild(
      id(new AphrontFormTextControl())
      ->setLabel(pht('Reviewer'))
      ->setName('reviewer')
      ->setHidden(true)
      ->setValue($user->getPHID()));

    if ($errors) {
      $errors = id(new PHUIInfoView())->setErrors($errors);
    }

    return $this->newDialog()
    ->setWidth(AphrontDialogView::WIDTH_FORM)
    ->setTitle($is_reviewer ? pht('Are you sure want to remove this user from your review list') : pht('Are you sure want to add this user to your review list'))
    ->appendChild($errors)
    ->appendParagraph($inst1)
    ->appendParagraph(null)
    ->appendForm($form)
    ->addSubmitButton($is_reviewer ? pht('Remove user from Review List') : pht('Add user to Review List'))
    ->addCancelButton($done_uri);
  }

}
