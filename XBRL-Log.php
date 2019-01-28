<?php

/**
 * Implements the a logging class.
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use XBRL\Formulas\Exceptions\FormulasException;

if ( ! class_exists( "\\Log", true ) )
{
	/**
	 * Load the Log class
	 */
	require_once dirname( __FILE__ ) . "/../log/Log.php";
	/**
	 * Load the event_log handler implementation
	 */
	require_once dirname( __FILE__ ) . "/../log/log/error-log.php";
}

/**
 * This a singleton class used to provide a common logging facility for all XBRL class instances.
 */
class XBRL_Log
{

	/**
	 * A reference to this singleton instance
	 * @var Singleton
	 */
	private static $instance;

	/**
	 * The log instance to use
	 * @var Log
	 */
	private $log;

	/**
	 * Flag holding the instance validation warning state.  Will be true if there has been at least one warning
	 * @var bool
	 */
	private $instanceValidationWarning = false;

	/**
	 * Flag holding a conformance issue warning state.  Will be true if there has been at least one warning
	 * @var bool
	 */
	private $conformanceIssueWarning = false;

	/**
	 * Flag holding a business rules violation warning state.  Will be true if there has been at least one warning
	 * @var bool
	 */
	private $businessRulesViolationeWarning = false;

	/**
	 * Get an instance of the global singleton
	 * @return XBRL_Log
	 */
	public static function getInstance()
	{
		if ( is_null( self::$instance ) )
		{
			self::$instance = new self();
			self::$instance->createLog( 'error_log', PEAR_LOG_TYPE_SYSTEM, 'xbrl_log',
				array(
					'lineFormat' => '[%{priority}] %{message}',
				)
			);

		}
		return self::$instance;
	}

	/**
	 * Attach an observer to the log
	 * @param \Log_observer $observer
	 * @return boolean
	 */
	public function attach( &$observer )
	{
		return $this->log->attach( $observer );
	}

	/**
	 * Detch the observer from the log
	 * @param \Log_observer $observer
	 * @return boolean
	 */
	public function detach( &$observer )
	{
		return $this->log->detach( $observer );
	}

	/**
	 * Set the log reporting priority
	 * @param int $priority
	 */
	public function setPriority( $priority = PEAR_LOG_ALL )
	{
		$this->log->setPriority( $priority );
	}

	/**
	 * Set a mask
	 * @param int $mask
	 */
	public function setMask( $mask = PEAR_LOG_ALL )
	{
		return $this->log->setMask( $mask );
	}

	/**
	 * This creates a specific type of log instance
	 * @param string $handler	The type of Log handler to construct
	 * @param string $name 		The name of the log resource to which the events
	 *							will be logged.  Defaults to an empty string.
	 * @param string $ident 	An identification string that will be included in
	 *							all log events logged by this handler.  This value
	 *							defaults to an empty string and can be changed at
	 *							runtime using the ``setIdent()`` method.
	 * @param array $conf		Associative array of key-value pairs that are
	 *							used to specify any handler-specific settings.
	 * @param int $level		Log messages up to and including this level.
	 *							This value defaults to ``PEAR_LOG_DEBUG``.
	 *							See `Log Levels`_ and `Log Level Masks`_.
	 * @return void
	 */
	public function createLog( $handler, $name, $ident, $conf = null, $level = null )
	{
		$this->log = Log::singleton( $handler, $name, $ident, $conf, $level );
	}

	/**
	 * If you know what you are doing and want to create a custom log,
	 * perhaps with custom handler, or perhaps you want a composite log
	 * that directs logs to multiple locations you set it here.
	 * @param Log $log The logging instance to use
	 * @return void
	 */
	public function setLog( $log )
	{
		$this->log = $log;
	}

