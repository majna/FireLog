<?php
App::uses('CakeLogInterface', 'Log');

/**
 * FirePHP Log engine
 * 
 * FirePHP log handler (http://www.firephp.org/), which uses the Wildfire protocol,
 * providing logging to Firebug Console from PHP.
 * 
 * Ported from Monolog FirePHP handler https://github.com/Seldaek/monolog to CakePHP and refactored.
 * 
 * WARNING: Using FirePHP on production sites can expose sensitive information. 
 * You must protect the security of your application by disabling FirePHP logging on production site.
 * 
 * Usage:
 *
 * {{{
 * CakeLog::config('fire', array('engine' => 'FireLog.FireLog'));
 * }}}
 *
 */
class FireLog implements CakeLogInterface {
	/**
	 * WildFire JSON header message format
	 */
	const PROTOCOL_URI = 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2';

	/**
	 * FirePHP structure for parsing messages & their presentation
	 */
	const STRUCTURE_URI = 'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1';

	/**
	 * Must reference a "known" plugin, otherwise headers won't display in FirePHP
	 */
	const PLUGIN_URI = 'http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/0.3';

	/**
	 * Header prefix for Wildfire to recognize & parse headers
	 */
	const HEADER_PREFIX = 'X-Wf';

	/**
	 * Whether or not Wildfire vendor-specific headers have been generated & sent yet
	 */
	protected static $initialized = false;
	/**
	 * Shared static message index between potentially multiple handlers
	 * @var int
	 */
	protected static $messageIndex = 1;
	/**
	 * Translates CakePHP log levels to Wildfire levels.
	 * Custom levels translates to 'INFO'.
	 * @var array
	 */
	protected $logLevels = array(
		'debug' => 'LOG',
		'notice' => 'INFO',
		'info' => 'INFO',
		'warning' => 'WARN',
		'error' => 'ERROR',
	);

	/**
	 * Creates & sends header for a record, ensuring init headers have been sent prior
	 *
	 * @param string $type The type of log you are making.
	 * @param string $message The message you want to log.
	 * @return boolean success of write.
	 */
	public function write($type, $message) {
		if (php_sapi_name() == 'cli' || !isset($_SERVER['REQUEST_URI'])) {
			return false;
		}

		if (!self::$initialized) {
			$initHeaders = array_merge(
				$this->createHeader(array('Protocol', 1), self::PROTOCOL_URI), $this->createHeader(array(1, 'Structure', 1), self::STRUCTURE_URI), $this->createHeader(array(1, 'Plugin', 1), self::PLUGIN_URI)
			);
			foreach ($initHeaders as $header => $content) {
				$this->sendHeader($header, $content);
			}
			self::$initialized = true;
		}

		$trace = debug_backtrace();
		$message = $this->convertToString($message);

		// Create JSON object describing the appearance of the message in the console
		$json = json_encode(array(
			array(
				'Type' => isset($this->logLevels[$type]) ? $this->logLevels[$type] : 'INFO',
				'File' => isset($trace[1]['file']) ? $trace[1]['file'] : null,
				'Line' => isset($trace[1]['line']) ? $trace[1]['line'] : null,
				'Label' => $type,
			), $message));

		// The message itself is a serialization of the above JSON object + it's length
		$log = sprintf('%s|%s|', strlen($json), $json);
		$header = $this->createHeader(array(1, 1, 1, self::$messageIndex++), $log);

		return $this->sendHeader(key($header), current($header));
	}

	/**
	 * Base header creation function used by init headers & record headers
	 *
	 * @param array $meta Wildfire Plugin, Protocol & Structure Indexes
	 * @param string $message Log message
	 * @return array Complete header string ready for the client as key and message as value
	 */
	protected function createHeader(array $meta, $message) {
		$header = sprintf('%s-%s', self::HEADER_PREFIX, join('-', $meta));

		return array($header => $message);
	}

	/**
	 * Send header string to the client
	 *
	 * @param string $header
	 * @param string $content
	 */
	protected function sendHeader($header, $content) {
		if (headers_sent()) {
			return false;
		}
		header(sprintf('%s: %s', $header, $content));
	}

	/**
	 * Convert variable to string ready for console output
	 * 
	 * @param type $data Variable to convert (string, array, object, resource...)
	 * @return type string Message as string
	 */
	protected function convertToString($data) {
		if (null === $data || is_scalar($data)) {
			return (string) $data;
		}

		return stripslashes(json_encode(Debugger::exportVar($data)));
	}

}