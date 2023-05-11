<?php

namespace Vipkwd\Tracy;

use Tracy\IBarPanel;

/**
 * Panel showing branch and commit hash of this branch to be able identify deployed version.
 *
 * @author Jan Drábek
 * @author Vojtěch Vondra
 * @author Štěpán Škorpil
 */
class GitVersionPanel implements IBarPanel
{

	private $read = false;

	private $dir;

	private $branch;
	private $commit;

	public function getPanel()
	{
		$this->parseHead();
		ob_start(function () {
		});
		require __DIR__ . '/templates/GitVersionPanel.panel.phtml';
		return ob_get_clean();
	}

	protected function getLogTail($rowCount = 5)
	{
		$dir = $this->findGitDir();
		$logHead = $dir . '/logs/HEAD';
		if (!$dir || !is_readable($logHead)) {
			return [];
		}
		$fp = fopen($logHead, 'r');
		fseek($fp, -1, SEEK_END);
		$pos = ftell($fp);
		$log = "";
		$rowCounter = -1;
		while ($rowCounter <= $rowCount && $pos >= 0) {
			$char = fgetc($fp);
			$log = $char . $log;
			if ($char == "\n")
				$rowCounter++;
			fseek($fp, $pos--);
		}
		$result = [];
		foreach (explode("\n", trim($log)) as $row) {
			$input = [];
			list($row, $input['action']) = explode("\t", $row, 2);
			list($input['from'], $input['to'], $input['user']) = explode(" ", $row, 3);
			list($ut, $zonetime) = explode(" +", $input['user']);

			$time = date('Y-m-d H:i:s', substr($ut, strrpos($ut, ' ')));
			$input['user'] = implode(
				'',
				[
					$time,
					'',
					'+',
					$zonetime,
					'( ',
					substr($ut, 0, strrpos($ut, ' ') + 1),
					')'
				]
			);
			unset($user);
			$result[(strtotime($time))] = $input;
		}
		krsort($result);
		return $result;
	}

	protected function getCurrentBranchName()
	{
		$this->parseHead();
		if ($this->branch) {
			return $this->branch;
		} elseif ($this->commit) {
			return 'detached';
		}
		return 'not versioned';
	}
	protected function getTags()
	{
		$dir = $this->findGitDir();

		if (is_dir($dir) && is_readable($dir)) {
			$files = scandir($dir . '/refs/tags');
			$message = '';

			if ($dir && is_array($files)) {
				foreach ($files as $file) {
					if ('.' !== $file && '..' !== $file) {
						$message .= $file . ' ';
					}
				}

				return $message;
			}
		}
		return null;
	}
	protected function getRemotes()
	{
		$dir = $this->findGitDir();

		try {
			$files = scandir($dir . '/refs/remotes');
		} catch (\ErrorException $e) {
			return null;
		}

		$message = '';

		if ($dir && is_array($files)) {
			foreach ($files as $file) {
				if ('.' !== $file && '..' !== $file) {
					$message .= $file . ' ';
				}
			}

			return $message;
		}

		return null;
	}
	protected function getCurrentCommitHash()
	{
		$this->parseHead();
		if ($this->commit) {
			return $this->commit;
		}
		return 'not versioned';
	}

	protected function config(bool $section = false)
	{
		$dir = $this->findGitDir();
		$data = [];
		if (is_dir($dir) && is_readable($dir)) {
			$fp = @fopen($dir . '/config', "r");
			$lastKey = '';
			while (!@feof($fp)) {
				$line = trim(@fgets($fp));
				$isSection = preg_match("/^\[([\w\ \"_]+)\]$/", $line, $matches);
				if ($isSection) {
					$lastKey = str_replace([' "', '"'], ['-', ''], $matches[1]);
				} else if (strrpos($line, " = ") !== false) {
					list($key, $val) = explode(' = ', $line);
					$data[$lastKey][trim($key)] = trim($val);
				}
			}
			unset($lastKey);
			@fclose($fp);
			if ($section && !empty($data)) {
				$_data = [];
				foreach (array_keys($data) as $section) {
					foreach (array_keys($data[$section]) as $field) {
						$_data[] = [
							"key" => "{$section}.$field",
							"val" => $data[$section][$field]
						];
						unset($field);
					}
					unset($section);
				}
				$data = $_data;
				unset($_data);
			}
		}
		return $data;
	}

	protected function getLastCommitMessage()
	{
		$dir = $this->findGitDir();

		$fileMessage = $dir . '/COMMIT_EDITMSG';
		if ($dir && is_readable($fileMessage)) {
			$message = file_get_contents($fileMessage);
			return $message;
		}
		return null;
	}

	public function getTab()
	{
		ob_start(function () {
		});
		require __DIR__ . '/templates/GitVersionPanel.tab.phtml';
		return ob_get_clean();
	}

	private function findGitDir()
	{
		if ($this->dir)
			return $this->dir;

		$scriptPath = $_SERVER['SCRIPT_FILENAME'];
		$dir = realpath(dirname($scriptPath));
		while ($dir !== false) {
			flush();
			$currentDir = $dir;
			$dir .= '/..';
			$dir = realpath($dir);
			$gitDir = $dir . '/.git';
			if (is_dir($gitDir)) {
				$this->dir = $gitDir;
				return $gitDir;
			}
			// Stop recursion to parent on root directory
			if ($dir == $currentDir) {
				break;
			}
		}
		return NULL;
	}

	private function parseHead()
	{
		if (!$this->read) {
			$dir = $this->findGitDir();

			$head = $dir . '/HEAD';
			if ($dir) {
				if (is_readable($head)) {
					$branch = file_get_contents($head);
					if (strpos($branch, 'ref:') === 0) {
						$parts = explode('/', $branch, 3);
						$this->branch = $parts[2];

						$commitFile = $dir . '/' . trim(substr($branch, 5, strlen($branch)));
						if (is_readable($commitFile)) {
							$this->commit = file_get_contents($commitFile);
						}
					} else {
						$this->commit = $branch;
					}
				}
			}
			$this->read = true;
		}
	}
}
