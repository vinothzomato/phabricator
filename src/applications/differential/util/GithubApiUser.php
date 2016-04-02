<?php

final class GithubApiUser
extends Phobject {

	protected $username;
	protected $token;
	protected $repo;

	protected $baseApiURL = "https://api.github.com/";

	public function createPullRequest($title,$base,$head){
		if (!$title || !$base || !$head) {
			throw new Exception(
				pht('Title, Base and Head are required to create pull request'));
		}

		$url = $this->baseApiURL.'/repos/'.$this->username.'/'.$this->repo.'/pulls';
		$postData = array(
			'title' => $title,
			'head' => $base,
			'base' => $head
			);
		return $this->executeCurlPostRequest($url,$postData);
	}

	private function executeCurlPostRequest($url, $postData){
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Authorization: token '.$this->token,
				'User-Agent: Zomato-Phabricator',
				'Content-Type: application/json'
				),
			CURLOPT_POSTFIELDS => json_encode($postData)
			));

		return curl_exec($ch);
	}

	private function executeCurlPutRequest($url, $postData){
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST => "PUT",
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Authorization: token '.$this->token,
				'User-Agent: Zomato-Phabricator',
				'Content-Type: application/json'
				),
			CURLOPT_POSTFIELDS => json_encode($postData)
			));

		return curl_exec($ch);
	}

	private function executeCurlGetRequest($url){
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Authorization: token '.$this->token,
				'User-Agent: Zomato-Phabricator',
				'Content-Type: application/json'
				),
			));

		return curl_exec($ch);
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