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
 * @version 0.9
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
 */

namespace XBRL\Formulas\Resources\Formulas;

use XBRL\Formulas\Resources\Variables\VariableSet;
use XBRL\Formulas\Resources\Formulas\Aspects\Aspect;
use lyquidity\XPath2\XPath2Expression;
use lyquidity\xml\MS\XmlNamespaceManager;
use XBRL\Formulas\VariableBinding;
use XBRL\Formulas\FactValues;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use XBRL\Formulas\Resources\Formulas\Aspects\Concept;
use XBRL\Formulas\Resources\Formulas\Aspects\Unit;
use XBRL\Formulas\Resources\Formulas\Aspects\EntityIdentifier;
use XBRL\Formulas\Resources\Formulas\Aspects\OCCFragments;
use XBRL\Formulas\Resources\Formulas\Aspects\OCCXPath;
use XBRL\Formulas\Resources\Formulas\Aspects\ExplicitDimension;
use XBRL\Formulas\Resources\Formulas\Aspects\TypedDimension;
use lyquidity\XPath2\XPath2Item;
use XBRL\Formulas\Resources\Formulas\Aspects\Period;
use XBRL\Formulas\Resources\Formulas\Aspects\Dimension;
use lyquidity\XPath2\Value\DecimalValue;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\CoreFuncs;
use lyquidity\XPath2\Undefined;
use XBRL\Formulas\Resources\Filters\ConceptName;
use XBRL\Formulas\Resources\Variables\FactVariable;
use XBRL\Formulas\Resources\Formulas\Aspects\Location;
use lyquidity\XPath2\Iterator\EmptyIterator;
use XBRL\Formulas\FactVariableBinding;
use XBRL\Formulas\ScopeVariableBinding;
use lyquidity\xml\QName;
use lyquidity\xml\schema\SchemaTypes;
use lyquidity\XPath2\XPath2Exception;
use lyquidity\XPath2\XPath2ResultType;
use XBRL\Formulas\Exceptions\FormulasException;

 /**
  * A class to process a formula definitions
  */
