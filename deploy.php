<?php
///////////////////////////////////////////////////////////////
//
//		PHP Github Webook
//		Author: spaceshiptrooper
//		Version: 0.0.6
//		File Last Updated: 2/2/2019 at 1:52 A.M.
//
///////////////////////////////////////////////////////////////

namespace Github\Webhook;

use \stdClass;

class Deploy {

	public function __construct(array $array = []) {

		$this->configuration = $array;

	}

	public function deploy() {

		header('Content-Type: application/json; charset=utf8;');

		return $this->request();

	}

	private function request() {

		if(isset($_SERVER['HTTP_USER_AGENT']) AND ($_SERVER['HTTP_USER_AGENT'] != '' OR $_SERVER['HTTP_USER_AGENT'] != NULL OR !empty($_SERVER['HTTP_USER_AGENT'])) AND strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') !== false) {

			if($_SERVER['REQUEST_METHOD'] == 'POST' OR (isset($this->configuration['options']['debug']) AND $this->configuration['options']['debug'] == true)) {

				if(isset($_POST['payload']) OR (isset($this->configuration['options']['debug']) AND $this->configuration['options']['debug'] == true)) {

					if(isset($_POST['payload'])) {
						$payload = json_decode($_POST['payload']);
					} else {

						// Only use this line to debug the payload manually.
						if(file_exists('github.json')) {
							$payload = json_decode(file_get_contents('github.json'));
						} else {
							$payload = new stdClass();
						}

					}

					if(is_object($payload)) {

						if(isset($payload->repository->private) AND $payload->repository->private == true AND !isset($this->configuration['github']['username']) AND !isset($this->configuration['github']['password'])) {

							header('HTTP/1.1 404 Not Found');
							$payloadError = $this->requestHeaderRawError(404, 'The Github repository you are using is private. Please provide a Github username and password if you want to use a private repository.');
							return json_encode($payloadError, JSON_PRETTY_PRINT);
							die();

						}

						if(isset($payload->repository->full_name)) {

							if(is_array($this->configuration['github']['repo'])) {

								if(in_array($payload->repository->full_name, $this->configuration['github']['repo'])) {

									if(isset($payload->hook) AND isset($payload->hook->events) AND isset($payload->hook->events[0]) AND $payload->hook->events[0] == 'push' AND isset($payload->hook->ping_url)) {

										header('HTTP/1.1 200 Ok');
										$success = $this->requestHeaderSuccess(200, 'Successful ping.');
										return json_encode($success, JSON_PRETTY_PRINT);

									} else {

										if(isset($payload->ref)) {

											return $this->getHeads($payload, $this->configuration['github']['branch']);

										} else {

											header('HTTP/1.1 404 Not Found');
											return $this->requestHeaderError(404, '404 Not Found');

										}

									}

								} else {

									header('HTTP/1.1 404 Not Found');
									return $this->requestHeaderError(404, '404 Not Found');

								}

							} else {

								header('HTTP/1.1 500 Whitelisted repos have to be in an array');
								return $this->requestHeaderError(500, 'Whitelisted repos have to be in an array');

							}

						} else {

							header('HTTP/1.1 404 Not Found');
							return $this->requestHeaderError(404, '404 Not Found');

						}

					} else {

						header('HTTP/1.1 500 Internal Server Error');
						return $this->requestHeaderError(3, 'Payload is required to be an object.');

					}

				} else {

					header('HTTP/1.1 500 Internal Server Error');
					return $this->requestHeaderError(2, 'Something is wrong.');

				}

			} else {

				header('HTTP/1.1 500 Internal Server Error');
				return $this->requestHeaderError(1, 'Incorrect request type.');

			}

		} else {

			header('HTTP/1.1 404 Not Found');
			return $this->requestHeaderError(404, '404 Not Found');

		}

	}

	private function getHeads($payload, $branch = 'master') {

		if($payload->ref == 'refs/heads/' . $branch) {

			$payloadTriggers = new stdClass();

			if(!isset($payload->after)) {
				header('HTTP/1.1 500 Internal Server Error');
				$payloadTriggers->repository = $this->requestHeaderRawError(4, 'Payload doesn\'t contain the after index key.');
			}

			if(!isset($payload->commits) OR !isset($payload->head_commit)) {
				header('HTTP/1.1 500 Internal Server Error');
				$payloadTriggers->commits = $this->requestHeaderRawError(4, 'Payload doesn\'t contain the commits index key.');
			}

			if(!isset($payload->repository)) {
				header('HTTP/1.1 500 Internal Server Error');
				$payloadTriggers->repository = $this->requestHeaderRawError(4, 'Payload doesn\'t contain the repository index key.');
			}

			if(isset($payload->after) AND (isset($payload->commits) OR isset($payload->head_commit)) AND isset($payload->repository)) {

				if(!empty($payload->commits[0]->added) OR !empty($payload->head_commit->added)) {

					$payloadTriggers->added = $this->createFile($payload);

				}

				if(!empty($payload->commits[0]->modified) OR !empty($payload->head_commit->modified)) {

					$payloadTriggers->modified = $this->updateFile($payload);

				}

				if(!empty($payload->commits[0]->removed) OR !empty($payload->head_commit->removed)) {

					$payloadTriggers->removed = $this->removeFile($payload);

				}

			}

			return json_encode($payloadTriggers, JSON_PRETTY_PRINT);

		} else {

			header('HTTP/1.1 404 Not Found');
			return $this->requestHeaderError(404, '404 Not Found');

		}

	}