	/**
	 * A convenience function for logging a emergency event.  It will log a
	 * message at the PEAR_LOG_EMERG log level.
	 *
	 * PEAR_LOG_EMERG
	 *
	 * @param   mixed   $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function emerg( $message )
	{
		if ( ! $this->log ) return;
		return $this->log->emerg( $message );
	}

	/**
	 * A convenience function for logging an alert event.  It will log a
	 * message at the PEAR_LOG_ALERT log level.
	 *
	 * PEAR_LOG_ALERT
	 *
	 * @param   mixed   $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function alert( $message )
	{
		if ( ! $this->log ) return;
		return $this->log->alert( $message );
	}

	/**
	 * A convenience function for logging a critical event.  It will log a
	 * message at the PEAR_LOG_CRIT log level.
	 *
	 * PEAR_LOG_CRIT
	 *
	 * @param  mixed $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function crit( $message )
	{
		if ( ! $this->log ) return;
		return $this->log->crit( $message );
	}

	/**
	 * A convenience function for logging a error event.  It will log a
	 * message at the PEAR_LOG_ERR log level.
	 *
	 * PEAR_LOG_ERR
	 *
	 * @param mixed $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function err( $message )
	{
		$ex = new Exception();
		echo $ex->getTraceAsString();

		if ( ! $this->log ) return;
		return $this->log->err( $message );
	}

	/**
	 * A convenience function for logging a warning event.  It will log a
	 * message at the PEAR_LOG_WARNING log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param   mixed   $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function warning( $message )
	{
		if ( ! $this->log ) return;
		return $this->log->warning( $message );
	}

	/**
	 * A convenience function for logging a notice event.  It will log a
	 * message at the PEAR_LOG_NOTICE log level.
	 *
	 * PEAR_LOG_NOTICE
	 *
	 * @param   mixed   $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function notice( $message )
	{
		if ( ! $this->log ) return;
		return $this->log->notice( $message );
	}

	/**
	 * A convenience function for logging a information event.  It will log a
	 * message at the PEAR_LOG_INFO log level.
	 *
	 * PEAR_LOG_INFO
	 *
	 * @param   mixed   $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function info( $message = "" )
	{
		if ( ! $this->log ) return;
		return $this->log->info( $message );
	}

	/**
	 * A convenience function for logging a debug event.  It will log a
	 * message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_DEBUG
	 *
	 * @param mixed $message	String or object containing the message to log.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function debug( $message )
	{
		if ( ! $this->log ) return;
		return $this->log->debug( $message );
	}

	/**
	 * A convenience function for logging a event about an XBRL dimensions specification conformance issue.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function dimension_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		$msg = sprintf( "[dimension] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		return $this->log->warning( $msg );
	}

	/**
	 * A convenience function for logging a event about an XBRL taxonony specification conformance issue.
	 * It will log a message at the PEAR_LOG_WARNING log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function taxonomy_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		$msg = sprintf( "[taxonomy] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		return $this->log->warning( $msg );
	}

	/**
	 * A convenience function for logging a event about an XBRL business rules violation issue.
	 * It will log a message at the PEAR_LOG_WARNING log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The rules topic reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function business_rules_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->businessRulesViolationeWarning = true;
		$msg = sprintf( "[business rules] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		return $this->log->warning( $msg );
	}

	/**
	 * A convenience function for logging a event about an XBRL formula specification conformance issue.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function formula_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		$errorMessage = sprintf( "[formula] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		$this->log->warning( $errorMessage );
		if ( isset( $source['error'] ) )
		{
			throw FormulasException::withType( $source['error'], "formula", $errorMessage );
		}
		return $errorMessage;
	}

	/**
	 * A convenience function for logging a event about an XBRL formula specification conformance issue.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function consistencyassertion_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		$errorMessage = $this->log->warning( sprintf( "[consistency assertion] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		if ( isset( $source['error'] ) )
		{
			throw FormulasException::withType( $source['error'], "consistency-assertion", $errorMessage );
		}
		return $errorMessage;
	}

	/**
	 * A convenience function for logging a event about an XBRL formula specification conformance issue.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function valueassertion_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		$errorMessage = $this->log->warning( sprintf( "[value assertion] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		if ( isset( $source['error'] ) )
		{
			throw FormulasException::withType( $source['error'], "value-assertion", $errorMessage );
		}
		return $errorMessage;
	}

	/**
	 * A convenience function for logging a event about an XBRL formula specification conformance issue.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function existenceassertion_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		$errorMessage = sprintf( "[existence assertion] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		$this->log->warning( $errorMessage );
		if ( isset( $source['error'] ) )
		{
			throw FormulasException::withType( $source['error'], "existence-assertion", $errorMessage );
		}
		return $errorMessage;
	}

	/**
	 * A convenience function for logging a formula evaluation result event.
	 * It will log a message at the PEAR_LOG_INFO log level.
	 *
	 * PEAR_LOG_INFO
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, link base, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function formula_evaluation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$message = "$section $message";
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_INFO, 'message' => $message, 'source' => $source ) );
		$this->log->info( $message );
		return $message;
	}

	/**
	 * A convenience function for logging an event about an XBRL instance specification conformance issue.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $section	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, segment, period, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function instance_validation( $section, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->instanceValidationWarning = true;
		// Concatenate the key and the value
		$msg = sprintf( "[instance] $message (Section %s - %s)", $section, $this->arrayToDescription( $source ) );
		$this->log->_announce( array( 'section' => $section, 'priority' => PEAR_LOG_WARNING, 'message' => $message, 'source' => $source ) );
		return $this->log->warning( $msg );
	}

	/**
	 * Convert an array of context information about the source of an log instance to a string
	 *
	 * @param array $source
	 */
	public function arrayToDescription( $source )
	{
		array_walk( $source, function( &$value, $key ) { $value = "$key: $value"; } );
		return implode( ', ', $source );
	}

