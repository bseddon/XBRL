<?php

/**
 * XBRL Formulas
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.1.1
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
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

namespace XBRL\Formulas;

use XBRL\Functions\Filters\Filter;
use XBRL\Formulas\Resources\Resource;

define( "ASPECT_LOCATION", "\\location" );
define( "ASPECT_CONCEPT", "\\concept" );
define( "ASPECT_ENTITY_IDENTIFIER", "\\entity_identifier" );
define( "ASPECT_VALUE", "\\identifier value" );
define( "ASPECT_SCHEME", "\\scheme" );
define( "ASPECT_PERIOD", "\\period" );
define( "ASPECT_PERIOD_TYPE", "\\period_type" );
define( "ASPECT_START", "\\period_start" );
define( "ASPECT_END", "\\period_end" );
define( "ASPECT_INSTANT", "\\period_instant" );
define( "ASPECT_UNIT", "\\unit" );
define( "ASPECT_MULTIPLY_BY", "\\multiply_by" );
define( "ASPECT_DIVIDE_BY", "\\divide_by" );
define( "ASPECT_AUGMENT", "\\augment" );
define( "ASPECT_COMPLETE_SEGMENT", "\\complete_segment" );
define( "ASPECT_COMPLETE_SCENARIO", "\\complete_scenario" );
define( "ASPECT_NON_XDT_SEGMENT", "\\nonXDT_segment" );
define( "ASPECT_NON_XDT_SCENARIO", "\\nonXDT_scenario" );
define( "ASPECT_DIMENSIONS", "\\all_dimensions" );
define( "ASPECT_OMIT_DIMENSIONS", "\\omit_dimensions" );
define( "ASPECT_PRECISION", "\\precision" );
define( "ASPECT_DECIMALS", "\\decimals" );

/**
 * Load XBRL class files
 * @param string $classname
 */
function formulas_autoload( $classname )
{
	if ( substr( $classname, 0, 13 ) != "XBRL\\Formulas" )
	{
		return false;
	}

	$classname = ucwords( str_replace( "XBRL\\Formulas", "", $classname . ".php" ) );

	$filename = __DIR__ . "/" . str_replace( "\\", "/", $classname );
	if ( ! file_exists( $filename ) )
	{
		return false;
	}

	require_once $filename;
}

spl_autoload_register( 'XBRL\\Formulas\\formulas_autoload' );

 /**
  * Provides controller functions for XBRL Formula processing
  */
 class Formulas
 {
 	/**
 	 * A list of the variable and formula elements that can be processed and how to generate a
 	 * class.  Note the '\\' at the end is required as this is part of the PHP namespace path.
 	 *
 	 * @var array $formulaElements
 	 */
	public static $formulaElements = array(

		'formula'					=> array(	'part' => 'Formulas\\',
												'namespace' => 'http://xbrl.org/2008/formula' ),
		'tuple'						=> array(	'part' => 'Formulas\\',
												'namespace' => 'http://xbrl.org/2010/formula/tuple' ),
		'valueAssertion'			=> array(	'part' => 'Assertions\\',
												'namespace' => 'http://xbrl.org/2008/assertion/value' ),
		'existenceAssertion'		=> array(	'part' => 'Assertions\\',
												'namespace' => 'http://xbrl.org/2008/assertion/existence' ),
		'consistencyAssertion'		=> array(	'part' => 'Assertions\\',
												'namespace' => 'http://xbrl.org/2008/assertion/consistency' ),

		'aspectCover'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2010/filter/aspect-cover' ),
		'ancestorFilter'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/tuple' ),
		'locationFilter'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/tuple' ),
		'parentFilter'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/tuple' ),
		'siblingFilter'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/tuple' ),

		'scenario'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/segment-scenario' ),
		'segment'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/segment-scenario' ),

		'nil'						=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/value' ),
		'precision'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/value' ),

		'matchConcept'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchLocation'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchUnit'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchEntityIdentifier'		=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchPeriod'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchSegment'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchScenario'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchNonXDTSegment'		=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchNonXDTScenario'		=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),
		'matchDimension'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/match' ),

		'generalMeasures'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/unit' ),
		'singleMeasure'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/unit' ),

		'conceptName'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/concept' ),
		'conceptPeriodType'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/concept' ),
		'conceptBalance'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/concept' ),
		'conceptCustomAttribute'	=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/concept' ),
		'conceptDataType'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/concept' ),
		'conceptSubstitutionGroup'	=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/concept' ),
		'period'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/period' ),
		'periodStart'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/period' ),
		'periodEnd'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/period' ),
		'periodInstant'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/period' ),
		'forever'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/period' ),
		'instantDuration'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/period' ),
		'general'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/general' ),
		'andFilter'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/boolean' ),
		'orFilter'					=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/boolean' ),
		'identifier'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/entity' ),
		'regexpIdentifier'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/entity' ),
		'regexpScheme'				=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/entity' ),
		'specificIdentifier'		=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/entity' ),
		'specificScheme'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/entity' ),
		'explicitDimension'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/dimension' ),
		'typedDimension'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/dimension' ),

		'relativeFilter'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2008/filter/relative' ),
		'conceptRelation'			=> array(	'part' => 'Filters\\',
												'namespace' => 'http://xbrl.org/2010/filter/concept-relation' ),

		'variableSet'				=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable' ),
		'factVariable'				=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable' ),
		'generalVariable'			=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable' ),
		'parameter'					=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable' ),
		'equalityDefinition'		=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable' ),
		'precondition'				=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable' ),
		'function'					=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2008/variable',
												'className' => 'Signature' ),
		'instance'					=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2010/variable/instance' ),
		'implementation'			=> array(	'part' => 'Variables\\',
												'namespace' => 'http://xbrl.org/2010/custom-function' ),
		'message'					=> array(	'part' => 'Messages\\',
												'namespace' => 'http://xbrl.org/2010/message' ),
	);

	/**
	 * Static initializer
	 */
	public static function __static()
	{ }

 	/**
 	 * Tests whether the name and namespace are from the formula or variable specifications
 	 * @param string $namespace
 	 * @param string $name
 	 */
 	public static function IsFormulaResource( $namespace, $name )
 	{
		return isset( Formulas::$formulaElements[ $name ] ) && Formulas::$formulaElements[ $name ]['namespace'] == $namespace;
 	}

  	/**
 	 * Processes a node to extract formula or variable information
 	 * @param string $name The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
 	public static function ProcessFormulaResource( $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
 	{
 		$localName = $domNode->localName;

		if ( ! isset( Formulas::$formulaElements[ $localName ] ) )
		{
			return false;
		}

 		if ( ! property_exists( $taxonomy->context, "formulaResources" ) )
		{
			$taxonomy->context->formulaResources = array();
		}

		// Construct a name for the class that will handle the element
		// resource using definitions in the static array $formulaElements
		$filterName = '\\XBRL\\Formulas\\Resources\\' .
			Formulas::$formulaElements[ $localName ]['part'] .
			( isset( Formulas::$formulaElements[ $localName ]['className'] )
				? Formulas::$formulaElements[ $localName ]['className']
				: ucfirst( $localName )
			);

		/**
		 * @var Resource $filter
		 */
		$filter = new $filterName;
		$resource = $filter->process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		return $resource;
 	}
 }

 Formulas::__static();