	private function createFile($payload) {

		$files = [];

		if(isset($payload->commits[0]->added)) {
			$commit = $payload->commits[0]->added;
		} elseif(isset($payload->head_commit->added)) {
			$commit = $payload->head_commit->added;
		}

		foreach($commit AS $added) {

			$addExplode = explode('/', $added);

			$count = count($addExplode);
			$end = end($addExplode);
			array_pop($addExplode);

			$addImplode = implode('/', $addExplode);

			if($count > 1) {

				if(!file_exists($addImplode)) {

					mkdir($addImplode, 0777, true);

				}

				$result = $this->requestGitHub($files, $payload, $added);

				if(file_exists($this->configuration['options']['destination'] . $added)) {

					file_put_contents($this->configuration['options']['destination'] . $added, $result);
					$files[] = $this->requestHeaderSuccess(200, $added . ' has been successfully added.');

					header('HTTP/1.1 200 Ok');

				} else {

					if(is_writeable($this->configuration['options']['destination'])) {

						$newFile = fopen($this->configuration['options']['destination'] . $added, 'w');

						return $this->createFile($payload);

					} else {

						header('HTTP/1.1 403 Permission denied');

						$files[] = $this->requestHeaderRawError(4, 'The file ' . $added . ' was not created because you do not have permission to the destination folder: ' . $this->configuration['options']['destination'] . ' Please go back and make sure that you have read and write permissions for that destination folder.');

					}

				}

			} else {

				$result = $this->requestGitHub($files, $payload, $added);

				if(file_exists($this->configuration['options']['destination'] . $added)) {

					file_put_contents($this->configuration['options']['destination'] . $added, $result);
					$files[] = $this->requestHeaderSuccess(200, $added . ' has been successfully added.');

					header('HTTP/1.1 200 Ok');

				} else {

					if(is_writeable($this->configuration['options']['destination'])) {

						$newFile = fopen($this->configuration['options']['destination'] . $added, 'w');

						return $this->createFile($payload);

					} else {

						header('HTTP/1.1 403 Permission denied');

						$files[] = $this->requestHeaderRawError(4, 'The file ' . $added . ' was not created because you do not have permission to the destination folder: ' . $this->configuration['options']['destination'] . ' Please go back and make sure that you have read and write permissions for that destination folder.');

					}

				}

			}

		}

		return $files;

	}

	private function updateFile($payload) {

		$files = [];

		if(isset($payload->commits[0]->modified)) {
			$commit = $payload->commits[0]->modified;
		} elseif(isset($payload->head_commit->modified)) {
			$commit = $payload->head_commit->modified;
		}

		foreach($commit AS $modified) {

			$modifiedExplode = explode('/', $modified);

			$count = count($modifiedExplode);
			$end = end($modifiedExplode);
			array_pop($modifiedExplode);
			$modifiedImplode = implode('/', $modifiedExplode);

			if($count > 1) {

				if(!file_exists($modifiedImplode)) {

					mkdir($modifiedImplode, 0777, true);

				}

				$result = $this->requestGitHub($files, $payload, $modified);

				if(file_exists($this->configuration['options']['destination'] . $modified)) {

					file_put_contents($this->configuration['options']['destination'] . $modified, $result);
					$files[] = $this->requestHeaderSuccess(200, $modified . ' has been successfully updated.');

					header('HTTP/1.1 200 Ok');

				} else {

					if(is_writeable($this->configuration['options']['destination'])) {

						$newFile = fopen($this->configuration['options']['destination'] . $modified, 'w');

						return $this->updateFile($payload);

					} else {

						header('HTTP/1.1 403 Permission denied');

						$files[] = $this->requestHeaderRawError(4, 'The file ' . $modified . ' could not be updated because you do not have permission to the destination folder: ' . $this->configuration['options']['destination'] . ' Please go back and make sure that you have read and write permissions for that destination folder.');

					}

				}

			} else {

				$result = $this->requestGitHub($files, $payload, $modified);

				if(file_exists($this->configuration['options']['destination'] . $modified)) {

					file_put_contents($this->configuration['options']['destination'] . $modified, $result);
					$files[] = $this->requestHeaderSuccess(200, $modified . ' has been successfully updated.');

					header('HTTP/1.1 200 Ok');

				} else {

					if(is_writeable($this->configuration['options']['destination'])) {

						$newFile = fopen($this->configuration['options']['destination'] . $modified, 'w');

						return $this->updateFile($payload);

					} else {

						header('HTTP/1.1 403 Permission denied');

						$files[] = $this->requestHeaderRawError(4, 'The file ' . $modified . ' could not be updated because you do not have permission to the destination folder: ' . $this->configuration['options']['destination'] . ' Please go back and make sure that you have read and write permissions for that destination folder.');

					}

				}

			}

		}

		return $files;

	}

