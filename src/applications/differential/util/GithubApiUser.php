<?php

final class GithubApiUser
extends Phobject {

	public $username;
	public $token;
	public $repo;

	protected $baseApiURL = "https://api.github.com/";

	public function createPullRequest($base,$head){
		if (!$base || !$head) {
			throw new Exception(
				pht('Base and Head are required to create pull request'));
		}
		return "https://api.github.com/repos/octocat/Hello-World/pulls/1347";
	}

}  	

?>