class Formula extends VariableSet
{
 	/**
 	 * A list of the variable and formula elements that can be processed and how to generate a
 	 * class.  Note the '\\' at the end is required as this is part of the PHP namespace path.
 	 *
 	 * @var array $formulaElements
 	 */
	private static $aspectElements = array(
		'concept'			=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula' ),
		'entityIdentifier'	=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula' ),
		'explicitDimension'	=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula' ),
		'occEmpty'			=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula',
										'className' => 'OCCEmpty'),
		'occFragments'		=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula',
										'className' => 'OCCFragments'),
		'occXpath'			=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula',
										'className' => 'OCCXpath'),
		'period'			=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula' ),
		'typedDimension'	=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula' ),
		'unit'				=> array(	'part' => '',
										'namespace' => 'http://xbrl.org/2008/formula' ),
	);

	/**
	 * The set of dimensional aspects.
	 * The order of these aspects
	 * @var array $dimensionAspectModelMembers
	 */
	public static $dimensionAspectModelMembers = array(
		ASPECT_LOCATION,
		ASPECT_CONCEPT,
		ASPECT_UNIT,
		ASPECT_PERIOD,
		ASPECT_ENTITY_IDENTIFIER,
		ASPECT_NON_XDT_SCENARIO,
		ASPECT_NON_XDT_SEGMENT,
		ASPECT_DIMENSIONS,
	);

	/**
	 * The elements valid for dimensional rules
	 * @var array $dimensionAspectRuleMembers
	 */
	public static $dimensionAspectRuleMembers = array(
		'location',
		'concept',
		'unit',
		'period',
		'entityIdentifier',
		'occEmpty',
		'occFragments',
		'occXpath',
		'explicitDimension',
		'typedDimension',
	);

	/**
	 * A list of aspects that are valid for non-dimensional models
	 * @var array $nonDimensionAspectModelMembers
	 */
	public static $nonDimensionAspectModelMembers = array(
		ASPECT_LOCATION,
		ASPECT_CONCEPT,
		ASPECT_UNIT,
		ASPECT_PERIOD,
		ASPECT_ENTITY_IDENTIFIER,
		ASPECT_COMPLETE_SCENARIO,
		ASPECT_COMPLETE_SEGMENT,
	);

	/**
	 * The elements valid for non-dimensional rules
	 * @var array $nonDimensionAspectRuleMembers
	 */
	public static $nonDimensionAspectRuleMembers = array(
		'location',
		'concept',
		'unit',
		'period',
		'entityIdentifier',
		'occEmpty',
		'occFragments',
		'occXpath',
	);

	/**
	 * An array mapping aspect names to their internal ids
	 * @var array
	 */
	public static $aspectCoversMap = array(
		'concept' => ASPECT_CONCEPT,
		'entity-identifier' => ASPECT_ENTITY_IDENTIFIER,
		'location' => ASPECT_LOCATION,
		'period' => ASPECT_PERIOD,
		'unit' => ASPECT_UNIT,
		'complete-segment' => ASPECT_COMPLETE_SEGMENT,
		'complete-scenario' => ASPECT_COMPLETE_SCENARIO,
		'non-XDT-segment' => ASPECT_NON_XDT_SEGMENT,
		'non-XDT-scenario' => ASPECT_NON_XDT_SCENARIO,
		'dimensions' => ASPECT_DIMENSIONS
	);

	/**
	 * A list of the possible component paths that could host scenarios, segments, and dimensions
	 * @var array $componentPaths
	 */
	public static $componentPaths = array(
		array( 'entity', 'segment' ),
		array( 'scenario' ),
		array( 'entity', 'scenario' ),
		array( 'segment' )
	);

	/**
	 * An XPath expression attribute that, when evaluated, MUST yield a sequence of element nodes
	 * @var string
	 */
	public $value = "";

	/**
	 * The name of the source to use
	 * @var string $source QName of the source to use (optional)
	 */
	public $source = null;

	/**
	 * The number of decimals in the formula result. This is mutually exclusive with $precision.
	 * @var int
	 */
	public $decimalsRule = null;

	/**
	 * The precision of the formula result. This is mutually exclusive with $precision.
	 * @var int
	 */
	public $precisionRule = null;

	/** Non combinable rules  */

	/**
	 * The concept name to use when generating facts
	 * @var Concept $conceptRule
	 */
	public $conceptRule = null;

	/**
	 * The period to use when generating facts
	 * @var array $periodRule
	 */
	public $periodRule = null;

	/**
	 * The entity identifier to use when generating facts
	 * @var array $entityIdentifierRule
	 */
	public $entityIdentifierRule = null;

	/**
	 * The location to use when generating facts. Defaults to <xbrli:xblr/>
	 * @var array $locationRule
	 */
	public $locationRule = null;

	/**
	 * If defined identfies the name of the arc to the tuple definition
	 * @var QName $tupleLocationRule
	 */
	public $tupleLocationRule = null;

	/** Combinable rules  */

	/**
	 * The scenario members to use when generating facts
	 * @var array $scenarioRule
	 */
	public $scenarioRule = null;

	/**
	 * The segment memebrs to use when generating facts
	 * @var array $segmentRule
	 */
	public $segmentRule = null;

	/**
	 * The unit to use when generating facts
	 * @var array $unitRule
	 */
	public $unitRule = null;

	/**
	 * The explicitDimensions to use when generating facts
	 * @var array[ExplicitDimension] $explicitDimensionRule
	 */
	public $explicitDimensionRule = null;

	/**
	 * The typedDimensions to use when generating facts
	 * @var array[TypedDimension] $typedDimensionRule
	 */
	public $typedDimensionRule = null;

	/**
	 * A list of any locally defined namespaces
	 * @var null|array $localNamespaces
	 */
	public $localNamespaces = null;

	/**
	 * A flag to indicate whether a value is required
	 * @var string $testValue
	 */
	protected $testValue = true;

	/**
	 * A compiled version of the 'select' property
	 * @var XPath2Expression $xpath2expression
	 */
	public $valueXPath2expression;

	/**
	 * A flag indicating whether this formula is the target for a consistency assertion
	 * @var bool $isConsistencyAssertionTarget
	 */
	public $isConsistencyAssertionTarget = false;

	/**
	 * The path to use when applying explicit dimension segments or scenarios
	 * @var string $explicitDimensionsComponentPath
	 */
	public $explicitDimensionsComponentPath;

	/**
	 * The path to use when applying typed dimension segments or scenarios
	 * @var string $typedDimensionsComponentPath
	 */
	public $typedDimensionsComponentPath;

	/**
	 * The path to use when applying scenarios
	 * @var string $scenariosComponentPath
	 */
	public $scenariosComponentPath;

	/**
	 * The path to use when applying segments
	 * @var string $segmentsComponentPath
	 */
	public $segmentsComponentPath;

	/**
	 * An instance to hold generated facts, units and contexts
	 * @var GeneratedFacts
	 */
	public $factsContainer;

	/**
	 * A list of the formula evaluation results
	 * @var array $evaluationResults
	 */
	public $evaluationResults = array();

	/**
	 * Return the fact corresponding to the source of the aspect
	 * @param array $source Three element array
	 * @param array $evaluationResult
	 * @param string|null $aspect
	 * @param \XBRL_Log $log
	 * @return DOMXPathNavigator
	 */
	public function getSourceFactWithErrorLogging( $source, $evaluationResult, $aspect, $log )
	{
		$result = $this->getSourceFact( $source, $evaluationResult, $aspect, $log );
		if ( $result ) return $result;

		$log->formula_validation( "Formula", "Invalid or missing source fact",
			array(
				'source' => $source,
				'error' => 'xffe:InvalidSource'
			)
		);
	}

	/**
	 * Return the fact corresponding to the source of the aspect
	 * @param array $source Three element array
	 * @param array $evaluationResult
	 * @param string|null $aspect
	 * @param \XBRL_Log $log
	 * @return DOMXPathNavigator
	 */
	public function getSourceFact( $source, $evaluationResult, $aspect, $log )
	{
		if ( is_null( $source ) ) return false;

		$sourceQName = $source instanceof QName ? $source : new QName( $source['originalPrefix'], $source['namespace'], $source['name'] );

		if ( is_null( $sourceQName ) )
		{
			\XBRL_Log::getInstance()->warning("The source is not valid");
			return false;
		}

		// if ( ! is_null( $aspect ) && $sourceQName->localName == "uncovered" &&  $sourceQName->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] )
		if ( ! is_null( $aspect ) && $this->getIsFormulaUncoveredQName( $sourceQName ) )
		{
			if ( ! $this->implicitFiltering )
			{
				$log->formula_validation( "Evaluation", "formula:funcovered used but the formula does not specify implicit filtering",
					array(
						'formula' => $this->label,
						'aspect' => $aspect,
						'error' => 'xbrlfe:illegalUseOfUncoveredQName',
					)
				);

				return null;
			}

			$foundUncovered = false;

			// Look in the bindings for a variable that is uncovered for $aspect
			foreach ( $evaluationResult['covered'] as $variableName => $covers )
			{
				if ( in_array( $aspect, $covers ) ) continue;
				$qname = qname( $variableName, $this->nsMgr->getNamespaces() );
				if ( ! isset( $evaluationResult['vars'][ $qname->clarkNotation() ] ) ) continue;
				if ( $evaluationResult['vars'][ $qname->clarkNotation() ] instanceof XPath2Item ||
					 $evaluationResult['vars'][ $qname->clarkNotation() ] instanceof Undefined	) continue;

				$sourceQName = $qname;
				$foundUncovered = true;
				break;
			}

			if ( ! $foundUncovered )
			{
				if ( $aspect != ASPECT_CONCEPT )
				{
					// $log->formula_validation( "Evaluation", "Unable to resolve formula:uncovered to an uncovered fact",
					// 	array(
					// 		'formula' => $this->label,
					// 		'aspect' => $aspect,
					// 		'error' => 'xbrlfe:undefinedSAV',
					// 	)
					// );
				}

				return null;
			}
		}

		if ( ! isset( $evaluationResult['vars'][ $sourceQName->clarkNotation() ] ) )
		{
			$log->warning("The source cannot be found as an evaluation variable");
			return false;
		}

		$value = $evaluationResult['vars'][ $sourceQName->clarkNotation() ];

		if ( isset( $this->variablesByQName[ $sourceQName->clarkNotation() ] ) )
		{
			/**
			 * @var FactVariable $variable
			 */
			$variable = $this->variablesByQName[ $sourceQName->clarkNotation() ];
			if ( $variable->bindAsSequence && $value instanceof XPath2NodeIterator )
			{
				if ( ! $value->getIsStarted() ) $value->rewind();
				return $value->current()->cloneInstance();
			}
		}

		/**
		 * @var DOMXPathNavigator $source
		 */
		return $value;
	}

	/**
	 * Handy function to report whether the class is the tuple class
	 * @return boolean True = Tuple; False = Formula
	 */
	public function isTuple()
	{
		return get_class( $this ) == 'Tuple';
	}

	/**
	 * A list of the rules that can have sources
	 * @var array $ruleNames
	 */
	private static $ruleNames = array(
		'conceptRule',
		'periodRule',
		'entityIdentifierRule',
		'locationRule',
		'scenarioRule',
		'segmentRule',
		'unitRule',
		'explicitDimensionRule',
		'typedDimensionRule'
	);

  	/**
 	 * Processes a node to extract formula or variable resource information
 	 * @param string $localName The name of the resource element being processed
 	 * @param \XBRL $taxonomy The taxonomy referencing the linkbase being processed
 	 * @param string $roleUri
 	 * @param string $linkbaseHref
 	 * @param string $label
 	 * @param \SimpleXMLElement $node A \SimpleXMLElement reference to the node to be processed
 	 * @param \DOMNode $domNode A \DOMNode reference to the node to be processed
	 * @param \XBRL_Log $log $log
 	 */
	public function process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log )
	{
		$attributes = $node->attributes();

		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );
		$isTuple = $result['variablesetType'] = $this->isTuple() ? 'tuple' : 'formula';

		// Get local namespaces but not any default
		$localNamespaces = array_filter( $node->getDocNamespaces( true, false ), function( $namespace, $prefix ) {
			return !empty( $prefix );
		}, ARRAY_FILTER_USE_BOTH );

		if ( count( $localNamespaces ) )
		{
			$this->localNamespaces = $localNamespaces;
			$result['localNamespaces'] = $this->localNamespaces;
		}

		if ( ! property_exists( $attributes, "implicitFiltering" ) )
		{
			$log->formula_validation( "Variables", "Missing implicit filtering attribute", array(
				'error' => 'xbrlve:missingRequiredAttribute'
			) );
		}

		$this->implicitFiltering = filter_var( $attributes->implicitFiltering, FILTER_VALIDATE_BOOLEAN );
		$result['implicitFiltering'] = $this->implicitFiltering;

		if ( property_exists( $attributes, "value" ) )
		{
			$this->value = (string)$attributes->value;
			$result['value'] = $this->value;
		}
		else
		{
			if ( $this->testValue )
			{
				$log->formula_validation( "Variables", "Missing value attribute", array(
					'error' => 'xbrlve:missingRequiredAttribute'
				) );
			}
		}

		if ( property_exists( $attributes, "source" ) )
		{
			$namespaces = $node->getDocNamespaces( true );
			/**
			 * @var QName $qName
			 */
			// If there is no prefix it should not be resolved to a default namespace
			$source = trim( $attributes->source );
			$qName = strpos( $source, ":" )
				? qname( $source, $namespaces )
				: new QName( "", null, $source );
			$this->source = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);
		}

		// If the source is uncovered then make sure implicit filtering is allowed
		if ( \XBRL::isValidating() )
		{
			// if ( $this->source['name'] == "uncovered" && $this->source['namespace'] == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] && ! $this->implicitFiltering )
			if ( $this->getIsFormulaUncoveredQName( $this->source ) && ! $this->implicitFiltering )
			{
				$log->formula_validation( "Formula", "The source is formula:uncovered so implicit filtering MUST be true", array(
					'formula' => $this->label,
					'error' => 'xbrlfe:illegalUseOfUncoveredQName'
				) );
			}
		}

		$result['source'] = $this->source;

		$nodeChildren = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] );

		if ( property_exists( $nodeChildren, "decimals" ) )
		{
			$this->decimalsRule = (string)$nodeChildren->decimals;
		}

		if ( property_exists( $nodeChildren, "precision" ) )
		{
			$precisionRule = (string)$nodeChildren->precision;
			$this->precisionRule = $precisionRule;
		}

		$result['decimalsRule'] = $this->decimalsRule;
		$result['precisionRule'] = $this->precisionRule;

		$formulaAspects = array();
		$validAspects = $this->aspectModel == "dimensional" ? self::$dimensionAspectRuleMembers : self::$nonDimensionAspectRuleMembers;

		$error = false;

		foreach ( $nodeChildren->aspects as /** @var \SimpleXMLElement $aspect */ $aspects )
		{
			$attributes = $aspects->attributes();

			$aspectsSource = $this->source;
			if ( property_exists( $attributes, 'source' ) )
			{
				$qname = qname( trim( $attributes->source ), $node->getDocNamespaces( true ) );
				// $aspectsSource = is_null( $qname ) ? $aspectsSource : $qname->clarkNotation();
				$aspectsSource = is_null( $qname )
					? $aspectsSource
					: array( 'namespace' => $qname->namespaceURI, 'originalPrefix' => $qname->prefix, 'name' => $qname->localName  );
			}

			foreach ( $aspects->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] ) as $key => $aspect )
			{
				$aspectLocalName = (string)$aspect->getName();

				if ( ! in_array( "$aspectLocalName", $validAspects ) )
				{
					$log->formula_validation( "Aspects", "Aspect type is not expected in the aspect model",
						array(
							'aspectModel' => $this->aspectModel,
							'aspectName' => $aspectLocalName,
							'error' => 'xbrlfe:unrecognisedAspectRule',
						)
					);
				}

				$aspectName = __NAMESPACE__ . "\\Aspects\\" .
					Formula::$aspectElements[ $aspectLocalName ]['part'] .
					( isset( Formula::$aspectElements[ $aspectLocalName ]['className'] )
						? Formula::$aspectElements[ $aspectLocalName ]['className']
						: ucfirst( $aspectLocalName )
					);

				/**
				 * @var Aspect $aspectInstance
				 */
				$aspectInstance = new $aspectName();
				$aspectInstance->source = $aspectsSource;
				$aspectValues = $aspectInstance->process( $aspectLocalName, $taxonomy, $roleUri, $linkbaseHref, $label, $aspect, $domNode, $log );

				switch( $aspectLocalName )
				{
					/** Non-combinable rules */

					case 'concept':
						$error |= ! is_null( $this->conceptRule ) ;
						$this->conceptRule = $aspectValues;
						break;

					case 'entityIdentifier':
						$error |= ! is_null( $this->entityIdentifierRule );
						$this->entityIdentifierRule = $aspectValues;
						break;

					case 'location':
						$error |= ! is_null( $this->locationRule );
						$this->locationRule = $aspectValues;
						break;

					case 'period':
						$error |= ! is_null( $this->periodRule );
						$this->periodRule = $aspectValues;
						break;

					/** Combinable */

					case 'unit':
						$this->unitRule = $aspectValues;
						break;

					case 'occEmpty':
						if ( $aspectInstance->occ == 'scenario' )
						{
							$this->scenarioRule[] = $aspectValues;
						}
						else
						{
							$this->segmentRule[] = $aspectValues;
						}
						break;

					case 'occFragments':
					case 'occXpath':
						if ( $aspectInstance->occ == 'scenario' )
						{
							$this->scenarioRule[] = $aspectValues;
						}
						else
						{
							$this->segmentRule[] = $aspectValues;
						}
						break;

					case 'explicitDimension':
						$this->explicitDimensionRule[] = $aspectValues;
						break;

					case 'typedDimension':
						$this->typedDimensionRule[] = $aspectValues;
						break;
				}
			}

			foreach ( $aspects->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA_TUPLE ] ) as $key => $aspect )
			{
				$attributes = $aspect->attributes();
				if ( property_exists( $attributes, "source" ) )
				{
					$this->tupleLocationRule = (string)$attributes->source;
					$result['tupleLocationRule'] = $this->tupleLocationRule;
				}
				else
				{
					$log->formula_validation( "Formula", "The source name cannot be found in the attributes of 'tuple:location'",
						array(
							'error' => 'xbrlfe:missingSourceattribute'
						)
					);
				}
			}
		}

		// If an error occurred reading the aspects report it
		if ( $error )
		{
			$log->formula_validation( "Formula Aspect rules", "One or more of the non-combinable aspects have been repeated", array() );
		}


		$result['conceptRule'] = $this->conceptRule;
		$result['periodRule'] = $this->periodRule;
		$result['entityIdentifierRule'] = $this->entityIdentifierRule;
		$result['locationRule'] = $this->locationRule;
		$result['scenarioRule'] = $this->scenarioRule;
		$result['segmentRule'] = $this->segmentRule;
		$result['unitRule'] = $this->unitRule;
		$result['explicitDimensionRule'] = $this->explicitDimensionRule;
		$result['typedDimensionRule'] = $this->typedDimensionRule;

		// $result['aspects'] = $this->aspects;

		return $result;
	}

	/**
	 * Provide a list of arcroles that are valid this resource
	 */
	public function getDescendantArcroles()
	{
		return array( \XBRL_Constants::$arcRoleVariableSet );
	}

	/**
	 * Return a list of the sources for all of the declared rules
	 * @return array
	 */
	protected function getAspectSources()
	{
		$sources = array();

		foreach ( Formula::$ruleNames as $ruleName )
		{
			$rule = $this->$ruleName;
			if ( ! isset( $rule['source'] ) ) continue;
			$sources[ $ruleName ] = $rule['source'];
		}

		return $sources;
	}

	/**
	 * Allow resources to be validated
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		$log = \XBRL_Log::getInstance();

		try
		{
			if ( isset( $this->value ) && ! empty( $this->value ) )
			{
				$expression = XPath2Expression::Compile( $this->value, $nsMgr );
				$this->valueXPath2expression = $expression;

				if ( ! $this->source && ! $this->unitRule )
				{
					$qnames = $expression->getParameterQNames();
					$vars = array();
					foreach( $qnames as $qname )
					{
						$vars[ $qname->clarkNotation() ] = 1;
					}
					$resultType = $expression->GetResultType( $vars );

					// If the result is number then there MUST be a unit rule
					if ( $resultType == XPath2ResultType::Number )
					{
						$log->formula_validation( "Formula", "A formula with a numeric result MUST have a unit rule",
							array(
								'error' => 'xbrlfe:missingUnitRule'
							)
						);
					}
				}
			}
		}
		catch ( XPath2Exception $ex )
		{
			// BMS 2018-03-27 Test 21201 V-01 requires that this compile failure return xbrlve:noCustomFunctionSignature
			//				  because the custom function my-own:ending-balance does not exist
			$log->formula_validation( "Formula", "Failed to compile value expression",
				array(
					'formula' => $this->label,
					'value' => $this->value,
					'error' => $ex->ErrorCode == 'XPST0017' ? "xbrlve:noCustomFunctionSignature" : $ex->ErrorCode,
					'reason' => $ex->getMessage()
				)
			);

			return false;

		}
		catch ( FormulasException $ex )
		{
			throw $ex;
		}
		catch ( \Exception $ex )
		{
			// BMS 2018-03-27 Test 21201 V-01 requires that this compile failure return xbrlve:noCustomFunctionSignature
			//				  becauase the custom function my-own:ending-balance does not exist
			$log->formula_validation( "Formula", "Failed to compile value expression",
				array(
					'formula' => $this->label,
					'value' => $this->value,
					'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "err:XPST0017" : $ex->ErrorCode ) : get_class( $ex ),
					'reason' => $ex->getMessage()
				)
			);

			return false;
		}

		if ( $this->tupleLocationRule )
		{
			// The name given in the rule MUST be one of the scope dependency indexes
			if ( ! isset( $this->scopeDependencies[ $this->tupleLocationRule ] ) )
			{
				// Not valid so remove the rule
				$log->warning( "The tuple loctation rule ({$this->tupleLocationRule}) on formula '{$this->label}' does not match the name on a variable scope arc so will be ignored." );
				$this->tupleLocationRule = null;
			}

		}

		if ( $this->decimalsRule && ! is_numeric( $this->precisionRule ) )
		{
			try
			{
				$xpath2Expression = XPath2Expression::Compile( $this->decimalsRule, $nsMgr );
			}
			catch ( XPath2Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Formula decimal value", "Failed to compile expression",
					array(
						'qname expression' => $this->decimalsRule,
						'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "xbrlve:noCustomFunctionSignature" : $ex->ErrorCode ) : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);
			}
			catch ( \Exception $ex )
			{
				// BMS 2018-03-27 Test 21201 V-02 requires that this compile failure return xbrlve:noCustomFunctionSignature
				//				  becauase the custom function my-own:ending-balance does not exist
				\XBRL_Log::getInstance()->formula_validation( "Formula decimal value", "Failed to compile expression",
					array(
						'qname expression' => $this->decimalsRule,
						'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "err:XPST0017" : $ex->ErrorCode ) : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);
			}
		}

		if ( $this->precisionRule && ! is_numeric( $this->precisionRule ) )
		{
			try
			{
				$xpath2Expression = XPath2Expression::Compile( $this->precisionRule, $nsMgr );
			}
			catch ( XPath2Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Formula precision value", "Failed to compile expression",
					array(
						'qname expression' => $this->precisionRule,
						'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "xbrlve:noCustomFunctionSignature" : $ex->ErrorCode ) : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);
			}
			catch ( \Exception $ex )
			{
				// BMS 2018-03-27 Test 21201 V-01 requires that this compile failure return xbrlve:noCustomFunctionSignature
				//				  becauase the custom function my-own:ending-balance does not exist
				\XBRL_Log::getInstance()->formula_validation( "Formula precision value", "Failed to compile expression",
					array(
						'qname expression' => $this->precisionRule,
						'error' => $ex instanceof XPath2Exception ? ( $ex->ErrorCode == 'XPST0017' ? "err:XPST0017" : $ex->ErrorCode ) : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);
			}
		}

		// If there is no source then concept MUST be explicitly declared
		if ( is_null( $this->source ) )
		{
			if ( is_null( $this->conceptRule ) )
			{
				$log->formula_validation( "Formula", "If there is no source then a concept aspect rule MUST be explicitly declared but is not provided",
					array(
						'formula' => $this->label,
						'error' => 'xbrlfe:missingConceptRule'
					)
				);
			}

			if ( is_null( $this->entityIdentifierRule ) )
			{
				$log->formula_validation( "Formula", "If there is no source then an entity aspect rule MUST be explicitly declared but is not provided",
					array(
						'formula' => $this->label,
						'error' => 'xbrlfe:missingEntityIdentifierRule'
					)
				);
			}

			if ( is_null( $this->periodRule ) )
			{
				$log->formula_validation( "Formula", "If there is no source then an period aspect rule MUST be explicitly declared but is not provided",
					array(
						'formula' => $this->label,
						'error' => 'xbrlfe:missingPeriodRule'
					)
				);
			}

			// Create a qname from either the qname or the expression
			/**
			 * @var QName $qname
			 */
			$qname = null;
			if ( $this->conceptRule['qnameExpression'] )
			{
				foreach ( $this->conceptRule['qnameExpression'] as $qnameExpression )
				{
					// Check any variables in the expression are valid
					$expression = XPath2Expression::Compile( $qnameExpression, $this->nsMgr );
					$variableRefs = $expression->getParameterQNames();
					foreach ( $variableRefs as $variableQName )
					{
						if ( ! isset( $this->variablesByQName[ $variableQName->clarkNotation() ] ) )
						{
							$log->formula_validation( "Concept rule", "The variable reference in the concept rule qname expression is not to a valid variable",
								array(
									'formula' => $this->label,
									'variable reference' => $variableQName->clarkNotation(),
									'error' => 'xbrlfe:conceptExpressionInvalid'
								)
							);
						}
					}
				}
			}
			else
			{
				$qname = new QName( $this->conceptRule['qname'][0]['originalPrefix'], $this->conceptRule['qname'][0]['namespace'], $this->conceptRule['qname'][0]['name'] );

				// BMS 2018-03-19 Must have been having too many mince pies.  These tests are relevant whenever there is a known concept.
				// 				  They have been moved lower down
				// BMS 2018-01-05 Now not sure why these tests are needed.
	 			// Next find out if the qname refers to a variable.  If it does look for a filter that defines a concept
	 			// Begin by assuming the qname refers to a taxonomy element
	 			$concept = $qname;
	 			if ( isset( $this->variablesByQName[ $concept->clarkNotation() ] ) )
	 			{
	 				/**
	 				 * @var ConceptName $selectedFilter
	 				 */
	 				$selectedFilter = null;
	 				foreach ( $this->variablesByQName[ $concept->clarkNotation() ]->filters as $filter )
	 				{
	 					if ( ! $filter instanceof ConceptName ) continue;
	 					$selectedFilter = $filter;
	 					break;
	 				}

	 				if ( $selectedFilter )
	 				{
						$concept = null;
						$totalFilters = count( $selectedFilter->qnames ) + count( $selectedFilter->qnameExpressions );
						if ( $totalFilters == 1 )
						{
							// It's one of these
							foreach ( $selectedFilter->qnames as $filterQName )
							{
								$concept = qname( $filterQName );
								$taxonomy = $this->xbrlTaxonomy->getTaxonomyForNamespace( $concept->namespaceURI );
								$concept->prefix = $taxonomy->getPrefix();
							}

							if ( ! $concept )
							{
								foreach ( $selectedFilter->qnameExpressions as $filterQNameExpression )
				 				{
									$result = $this->evaluateXPath( $this, $this->conceptRule->$filterQNameExpression );
									$concept = new QName( $result->Prefix, $result->NamespaceUri, $result->LocalName );
			 					}
							}
						}

						if ( $totalFilters != 1 || ! $concept )
						{
							$log->formula_validation( "Formula", "There is no formula source but the concept aspect rule does not define a reference to a schema element",
								array(
									'formula' => $this->label,
									'error' => 'xbrlfe:missingConceptRule'
								)
							);
						}
	 				}
	 				else
	 				{
	 					// If the concept references a variable and there is no filter then the
	 					// the concept cannot be resolved statically.
	 					$concept = null;
	 				}
				}

				if ( $concept )
				{
					// BMS 2017-12-24 Move the following tests here because surely they are only relevant when there is no formula source
					/**
					 * @var SchemaTypes $types
					 */
					$types = \XBRL_Types::getInstance();
					$conceptElement = $this->xbrlTaxonomy->getElementByName( $concept->localName );
					// Or could do it this way
					// $conceptElement = $types->getElement( $this->conceptRule['qname'][0]['name'], $this->conceptRule['qname'][0]['originalPrefix'] );
					// $isNumeric = $types->resolvesToBaseType( $conceptElement['types'][0], array( 'xs:decimal', 'xs:decimal' ) );
					if ( ! $conceptElement )
					{
						$log->formula_validation( "Formula", "The concept provided by the concept aspect rule is not a valid schema element",
							array(
								'formula' => $label,
								'error' => 'xbrlfe:missingConceptRule'
							)
						);
					}

					// BMS 2018-04-09 Extra test candidate not required any more but it doesn't hurt.
					$isNumeric = $types->resolvesToBaseType( $conceptElement['type'], array( 'xs:decimal', 'xsd:decimal' ) );
					if ( is_null( $this->source ) && is_null( $this->unitRule ) )
					{
						// Check to see if the concept references a numeric type
						if ( $isNumeric )
						{
							$log->formula_validation( "Formula", "If there is no source and the concept references a numeric type then a unit aspect rule MUST be explicitly declared but is not provided",
								array(
									'formula' => $this->label,
									'error' => 'xbrlfe:missingUnitRule'
								)
							);
						}
					}
					else if ( ! is_null( $this->unitRule ) )
					{
						if ( ! $isNumeric )
						{
							$log->formula_validation( "Formula", "The concept aspect rule defines a non-numeric concept so a unit is not valid",
								array(
									'formula' => $this->label,
									'error' => 'xbrlfe:conflictingAspectRules'
								)
							);
						}
					}

					// BMS 2018-03-19 Don't know why this is here when it is defined below
					// // Make sure there is no conflict between the concept and period aspect rule
					// if ( ! is_null( $this->periodRule ) )
					// {
					// 	if ( ! is_null( $this->periodRule['rav'] ) && $this->periodRule['rav'] != $conceptElement['periodType'] )
					// 	{
					// 		$log->formula_validation( "Formula", "The type of the period aspect rule conflicts with the period type of the concept aspect rule",
					// 			array(
					// 				'period aspect type' => $this->periodRule['rav'],
					// 				'concept period type' => $conceptElement['periodType'],
					// 				'formula' => $this->label,
					// 				'error' => 'xbrlfe:conflictingAspectRules'
					// 			)
					// 		);
					// 	}
					// }
				}
			}
		}
		else // $source is not null
		{
			$sourceQName = new QName( $this->source['originalPrefix'], $this->source['namespace'], $this->source['name'] );

			// If the if the source is not the uncovered qname make sure the referenced variable exists
			// if ( $sourceQName->namespaceURI != \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] ||
			//	 $sourceQName->localName != "uncovered" )
			if ( ! $this->getIsFormulaUncoveredQName( $sourceQName ) )
			{
				if ( ! isset( $this->variablesByQName[ $sourceQName->clarkNotation() ] ) ||
					 ! $this->variablesByQName[ $sourceQName->clarkNotation() ] instanceof FactVariable
				)
				{
					$log->formula_validation( "Formula", "",
						array(
							'concept' => $sourceQName->prefix ? "{$sourceQName->prefix}:{$sourceQName->localName}" : $sourceQName->localName,
							'formula' => $this->label,
							'error' => 'xbrlfe:nonexistentSourceVariable',
						)
					);
				}

				/**
				 * Look for the variable
				 * @var FactVariable $variable
				 */
				$variable = $this->variablesByQName[ $sourceQName->clarkNotation() ];
				if ( $variable->bindAsSequence )
				{
					// TODO Should check the XPath implied by the fallback value but, for now,
					//	    copy Arelle and fail if there is any fallback value
					$log->formula_validation( "Formula", "Formula source is a fact variable that binds as a sequence",
						array(
							'formula' => $this->label,
							'source' => $sourceQName->prefix ? "{$sourceQName->prefix}:{$sourceQName->localName}" : $sourceQName->localName,
							'error' => 'xbrlfe:defaultAspectValueConflicts',
						)
					);
				}
			}

			if ( $this->conceptRule['qname'] )
			{
				// BMS 2015-03-26
				// If the concept rule is really a source then the qname will reference a variable so reset
				$concept = new QName( $this->conceptRule['qname'][0]['originalPrefix'], $this->conceptRule['qname'][0]['namespace'], $this->conceptRule['qname'][0]['name'] );
				if ( isset( $this->variablesByQName[ $concept->clarkNotation() ] ) )
				{
					$concept = null;
				}
			}
		}

		if ( isset( $concept ) )
		{
			// BMS 2018-03-19 Must have been having too many mince pies.  These tests are relevant whenever there is a known concept.
			// 				  So they belong here.
			// BMS 2017-12-24 Move the following tests here because surely they are only relevant when there is no formula source
			$types = \XBRL_Types::getInstance();
			$conceptElement = $this->xbrlTaxonomy->getElementByName( $concept->localName );
			// Or could do it this way
			// $conceptElement = $types->getElement( $this->conceptRule['qname'][0]['name'], $this->conceptRule['qname'][0]['originalPrefix'] );
			// $isNumeric = $types->resolvesToBaseType( $conceptElement['types'][0], array( 'xs:decimal', 'xsd:decimal' ) );
			if ( ! $conceptElement )
			{
				$log->formula_validation( "Formula", "The concept provided by the concept aspect rule is not a valid schema element",
					array(
						'formula' => $label,
						'error' => 'xbrlfe:missingConceptRule'
					)
				);
			}

			// BMS 2018-04-09 Extra test candidate not required any more but it doesn't hurt.
			$isNumeric = $types->resolvesToBaseType( $conceptElement['type'], array( 'xs:decimal', 'xsd:decimal' ) );
			if ( is_null( $this->source ) && is_null( $this->unitRule ) )
			{
				// Check to see if the concept references a numeric type
				if ( $isNumeric )
				{
					$log->formula_validation( "Formula", "If there is no source and the concept references a numeric type then a unit aspect rule MUST be explicitly declared but is not provided",
						array(
							'formula' => $this->label,
							'error' => 'xbrlfe:missingUnitRule'
						)
					);
				}
			}
			else if ( ! is_null( $this->unitRule ) )
			{
				if ( ! $isNumeric )
				{
					$log->formula_validation( "Formula", "The concept aspect rule defines a non-numeric concept so a unit is not valid",
						array(
							'formula' => $this->label,
							'error' => 'xbrlfe:conflictingAspectRules'
						)
					);
				}
			}

			// Make sure there is no conflict between the concept and period aspect rule
			if ( ! is_null( $this->periodRule ) )
			{
				if ( ! is_null( $this->periodRule['rav'] ) && $this->periodRule['rav'] != $conceptElement['periodType'] )
				{
					$log->formula_validation( "Formula", "The type of the period aspect rule conflicts with the period type of the concept aspect rule",
						array(
							'period aspect type' => $this->periodRule['rav'],
							'concept period type' => $conceptElement['periodType'],
							'formula' => $this->label,
							'error' => 'xbrlfe:conflictingAspectRules'
						)
					);
				}
			}

		}

		$sources = $this->getAspectSources();
		foreach ( $sources as $ruleName => $source )
		{
			if ( $this->getIsFormulaUncoveredQName( $source ) ) continue;

			$sourceQName = new QName( $source['originalPrefix'], $source['namespace'], $source['name'] );
			/**
			 * Look for the variable
			 * @var FactVariable $variable
			 */
			$variable = $this->variablesByQName[ $sourceQName->clarkNotation() ];
			if ( ! $variable->fallbackValue ) continue;

			// TODO Should check the XPath implied by the fallback value but, for now,
			//	    copy Arelle and fail if there is any fallback value
			$log->formula_validation( "Formula aspects", "Formula source is a fact variable that has a fallback value",
				array(
					'formula' => $this->label,
					'rule' => $ruleName,
					'error' => 'xbrlfe:bindEmptySourceVariable',
				)
			);
		}

		return true;
	}

	/**
	 * Return true if $source is the forula:uncovered QName
	 * @param array|QName $source
	 * @return boolean
	 */
	public function getIsFormulaUncoveredQName( $source )
	{
		return $source instanceof QName
			? $source->namespaceURI == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] && $source->localName == 'uncovered'
			: $source['namespace'] == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FORMULA ] && $source['name'] == 'uncovered';
	}

	/**
	 * Allows a resource to return a list of the qnames of any variable references they contain
	 * @return array[\QName]
	 */
	public function getVariableRefs()
	{
		// It is helpful for this function to return references in the 'value' statment and any filter expressions
		// because the check in XBRL-Formulas that detects xbrlve:unresolvedDependency needs them
		$result = array();
		foreach ( $this->variablesByQName as $qname => $variable )
		{
			$result = array_merge( $result, $variable->getVariableRefs() );
		}

		if ( $this->valueXPath2expression instanceof XPath2Expression )
		{
			$result = array_merge( $result, $this->valueXPath2expression->getParameterQNames() );
		}

		$result = array_unique( $result );

		return $result;
	}

	/**
	 * Give the variable set instance an opportunity to process the facts
	 */
	public function evaluateResult()
	{
		global $debug_statements;

		if ( $debug_statements )
		{
			\XBRL_Log::getInstance()->info( "Process formula" ) ;
		}

		try
		{
			// Check all variables are not all fallback and that preconditions are met
			if ( parent::evaluateResult() )
			{
				$vars = $this->getBindingsAsVars();
				$covered = array();
				$defined = array();

				foreach ( $this->factVariableBindings as $qname => /** @var VariableBinding $binding */ $binding )
				{
					$covered[ $qname ] = $binding->aspectsCovered;
					$defined[ $qname ] = $binding->aspectsDefined;
				}

				$result = $this->value
					? parent::evaluateXPathExpression( $this, $this->valueXPath2expression, $vars )
					: EmptyIterator::$Shared;

				$lastFactBinding = $this->getLastFactBinding();
				if ( ! $lastFactBinding )
				{
					// If a formula has no variables (like a tuple) then there will be no binding so create a dummy one.
					// This is needed so the aspects defined are propagated
					$lastFactBinding = new FactVariableBinding( $this->xbrlInstance, null );
					$lastFactBinding->facts = EmptyIterator::$Shared;
					$lastFactBinding->aspectsDefined = $this->covers;
					$pos = array_search( ASPECT_DIMENSIONS, $lastFactBinding->aspectsDefined );
					if ( $pos !== false ) unset( $lastFactBinding->aspectsDefined[ $pos ] );
				}

				$evaluationResult = array(
					'result' => $result,
					'vars' => $vars,
					'covered' => $covered,
					'defined' => $defined,
					'lastFactBinding' => $lastFactBinding,
					'variableSet' => $this->label,
					'isTuple' => $this instanceof Tuple,
					'GUID' => \XBRL::GUID(),
				);

				$evaluationResult['uncoveredAspectFacts'] = $evaluationResult['lastFactBinding'] instanceof FactVariableBinding
					? $evaluationResult['lastFactBinding']->uncoveredAspectFacts
					: array();

				if ( $this->tupleLocationRule && isset( $this->scopeDependencies[ $this->tupleLocationRule] ) )
				{
					$scopeArc = $this->scopeDependencies[ $this->tupleLocationRule];
					$sourceFormula = $scopeArc['from'];

					// Look for the ScopeVaribleBinding with the same index as the $scopeArc['name']
					$scopeBindingQName = new QName( $scopeArc['name']['originalPrefix'], $scopeArc['name']['namespace'], $scopeArc['name']['name'] );
					if ( isset( $this->factVariableBindings[ $scopeBindingQName->clarkNotation() ] ) )
					{
						/**
						 * @var ScopeVariableBinding $scopeBinding
						 */
						$scopeBinding = $this->factVariableBindings[ $scopeBindingQName->clarkNotation() ];
						if ( $scopeBinding instanceof ScopeVariableBinding )
						{
							$evaluationResult['parentTupleGUID'] = $scopeBinding->scopeFactGUID;
							$evaluationResult['tuple'] = $sourceFormula;
						}
					}

					// // Look for the first previous evaluation that has a variable set value of $source formula
					// $sourceFormulaResults = array_filter( $this->evaluationResults, function( $evaluationResult ) use( $sourceFormula )
					// {
					// 	return $evaluationResult['variableSet'] == $sourceFormula;
					// } );
                    //
					// if ( count( $sourceFormulaResults ) == 1 )
					// {
					// 	$previousResult = reset( $sourceFormulaResults );
					// 	$evaluationIndex = key( $sourceFormulaResults );
                    //
					// 	$evaluationResult['parentTupleGUID'] = $evaluationIndex; // $previousResult['tupleGUID'];
					// 	$evaluationResult['tuple'] = $previousResult['variableSet'];
					// }
					// else
					// {
					// 	// Look for a previous evaluation result from the source formula that has the same uncovered aspect facts
					// 	foreach ( $sourceFormulaResults as $evaluationIndex => $previousResult )
					// 	{
					// 		$commonAspects = array_intersect( array_keys( $previousResult['uncoveredAspectFacts'] ), array_keys( $evaluationResult['uncoveredAspectFacts'] ) );
					// 		foreach ( $commonAspects as $aspect )
					// 		{
					// 			if ( is_null( $previousResult['uncoveredAspectFacts'][ $aspect ] ) || is_null( $evaluationResult['uncoveredAspectFacts'][ $aspect ] ) ) continue;
					// 			/**
					// 			 * @var DOMXPathNavigator $previousFact
					// 			 */
					// 			$previousFact = $previousResult['uncoveredAspectFacts'][ $aspect ];
					// 			/**
					// 			 * @var DOMXPathNavigator $evaluationFact
					// 			 */
					// 			$evaluationFact = $evaluationResult['uncoveredAspectFacts'][ $aspect ];
                    //
					// 			if ( $previousFact->getUnderlyingObject()->getNodePath() != $evaluationFact->getUnderlyingObject()->getNodePath() ) continue;
                    //
					// 			$evaluationResult['parentTupleGUID'] = $evaluationIndex; // $previousResult['tupleGUID'];
					// 			$evaluationResult['tuple'] = $previousResult['variableSet'];
                    //
					// 			break 2;
					// 		}
					// 	}
					// }
				}

				$this->evaluationResults[ $evaluationResult['GUID'] ] = $evaluationResult;

				return true;
			}
		}
		catch( XPath2Exception $ex )
		{
			throw $ex;
		}
		catch( \Exception $ex )
		{
			// Do nothing
		}

		return false;
	}

	/**
	 * A cache for a compiled expression
	 * @var XPath2Expression $precisionRuleXPath
	 */
	private $precisionRuleXPath = null;

	/**
	 * A cache for a compiled expression
	 * @var XPath2Expression $decimalsRuleXPath
	 */
	private $decimalsRuleXPath = null;

	/**
	 * Abstract function to allow descendents to process output
	 * @param \XBRL_Log $log
	 */
	public function ProcessEvaluationResult( $log )
	{
		global $debug_statements;

		if ( $debug_statements )
		{
			\XBRL_Log::getInstance()->info( "Output instance document ({$this->label})" );
		}

		// Any additional namespaces
		if ( ! $this->factsContainer->namespaces )
		{
			$this->factsContainer->namespaces = $this->xbrlInstance->getInstanceNamespaces();
		}
		$namespaces =& $this->factsContainer->namespaces;

		$taxonomy = $this->xbrlTaxonomy;
		$primaryItems = $taxonomy->getDefinitionPrimaryItems( false );

		foreach ( $this->evaluationResults as $evaluationResultGUID => $evaluationResult )
		{
			// Only use results generated by this variable set.  Other results may exist added as a result of a scope variable arc.
			if ( isset( $evaluationResult['variableSet'] ) && $evaluationResult['variableSet'] != $this->label ) continue;

			// Clear any previous value
			$primaryItemRoles = null;

			if ( $evaluationResult['result'] instanceof XPath2NodeIterator )
			{
				$count = $evaluationResult['result']->getCount();
				if ( $count > 1 )
				{
					$log->formula_validation( "Formula", "The formula result is not a single value",
						array(
							'formula' => $this->label,
							'error' => 'xbrlfe:nonSingletonOutputValue'
						)
					);
				}

				if ( $count == 0 )
				{
					$evaluationResult['result'] = Undefined::getValue();
				}
				else
				{
					$evaluationResult['result']->rewind();
					$evaluationResult['result'] = $evaluationResult['result']->getCurrent()->getValue();
				}
			}

			// Source evaluation has to be done within the context of a
			// produced fact because the source may reference a variable

			// Use the concept aspect flag in case the formula source is formula:uncovered
			// If there is no valid source an exception will be thrown
			$sourceFact = $this->getSourceFact( $this->source, $evaluationResult, ASPECT_CONCEPT, \XBRL_Log::getInstance() );

			$contextRef = null;
			if ( $sourceFact )
			{
				// Get the context ref from the source
				$contextRef = FactValues::getContextRef( $sourceFact );
			}
			else
			{
				// Use the context ref of one of the formula variables
				$variableRefs = $this->getVariableRefs();
				foreach ( $variableRefs as $variableRef )
				{
					$contextFact = $this->getSourceFact( $variableRef, $evaluationResult, ASPECT_CONCEPT, \XBRL_Log::getInstance() );
					// As well as being null the returned context fact could be aN XPath2Item (fallback value)
					if ( ! $contextFact ) continue;
					if ( $contextFact instanceof XPath2NodeIterator )
					{
						$clone = $contextFact->CloneInstance();
						$clone->Reset();
						if ( $clone->MoveNext() )
						{
							$contextFact = $clone->getCurrent();
						}
					}
					if ( ! $contextFact instanceof DOMXPathNavigator ) continue;

					$contextRef = FactValues::getContextRef( $contextFact );
					break;
				}
			}

			// Get the unit ref from the source
			// $unitRef = FactValues::getUnitRef( $sourceFact );

			// Get the context of the formula source fact
			$orginalContext = $contextRef ? $this->xbrlInstance->getContext( $contextRef ) : array();

			// Get the concept RAV
			$conceptQName = $this->getConceptAspectQName( $sourceFact, $evaluationResult, $log );

			// Get the entity identifier RAV
			$entityIdentifier = $this->getEntityIdentifierAspectValue( $this->source, $evaluationResult, $log );

			// Get the location RAV
			$locationQName = $this->getLocationAspect( $this->source, $evaluationResult, $log );

			// Get the period RAV
			$period = $this->getPeriodAspectValue( $this->source, $evaluationResult, $log );

			// Get the scenario RAV
			$componentType = 'scenario';
			foreach ( Formula::$componentPaths as $componentPath )
			{
				if ( ! in_array( $componentType, $componentPath ) ) continue;
				$this->scenariosComponentPath = $componentPath;
				$scenarios = $this->getComponentAspectValue( $this->source, $this->aspectModel == 'dimensional' ? ASPECT_NON_XDT_SCENARIO : ASPECT_COMPLETE_SCENARIO, $componentType, $evaluationResult, $log );
				if ( $scenarios ) break;
			}

			// Get the segment RAV
			$componentType = 'segment';
			foreach ( Formula::$componentPaths as $componentPath )
			{
				if ( ! in_array( $componentType, $componentPath ) ) continue;
				$this->segmentsComponentPath = $componentPath;
				$segments = $this->getComponentAspectValue( $this->source, $this->aspectModel == 'dimensional' ? ASPECT_NON_XDT_SEGMENT : ASPECT_COMPLETE_SEGMENT, $componentType, $evaluationResult, $log );
				if ( $segments ) break;
			}

			$contextDimensions = array();
			$elementTaxonomy = $taxonomy->getTaxonomyForNamespace( $conceptQName->namespaceURI );
			$conceptElement = $elementTaxonomy->getElementByName( $conceptQName->localName );

			if ( $this->aspectModel == 'dimensional' )
			{
				// Remove any XDT components from the segments and scenarios list
				unset( $scenarios['explicitMember'] );
				unset( $scenarios['typedMember'] );
				unset( $segments['explicitMember'] );
				unset( $segments['typedMember'] );

				$conceptPrimaryItemId = "{$elementTaxonomy->getTaxonomyXSD()}#{$conceptElement['id']}";
				$contextDimensions = array();
				$typedDimensions = array();
				// Only need to process these if the target concept is a primary item
				if ( isset( $primaryItems[ $conceptPrimaryItemId ] ) )
				{
					$primaryItemRoles = $taxonomy->getPrimaryItemDRS( $primaryItems[ $conceptPrimaryItemId ]);

					// Record if ANY of the hyercubes linked to the primary item are not closed
					$isClosed = true;

					// Get a list of all the possoble explicit dimensions
					$explicitTaxonomyDimensions = array();
					$typedTaxonomyDimensions = array();

					foreach ( $primaryItemRoles as $HypercubeId => $roles )
					{
						foreach ( $roles as $roleUri => $details )
						{
							$contextElement = $details['parents'][ $conceptPrimaryItemId ]['contextElement'];

							if ( ! $details['parents'][ $conceptPrimaryItemId ]['closed'] )
							{
								$isClosed = false;
							}

							foreach ( $details['dimensions'] as $dimensionId => $dimension )
							{
								// Get the element
								$dimTaxonomy = $taxonomy->getTaxonomyForXSD( $dimensionId );
								$dimElement = $dimTaxonomy->getElementById( $dimensionId );
								$dimQName = new QName( null, $dimTaxonomy->getNamespace(), $dimElement['name'] );
								if ( ! isset( $dimension['explicit'] ) || $dimension['explicit'] )
								{
									$explicitTaxonomyDimensions[ $dimQName->clarkNotation() ] = $isClosed;
								}
								else
								{
									$typedTaxonomyDimensions[ $dimQName->clarkNotation() ] = $isClosed;
								}
							}
						}
					}

					// $explicitTaxonomyDimensions = array_unique( $explicitTaxonomyDimensions );
					// $typedTaxonomyDimensions = array_unique( $typedTaxonomyDimensions );

					$aspectsDefinedDimensions = array_reduce( $evaluationResult['defined'],
						function( $carry, $aspectsDefined ) {
							return array_unique( array_merge( $carry, array_filter( $aspectsDefined, function( $aspect ) { return $aspect[0] != '\\'; } ) ) );
						}, array() );

					foreach ( $aspectsDefinedDimensions as $aspectsDefinedDimension )
					{
						// Look to see if the aspect defined dimension is defined as a hypercube dimension
						if ( isset( $explicitTaxonomyDimensions[ $aspectsDefinedDimension ] ) ||
							 isset( $typedTaxonomyDimensions[ $aspectsDefinedDimension ] ) )
						{
							// It is so nothing to do
							continue;
						}

						if ( $isClosed )
						{
							// Can't warn here because a scenario is that there is a context dimension to be removed
						}

						// It's not so get the element find out if the dimension is typed or explicit
						$dimensionQName = qname( $aspectsDefinedDimension );
						$dimTaxonomy = $taxonomy->getTaxonomyForNamespace( $dimensionQName->namespaceURI );
						$dimElement = $dimTaxonomy->getElementByName( $dimensionQName->localName );
						if ( isset( $dimElement['typedDomainRef'] ) )
						{
							$typedTaxonomyDimensions[ $aspectsDefinedDimension ] = $isClosed;
						}
						else
						{
							$explicitTaxonomyDimensions[ $aspectsDefinedDimension ] = $isClosed;
						}
					}

					// This function has taken a lot of deliberation.  Here's a summary I'm writing in one of my less confused
					// moments. By this line $explicitTaxonomyDimensions will contain either a list of dimensions associated with
					// the hypercubes of the primary item and/or those which are uncovered dimensions.  A dimension is necessary
					// before calling getExplicitDimensionAspectValue() because this function and any rules is uses may need
					// to resolve the 'uncovered' QName if it is used as a rule source and this resolution will require the name
					// of an uncovered dimension.  As a result one of the following will be true.  If not, it means there will
					// be no OCC dimension values to worry about.
					//
					// 1) There are dimensions associated with the primary item hypercubes
					// 2) There are dimensions discovered and added to the 'aspectsDefined' list
					// 3) There are no dimensions but there is a rule that might add a dimension(*)
					//
					// These conditions are additive.  For example there may be hypercube dimensions and discovered dimensions
					// and one or more rules.
					// If there are no dimensions (hypercube or discovered) there may be one or more rules.  In this case
					// a dummy rule will be added to $explicitTaxonomyDimensions so the getExplicitDimensionAspectValue() will
					// be called.
					// Finally, there there is no information here about whether an exising uncovered dimension belongs to
					// a segment or scenario so definately not about whether the componet is a child of the entity node.
					// To handle this, the getExplicitDimensionAspectValue() may be called up to four times - once each for
					// the combinations of scenario/segment and entity/not entity child.
					//
					// These note apply equally to processing the $typedTaxonomyDimensions variable.

					if ( ! $explicitTaxonomyDimensions && $this->explicitDimensionRule )
					{
						// Add a dummy dimension so the rule(s) have chance to run
						$explicitTaxonomyDimensions[''] = $isClosed;
					}

					// BMS 2018-02-19 Updated to combine dimension results before validating them and their paths
					$dimensionsResult = array();
					// Get the explicit dimension RAV
					foreach ( $explicitTaxonomyDimensions as $dimension => $closed )
					foreach ( Formula::$componentPaths as $componentPath )
					{
						$componentType = in_array( "scenario", $componentPath ) ? 'scenario' : 'segment';

						// This class variable is used to pass component path information to the ExplicitDimension instance
						$this->explicitDimensionsComponentPath = $componentPath;
						$explicitDimensionsResult = $this->getExplicitDimensionAspectValue( $this->source, $componentPath, $evaluationResult, $dimension, $log );
						if ( $explicitDimensionsResult )
						{
							$dimensionsResult = array_merge( $dimensionsResult, $explicitDimensionsResult );
							// // Check the dimension(s) and primary item
							// // Chack is the segment/scenario path is consistent with the context element of the primary item
							// $componentPath = $this->validateContextDimensions( $taxonomy, $conceptPrimaryItemId, $explicitDimensionsResult, $primaryItemRoles, $componentPath );
							// $contextDimensions['explicitMember'][ $dimension ] = array(
							// 	'dimensions' => $explicitDimensionsResult,
							// 	'paths' => $componentPath
							// );
							break;
						}
					}

					if ( ! $typedTaxonomyDimensions && $this->typedDimensionRule )
					{
						// Add a dummy dimension so the rule(s) have chance to run
						$typedTaxonomyDimensions[''] = $isClosed;
					}

					// Get the typed dimension RAV
					foreach ( $typedTaxonomyDimensions as $dimension => $closed )
					foreach ( Formula::$componentPaths as $componentPath )
					{
						$componentType = in_array( "scenario", $componentPath ) ? 'scenario' : 'segment';

						// This class variable is used to pass component path information to the TypedDimension instance
						$this->typedDimensionsComponentPath = $componentPath;
						$typedDimensionsResult = $this->getTypedDimensionAspectValue( $this->source, $componentPath, $evaluationResult, $dimension, $log );
						if ( $typedDimensionsResult )
						{
							$dimensionsResult = array_merge( $dimensionsResult, $typedDimensionsResult );
							// // Check the dimension(s) and primary item
							// // Is the segment/scenario path is consistent with the context element of the primary item
							// $componentPath = $this->validateContextDimensions( $taxonomy, $conceptPrimaryItemId, $typedDimensionsResult, $primaryItemRoles, $componentPath );
							// $contextDimensions['typedMember'][ $dimension ] = array(
							// 	'dimensions' => $typedDimensionsResult,
							// 	'paths' => $componentPath
							// );
							break;
						}
					}

					// Check the dimension(s) and primary item
					$componentPath = $this->validateContextDimensionsAlt( $conceptPrimaryItemId, $dimensionsResult, $primaryItemRoles, $componentPath );
					$contextDimensions = array();
					foreach ( $dimensionsResult as $dimensionId => $members )
					{
						$qname = qname( $dimensionId, $this->nsMgr->getNamespaces() );
						$memberType = in_array( $qname->clarkNotation(), $this->typedDimensions ) ? 'typedMember' : 'explicitMember';
						if ( ! isset( $contextDimensions[ $memberType ][ $qname->clarkNotation() ] ) )
						{
							$contextDimensions[ $memberType ][ $qname->clarkNotation() ] = array(
								'dimensions' => array(),
								'paths' => $componentPath
							);
						}
						$contextDimensions[ $memberType ][ $qname->clarkNotation() ]['dimensions'] = array( $dimensionId => $members );
					}
				}
			}

			// Create a context
			$newContext = array();
			// Any additional namespaces
			// $namespaces = $this->xbrlInstance->getInstanceNamespaces();

			$addPath = function( $context, $path, $extra, $values )
			{
				$selected = &$context;
				foreach ( $path as $element )
				{
					if ( ! isset( $selected[ $element ] ) )
					{
						$selected[ $element ] = array();
					}

					$selected = &$selected[ $element ];
				}

				if ( $extra )
				{
					if ( ! isset( $selected[ $extra ] ) )
					{
						$selected[ $extra ] = array();
					}
					$selected = &$selected[ $extra ];
				}

				$selected = array_merge( $selected, $values );
				unset( $selected );

				return $context;
			};

			if ( $entityIdentifier )
			{
				$newContext['entity']['identifier'] = $entityIdentifier;
			}

			if ( $period )
			{
				$newContext['period'] = $period;
			}

			if ( $scenarios )
			{
				// Need to add the scenarios to the correct path
				$extra = $this->scenarioRule ? '' : '';
				$newContext = $addPath( $newContext, $this->scenariosComponentPath, $extra, $scenarios );
				// $newContext['scenario']['member'] = $scenarios;
			}

			if ( $segments )
			{
				// Need to add the segments to the correct path
				// BMS 2018-02-19 Conflict here between when an 'extra' value of member is needed and when not.
				// BMS 2018-03-22 I believe this issue is resolved.  An additional 'extra' value is not required.
				//				  See the note of the same date in getComponentAspectValue()
				//
				$extra = $this->segmentRule ? '' : '';
				$newContext = $addPath( $newContext, $this->segmentsComponentPath, $extra, $segments );
			}

			if ( $contextDimensions )
			{
				foreach ( $contextDimensions as $memberType => $dimensions )
				{
					foreach ( $dimensions as $aspectDimension => $details )
					{
						$paths = $details['paths'];
						$dimensions = Dimension::IndexToContext( $details['dimensions'] );
						$newContext = $addPath( $newContext, $paths, $memberType, $dimensions );
					}
				}
			}

			// $originalContextHash = $this->hashArray( $orginalContext );

			// A precautionary measure.  You know, just in case.
			if ( is_null( $this->factsContainer ) )
			{
				$this->factsContainer = new GeneratedFacts();
			}

			if ( ! $evaluationResult['isTuple'] )
			{
				$hash = $this->hashArray( $newContext );

				if ( isset( $this->factsContainer->contextHashes[ $hash['hash'] ] ) )
				{
					// Since it is equivalent to an existing context, use the existing context ref
					$contextRef = $this->factsContainer->contextHashes[ $hash['hash'] ];
				}
				else
				{
					if ( ! $contextRef )
					{
						$contextRef = "context";
					}

					if ( isset( $this->factsContainer->contexts[ $contextRef ] ) )
					{
						// The contextRef has been used but the hash is not the same
						// So generate a new context ref and carry on
						if ( isset( $this->factsContainer->contextRefIndexes[ $contextRef ] ) )
						{
							$index = $this->factsContainer->contextRefIndexes[ $contextRef ];
							$index++;
							$this->factsContainer->contextRefIndexes[ $contextRef ] = $index;
							$contextRef .= "-$index";
						}
						else
						{
							$index = 1;
							$this->factsContainer->contextRefIndexes[ $contextRef ] = $index;
							$contextRef .= "-$index";
						}
					}

					// // BMS 2018-04-04 Moved up
					// if ( ! $contextRef )
					// {
					// 	$contextRef = "context";
					// }

					$this->factsContainer->contextHashes[ $hash['hash'] ] = $contextRef;
					$this->factsContainer->contexts[ $contextRef ] = $newContext;
				}
			}

			// Create a fact
			$fact = array();

			// BMS 2018-04-01 Check to see if the qname namespace is a taxonomy
			$tax = $this->xbrlTaxonomy->getTaxonomyForNamespace( $conceptQName->namespaceURI );
			if ( $tax )
			{
				// Set the qname prefix
				$conceptQName->prefix = $tax->getPrefix();
			}

			// If this prefix does not exist in the namespace list add it.
			if ( ! isset( $namespaces[ $conceptQName->prefix ] ) )
			{
				$namespaces[ $conceptQName->prefix ] = $conceptQName->namespaceURI;
			}

			$fact['concept'] = $conceptQName;
			$fact['contextRef'] = $evaluationResult['isTuple'] ? null : $contextRef;

			if ( $evaluationResult['result'] instanceof Undefined )
			{
				$fact['value'] = null;
			}
			else if ( $evaluationResult['result'] instanceof DOMXPathNavigator )
			{
				$value = $evaluationResult['result']->getValue();
				$fact['value'] = ! $value && FactValues::isNull( $evaluationResult['result'] )
					? null
					: $value;
			}
			else
			{
				$fact['value'] = $evaluationResult['result'] instanceof XPath2Item || $evaluationResult['result'] instanceof DecimalValue
					  ? $evaluationResult['result']->getValue()
					  : ( $evaluationResult['result'] instanceof CoreFuncs::$False
					  		? "false"
					  		: ( $evaluationResult['result'] instanceof CoreFuncs::$True
					  				? 'true'
					  				: $evaluationResult['result']
					  		  )
						);
			}

			$unit = null;
			$typeIsNumeric = false;
			// BMS 2018-04-09 Extra test candidate not required any more but it doesn't hurt.
			if ( \XBRL_Types::getInstance()->resolvesToBaseType( $conceptElement['type'], array( 'xs:decimal', 'xsd:decimal' ) ) )
			{
				$typeIsNumeric = true;
				// Create a unit
				$unit = $this->getUnitAspectValue( $this->source, $evaluationResult, $log );
				$unitRule = Unit::fromArray( $this->unitRule );
				$namespaces = array_merge( $namespaces, $unitRule->getAdditionalNamespaces( $this, $evaluationResult ) );
			}

			if ( $typeIsNumeric && is_numeric( $fact['value'] ) )
			{
				if ( ! is_null( $this->precisionRule ) )
				{
					if ( is_numeric( $this->precisionRule ) )
					{
						$fact['precision'] = $this->precisionRule;
					}
					else
					{
						if ( ! $this->precisionRuleXPath )
						{
							$this->precisionRuleXPath = XPath2Expression::Compile( $this->precisionRule, $this->nsMgr );
						}

						$result = CoreFuncs::Atomize( $this->evaluateXPathExpression( $this, $this->precisionRuleXPath, $this->getParametersAsVars( $evaluationResult['vars'] ) ) );
						$fact['precision'] = $result instanceof XPath2Item
							? $result->getValue()
							: $result;
					}
				}
				else if ( ! is_null( $this->decimalsRule ) )
				{
					if ( is_numeric( $this->decimalsRule ) )
					{
						$fact['decimals'] = $this->decimalsRule;
					}
					else
					{
						if ( ! $this->decimalsRuleXPath )
						{
							$this->decimalsRuleXPath = XPath2Expression::Compile( $this->decimalsRule, $this->nsMgr );
						}

						$result = CoreFuncs::Atomize( $this->evaluateXPathExpression( $this, $this->decimalsRuleXPath, $this->getParametersAsVars( $evaluationResult['vars'] ) ) );
						$fact['decimals'] = $result instanceof XPath2Item
							? $result->getValue()
							: $result;
					}
				}
				else
				{
					// This is the default rule specified in the formula specification section 2.1.1.1 (last sentence).
					$fact['precision'] = "0";
				}

				if ( isset( $fact['decimals'] ) )
				{
					$fact['value'] = number_format( $fact['value'], $fact['decimals'], ".", "" );
				}
			}

			if ( $unit )
			{
				$hash = $this->hashArray( reset( $unit ) );
				if ( isset( $this->factsContainer->unitHashes[ $hash['hash'] ] ) )
				{
					// Since it is equivalent to an existing unit, use the existing unit ref
					$unitRef = $this->factsContainer->unitHashes[ $hash['hash'] ];
				}
				else
				{
					$unitRef = key( $unit );
					if ( isset( $this->factsContainer->units[ $unitRef ] ) )
					{
						// The unitRef has been used but the hash is not the same
						// So generate a new context ref and carry on
						if ( isset( $this->factsContainer->unitRefIndexes[ $unitRef ] ) )
						{
							$index = $this->factsContainer->unitRefIndexes[ $unitRef ];
							$index++;
							$this->factsContainer->unitRefIndexes[ $unitRef ] = $index;
							$unitRef .= "-$index";
						}
						else
						{
							$index = 1;
							$this->factsContainer->unitRefIndexes[ $unitRef ] = $index;
							$unitRef .= "-$index";
						}
					}

					$this->factsContainer->unitHashes[ $hash['hash'] ] = $unitRef;
					$this->factsContainer->units[ $unitRef ] = reset( $unit );
				}

				$fact['unitRef'] = $unitRef;
			}
			else
			{
				// If the fact is numeric then there MUST be a unit RAV
				if ( $typeIsNumeric && is_numeric( $fact['value'] ) )
				{
					$log->formula_validation( "Unit aspect", "No aspect for numeric fact",
						array(
							'formula' => $this->label,
							'error' => 'xbrlfe:missingUnitRule'
						)
					);
				}
			}

			// Copy over any tuple information
			if ( isset( $evaluationResult['tuple'] ) )
			{
				$fact['tuple'] = $evaluationResult['tuple'];
				$fact['parentTupleGUID'] = $evaluationResult['parentTupleGUID'];
			}

			$fact['isTuple'] = $evaluationResult['isTuple'];

			$this->factsContainer->facts[ $this->label ][ $evaluationResultGUID ] = $fact;
			$this->factsContainer->vars[ $this->label ][ $evaluationResultGUID ] = $evaluationResult['vars'];
			$this->factsContainer->sources[ $this->label ] = $this->conceptRule ? null : $sourceFact;
			// $this->factsContainer->namespaces = $namespaces;
		}
	}

	/**
	 * Allows the class to compare the expected result node to the result created
	 * Returns false if there is no problem or an error string to report if there is.
	 * @param string $testCaseFolder
	 * @param \SimpleXMLElement $expectedResultNode	The content of the <result> node from the test case.
	 * 												The relevant test class will know how to handle its content.
	 * @return bool|string False if there is no error or a string that describes the failure
	 */
	public function compareResult( $testCaseFolder, $expectedResultNode )
	{
		return "The formula result should be compared by the GeneratedFacts class";
	}

	/**
	 * Check the context dimensions are valid for the output concept component. The dimensions associated with the component
	 * of an input primary item maybe valid but not valid for the output primary item.
	 * @param \XBRL $taxonomy
	 * @param string $conceptPrimaryItemId
	 * @param array $componentDimensions
	 * @param array $primaryItemRoles
	 * @param array $componentPath
	 * @return array A final component path to use.
	 */
	private function validateContextDimensions( $taxonomy, $conceptPrimaryItemId, $componentDimensions, $primaryItemRoles, $componentPath )
	{
		// Check the dimension(s) and primary item
		// Is the segment/scenario path is consistent with the context element of the primary item
		foreach ( $primaryItemRoles as $hypercubeId => $hypercube )
		{
			foreach ( $hypercube as $roleUri => $role )
			{
				// If the hypercube is closed then the dimensions MUST be defined in the hypercube
				// of the role or the hypercube is not valid (XBRL Dimensions 1.0 section 3.1.3)
				$closed = $role['parents'][ $conceptPrimaryItemId ]['closed'];
				if ( $closed )
				{
					if ( ! $role['dimensions'] ) break 2;
					foreach ( $componentDimensions as $dimensionId => $members )
					{
						// Look for the context dimension in the primary item role dimensions
						// $qname = qname( $dimensionId );
						// $prefix = $this->xbrlInstance->getPrefixForNamespace( $qname->namespaceURI );
						$element = \XBRL_Types::getInstance()->getElement( $dimensionId );
						if ( ! $element )
						{
							$log->warning("Cannot find the context dimension {$contextDimension['dimension']} in the hypercube role '$roleUri' for primary item '{$conceptQName->localName}'");
							break;
						}
						$contextDimensionId = "{$taxonomy->getTaxonomyXSD()}#{$element['id']}";
						if ( ! isset( $role['dimensions'][ $contextDimensionId ] ) ) continue 2;
					}
				}

				$contextElement = $role['parents'][ $conceptPrimaryItemId ]['contextElement'];

				if ( in_array( $contextElement, $componentPath ) ) continue;
				if ( $contextElement == 'scenario' )
				{
					$componentPath = array( 'scenario' );
				}
				else if ( $contextElement == 'segment' )
				{
					$componentPath = array( 'entity', 'segment' );
				}
				break 2;
			}
		}

		return $componentPath;
	}

	/**
	 * Check the context dimensions are valid for the output concept component. The dimensions associated with the component
	 * of an input primary item maybe valid but not valid for the output primary item.
	 * @param string $conceptPrimaryItemId
	 * @param array $componentDimensions
	 * @param array $primaryItemRoles
	 * @param array $originalComponentPath
	 * @return array A final component path to use.
	 */
	private function validateContextDimensionsAlt( $conceptPrimaryItemId, $componentDimensions, $primaryItemRoles, $originalComponentPath = array() )
	{
		// Check the dimension(s) and primary item
		// Is the segment/scenario path is consistent with the context element of the primary item
		// In this version, first look for a role that matches ALL the component dimensions

		foreach( array( true, false ) as $closed )
		{
			foreach ( $primaryItemRoles as $hypercubeId => $hypercube )
			{
				foreach ( $hypercube as $roleUri => $role )
				{
					// If the hypercube is closed then the dimensions MUST be defined in the hypercube
					// of the role or the hypercube is not valid (XBRL Dimensions 1.0 section 3.1.3)
					$isClosed = $role['parents'][ $conceptPrimaryItemId ]['closed'];

					if ( $isClosed || $closed )
					{
						// If the number of hypercube dimensions is less than the number of
						// component dimensions and looking at closed hypercubes then this one cannot fit
						if ( count( $componentDimensions ) > count( $role['dimensions'] ) ) continue;

						// Now see if it is possible to match all the dimensions in this
						// role with the dimensions in the component dimensions list
						foreach ( $componentDimensions as $dimensionId => $members )
						{
							// Look for the context dimension in the primary item role dimensions
							$qname = qname( $dimensionId, $this->nsMgr->getNamespaces() );
							$dimTaxonomy = $this->xbrlTaxonomy->getTaxonomyForNamespace( $qname->namespaceURI );
							if ( ! $dimTaxonomy )
							{
								\XBRL_Log::getInstance()->warning("Unable to find the taxonomy for the dimension '$dimensionId'");
								continue 2;
							}
							$qname->prefix = $dimTaxonomy->getPrefix();

							$element = \XBRL_Types::getInstance()->getElement( $qname->localName, $qname->prefix );
							if ( ! $element )
							{
								\XBRL_Log::getInstance()->warning("Cannot find the component dimension element {$dimensionId} for primary item '{$conceptPrimaryItemId}'");
								continue 2;
							}
							$componentDimensionId = "{$dimTaxonomy->getTaxonomyXSD()}#{$element['id']}";
							if ( ! isset( $role['dimensions'][ $componentDimensionId ] ) ) continue 2;
						}
					}

					$contextElement = $role['parents'][ $conceptPrimaryItemId ]['contextElement'];

					$componentPath = array();
					if ( $contextElement == 'scenario' )
					{
						$componentPath = array( 'scenario' );
					}
					else if ( $contextElement == 'segment' )
					{
						$componentPath = array( 'entity', 'segment' );
					}
					return $componentPath;
				}
			}
		}

		\XBRL_Log::getInstance()->formula_validation( "Formulas", "Unable to find a role for a context with the same dimensions", array(

		) );

		// If there are no roles it is likely because there is a mismatch between the primary item
		// and the hypercube dimensions.  Segment or scenario dimensions should not be dropped without
		// an omit OCC rule so return the original path.  See 12061 V-13
		return $originalComponentPath;
	}

	/**
	 * Get the concept to be used for this fact.  If a concept rule does not exist, use the formula source value
	 * @param DOMXPathNavigator $sourceFact
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return QName|NULL
	 */
	private function getConceptAspectQName( $sourceFact, $evaluationResult, $log )
	{
		// Get the concept name from the source
		if ( is_null( $this->conceptRule ) )
		{
			if ( is_null( $sourceFact ) )
			{
				return null;
			}

			// Make sure the namespace is valid in the instance document and use the instance document prefix if different
			// BMS 2018-03-01 Using the function getPrefixForDocumentNamespace can be a problem because if more than one
			//				  prefix references the same namespace then only the last prefix in the original namespace list
			//				  will be returned.  Instead, look for a namespace for the prefix.  If one exists then the prefix
			//				  is OK.  If not then look up an alternative prefix. Or failing that, add the fact's prefix and
			//				  namespace to the document's namespace collection
			// $instancePrefix =  $this->xbrlInstance->getPrefixForDocumentNamespace( $conceptQName->namespaceURI );
			$namespace = $this->xbrlInstance->getNamespaceForPrefix( $sourceFact->getPrefix() );
			$instancePrefix = $namespace && $namespace == $sourceFact->getNamespaceURI()
				? $instancePrefix = $sourceFact->getPrefix()
				: $this->xbrlInstance->getPrefixForDocumentNamespace( $sourceFact->getNamespaceURI() );
			if ( ! $instancePrefix )
			{
				$this->xbrlInstance->addNamespace( $sourceFact->getPrefix(), $sourceFact->getNamespaceURI() );
				$instancePrefix = $sourceFact->getPrefix();
			}

			return new QName( $instancePrefix, $sourceFact->getNamespaceURI(), $sourceFact->getLocalName() );
		}
		else
		{
			/**
			 * @var Concept $concept
			 */
			$concept = Concept::fromArray( $this->conceptRule );
			// This function will throw an exception if there is a problem so there is no return value to check
			$this->checkSAVConflicts( $concept->source, ASPECT_CONCEPT, $evaluationResult, $log );
			/**
			 * @var QName $conceptQName
			 */
			$conceptQName = $concept->getValue( $this, $evaluationResult, $log );
			// BMS 2018-03-01 See comments above with the same date
			// $instancePrefix =  $this->xbrlInstance->getPrefixForDocumentNamespace( $conceptQName->namespaceURI );
			$namespace = $this->xbrlInstance->getNamespaceForPrefix( $conceptQName->prefix );
			$instancePrefix = $namespace && $namespace == $conceptQName->namespaceURI
				? $instancePrefix = $conceptQName->prefix
				: $this->xbrlInstance->getPrefixForDocumentNamespace( $conceptQName->namespaceURI );
			if ( ! $instancePrefix )
			{
				$this->xbrlInstance->addNamespace( $conceptQName->prefix, $conceptQName->namespaceURI );
				$instancePrefix = $conceptQName->prefix;
			}
			$conceptQName->prefix = $instancePrefix;
			return $conceptQName;
		}

	}

	/**
	 * Get the entity identifier to be used for this fact.  If an entity identifier rule does not exist, use the formula source value
	 * @param array $source
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return array|NULL
	 */
	private function getEntityIdentifierAspectValue( $source, $evaluationResult, $log )
	{

		// Get the entity identifier from the source
		if ( is_null( $this->entityIdentifierRule ) )
		{
			$context = $this->getContextForSource( $source, $evaluationResult, ASPECT_ENTITY_IDENTIFIER, $log, $contextRef );
			if ( ! $context || is_null( $context['entity']['identifier'] ) ) return null;

			// TODO What is in the $context array
			return $context['entity']['identifier'];
		}
		else
		{
			/**
			 * @var EntityIdentifier $identifier
			 */
			$identifier = EntityIdentifier::fromArray( $this->entityIdentifierRule );
			// This function will throw an exception if there is a problem so there is no return value to check
			$this->checkSAVConflicts( $identifier->source, ASPECT_ENTITY_IDENTIFIER, $evaluationResult, $log );
			return $identifier->getValue( $this, $evaluationResult, $log );
		}

	}

	/**
	 * Get the location to be used for this fact.  If a location rule does not exist, assume <xbrli:xbrl/>
	 * @param string $source
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return QName|NULL
	 */
	private function getLocationAspect( $source, $evaluationResult, $log )
	{
		// Get the location from the source
		if ( is_null( $this->locationRule ) )
		{
			return qname( "xbrli:xbrl", $this->nsMgr->getNamespaces() );
		}
		else
		{
			// TODO
			// This function will throw an exception if there is a problem so there is no return value to check
			$location = Location::fromArray( $this->locationRule );
			$this->checkSAVConflicts( $location->source, ASPECT_LOCATION, $evaluationResult, $log );
		}
	}

	/**
	 * Get the period to be used for this fact.  If a period rule does not exist, use the formula source value
	 * @param array $source
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return array|NULL
	 */
	private function getPeriodAspectValue( $source, $evaluationResult, $log )
	{
		// Get the period name from the source
		if ( is_null( $this->periodRule ) )
		{
			$context = $this->getContextForSource( $source, $evaluationResult, ASPECT_PERIOD, $log, $contextRef );
			if ( ! $context || ! isset( $context['period'] ) ) return null;

			// TODO What is in the $context array
			return $context['period'];
		}
		else
		{
			/**
			 *
			 * @var Period $period
			 */
			$period = Period::fromArray( $this->periodRule );
			// This function will throw an exception if there is a problem so there is no return value to check
			$this->checkSAVConflicts( $period->source, ASPECT_PERIOD, $evaluationResult, $log );
			return $period->getValue( $this, $evaluationResult, $log );
		}
	}

	/**
	 * Get the explicit diemsion to be used for this fact.  If an explicit dimension rule does not exist, use the formula source value
	 * @param string $source The formula source
	 * @param array $componentPath
	 * @param array $evaluationResult
	 * @param array $explicitDimension
	 * @param \XBRL_Log $log
	 * @return array|null
	 */
	private function getExplicitDimensionAspectValue( $source, $componentPath, $evaluationResult, $explicitDimension, $log )
	{
		$result = $this->getComponentForPath( $source, $componentPath, 'explicitMember', $evaluationResult, $explicitDimension, $log, $contextRef );
		$context = Dimension::contextToIndex( $result, true, $this, $explicitDimension );

		if ( ! $this->explicitDimensionRule )
		{
			return $context;
		}

		$this->context = $context;
		$this->contextRef = $contextRef;

		foreach ( $this->explicitDimensionRule as $explicitDimensionRule )
		{
			/**
			 * @var ExplicitDimension $explicit
			 */
			$explicit = ExplicitDimension::fromArray( $explicitDimensionRule );
			$dimensionClark = "{{$explicit->dimension['namespace']}}{$explicit->dimension['name']}";
			if ( ! empty( $explicitDimension ) )
				if ( $dimensionClark != $explicitDimension ) continue;
			$explicit->aspectDimension = $explicitDimension;
			$this->context = $explicit->getValue( $this, $evaluationResult, $log );
		}

		$explicitDimensions = $this->context;
		unset( $this->context );
		unset( $this->contextRef );

		return $explicitDimensions;
	}

	/**
	 * Get the typed diemsion to be used for this fact.  If an typed dimension rule does not exist, use the formula source value
	 * @param array $source
	 * @param string $componentPath
	 * @param array $evaluationResult
	 * @param string $typedDimension
	 * @param \XBRL_Log $log
	 * @return array|null
	 */
	private function getTypedDimensionAspectValue( $source, $componentPath, $evaluationResult, $typedDimension, $log )
	{
		$result = $this->getComponentForPath( $source, $componentPath, 'typedMember', $evaluationResult, $typedDimension, $log, $contextRef );
		$context = Dimension::contextToIndex( $result, false, $this, $typedDimension );

		if ( ! $this->typedDimensionRule )
		{
			return $context;
		}

		$this->context = $context;
		$this->contextRef = $contextRef;

		foreach ( $this->typedDimensionRule as $typedDimensionRule )
		{
			/**
			 * @var TypedDimension $typed
			 */
			$typed = TypedDimension::fromArray( $typedDimensionRule );
			$dimensionClark = "{{$typed->dimension['namespace']}}{$typed->dimension['name']}";
			if ( ! empty( $typedDimension ) )
				if ( $dimensionClark != $typedDimension ) continue;
			$typed->aspectDimension = $typedDimension;
			$this->context = $typed->getValue( $this, $evaluationResult, $log );
		}

		$typedDimensions = $this->context;
		unset( $this->context );
		unset( $this->contextRef );

		return $typedDimensions;
	}

	/**
	 * Get the scenario to be used for this fact.  If a scenario rule does not exist, use the formula source value
	 * @param array $source
	 * @param string $aspect
	 * @param string $componentType
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return array|NULL
	 */
	private function getComponentAspectValue( $source, $aspect, $componentType, $evaluationResult, $log )
	{
		// Unlike other rules there is no one scenario rule. Instead is it made up of a collection of rules.
		// They must be processed in order to produce a final list of scenarios
		$rules = $componentType == 'segment' ? $this->segmentRule : $this->scenarioRule;

		if ( ! $rules )
		{
			$extra = in_array( $aspect, array( ASPECT_COMPLETE_SCENARIO, ASPECT_COMPLETE_SEGMENT ) ) ? '' : '';
			return $this->getComponentForPath( $source, ( $componentType == 'segment' ? $this->segmentsComponentPath : $this->scenariosComponentPath ), $extra, $evaluationResult, $aspect, $log, $contextRef );
		}

		$contextRef = null;
		$ruleContextRef = null;
		$context = array();

		foreach ( $rules as $rule )
		{
			// The context has to be rule source specific but cannot be replaced by the context for every rule
			$ruleContext = $this->getComponentForPath( $rule['source'], ( $componentType == 'segment' ? $this->segmentsComponentPath : $this->scenariosComponentPath ), 'member', $evaluationResult, $aspect, $log, $ruleContextRef );
			$context = is_null( $contextRef ) || $contextRef != $ruleContextRef
				? array_merge( $context, $ruleContext )
				: $context;

			if ( $contextRef != $ruleContextRef ) $contextRef = $ruleContextRef;

			if ( $rule['aspectType'] == 'occEmpty' ) // occEmtpy
			{
				$context = array();
			}
			else if ( $rule['aspectType'] == 'occFragments' ) // occFragments
			{
				/**
				 * @var OCCFragments $fragments
				 */
				$fragments = OCCFragments::fromArray( $rule );
				// This function will throw an exception if there is a problem so there is no return value to check
				$this->checkSAVConflicts( $fragments->source, $aspect, $evaluationResult, $log );
				$members = $fragments->getValue( $this, $evaluationResult, $log );
				$context = array_merge( $context, $members );
			}
			else if ( $rule['aspectType'] == 'occXpath' ) // occXpath
			{
				/**
				 * @var OCCXPath $xpath
				 */
				$xpath = OCCXPath::fromArray( $rule );
				// This function will throw an exception if there is a problem so there is no return value to check
				$this->checkSAVConflicts( $xpath->source, $aspect, $evaluationResult, $log );
				$members = $xpath->getValue( $this, $evaluationResult, $log );
				$context = array_merge( $context, $members );
			}

		}

		// BMS 2018-03-22 The call to getComponentForPath() above 'pops' the containing 'member'
		//				  element so if there are any elements in the context array wrap them
		//				  in a member element.
		if ( $context )
		{
			$context = array( 'member' => $context );
		}

		return $context;
	}

	/**
	 * Return the context for a source.  if there is a problem accessing the source fact then an exception will be thrown.
	 * @param string $source The source variable to use
	 * @param array $evaluationResult
	 * @param string $aspect The aspect identifier to use
	 * @param \XBRL_Log $log
	 * @param string $contextRef (reference)
	 * @return array the resulting context
	 */
	private function getContextForSource( $source, $evaluationResult, $aspect, $log, &$contextRef = null )
	{
		$sourceFact = $this->getSourceFact( $source, $evaluationResult, $aspect, $log );
		if ( ! $sourceFact ) return array();

		// Get the context ref from the source
		$contextRef = FactValues::getContextRef( $sourceFact );

		$context = $this->xbrlInstance->getContext( $contextRef );

		return $context;
	}

	/**
	 * Common routine to use to retreive the context section for a segment (segments and dimensions)
	 * @param string $source The source variable to use
	 * @param array $path An array where the elements represent the path
	 * @param string $extra An extra string
	 * @param array $evaluationResult
	 * @param string $aspect The aspect identifier to use
	 * @param \XBRL_Log $log
	 * @param string $contextRef (reference)
	 * @return
	 */
	public function getComponentForPath( $source, $path, $extra, $evaluationResult, $aspect, $log, &$contextRef = null )
	{
		$context = $this->getContextForSource( $source, $evaluationResult, $aspect, $log, $contextRef );

		if ( $context )
		{
			foreach ( $path as $element )
			{
				if ( ! $context ) continue;
				if ( ! isset( $context[ $element ] ) )
				{
					$context = array();
					continue;
				}
				$context = $context[ $element ];
			}

			if ( $extra )
			{
				$context = isset( $context[ $extra ] )
					? $context[ $extra ]
					: array();
			}
		}

		return $context;
	}

	/**
	 * Get the unit to be used for this fact.  If a unit rule does not exist, use the formula source value
	 * @param string $source
	 * @param array $evaluationResult
	 * @param \XBRL_Log $log
	 * @return array|null
	 */
	private function getUnitAspectValue( $source, $evaluationResult, $log )
	{
		// Get the unit from the source
		if ( is_null( $this->unitRule ) )
		{
			if ( ! $this->source )
			{
				return false;
			}

			$sourceFact = $this->getSourceFactWithErrorLogging( $source, $evaluationResult, ASPECT_UNIT, $log );
			$unitRef = FactValues::getUnitRef( $sourceFact );

			if ( ! $unitRef ) return null;

			// TODO What is in the $unit array
			$unit = $this->xbrlInstance->getUnit( $unitRef );
			return array( $unitRef => $unit );
		}
		else
		{
			/**
			 * @var Unit $unit
			 */
			$unit = Unit::fromArray( $this->unitRule );
			// This function will throw an exception if there is a problem so there is no return value to check
			$this->checkSAVConflicts( $unit->source, ASPECT_UNIT, $evaluationResult, $log );
			return $unit->getValue( $this, $evaluationResult, $log );
		}

	}

	/**
	 * Generates hashes for all the elements of the key
	 * @param $elements An array of key elements for which to create the hashes
	 * @return array An array of the elements indexed by their hashes and the overall hash
	 */
	private function hashArray( $elements )
	{
		$hashes = array();

		if ( is_array( $elements ) )
		{
			foreach ( $elements as $key => $element )
			{
				if ( is_object( $element ) )
				{
					$hash = spl_object_hash( $element ) . $key;
					$hashes[ $hash ] = $element;
				}
				else if ( is_array( $element ) )
				{
					extract( $this->hashArray( $element ) ) . $key;
					$hashes[ $hash ] = $element;
				}
				else
				{
					$hash = hash( 'sha256', $element . $key );
					$hashes[ $hash ] = $element;
				}
			}
		}
		else
		{
			$hash = hash( 'sha256', $elements );
			$hashes[ $hash ] = $elements;
		}

		return array( 'hash' => hash( 'sha256', serialize( array_keys( $hashes ) ) ), 'element_hashes' => $hashes );
	}

	/**
	 * Check to see if there are conflicts between filter definitions and aspect definitions
	 * (see http://www.xbrl.org/Specification/formula/REC-2009-06-22/formula-REC-2009-06-22.html section 2.1.2.1)
	 * Error code xbrlfe:sequenceSAVConflicts MUST be thrown if a source contains the QName of a fact variable that
	 * binds as a sequence unless the aspect rule addresses an aspect that is not covered by a filter for the fact variable
	 * @param array $source
	 * @param string $aspect
	 * @param aray $evaluationResult
	 * @param \XBRL_Log $log
	 */
	private function checkSAVConflicts( $source, $aspect, $evaluationResult, $log )
	{
		// Find out if $source refers to a variable in the evaluation result
		$qname = new QName( $source['originalPrefix'], $source['namespace'], $source['name'] );
		if ( ! isset( $evaluationResult['vars'][ $qname->clarkNotation() ] ) ) return;

		if ( ! isset( $this->variablesByQName[ $qname->clarkNotation() ] ) ) return;

		/**
		 * Get the variable
		 * @var FactVariable $variable
		 */
		$variable = $this->variablesByQName[ $qname->clarkNotation() ];
		if ( ! $variable->bindAsSequence ||
			 ! ( isset( $evaluationResult['covered'][ $qname->clarkNotation() ] ) &&
			   	 in_array( $aspect, $evaluationResult['covered'][ $qname->clarkNotation() ] )
			   )
		)
		{
			return;
		}

		// Report the error
		$log->formula_validation( "Formula", "The aspect source contains the QName of a fact variable that binds as a sequence where that fact's aspect rule covers this filtered aspect",
			array(
				'aspect' => $aspect,
				'source' => "{$qname->prefix}:{$qname->localName}",
				'formula' => $this->label,
				'error' => "xbrlfe:sequenceSAVConflicts",
			)
		);
	}
}