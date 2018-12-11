<?php

/**
 * An log observer used to capture taxonomy validation messages so they can be reported by the app
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 1.0
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 * @License: GPL 3.0
 *
 */

namespace lyquidity\dfb;

/**
 * Implementation of a log observer
 */
class Observer extends \Log_observer
{
	/**
	 * A list of any issue found
	 * @var array
	 */
	private $issues = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct( PEAR_LOG_ALL );

		// error_log("timeout: " . ini_get('max_execution_time') );
		error_reporting( E_WARNING | E_PARSE | E_NOTICE );
	}

	/**
	 * Called by the log instance to pass log information
	 * @param array $event
	 */
	public function notify( $event )
	{
		if ( ! isset( $event['section'] ) || ! isset( $event['source'] ) ) return;
		$source = $event['section'];

		$source = array( 'message' => $event['message'], 'details' => $event['source'] );
		$this->addSection( $event['section'], $source );
	}

	/**
	 * Add extra entries into the session
	 * @param string $name
	 * @param array $data
	 */
	public function addItem( $name, $data )
	{
		$this->issues[ $name ][] = $data;
	}

	/**
	 * Add extra entries into the session
	 * @param string $section
	 * @param array $data
	 */
	public function addSection( $section, $data )
	{
		$this->issues['sections'][ $section ][] = $data;
	}

	/**
	 * Clears the transient record for the name
	 */
	public function clearItems()
	{
		$this->issues = array();
	}

	/**
	 * Get the current set of issues
	 */
	public function getIssues()
	{
		return $this->issues;
	}
}
