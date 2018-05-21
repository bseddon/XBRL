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
 *
 */

namespace XBRL\Formulas\Resources\Filters;

 use lyquidity\XPath2\XPath2Expression;
use XBRL\Formulas\Resources\Variables\VariableSet;
use lyquidity\xml\QName;
use lyquidity\XPath2\XPath2Exception;

 /**
  * Implements the filter class for the concept relation filter
  * http://www.xbrl.org/Specification/conceptRelationFilters/REC-2011-10-24/conceptRelationFilters-REC-2011-10-24.html#sec-concept-relation-filter
  */
class ConceptRelation extends Filter
{
	// $variable, $qname and $qnameExpression are different ways to express the idea of 'source' for the concept relation
	/**
	 * A array representing the name of a fact variable
	 * @var array
	 */
	public $variable;

	/**
	 * A qname to the concept to be matched
	 * @var string
	 */
	public $qname;

	/**
	 * An XPath expression to be resolved that computes the qname of a concept to be matched
	 * @var string $qnameExpression
	 */
	public $qnameExpression;

	// $linkrole and $linkroleExpression
	/**
	 * The linkrole to match
	 * @var string $linkrole
	 */
	public $linkrole;

	/**
	 * An XPath expression to be resolved that computes the linkrole of a concept to be matched
	 * @var string $linkroleExpression
	 */
	public $linkroleExpression;

	// $linkname and $linknameExpression
	/**
	 * The linkname to match
	 * @var string $linkname
	 */
	public $linkname;

	/**
	 * An XPath expression to be resolved that computes the linkname of a concept to be matched
	 * @var string $linknameExpression
	 */
	public $linknameExpression;

	// $arcrole and $arcroleExpression
	/**
	 * The arcrole to match
	 * @var string $arcrole
	 */
	public $arcrole;

	/**
	 * An XPath expression to be resolved that computes the arcrole of a concept to be matched
	 * @var string $arcroleExpression
	 */
	public $arcroleExpression;

	// $arcname and $arcnameExpression
	/**
	 * The arcname to match
	 * @var string $arcname
	 */
	public $arcname;

	/**
	 * An XPath expression to be resolved that computes the arcname of a concept to be matched
	 * @var string $arcnameExpression
	 */
	public $arcnameExpression;

	/**
	 * Optional axis name
	 * One of child-or-self, child, descendant-or-self, descendant, parent-or-self,
	 * parent, ancestor-or-self, ancestor, sibling, sibling-or-self or sibling-or-descendant
	 * @var string $axis
	 */
	public $axis;

	/**
	 * An optional count of generations
	 * @var int $generations
	 */
	public $generations;

	/**
	 * An optional XPath expression that can be used as a test
	 * @var string
	 */
	public $test;

	/**
	 * A compiled expression of the $test value (if there is one)
	 * @var XPath2Expression $testXPath2Expression
	 */
	public $testXPath2Expression;

	/**
	 * A compiles expression of the $qnameExpression (if there is one)
	 * @var XPath2Expression $qnameXPath2Expression
	 */
	public $qnameXPath2Expression;