	/**
	 * Returns the current instance validation warning state
	 * @return boolean
	 */
	public function hasInstanceValidationWarning()
	{
		return $this->instanceValidationWarning;
	}

	/**
	 * Resets the current instance validation warning state
	 * @return boolean
	 */
	public function resetInstanceValidationWarning()
	{
		$this->instanceValidationWarning = false;
	}

	/**
	 * Returns the current instance validation warning state
	 * @return boolean
	 */
	public function hasConformanceIssueWarning()
	{
		return $this->conformanceIssueWarning || $this->hasInstanceValidationWarning();
	}

	/**
	 * Returns the current instance validation warning state
	 * @return boolean
	 */
	public function hasBusinessRulesViolationWarning()
	{
		return $this->businessRulesViolationeWarning;
	}

	/**
	 * Resets the current instance validation warning state
	 * @return boolean
	 */
	public function resetConformanceIssueWarning()
	{
		$this->resetInstanceValidationWarning();
		$this->conformanceIssueWarning = false;
	}

	/**
	 * Resets the current business rules violation warning state
	 * @return boolean
	 */
	public function resetBusinessRulesViolationWarning()
	{
		$this->businessRulesViolationeWarning = false;
	}

	/**
	 * A convenience function for logging an event about an XBRL conformance test.
	 * It will log a message at the PEAR_LOG_DEBUG log level.
	 *
	 * PEAR_LOG_WARNING
	 *
	 * @param string $testid	The specification section reference
	 * @param string $message	String or object containing the message to log.
	 * @param array $source		An array containing details about the source such as the element id, segment, period, etc.
	 * @return  boolean True if the message was successfully logged.
	 */
	public function conformance_issue( $testid, $message, $source )
	{
		if ( ! $this->log ) return;
		$this->conformanceIssueWarning = true;
		// Concatenate the key and the value
		return $this->log->warning( sprintf( "[instance] $message (Section %s - %s)", $testid, $this->arrayToDescription( $source ) ) );
	}

	/**
	 * Set log output for console and error_log
	 * @return XBRL_Log
	 */
	public function debugLog()
	{
		$logConsole  = Log::singleton( 'console', '', 'console',
			array(
				'lineFormat' => '%{timestamp} [%{priority}] %{message}',
				'timeFormat' => '%Y-%m-%d %H:%M:%S',
			)
		);

		$logError = Log::singleton( 'error_log', PEAR_LOG_TYPE_SYSTEM, 'error_log',
			array(
				'lineFormat' => '[%{priority}] %{message}',
			)
		);

		$logComposite = Log::singleton( 'composite' );
		$logComposite->addChild( $logConsole );
		$logComposite->addChild( $logError );

		$this->setLog( $logComposite );
	}
}
