<?php

final class PhabricatorProjectReviewersProfilePanel
  extends PhabricatorProfilePanel {

  const PANELKEY = 'project.reviewers';

  public function getPanelTypeName() {
    return pht('Project Reviewers');
  }

  private function getDefaultName() {
    return pht('Reviewers');
  }

  public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config) {
    $name = $config->getPanelProperty('name');

    if (strlen($name)) {
      return $name;
    }

    return $this->getDefaultName();
  }

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setPlaceholder($this->getDefaultName())
        ->setValue($config->getPanelProperty('name')),
    );
  }

  protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {

    $project = $config->getProfileObject();

    $id = $project->getID();

    $name = $this->getDisplayName($config);
    $icon = 'fa-group';
    $href = "/project/reviewers/{$id}/";

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