	/**
	 * A list of the compiled expressions
	 * @var array $expressionsList
	 */
	private $expressionsList = array();

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->expressionsList = array(
			'Arc name Expression' 	=> array( 'xpath' => 'arcnameXPath2Expression ',	'expression' => $this->arcnameExpression ),
			'Arc role Expression' 	=> array( 'xpath' => 'arcroleXPath2Expression ',	'expression' => $this->arcroleExpression ),
			'Linkname Expression'	=> array( 'xpath' => 'linknameXPath2Expression',	'expression' => $this->linknameExpression ),
			'Linkrole Expression'	=> array( 'xpath' => 'linkroleXPath2Expression',	'expression' => $this->linkroleExpression ),
			'QName Expression'		=> array( 'xpath' => 'qnameXPath2Expression',		'expression' => $this->qnameExpression ),
			'Test Expression'		=> array( 'xpath' => 'testXPath2Expression',		'expression' => $this->test ),
		);
	}

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
		$result = parent::process( $localName, $taxonomy, $roleUri, $linkbaseHref, $label, $node, $domNode, $log );

		$children = $node->children( \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_CRF ] );
		if ( ! count( $children ) )
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Concept relation Filters", "There are no child elements in the concept relation element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}

			return $result;
		}

		$namespaces = $node->getDocNamespaces(true);

		if ( property_exists( $children, "variable" ) )
		{
			/**
			 * @var QName $qName
			 */
			// If there is no prefix it should not be resolved to a default namespace
			$variable = trim( $children->variable );
			$qName = strpos( $variable, ":" )
				? qname( $variable, $namespaces )
				: new QName( "", null, $variable );

			$this->variable = array(
				'name' => is_null( $qName ) ? $source : $qName->localName,
				'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
				'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
			);
		}
		else if ( property_exists( $children, "qname" ) )
		{
			$qname = trim( $children->qname );
			if ( $qname == "xfi:root" )
			{
				$this->qname = $qname;
			}
			else
			{
				$qname = qname( $qname, $namespaces );
				$this->qname = is_null( $qname ) ? null : $qname->clarkNotation();
			}
		}
		else if ( property_exists( $children, "qnameExpression" ) )
		{
			$this->qnameExpression = trim( $children->qnameExpression );
		}
		else
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Concept relation Filters", "No variable, qname or qnameExpression element in the Concept relation filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}

		if ( property_exists( $children, "linkrole" ) )
		{
			$this->linkrole = trim( $children->linkrole );
		}
		else if ( property_exists( $children, "linkroleExpression" ) )
		{
			$this->linkroleExpression = trim( $children->linkroleExpression );
		}
		else
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Concept relation Filters", "No linkrole or linkroleExpression element in the Concept relation filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}

		if ( property_exists( $children, "linkname" ) )
		{
			$this->linkname = trim( $children->linkname );
		}
		else if ( property_exists( $children, "linknameExpression" ) )
		{
			$this->linkroleExpression = trim( $children->linknameExpression );
		}


		if ( property_exists( $children, "arcrole" ) )
		{
			$this->arcrole = trim( $children->arcrole );
		}
		else if ( property_exists( $children, "arcroleExpression" ) )
		{
			$this->arcroleExpression = trim( $children->arcroleExpression );
		}
		else
		{
			if ( \XBRL::isValidating() )
			{
				$log->formula_validation( "Concept relation Filters", "No linkrole or linkroleExpression element in the Concept relation filter element", array(
					'roleuri' => $roleUri,
					'label' => $label,
					'localname' => $localName,
				) );
			}
		}

		if ( property_exists( $children, "arcname" ) )
		{
			$this->arcname = trim( $children->arcname );
		}
		else if ( property_exists( $children, "arcnameExpression" ) )
		{
			$this->arcnameExpression = trim( $children->arcnameExpression );
		}

		if ( property_exists( $children, "axis" ) )
		{
			$this->axis = trim( $children->axis );
		}

		if ( property_exists( $children, "generations" ) )
		{
			$this->generations = trim( $children->generations );
		}

		$attributes = $node->attributes();

		if ( property_exists( $attributes, "test" ) )
		{
			$this->test = trim( $attributes->test );
		}

		$result = array_merge( $result,
			array(
				'arcname'				=> $this->arcname,
				'arcnameExpression' 	=> $this->arcnameExpression,
				'arcrole'				=> $this->arcrole,
				'arcroleExpression' 	=> $this->arcroleExpression,
				'axis'					=> $this->axis,
				'generations'			=> $this->generations,
				'linkname'				=> $this->linkname,
				'linknameExpression'	=> $this->linknameExpression,
				'linkrole'				=> $this->linkrole,
				'linkroleExpression'	=> $this->linkroleExpression,
				'qname'					=> $this->qname,
				'qnameExpression'		=> $this->qnameExpression,
				'test'					=> $this->test,
				'variable'				=> $this->variable,
			)
		);

		$result = parent::storeFilter( $result, $localName );

		return $result;
	}

	/**
	 * Converts a node to an XPath query
	 *
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return string
	 */
	public function toQuery( $variableSet, $factVariableBinding )
	{
		/**
		 * These are the arguments to the xfi:concept-relationships function
	     * #source
		 * #linkrole
		 * #arcrole
		 * #axis
		 * #generations
		 * #linkname
		 * #arcname
		 */

		$source = $this->variable
					? ( "fn:node-name(" . ( $this->variable['originalPrefix'] ? "\${$this->variable['originalPrefix']}:$this->variable['name']" : "\${$this->variable['name']}" ) . ")" )
					: ( $this->qnameExpression
							? $this->qnameExpression
							: "QName('" . qname($this->qname, $variableSet->nsMgr->getNamespaces())->namespaceURI . "', '" . qname($this->qname, $variableSet->nsMgr->getNamespaces())->localName . "')"
					  );

		$linkrole = $this->linkrole
					? "'{$this->linkrole}'"
					: ( $this->linkroleExpression
							? $this->linkroleExpression
							: "()"
					  );

		$arcrole = $this->arcrole
					? "'{$this->arcrole}'"
					: ( $this->arcroleExpression
							? $this->arcroleExpression
							: "()"
					  );

		$axis = "{$this->axis}";
		$generations = $this->generations ? $this->generations : "()";

		$linkname = $this->linkname
					? "'{$this->linkname}'"
					: ( $this->linknameExpression
							? $this->linknameExpression
							: "()"
					  );

		$arcname = $this->arcname
					? "'{$this->arcname}'"
					: ( $this->arcnameExpression
							? $this->arcnameExpression
							: "()"
					  );

		$test = $this->test
					? $this->test
					: "true()";

		// Alternative line end goes here (for query formatting)
		$le = "";

		$query = "( $le" .
				 "	( $le" .
				 "		some \$relationship in $le" .
				 "			xfi:concept-relationships( $le" .
				 "				$source, $le" .
				 "				$linkrole, $le" .
				 "				$arcrole, $le" .
				 "				" . str_replace( "-or-self", "", "'$axis'" ) . ",$le" .
		//		 "				fn:replace($axis,'-or-self',''), $le" .
				 "				$generations, $le" .
				 "				$linkname, $le" .
				 "				$arcname $le" .
				 "			) $le" .
				 "		satisfies $le" .
				 "			( $le" .
				 "				\$relationship[$test] and $le" .
				 "				(fn:node-name(.) eq $le" .
             	 "					( $le" .
             	 "						if (matches('$axis','ancestor|parent')) $le" .
              	 "						then xfi:relationship-from-concept(\$relationship) $le" .
              	 "						else xfi:relationship-to-concept(\$relationship) $le" .
              	 "					) $le" .
              	 "				) $le" .
              	 "			) $le" .
              	 "  ) $le";

		$clause1 = "" .
				 "	or $le" .
				 "	( $le" .
		//		 "		fn:starts-with($axis,'sibling') and $le" .
				 "		fn:empty( $le" .
				 "			xfi:concept-relationships( $source, $linkrole, $arcrole, 'parent', 1, $linkname, $arcname) $le" .
				 "		) and $le" .
				 "		fn:empty( $le" .
				 "			xfi:concept-relationships( fn:node-name(.), $linkrole, $arcrole, 'parent', 1, $linkname, $arcname) $le" .
				 "		) and $le" .
				 "		fn:exists( $le" .
				 "			xfi:concept-relationships( fn:node-name(.), $linkrole, $arcrole, 'child', 1, $linkname, $arcname) $le" .
				 "		) and $le" .
				 "		(fn:node-name(.) ne $source) $le" .
				 "	) $le";

		$clause2 = "" .
				 "	or $le" .
				 "	( $le" .
		//		 "		fn:ends-with($axis,'-or-self') $le" .
		//		 " 		and $le" .
		//		 "		( $le" .
				 "			if ( QName('http://www.xbrl.org/2008/function/instance','root') eq $source) $le" .
				 "			then $le" .
				 "			( $le" .
				 "				fn:empty( $le" .
				 "					xfi:concept-relationships( fn:node-name(.), $linkrole, $arcrole, 'parent', 1, $linkname, $arcname) $le" .
				 "				) and $le" .
				 "				fn:exists( $le" .
				 "					xfi:concept-relationships( fn:node-name(.), $linkrole, $arcrole, 'child', 1, $linkname, $arcname) $le" .
				 "				) $le" .
				 "			) $le" .
				 "			else $le" .
				 "			( $le" .
				 "				fn:node-name(.) eq $source $le" .
				 "			) $le" .
		//		 "		) $le" .
				 "  ) $le";

		$query = $query . ( \XBRL::startsWith( $axis, 'sibling' ) ? $clause1 : "" ) . ( \XBRL::endsWith( $axis, '-or-self' ) ? $clause2 : "" );
		$query .= ") $le";
		if ( empty( $le ) )
		{
			$query = preg_replace( "/\s\t+/", " ", $query );
		}
		echo $query;
		return $query;
	}

	/**
	 * Returns the set of aspects covered by this instance
	 * @param VariableSet $variableSet
	 * @param FactVariableBinding $factVariableBinding
	 * @return an array of aspect identifiers
	 */
	public function getAspectsCovered( $variableSet, $factVariableBinding )
	{
		return array( ASPECT_CONCEPT );
	}

	/**
	 * Check the select and as
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::validate()
	 * @param VariableSet $variableSet
	 * @param XmlNamespaceManager $nsMgr
	 */
	public function validate( $variableSet, $nsMgr )
	{
		$testExpression = function( $expressionName, $expression )
		{
			$xpath2Expression = null;

			try
			{
				$xpath2Expression = XPath2Expression::Compile( $expression, $nsMgr );
				if ( parent::checkForCoverXFIFunctionUse( $this->qnameExpression, $xpath2Expression ) )
				{
					return false;
				}
			}
			catch ( Exception $ex )
			{
				\XBRL_Log::getInstance()->formula_validation( "Concept relation filter", "Failed to compile qname expression",
					array(
						'expression name' => $expressionName,
						'qname expression' => $expression,
						'error' => $ex instanceof XPath2Exception ? $ex->ErrorCode : get_class( $ex ),
						'reason' => $ex->getMessage()
					)
				);

				return false;
			}

			return $xpath2Expression;
		};

		foreach ( $this->expressionsList as $expressionName => $details )
		{
			if ( ! $details['expression'] ) continue;
			$xpath2Expression = $testExpression( $expressionName, $details['expression'] );
			if ( ! $xpath2Expression ) continue;
			$this->${$details['expression']} = $xpath2Expression;
		}

		return true;
	}

	/**
	 * Return any parameter references in the select statement (if there is one)
	 * {@inheritDoc}
	 * @see \XBRL\Formulas\Resources\Resource::getVariableRefs()
	 */
	public function getVariableRefs()
	{
		$variableRefs = array();

		if ( $this->variable )
		{
			$variableRefs[] = new QName(
				$this->variable['originalPrefix'],
				$this->variable['namespace'],
				$this->variable['name']
			);
		}

		foreach ( $this->expressionsList as $expressionName => $details )
		{
			if ( ! $details['expression'] ) continue;
			$variableRefs = array_merge( $variableRefs, $this->${$details['xpath']}->getParameterQNames() );
		}

		return $variableRefs;
	}

}
