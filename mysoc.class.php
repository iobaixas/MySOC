<?php
/**
 * MYSOC - MySQL Object Collection
 * 
 * A bunch of classes that provides an OOP way of building MySQL queries,
 * the main objective is providing a COMPLETE set of fully chainable instructions,
 * specific to MySQL. 
 *
 * @copyright Ignacio Baixas 2012
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
 * @author Ignacio Baixas <iobaixas@gmail.com>
 * @version 0.3
 *
 */

// TODO: Put common definitions in mysoc.common.inc.php and all
// where related stuff in mysoc.where.inc.php.

// TODO: Add debugging options.

class MYSOC_Exception extends Exception { }
class MYSOC_NotAvaliable extends MYSOC_Exception {}

/**
 * Base MYSOC class, provides a common ground for all operators.
 */
class MYSOC_Operator
{
	protected $parent; 
	
	function __construct($_parent)
	{
		$this->parent = $_parent;
	}
	
	/**
	 * Gets this operator parent, usefull when chaining.
	 * 
	 * @return MYSOC_Operator|mixed parent operator.
	 */
	function end()
	{
		return $this->parent;
	}
	
	/**
	 * Renders this operator.
	 * 
	 * @param _args
	 */
	function render(&$_args)
	{
		return '';
	}
}

class MYSOC_TableRef extends MYSOC_Operator
{	
	private $table;
	private $join = NULL;
	
	function __construct($_parent, $_table='DUAL')
	{
		parent::__construct($_parent);
		$this->table = $_table;
	}
	
	function render(&$_args)
	{
		return $table;
	}
	
	function join() 
	{	
		// TODO
	}
	
	function left_join($_table, $_on) 
	{
		// TODO
	}	
}

class MYSOC_Update extends MYSOC_TableRef
{
	private $head = 'UPDATE ';
	private $parts = array();
	private $where = NULL;
	
	function render(&$_args)
	{
		$expl = array();
		foreach($this->parts as $part) {
			$expl[] = $part[1];
			if($part[2] === FALSE) continue;
			if(is_array($part[2])) $_args = array_merge($_args, $part[2]);
			else $_args[] = $part[2];
		}
		
		$sql = $this->head.parent::render($_args).' SET '.implode(',',$expl);
		if(isset($this->where)) $sql .= ' ' . $this->where->render($_args);
		return $sql;
	}
	
	function low_priority()
	{
		if(strpos($head,'LOW_PRIORITY') === FALSE) $head .= 'LOW_PRIORITY ';
		return $this;
	}
	
	function ignore()
	{
		if(strpos($head,'IGNORE') === FALSE) $head .= 'IGNORE ';
		return $this;
	}
	
	function set($_sexpr, $_args=FALSE)
	{
		$this->parts[] = array($_sexpr, $_args);
		return $this;
	}
	
	function where()
	{
		return $this->where = new MYSOC_Where($this);
	}
}

/**
 * Provides WHERE's operator boolean logic.
 */
class MYSOC_Boolean extends MYSOC_Operator
{
	const PART_SIMPLE = 0;
	const PART_COMP = 1;
	
	private $logic;
	private $parts = array();
	private $nested = FALSE;
	
	function __construct($_parent, $_logic='AND')
	{
		parent::__construct($_parent);
		$this->logic = " $_logic ";
	}
	
	/**
	 * (non-PHPdoc)
	 * @see MYSOC_Operator::render()
	 */
	function render(&$_args)
	{
		$expl = array();
		foreach($this->parts as $part) {
			if($part[0] == self::PART_SIMPLE) {
				$expl[] = $part[1];
				if($part[2] === FALSE) continue;
				if(is_array($part[2])) $_args = array_merge($_args, $part[2]);
				else $_args[] = $part[2];
			} else $expl[] = $part[1]->render( $_args);
		}
		
		if($this->nested) return '(' . implode($this->logic,$expl) . ')';
		else return implode($this->logic,$expl);
	}
	
	/**
	 * Adds a simple condition to the statement chain.
	 * 
	 * @param string $_wexpr where expression.
	 * @param mixed|array $_args OPTIONAL sql arguments (will be escaped).
	 * @return MYSOC_Boolean myself
	 */
	function is($_wexpr, $_args=FALSE)
	{
		$this->parts[] = array(self::PART_SIMPLE, $_wexpr, $_args);
		return $this;
	}
	
