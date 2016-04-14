<?php

final class PhabricatorProjectReviewerListView
  extends PhabricatorProjectUserListView {

  function __construct() {
    $this->setHref('reviewers');
  }  

  protected function canEditList() {
    $viewer = $this->getUser();
    $project = $this->getProject();

    if (!$project->supportsEditMembers()) {
      return false;
    }

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  protected function getNoDataString() {
    return pht('This project does not have any reviewers.');
  }

  protected function getRemoveURI($phid) {
    $project = $this->getProject();
    $id = $project->getID();
    return "/project/reviewers/{$id}/remove/?phid={$phid}";
  }

  protected function getHeaderText() {
    return pht('Reviewers');
  }

}
