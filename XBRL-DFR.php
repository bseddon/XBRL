<?php

/**
 * Digital Financial Reporting taxonomy implementation
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2019 Lyquidity Solutions Limited
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
 *
 */

/**
 * Load the XBRL implementation
 */
require_once('XBRL.php');

class XBRL_DFR extends XBRL
{
	/**
	 * Holds a list of features
	 * @var array
	 */
	private $features = array();

	/**
	 * An array of conceptual model arcroles and relationships
	 * @var array|null
	 */
	private static $conceptualModelRoles;

	public static function getConceptualModelRoles( $cacheLocation = null )
	{
		if ( is_null( self::$conceptualModelRoles ) )
		{
			$context = XBRL_Global::getInstance();
			if ( ! $context->useCache && $cacheLocation )
			{
				$context->cacheLocation = $cacheLocation;
				$context->useCache = true;
				$context->initializeCache();
			}

			$taxonomy = new XBRL();
			$taxonomy->context = $context;
			$taxonomy->addLinkbaseRef( "http://xbrlsite.azurewebsites.net/2016/conceptual-model/reporting-scheme/ipsas/model-structure/ModelStructure-rules-ipsas-def.xml", "conceptual-model");
			$cm = $taxonomy->getTaxonomyForXSD("cm.xsd");
			$nonDimensionalRoleRef = $cm->getNonDimensionalRoleRefs( XBRL_Constants::$defaultLinkRole );
			$cmArcRoles = $nonDimensionalRoleRef[ XBRL_Constants::$defaultLinkRole ];
			unset( $taxonomy );
			XBRL::reset();

			self::$conceptualModelRoles = $cmArcRoles;
		}
		return self::$conceptualModelRoles;

	}

	/**
	 * Default constructor
	 */
	function __construct()
	{
		$this->features = array( "conceptual-model" => array(
			'ReportDateAxis' => XBRL_Constants::$dfrReportDateAxis,
			'ReportingEntityAxis' => XBRL_Constants::$dfrReportingEntityAxis,
			'LegalEntityAxis' => XBRL_Constants::$dfrLegalEntityAxis,
			'ConceptAxis' => XBRL_Constants::$dfrConceptAxis,
			'BusinessSegmentAxis' => XBRL_Constants::$dfrBusinessSegmentAxis,
			'GeographicAreaAxis' => XBRL_Constants::$dfrGeographicAreaAxis,
			'OperatingActivitiesAxis' => XBRL_Constants::$dfrOperatingActivitiesAxis,
			'InstrumentAxis' => XBRL_Constants::$dfrInstrumentAxis,
			'RangeAxis' => XBRL_Constants::$dfrRangeAxis,
			'ReportingscenarioAxis' => XBRL_Constants::$dfrReportingscenarioAxis,
			'CalendarPeriodAxis' => XBRL_Constants::$dfrCalendarPeriodAxis,
			'ReportDateAxis' => XBRL_Constants::$dfrReportDateAxis,
			'FiscalPeriodAxis' => XBRL_Constants::$dfrFiscalPeriodAxis,
			'origionallyStatedLabel' => 'origionallyStated',
			'restatedLabel' => XBRL_Constants::$labelRoleRestated,
			'periodStartLabel' => XBRL_Constants::$labelRolePeriodStartLabel,
			'periodEndLabel' => XBRL_Constants::$labelRolePeriodEndLabel
		) );
	}

	/**
	 * This function allows a descendent to do something with the information before it is deleted if helpful
	 * This function can be overridden by a descendent class
	 *
	 * @param array $dimensionalNode A node which has element 'nodeclass' === 'dimensional'
	 * @param array $parentNode
	 * @return bool True if the dimensional information should be deleted
	 */
	protected function beforeDimensionalPruned( $dimensionalNode, &$parentNode )
	{
		return false;
	}

	/**
	 * Gets an array containing a list of extra features supported usually by descendent implementation
	 * @param string $feature (optional) If supplied just the array for the feature is returned or all
	 * 									 features.  If supplied and not found an empty array is returned
	 * @return array By default there are no additional features so the array is empty
	 */
	public function supportedFeatures( $feature = null )
	{
		return $feature
			? ( isset( $this->features[ $feature ] ) ? $this->features[ $feature ] : array() )
			: $this->featrues;
	}
}
