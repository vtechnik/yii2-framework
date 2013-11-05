<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\web;

use Yii;
use yii\base\Component;

/**
 * AssetConverter supports conversion of several popular script formats into JS or CSS scripts.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AssetConverter extends Component implements AssetConverterInterface
{
	/**
	 * @var array the commands that are used to perform the asset conversion.
	 * The keys are the asset file extension names, and the values are the corresponding
	 * target script types (either "css" or "js") and the commands used for the conversion.
	 */
	public $commands = [
		'less' => ['css', 'lessc {from} {to}'],
		'scss' => ['css', 'sass {from} {to}'],
		'sass' => ['css', 'sass {from} {to}'],
		'styl' => ['js', 'stylus < {from} > {to}'],
		'coffee' => ['js', 'coffee -p {from} > {to}'],
		'ts' => ['js', 'tsc --out {to} {from}'],
	];

	/**
	 * Converts a given asset file into a CSS or JS file.
	 * @param string $asset the asset file path, relative to $basePath
	 * @param string $basePath the directory the $asset is relative to.
	 * @return string the converted asset file path, relative to $basePath.
	 */
	public function convert($asset, $basePath)
	{
		$pos = strrpos($asset, '.');
		if ($pos !== false) {
			$ext = substr($asset, $pos + 1);
			if (isset($this->commands[$ext])) {
				list ($ext, $command) = $this->commands[$ext];
				$result = substr($asset, 0, $pos + 1) . $ext;
				if (@filemtime("$basePath/$result") < filemtime("$basePath/$asset")) {
					$this->runCommand($command, $basePath, $asset, $result);
				}
				return $result;
			}
		}
		return $asset;
	}

	/**
	 * Runs a command to convert asset files.
	 * @param string $command the command to run
	 * @param string $basePath asset base path and command working directory
	 * @param string $asset the name of the asset file
	 * @param string $result the name of the file to be generated by the converter command
	 * @return bool true on success, false on failure. Failures will be logged.
	 */
	protected function runCommand($command, $basePath, $asset, $result)
	{
		$command = strtr($command, [
			'{from}' => escapeshellarg("$basePath/$asset"),
			'{to}' => escapeshellarg("$basePath/$result"),
		]);
		$descriptor = array(
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		$proc = proc_open($command, $descriptor, $pipes, $basePath);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		foreach($pipes as $pipe) {
			fclose($pipe);
		}
		$status = proc_close($proc);

		if ($status !== 0) {
			Yii::error("AssetConverter command '$command' failed with exit code $status:\nSTDOUT:\n$stdout\nSTDERR:\n$stderr\n");
		} else {
			Yii::trace("Converted $asset into $result:\nSTDOUT:\n$stdout\nSTDERR:\n$stderr", __METHOD__);
		}
		return $status === 0;
	}
}
