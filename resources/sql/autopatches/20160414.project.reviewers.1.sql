/* PhabricatorProjectProjectHasReviewerEdgeType::EDGECONST = 98 */
/* PhabricatorProjectMaterializedReviewerEdgeType::EDGECONST = 99 */

INSERT IGNORE INTO {$NAMESPACE}_project.edge (src, type, dst, dateCreated)
  SELECT src, 99, dst, dateCreated FROM {$NAMESPACE}_project.edge
  WHERE type = 98;