	/**
	 * Shorcut used to add an IN condition to the chain using constant values.
	 * 
	 * @param string $_name Column name (not escaped)
	 * @param array $_values IN values.
	 * @return MYSOC_Boolean myself
	 */
	function in($_name, $_values)
	{
		$repeat = count($_values);
		if($repeat > 0) {
			$sql = "$_name IN (" . str_repeat('?,',$repeat-1) . "?)";
			$this->parts[] = array(self::PART_SIMPLE, $_sql, $_values);
		}
		return $this;
	}

	/** 
	 * Adds a nested OR boolean operator to the statement chain.
	 * 
	 * @return MYSOC_Boolean New operator.
	 */
	function where_or() { return $this->_expand('OR'); }
	
	/**
	 * Adds a nested AND boolean operator to the statement chain.
	 *
	 * @return MYSOC_Boolean New operator.
	 */
	function where_and() { return $this->_expand('AND'); }
	
	// Auxiliary method used by where_xx methods.
	private function _expand($_logic)
	{
		$where = new MYSOC_Boolean($this, $_logic);
		$where->nested = TRUE;
		$this->parts[] = array(self::PART_COMP, $where);
		return $where;
	}
}

/**
 * Represents a mysql WHERE statement.
 *
 */
class MYSOC_Where extends MYSOC_Boolean
{
	private $group = NULL; 	// Group by statement
	private $order = NULL; 	// Order by statement
	private $limit = NULL; 	// Limit options
	private $tail = '';		// Statement tail
		
	/**
	 * (non-PHPdoc)
	 * @see MYSOC_Boolean::render()
	 */
	function render(&$_args)
	{
		$sql = 'WHERE ' . parent::render($_args);
		if($this->group) $sql .= ' ' . $this->group->render($_args);
		if($this->order) $sql .= ' ' . $this->order->render($_args);
		if($this->limit) {
			$sql .= ' LIMIT ?,?';
			array_push($_args, $this->limit[0], $this->limit[1]);
		}
		return $sql . $this->tail;
	}
	
	/**
	 * Adds a group by statement, if called multiple times the last statement is
	 * used.
	 * 
	 * @return MYSOC_GroupBy Group by operator.
	 */
	function group_by()
	{
		return $this->group = new MYSOC_GroupBy($this);
	}
	
	/**
	 * Adds am order by statement, if called multiple times the last statement is
	 * used.
	 * 
	 * @return MYSOC_OrderBy Order by operator.
	 */
	function order_by()
	{
		return $this->order = new MYSOC_OrderBy($this);
	}
	
	/**
	 * Sets the limit options for this statement.
	 * 
	 * @param int $_count Maximum number of rows to return.
	 * @param int $_offset OPTIONAL Row offset
	 * @return MYSOC_Where myself
	 */
	function limit($_count, $_offset=0)
	{
		$this->limit = array($_offset, $_count);
		return $this;
	}
	
	/**
	 * Sets an exclusive lock on read columns. This kind of lock waits for the 
	 * latest data and is released on transaction end.
	 * 
	 * @return MYSOC_Where myself
	 */
	function for_update()
	{
		$this->tail = ' FOR UPDATE';
		return $this;
	}
	
	/**
	 * Sets a shared mode lock (others can read but not modify). This kind of 
	 * lock waits for the latest data and is released on transaction end.
	 * 
	 * @return MYSOC_Where myself
	 */
	function lock_in_share_mode()
	{
		$this->tail = ' LOCK IN SHARE MODE';
		return $this;
	}
}

/**
 * Represents a GROUP BY statement.
 */
class MYSOC_GroupBy extends MYSOC_Operator
{
	// TODO
}

/**
 * Represents an ORDER BY statement.
 */
class MYSOC_OrderBy extends MYSOC_Operator
{
	private $parts = array();
		
	/**
	 * (non-PHPdoc)
	 * @see MYSOC_Operator::render()
	 */
	function render(&$_args)
	{
		return 'ORDER BY ' . implode(',',$this->parts);
	}
	
	/**
	 * Adds a DESC order 
	 * Enter description here ...
	 * @param unknown_type $_name
	 */
	function desc($_name) { $this->parts[] = "$_name DESC"; }	
	function asc($_name) { $this->parts[] = "$_name ASC"; }
}