	private function removeFile($payload) {

		$files = [];

		if(isset($payload->commits[0]->removed)) {
			$commit = $payload->commits[0]->removed;
		} elseif(isset($payload->head_commit->removed)) {
			$commit = $payload->head_commit->removed;
		}

		foreach($commit AS $removed) {

			$removeExplode = explode('/', $removed);

			$count = count($removeExplode);
			$end = end($removeExplode);
			$start = $removeExplode[0];
			array_pop($removeExplode);

			$removeImplode = implode('/', $removeExplode);

			if($count > 1) {

				$result = $this->requestGitHub($files, $payload, $removed);

				if(file_exists($start)) {

					if(is_dir($start)) {

						if(is_writable($removed)) {

							$this->rmdir($start);

							$files[] = $this->requestHeaderSuccess(200, $start . ' has been successfully deleted.');

							header('HTTP/1.1 200 Ok');

						} else {

							header('HTTP/1.1 403 Permission denied');

							$files[] = $this->requestHeaderRawError(4, 'The folder ' . $start . ' could not be removed because you do not have permission to that folder. Please go back and make sure that you have read, write, and executable permissions for that folder.');

						}

					} else {

						if(is_writable($removed)) {

							unlink($removed);

							$files[] = $this->requestHeaderSuccess(200, $removed . ' has been successfully deleted.');

							header('HTTP/1.1 200 Ok');

						} else {

							header('HTTP/1.1 403 Permission denied');

							$files[] = $this->requestHeaderRawError(4, 'The file ' . $removed . ' could not be removed because you do not have permission to that file. Please go back and make sure that you have read, write, and executable permissions for that file.');

						}

					}

				} else {

					header('HTTP/1.1 404 Not Found');

					$files[] = $this->requestHeaderRawError(4, 'The file ' . $removed . ' could not be located and therefore could not be deleted.');

				}

			} else {

				$result = $this->requestGitHub($files, $payload, $removed);

				if(file_exists($start)) {

					if(is_dir($start)) {

						if(is_writable($removed)) {

							$this->rmdir($start);

							$files[] = $this->requestHeaderSuccess(200, $start . ' has been successfully deleted.');

							header('HTTP/1.1 200 Ok');

						} else {

							header('HTTP/1.1 403 Permission denied');

							$files[] = $this->requestHeaderRawError(4, 'The folder ' . $start . ' could not be removed because you do not have permission to that folder. Please go back and make sure that you have read, write, and executable permissions for that folder.');

						}

					} else {

						if(is_writable($removed)) {

							unlink($removed);

							$files[] = $this->requestHeaderSuccess(200, $removed . ' has been successfully deleted.');

							header('HTTP/1.1 200 Ok');

						} else {

							header('HTTP/1.1 403 Permission denied');

							$files[] = $this->requestHeaderRawError(4, 'The file ' . $removed . ' could not be removed because you do not have permission to that file. Please go back and make sure that you have read, write, and executable permissions for that file.');

						}

					}

				} else {

					header('HTTP/1.1 404 Not Found');

					$files[] = $this->requestHeaderRawError(4, 'The file ' . $removed . ' could not be located and therefore could not be deleted.');

				}

			}

		}

		return $files;

	}

	private function requestGitHub($files, $payload, $type) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://github.com');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

		if(isset($this->configuration['github']['username']) AND isset($this->configuration['github']['password'])) {

			curl_setopt($ch, CURLOPT_USERPWD, $this->configuration['github']['username'] . ':' . $this->configuration['github']['password']);

		}

		curl_setopt($ch, CURLOPT_URL, 'https://raw.githubusercontent.com/' . $payload->repository->full_name . '/' . $payload->after . '/' . $type);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		$result = curl_exec($ch);
		curl_close ($ch);

		if($result == "404: Not Found\n") {

			$result = '';

		}

		return $result;

	}

	private function rmdir($dir) {

		if(is_dir($dir)) {

			$objects = scandir($dir);
			foreach($objects as $object) {

				if($object != '.' && $object != '..') {

					if(filetype($dir . '/' . $object) == 'dir') {
						$this->rmdir($dir . '/' . $object);
					} else {
						unlink($dir . '/' . $object);
					}

				}

			}

			reset($objects);
			rmdir($dir);

		}

	}

	private function requestHeaderError($code, $message) {

		$array = [
			'error_code' => $code,
			'error_message' => $message
		];

		$array = json_encode($array, JSON_PRETTY_PRINT);

		return $array;

	}

	private function requestHeaderRawError($code, $message) {

		$array = [
			'error_code' => $code,
			'error_message' => $message
		];

		return $array;

	}

	private function requestHeaderSuccess($code, $message) {

		$array = [
			'code' => $code,
			'message' => $message
		];

		return $array;

	}

}