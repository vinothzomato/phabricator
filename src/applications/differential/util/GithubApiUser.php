<?php

final class GithubApiUser
extends Phobject {

	protected $username;
	protected $token;
	protected $repo;

	protected $baseApiURL = "https://api.github.com/";

	public function createPullRequest($base,$head){
		if (!$base || !$head) {
			throw new Exception(
				pht('Base and Head are required to create pull request'));
		}
		return "https://api.github.com/repos/octocat/Hello-World/pulls/1347";
	}

	public function mergePullRequest($id){
		if (!$id) {
			throw new Exception(
				pht('Base and Head are required to create pull request'));
		}
		return "successful";
	}

	public function getUsername() {
		return $this->username;
	}

	public function getToken() {
		return $this->token;
	}

	public function getRepo() {
		return $this->repo;
	}

	public function setUsername($username) {
		$this->username = $username;
	}

	public function setToken($token) {
		$this->token = $token;
	}

	public function setRepo($repo) {
		$this->repo = $repo;
	}
}  	

?>