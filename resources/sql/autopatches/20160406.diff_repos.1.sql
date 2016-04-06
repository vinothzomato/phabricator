ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD repo VARCHAR(255) COLLATE utf8_general_ci DEFAULT NULL;

ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD base VARCHAR(255) COLLATE utf8_general_ci DEFAULT NULL;

ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD head VARCHAR(255) COLLATE utf8_general_ci DEFAULT NULL;    