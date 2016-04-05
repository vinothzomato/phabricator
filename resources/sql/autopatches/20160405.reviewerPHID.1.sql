ALTER TABLE {$NAMESPACE}_user.user
  ADD reviewerPHID VARCHAR(64) COLLATE utf8_bin;

 ALTER TABLE {$NAMESPACE}_user.user
  ADD KEY `key_reviewer` (reviewerPHID); 