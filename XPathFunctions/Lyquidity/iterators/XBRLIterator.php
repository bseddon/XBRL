<?php
/**
 * XPath 2.0 for PHP
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2017 Lyquidity Solutions Limited
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

namespace XBRL\functions\lyquidity\iterators;

use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\XPath2Context;

/**
 * XBRLIterator (public)
 * Implements a base iterator for XBRL iterators so they can share common functions where they exist
 */
class XBRLIterator extends XPath2NodeIterator
{
	/**
	 * The context passed in to the constructor
	 * @var XPath2Context $context
	 */
	protected $context;

	/**
	 * Constructor
	 * @param XPath2Context $context
	 */
	public function __construct( $context )
	{
		parent::__construct();
		$this->context = $context;
	}
}
