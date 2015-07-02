<?php

	/**
	 * @todo: Remove interface check once development environment can support Countable interface.
	 */
	if (!interface_exists('Countable', false)) {
		interface Countable {
			public function count();
		}
	}
	
	/**
	 * Iterator object that represents a result set from a C1ModelBase::fetch_by_criteria call.
	 * Mainly acts as a wrapper around an C1SQLIterator.  Allows looping through the objects
	 * in the result set as well as additional features such as getting filtering options
	 * and pagination details within the result set.
	 */
	class C1ResultSet implements Iterator, Countable, ArrayAccess {
		
		// Configuration settings
		private $_model;
		private $_criteria;
		private $_page;
		private $_per_page;
		private $_order_by;
		
		// Runtime settings
		private $_base = false;
		private $_count = false;
		private $_total = false;
		private $_pages = false;
		private $_iterator = false;
		private $_key = false;
		private $_callback = false;
		
		/**
		 * Creates a new record set.
		 * @param	object C1ModelBase $model The model that should generate the queries
		 * @param	array $criteria The criteria that the results should be generated from
		 * @param	integer $page The page that should be displayed
		 * @param	integer $per_page The number of records to display per page
		 */
		public function __construct(&$model, $criteria, $page = 1, $per_page = false, $order_by = false) {
			$this->_model = $model;
			$this->_criteria = $criteria;
			$this->_page = 0 + $page;
			$this->_per_page = 0 + $per_page;
			$this->_order_by = $order_by;
		}
		
		public function __get($var) {
			$name = '_' . $var;
			switch ($var) {
				case 'criteria':
				case 'page':
				case 'per_page':
					return $this->$name;
				case 'count':
					return $this->count();
				case 'pages':
				case 'page_count':
					if ($this->_pages === false)
						$this->_init(true);
					return $this->_pages;
				case 'start':
					if ($this->_pages === false)
						$this->_init(true);
					if ($this->__get('total') == 0)
						return 0;
					return $this->_per_page ? ((($this->_page - 1) * $this->_per_page) + 1) : 1;
				case 'end':
					if ($this->__get('per_page') == false)
						return $this->__get('total');
					return min($this->__get('total'), $this->__get('start') + $this->__get('per_page') - 1);
				case 'total':
				case 'record_count':
					if ($this->_total === false)
						$this->_init(true);
					return $this->_total;
				case 'object':
					if ($this->_iterator === false)
						$this->_init();
					return $this->_iterator;
				case 'array':
				case 'rows':
					return $this->rows();
				default:
					throw new C1ParameterException('"' . $var . '" is not a gettable property of C1ResultSet');
			}
		}
		
		public function __set($var, $value) {
			switch ($var) {
				case 'callback':
					$this->_callback = $value;
					return;
			}
			throw new C1ParameterException('"' . $var . '" is not a settable property of C1ResultSet');
		}
		
		/**
		 * Initialises the object on demand to save processing time.
		 * @param	boolean $detailed True to determine extra information for pagination; false to just create the iterator
		 */
		private function _init($detailed = false) {
			global $db;
			
			// If we don't have the sql base
			// Ask the model base to return the query details
			if ($this->_base === false)
				$this->_base = $this->_model->build_sql_by_criteria($this->_criteria, $this->_page, $this->_per_page, $this->_order_by);
				
			// Combine the sql query
			$sql = ' FROM `' . $this->_base['table'] . '`' .
				($this->_base['join_sql'] ? (' ' . implode(' ', $this->_base['join_sql'])) : '') .
				($this->_base['where_sql'] ? (' WHERE ' . implode(' AND ', $this->_base['where_sql'])) : '') .
				(array_safe($this->_base, 'group_by', false) ? (' GROUP BY ' . $this->_base['group_by']) : '');
			$params = array();
			foreach ($this->_base['join_params'] as $p)
				$params = array_merge($params, $p);
			$params = array_merge($params, $this->_base['where_params']);
			
			// Determine the total records and pages
			if ($detailed && $this->_total === false) {
				$total_sql = 'SELECT COUNT(' . (array_safe($this->_base, 'distinct', false) ? ('DISTINCT ' . $this->_base['table'] . '.id') : '*') . ')' . $sql;
				if (array_safe($this->_base, 'distinct', false))
					$total_sql = preg_replace('/' . preg_quote(' GROUP BY ' . $this->_base['table'] . '.id') . '$/', '', $total_sql);
				$this->_total = 0 + $db->getone($total_sql, $params);
				$this->_pages = $this->_per_page ? ceil($this->_total / $this->_per_page) : 1;
				if ($this->_page > $this->_pages)
					$this->_page = $this->_pages;
			}
			
			// If there is already an iterator do not proceed
			if ($this->_iterator !== false)
				return;
			
			// Ensure the page is valid and determine where we start in the results
			if ($this->_page < 1)
				$this->_page = 1;
			$from = $this->_per_page ? (($this->_page - 1) * $this->_per_page) : false;
			
			// Finalise the sql
			$sql = 'SELECT ' .
				(array_safe($this->_base, 'distinct', false) ? 'DISTINCT ' : '') .
				trim(
					(array_safe($this->_base, 'select_only', false) ? '' : ('`' . $this->_base['table'] . '`.* ')) .
					', ' . implode(', ', $this->_base['select_sql']),
				', ') .
				$sql .
				($this->_base['order_by'] ? (' ORDER BY  ' . $this->_base['order_by']) : '') .
				($from !== false ? (' LIMIT ' . $from . ', ' . $this->_per_page) : '');
			$params = array_merge($this->_base['select_params'], $params);
			
			// Create the iterator
			$this->_iterator = new C1SQLIterator($sql, $params, $this->_callback ? $this->_callback : array($this->_model, 'build_by_id'));
		}
		
		/**
		 * Returns the number of results in this set.
		 * @return	integer The number of results
		 */
		public function count() {
			global $db;
			if ($this->_iterator === false)
				$this->_init(true);
			if ($this->_count === false)
				$this->_count = $this->_iterator->count();
			return $this->_count;
		}
		
		/**
		 * Returns the current value in the iterator.
		 */
		public function current() {
			if ($this->_iterator === false)
				$this->_init();
			return $this->_iterator->current();
		}
		
		/**
		 * Returns the current key in the iterator.
		 */
		public function key() {
			return $this->_iterator->key();
		}
		
		/**
		 * Goes back to the start of the result set.
		 */
		public function rewind() {
			if ($this->_iterator === false)
				$this->_init();
			return $this->_iterator->rewind();
		}
		
		/**
		 * Changes the start position.
		 * @param	integer $start The start position (0 for first record)
		 */
		public function start($start) {
			if ($this->_iterator === false)
				$this->_init();
			return $this->_iterator->start($start);
		}
		
		/**
		 * Returns the options for a particular property of the provided class
		 * within the given result set.
		 * @param	string $col_names The format of the options to return
		 * @param	boolean $add_blank True to add a blank entry; false to not have a blank entry;
		 * 			anything else to have the blank entry have this display value
		 * @return	array The filter options
		 * @todo	Get it working
		 */
		public function fetch_options($output, $add_blank = false, $value = false) {
			
			// Determine the base criteria
			$this->_init();
			
			// Ask the model to give us the options
			return $this->_model->build_options($output, $add_blank, $value, false, $this->_criteria);
		}
		
		/**
		 * Moves to the next record in the set.
		 */
		public function next() {
			return $this->_iterator->next();
		}
		
		/**
		 * Indicates if the key is valid.
		 */
		public function valid() {
			return $this->_iterator->valid();
		}
		
		/**
		 * Indicates if an offset exists.
		 * @param	mixed $offset The offset to check
		 * @return	boolean True if the offset exists; false otherwise
		 */
		public function offsetExists($offset) {
			try {
				$this->__get($offset);
				return true;
			} catch (C1ParameterException $e) {
				return $this->_iterator->offsetExists($offset);
			}
		}
		
		/**
		 * Returns the value of a specific key.
		 * @param	mixed $offset The offset to get
		 * @return	mixed The value of the offset
		 */
		public function offsetGet($offset) {
			try {
				return $this->__get($offset);
			} catch (C1ParameterException $e) {
				return $this->_iterator->offsetGet($offset);
			}
		}
		
		/**
		 * Changes the value of a particular key.
		 * @param	mixed $offset The offset to set
		 * @throws	C1ParameterException The values can not be updated
		 */
		public function offsetSet($offset, $value) {
			throw new C1ParameterException('Unable to set values in a C1ResultSet.');
		}
		
		/**
		 * Removes a particular key.
		 * @param	mixed $offset The offset to unset
		 */
		public function offsetUnset($offset) {
			throw new C1ParameterException('Unable to unset values in a C1ResultSet.');
		}
		
		/**
		 * Returns the raw row data.
		 * @return	C1SQLIterator The raw row data
		 */
		public function rows() {
			if ($this->_iterator === false)
				$this->_init();
			$iterator = clone $this->_iterator;
			$iterator->callback = false;
			if (array_safe(array_safe($this->_criteria, 'select', array()), 'parse', false) === true)
				$iterator->callback = array($this, '_parse_row');
			return $iterator;
		}
		
		/**
		 * Converts the results to an array.
		 * @return	array The results as an array
		 */
		public function to_array() {
			$this->_init();
			return $this->_iterator->to_array();
		}
		
		/**
		 * Converts the values within a raw row based on the field definitions in the model.
		 * @param	array $row The row to convert
		 * @return	array The converted row
		 */
		public function _parse_row($row) {
			foreach ($row as $key => $value) {
				$field = $this->_model->get_field($key);
				if ($field)
					$row[$key] = $field->load($value);
			}
			return $row;
		}
		
	}
	
	
	/**
	 * Builds a custom result set.
	 */
	class C1SQLResultSet implements Iterator, Countable, ArrayAccess {
		
		private $_iterator, $_page, $_per_page;
		private $_page_count = false, $_total = false, $_start = false;
		
		/**
		 * Creates a new custom result set.
		 * @param	object C1SQLIterator The SQL iterator that returns all records
		 * @param	integer $page The page being displayed
		 * @param	integer $per_page The number of records per page
		 */
		public function __construct(C1SQLIterator $iterator, $page = 1, $per_page = false) {
			$this->_iterator = $iterator;
			$this->_page = 0 + $page;
			$this->_per_page = 0 + $per_page;
		}
		
		public function __get($var) {
			$name = '_' . $var;
			$this->_init();
			switch ($var) {
				case 'page':
				case 'per_page':
				case 'page_count':
					return $this->$name;
				case 'total':
				case 'record_count':
					return $this->_total;
				case 'start':
					if ($this->_start !== false)
						return $this->_start;
					return $this->_per_page ? ((($this->_page - 1) * $this->_per_page) + 1) : 1;
				case 'count':
					return $this->count();
				case 'object':
					return $this->_iterator;
				case 'array':
				case 'rows':
					$iterator = clone $this->_iterator;
					$iterator->callback = false;
					return $iterator;
				default:
					throw new C1ParameterException('"' . $var . '" is not a gettable property of C1SQLResultSet');
			}
		}
		
		public function __set($var, $value) {
			switch ($var) {
				case 'start':
					$this->_start = $value;
					return;
			}
			throw new C1ParameterException('"' . $var . '" is not a settable property of C1SQLResultSet');
		}
		
		/**
		 * Initializes the SQL iterator and automatically determines the number of pages.
		 */
		private function _init() {
			global $db;
			
			// If already initialized, then bail
			if ($this->_page_count !== false)
				return;
			
			// Strip out the current select and order by clauses
			// from the iterator so we can figure out the total count quickly
			$sql = $this->_iterator->sql;
			$params = $this->_iterator->params;
			$count = 'COUNT(*)';
			if (preg_match('/^SELECT DISTINCT ([^, ]+)/i', $sql, $matches))
				$count = 'COUNT(DISTINCT ' . $matches[1] . ')';
			$sql = preg_replace('/^SELECT (.+) FROM /i', 'SELECT ' . $count . ' FROM ', $sql);
			$sql = preg_replace('/ ORDER BY .+/', '', $sql);
			
			// Determine the total records and number of pages
			$this->_total = $db->getone($sql, $params);
			$this->_page_count = $this->_per_page ? ceil($this->_total / $this->_per_page) : 1;
			if ($this->_page > $this->_page_count)
				$this->_page = $this->_page_count;
			if ($this->_page < 1)
				$this->_page = 1;
				
			// Add the LIMIT statement to the iterator
			if ($this->_per_page)
				$sql = $this->_iterator->sql . ' LIMIT ' . ($this->__get('start') - 1) . ', ' . $this->_per_page;
			elseif ($this->_start !== false)
				$sql = $this->_iterator->sql . ' LIMIT ' . ($this->__get('start') - 1) . ', 999999999';
			else
				$sql = $this->_iterator->sql;
			$this->_iterator->sql = $sql;
			
		}
		
		/**
		 * Returns the number of results in this set.
		 * @return	integer The number of results
		 */
		public function count() {
			$this->_init();
			return $this->_total;
		}
		
		/**
		 * Returns the current value in the iterator.
		 */
		public function current() {
			$this->_init();
			return $this->_iterator->current();
		}
		
		/**
		 * Returns the current key in the iterator.
		 */
		public function key() {
			$this->_init();
			return $this->_iterator->key();
		}
		
		/**
		 * Goes back to the start of the result set.
		 */
		public function rewind() {
			$this->_init();
			return $this->_iterator->rewind();
		}
		
		/**
		 * Moves to the next record in the set.
		 */
		public function next() {
			$this->_init();
			return $this->_iterator->next();
		}
		
		/**
		 * Sets the starting position.
		 * @param	integer $position The starting position (0 for first)
		 */
		public function start($position) {
			$this->__set('start', $position);
		}
		
		/**
		 * Indicates if the key is valid.
		 */
		public function valid() {
			$this->_init();
			return $this->_iterator->valid();
		}
		
		/**
		 * Indicates if an offset exists.
		 * @param	mixed $offset The offset to check
		 * @return	boolean True if the offset exists; false otherwise
		 */
		public function offsetExists($offset) {
			$this->_init();
			try {
				$this->__get($offset);
				return true;
			} catch (C1ParameterException $e) {
				return $this->_iterator->offsetExists($offset);
			}
		}
		
		/**
		 * Returns the value of a specific key.
		 * @param	mixed $offset The offset to get
		 * @return	mixed The value of the offset
		 */
		public function offsetGet($offset) {
			$this->_init();
			try {
				return $this->__get($offset);
			} catch (C1ParameterException $e) {
				return $this->_iterator->offsetGet($offset);
			}
		}
		
		/**
		 * Changes the value of a particular key.
		 * @param	mixed $offset The offset to set
		 * @throws	C1ParameterException The values can not be updated
		 */
		public function offsetSet($offset, $value) {
			throw new C1ParameterException('Unable to set values in a C1SQLResultSet.');
		}
		
		/**
		 * Removes a particular key.
		 * @param	mixed $offset The offset to unset
		 */
		public function offsetUnset($offset) {
			throw new C1ParameterException('Unable to unset values in a C1SQLResultSet.');
		}
		
		/**
		 * Converts the results to an array.
		 * @return	array The results as an array
		 */
		public function to_array() {
			$this->_init();
			return $this->_iterator->to_array();
		}
		
	}
	
	
	/**
	 * Allows you to traverse multiple iterators as if they were one.
	 */
	class C1IteratorGroup implements Iterator, Countable {
		
		// Configuration variables
		private $_iterators = array();
		private $_iterator = null;
		private $_count = false;
		private $_current = null;
		private $_start = 0;
		
		/**
		 * Creates the new iterator.
		 * @param	array $iterators The iterators to loop through
		 */
		public function __construct($iterators) {
			$this->_iterators = $iterators;
		}
		
		/**
		 * Returns the number of results in this set.
		 * @return	integer The number of results
		 */
		public function count() {
			if ($this->_count === false) {
				$this->_count = 0;
				$keys = array_keys($this->_iterators);
				foreach ($keys as $id)
					$this->_count += $this->_iterators[$id]->count();
			}
			return $this->_count;
		}
		
		/**
		 * Returns the current value in the iterator.
		 */
		public function current() {
			if ($this->_current === null)
				$this->_current = current($this->_iterators);
			$val = $this->_current !== false ? $this->_current->current() : false;
			if ($val === false)
				$val = $this->next();
			return $val;
		}
		
		/**
		 * Returns the current key in the iterator.
		 */
		public function key() {
			return $this->_current->key();
		}
		
		/**
		 * Goes back to the start of the result set.
		 */
		public function rewind() {
			$this->_current = null;
			reset($this->_iterators);
			
			// If there is a starting position
			if ($this->_start > 0) {
				$position = $this->_start;
				$iterator = current($this->_iterators);
				while ($iterator) {
					$count = $iterator->count();
					if ($position < $count) {
						$iterator->start($position);
						break;
					}
					$position -= $count;
					$iterator = next($this->_iterators);
				}
			}
			
		}
		
		/**
		 * Changes the starting position.
		 * @param	integer $position The new starting position (0 for first)
		 */
		public function start($position) {
			if ($position == 0)
				return;
			$this->_start = $position;
		}
		
		/**
		 * Moves to the next record in the set.
		 */
		public function next() {
			
			// If we haven't started looping through the iterators array
			// initialise it
			if ($this->_current === null)
				$this->current();
				
			// If there are no more iterators then it will always return false
			if ($this->_current === false)
				return false;

			// Get the next item from the current iterator
			$next = $this->_current->next();
				
			// If there are no more items in the current iterator,
			// move to the next iterator
			if ($next === false) {
				while ($this->_current !== false) {
					$this->_current = next($this->_iterators);
					if ($this->_current !== false) {
						$this->_current->rewind();
						$next = $this->_current->current();
						if ($next !== false)
							return $next;
					}
				}
			}
			
			return $next;
		}
		
		/**
		 * Indicates if the key is valid.
		 */
		public function valid() {
			return $this->_current !== false && ($this->_current === null || $this->_current->valid());
		}
		
	}
	
	
	/**
	 * Iterator object that when given an array will use the specified callback
	 * to convert the array value.
	 *
	 * The main use of this class is to loop through an array of ids that are
	 * converted into objects.
	 *
	 * NOTE: array_* functions will not work on this class.
	 *       Use array_safe function to check if a key exists (instead of array_key_exists).
	 *       Use keys method to return array of keys (instead of array_keys).
	 *       Use to_array method to truly convert to an array.
	 */
	class C1ArrayIterator implements Iterator, Countable, ArrayAccess {
		
		// Configuration variables
		private $_array;
		private $_callback;
		private $_callback_args;
		private $_callback_count;
		private $_extra = array();
		private $_check = false;
		
		// Processing variables
		private $_current = false;
		
		/**
		 * Creates a new iterator.
		 * @param	array $array The array to process
		 * @param	callback $callback Conversion function; if not supplied, the original value will be returned
		 * @param	array $callback_args The arguments sent to the callback function; the first element will be the second argument
		 */
		public function __construct($array, $callback = false, $callback_args = false, $status_check = false) {
			if (isset($array) && is_array($array))
				$this->_array = $array;
			else {
				$this->_array = array();
				debug('Provided array is not an array:');
				debug($array, true);
			}
			$this->_array = $array;
			$this->_callback = $callback;
			$this->_callback_args = array_merge(array(null), $callback_args === false ? array() : $callback_args);
			$this->_callback_count = count($this->_callback_args);
			$this->_check = $status_check === true;
		}
		
		public function __get($var) {
			switch ($var) {
				case 'array':
				case 'rows':
					$name = '_' . $var;
					return $this->$name;
				case 'count':
				case 'total':
					return $this->count();
				default:
					if (array_key_exists($var, $this->_extra))
						return $this->_extra[$var];
			}
			return false;
		}
		
		public function __set($var, $value) {
			switch ($var) {
				case 'array':
				case 'rows':
					throw new C1ParameterException('"' . $var . '" is not a settable property of C1ArrayIterator');
				default:
					$this->_extra[$var] = $value;
			}
		}
		
		/**
		 * Converts the value using the callback.
		 * @param	mixed $value The value to convert
		 * @return	mixed The converted value; or the original if there is no callback
		 */
		private function _convert($value) {
			if ($value !== false && $this->_callback !== false) {
				
				// Segmentation faults can occur with call_user_func_array
				// but not with call_user_func when a class needs to be autoloaded.
				// As such try to use call_user_func where possible.
				if ($this->_callback_count == 1) {
					$value = call_user_func($this->_callback, $value);
				} elseif ($this->_callback_count == 2) {
					$value = call_user_func($this->_callback, $value, $this->_callback_args[1]);
				} else {
					$this->_callback_args[0] = $value;
					$value = call_user_func_array($this->_callback, $this->_callback_args);
				}
				if ($this->_check && $value instanceof C1ModelBase && !$value->is_active)
					$value = false;
			}
			return $value;
		}
		
		/**
		 * Returns the number of elements.
		 * @return	integer The number of elements in the array
		 */
		public function count() {
			return count($this->_array);
		}
		
		/**
		 * Returns the current element.
		 * @return	mixed The current element
		 */
		public function current() {
			if ($this->_current === false) {
				$this->_current = current($this->_array);
				if ($this->_current !== false)
					$this->_current = $this->_convert($this->_current);
				if ($this->_current === false)
					$this->next();
			}
			return $this->_current;
		}
		
		/**
		 * Returns the key of the current element.
		 * @return	integer The key of the current element
		 */
		public function key() {
			return key($this->_array);
		}
		
		/**
		 * Returns the next element.
		 * @return	mixed The next element
		 */
		public function next() {
			$this->_current = next($this->_array);
			if ($this->_current !== false) {
				$this->_current = $this->_convert($this->_current);
				if ($this->_current === false)
					$this->next();
			}
			return $this->_current;
		}
		
		/**
		 * Moves back to the first element.
		 */
		public function rewind() {
			return reset($this->_array);
		}
		
		/**
		 * Checks if the current position is valid.
		 * @return	boolean True if valid; false otherwise
		 */
		public function valid() {
			if ($this->_current === false)
				$this->current();
			return ($this->_current !== false);
		}
		
		/**
		 * Indicates if a specific key exists.
		 * @param	mixed $offset The key
		 * @return	boolean True if exists; false otherwise
		 */
		public function offsetExists($offset) {
			return array_key_exists($offset, $this->_array);
		}
		
		/**
		 * Returns the value at the specified key.
		 * @param	mixed $offset The key
		 * @return	mixed The value; or false if not found
		 */
		public function offsetGet($offset) {
			if (array_key_exists($offset, $this->_array))
				return $this->_convert($this->_array[$offset]);
			return false;
		}
		
		/**
		 * Unsupported as these lists should not be changed.
		 */
		public function offsetSet($offset, $value) {
			throw new C1ParameterException('offsetSet is not a supported function of C1ArrayIterator');
		}
		
		/**
		 * Unsupported as these lists should not be changed
		 */
		public function offsetUnset($offset) {
			throw new C1ParameterException('offsetUnset is not a supported function of C1ArrayIterator');
		}
		
		/**
		 * Returns a list of all keys.
		 * @return	array A list of keys
		 */
		public function keys() {
			return array_keys($this->_array);
		}
		
		/**
		 * Converts this iterator to a true array.
		 * @return	array This iterator converted to an array
		 */
		public function to_array($convert = true) {
			$array = array();
			foreach ($this->_array as $key => $value)
				$array[$key] = $convert ? $this->_convert($value) : $value;
			return $array;
		}
		
	}

	/**
	 * Iterator object that when given an sql query, queries the database and
	 * uses the specified callback to convert the database row into an object
	 * (or other variable) when the object is used in a foreach loop.
	 *
	 * The main benefits of using this class are objects are only instantiated
	 * when required and results only need to be processed once (rather than
	 * processing results and storing objects in array, then processing object array).
	 *
	 * NOTE: array_* functions will not work on this class.
	 *       Use array_safe function to check if a key exists (instead of array_key_exists).
	 *       Use keys method to return array of keys (instead of array_keys).
	 *       Use to_array method to truly convert to an array.
	 */
	class C1SQLIterator implements Iterator, Countable, ArrayAccess {
		
		// Configuration variables
		private $_sql;
		private $_params;
		private $_callback;
		private $_callback_args;
		private $_callback_count;
		private $_extra = array();
		
		// Processing variables
		private $_results = false;
		private $_current = false;
		private $_current_key = false;
		private $_keys = false;
		private $_start = 0;
		
		/**
		 * Creates a new iterator.
		 * @param	string $sql The sql statement to process
		 * @param	array $params The parameter array
		 * @param	callback $callback Conversion function; if not supplied, the row array will be returned
		 * @param	array $callback_args The arguments sent to the callback function; the first element will be the second argument
		 */
		public function __construct($sql, $params = false, $callback = false, $callback_args = false) {
			$this->_sql = $sql;
			$this->_params = $params === false ? array() : $params;
			$this->_callback = $callback;
			$this->_callback_args = array_merge(array(null), $callback_args === false ? array() : $callback_args);
			$this->_callback_count = count($this->_callback_args);
		}
		
		public function __get($var) {
			switch ($var) {
				case 'sql':
					return $this->_sql;
				case 'params':
					return $this->_params;
				case 'count':
				case 'total':
					return $this->count();
				default:
					if (array_key_exists($var, $this->_extra))
						return $this->_extra[$var];
			}
			return false;
		}
		
		public function __set($var, $value) {
			switch ($var) {
				case 'sql':
					$this->_sql = $value;
					break;
				case 'function':
					throw new C1ParameterException('"' . $var . '" is not a settable property of C1SQLIterator');
				case 'callback':
					if ($value !== false && !is_callable($value))
						throw new C1ParameterException('C1SQLIterator::' . $var . ' must be a callable function');
					$this->_callback = $value;
					break;
				default:
					$this->_extra[$var] = $value;
			}
		}
		
		/**
		 * Converts a value via the callback.
		 * @param	mixed $value The value to change
		 * @return	mixed The value changed via the callback; or the original if there is no callback
		 */
		private function _convert($value) {
			if ($this->_callback != false) {
				
				// Segmentation faults can occur with call_user_func_array
				// but not with call_user_func when a class needs to be autoloaded.
				// As such try to use call_user_func where possible.
				if ($this->_callback_count == 1) {
					$value = call_user_func($this->_callback, $value);
				} elseif ($this->_callback_count == 2) {
					$value = call_user_func($this->_callback, $value, $this->_callback_args[1]);
				} else {
					$this->_callback_args[0] = $value;
					$value = call_user_func_array($this->_callback, $this->_callback_args);
				}
				
			}
			return $value;
		}
		
		/**
		 * Initialises the query.
		 */
		private function _init() {
			global $db;
			if ($this->_results === false)
				$this->_results = $db->execute($this->_sql, $this->_params);
			if ($this->_start > 0)
				$this->_results->move($this->_start);
			$this->_current = false;
			$this->_current_key = false;
		}
		
		/**
		 * Returns the number of elements.
		 * @return	integer The number of elements in the result set
		 */
		public function count() {
			if ($this->_results === false)
				$this->_init();
			return $this->_results->recordcount();
		}
		
		/**
		 * Returns the current element.
		 * @return	mixed The current element
		 */
		public function current() {
			if ($this->_current === false)
				$this->next();
			return $this->_current;
		}
		
		/**
		 * Returns the key of the current element(the id column of the current row if available; element number otherwise).
		 * @return	integer The key of the current element
		 */
		public function key() {
			return $this->_current_key;
		}
		
		/**
		 * Returns the next element.
		 * @return	mixed The next element
		 */
		public function next() {
			
			// Get the next row
			if ($this->_results === false)
				$this->_init();
			$this->_current_key = false;
			$this->_current = $this->_results->fetchrow();
			
			// Convert the row via the callback function
			if ($this->_current !== false) {
				$this->_current_key = array_safe($this->_current, 'id', false) !== false ? $this->_current['id'] : $this->_results->currentrow();
				$this->_current = $this->_convert($this->_current);
			} else {
				$this->_current_key = $this->_results->currentrow();
			}
			
			return $this->_current;
		}
		
		/**
		 * Moves back to the first element.
		 */
		public function rewind() {
			if ($this->_results !== false)
				$this->_results->moveFirst();
			$this->_init();
			$this->next();
			return $this->_current;
		}
		
		/**
		 * Starts at a specific point
		 * @param	integer $position The start position (0 for first record)
		 */
		public function start($position) {
			$this->_start = $position;
			if ($this->_results !== false && $position > 0)
				$this->_results->move($position);
		}
		
		/**
		 * Checks if the current position is valid.
		 * @return	boolean True if valid; false otherwise
		 */
		public function valid() {
			if ($this->_results === false)
				$this->next();
			return ($this->_current !== false);
		}
		
		/**
		 * Loads all keys from the database.
		 */
		private function _load_keys() {
			if ($this->_keys !== false)
				return;
			
			// Move to the start of the results
			if ($this->_results === false)
				$this->_init();
			$current_row = $this->_results->currentrow();
			$this->_results->movefirst();
			
			// Load all keys
			$this->_keys = array();
			while($row = $this->_results->fetchrow()) {
				$id = array_safe($row, 'id', false);
				$this->_keys[] = ($id !== false ? $id : $this->_results->currentrow());
			}
			
			// Restore previous position in record set
			$this->_results->move($current_row);
		}
		
		/**
		 * Indicates if a specific key exists.
		 * @param	mixed $offset The key
		 * @return	boolean True if exists; false otherwise
		 */
		public function offsetExists($offset) {
			if ($this->_keys === false)
				$this->_load_keys();
			return in_array($offset, $this->_keys);
		}
		
		/**
		 * Returns the value at the specified key.
		 * @param	mixed $offset The key
		 * @return	mixed The value; or false if not found
		 */
		public function offsetGet($offset) {
			if ($this->_keys === false)
				$this->_load_keys();
			$offset_row = array_search($offset, $this->_keys);
			if ($offset_row !== false) {
				$current_row = $this->_results->currentrow();
				$this->_results->move($offset_row);
				$row = $this->_results->fetchrow();
				if ($row !== false)
					return $this->_convert($row);
				$this->_results->move($current_row);
			}
			return false;
		}
		
		/**
		 * Unsupported as these lists should not be changed.
		 */
		public function offsetSet($offset, $value) {
			throw new C1ParameterException('offsetSet is not a supported function of C1SQLIterator');
		}
		
		/**
		 * Unsupported as these lists should not be changed
		 */
		public function offsetUnset($offset) {
			throw new C1ParameterException('offsetUnset is not a supported function of C1SQLIterator');
		}
		
		/**
		 * Returns the keys of all elements.
		 * @return	array The list of keys
		 */
		public function keys() {
			if ($this->_keys === false)
				$this->_load_keys();
			return $this->_keys;
		}
		
		/**
		 * Converts this iterator to a true array.
		 * @return	array This iterator converted to an array
		 */
		public function to_array($convert = true) {
			global $db;
			if (!$convert) {
				$array = array();
				$results = $db->execute($this->_sql, $this->_params);
				foreach ($results as $row) {
					if (count($row) == 1) {
						foreach ($row as $value)
							$array[] = $value;
						continue;
					}
					$array[] = $row;
				}
			}
			$array = array();
			$this->rewind();
			while ($this->valid()) {
				$v = $this->current();
				$array[$this->key()] = $v;
				$this->next();
			}
			return $array;
		}
		
		/**
		 * Exports the result set to a tab separated file.
		 * @param	string|false $filename The name of the file to export to; or false to generate temporary file
		 * @return	string The name of the file exported to
		 */
		public function to_tsv($filename = false) {
			
			// Open the file
			if ($filename === false)
				$filename = tempnam('/tmp', 'c1-iterator');
			$file = fopen($filename, 'w');
			
			// Generate the file
			$first = true;
			$this->rewind();
			while ($this->valid()) {
				$row = $this->current();
				
				// Output the header if the first row
				if ($first) {
					$sep = '';
					$line = '';
					foreach ($row as $key => $value) {
						$line .= $sep . $key;
						$sep = "\t";
					}
					fwrite($file, $line . "\r\n");
					$first = false;
				}
				
				// Output the line
				$sep = '';
				$line = '';
				foreach ($row as $key => $value) {
					$line .= $sep . str_replace(array("\n", "\r"), array('\\n', ''), $value);
					$sep = "\t";
				}
				fwrite($file, $line . "\r\n");
				
				$this->next();
			}

			return $filename;
		}
		
		/**
		 * Exports the result set to a tab separated file.
		 * @param	string|false $filename The name of the file to export to; or false to generate temporary file
		 * @return	string The name of the file exported to
		 */
		public function to_csv($filename = false) {
			
			// Open the file
			if ($filename === false)
				$filename = tempnam('/tmp', 'c1-iterator');
			$file = fopen($filename, 'w');
			
			// Generate the file
			$first = true;
			$this->rewind();
			while ($this->valid()) {
				$row = $this->current();
				
				// Output the header if the first row
				if ($first) {
					fputcsv($file, array_keys($row));
					$first = false;
				}
				
				// Output the file
				fputcsv($file, array_values($row));
				
				$this->next();
			}
			
			return $filename;
		}
		
		
	}

	/**
	 * Iterator object that when given a file path will loop through each line of the file
	 * and use the specified call back to convert the raw data.
	 *
	 * The main benefit of this class is only one line is in memory at any one time.
	 *
	 * NOTE: array_* functions will not work on this class.
	 *       Use array_safe function to check if a key exists (instead of array_key_exists).
	 *       Use keys method to return array of keys (instead of array_keys).
	 *       Use to_array method to truly convert to an array.
	 */
	class C1CSVIterator implements Iterator, Countable {
		
		// Configuration variables
		private $_filename;
		private $_header_row;
		private $_callback;
		private $_callback_args;
		private $_delimeter;
		private $_enclosure;
		
		// Processing variables
		private $_file = false;
		private $_first = true;
		private $_current = false;
		private $_current_key = false;
		private $_count = false;
		private $_start = 0;
		private $_trim = true;
		
		/**
		 * Creates a new iterator object and opens the file for reading.
		 * @param	string $file The file to open
		 * @param	boolean $header_row True to ignore the first row; false otherwise
		 * @param	string $delimeter The delimeter between fields
		 * @param	string $enclosure The field enclosure
		 * @param	boolean $trim Automatically trim each value in a row before calling the callback
		 */
		public function __construct($file, $header_row = true, $callback = false, $callback_args = false, $delimeter = ',', $enclosure = '"', $trim = true) {
			
			// Update the configuration
			$this->_filename		= $file;
			$this->_header_row		= $header_row;
			$this->_callback		= $callback;
			$this->_callback_args	= $callback_args;
			$this->_delimeter		= $delimeter;
			$this->_enclosure		= $enclosure;
			$this->_start			= $header_row ? 1 : 0;
			$this->_trim			= $trim == true;
			
		}
		
		public function __get($var) {
			switch ($var) {
				case 'count':
				case 'total':
					return $this->count();
				case 'start':
					return 1;
				case 'line':
					return $this->_current_key;
			}
		}
		
		/**
		 * Opens the file for reading.
		 */
		private function _init() {
			if ($this->_file !== false)
				fclose($this->_file);
			$this->_file = fopen($this->_filename, 'r');
			if ($this->_file === false)
				throw new Exception('Unable to open file for reading: ' . $this->_filename);
			$this->_first = true;
			$this->_current = false;
			$this->_current_key = false;
		}
		
		/**
		 * Converts the value using the callback.
		 * @param	mixed $value The value to convert
		 * @return	mixed The converted value; or the original if there is no callback
		 */
		private function _convert($value) {
			if ($value !== false && $this->_callback !== false) {
				$value['line'] = $this->__get('line');
				if ($this->_trim)
					$value = array_map('trim', $value);
				$this->_callback_args[0] = $value;
				$value = call_user_func_array($this->_callback, $this->_callback_args);
			}
			return $value;
		}
		
		/**
		 * Returns the number of lines in the CSV.
		 * @return	integer The number of rows
		 */
		public function count() {
			
			// If there is a count return it
			if ($this->_count !== false)
				return $this->_count;
			
			// Open the file separately and record the number of lines
			$this->_count = 0;
			$first = true;
			$file = fopen($this->_filename, 'r');
			while (($line = fgets($file, 65536)) !== false) {
				
				// Skip the first row if required
				if ($first) {
					$first = false;
					if ($this->_header_row)
						continue;
				}
				
				// Update the row count
				$this->_count++;
			}
			fclose($file);
			
			return $this->_count;
		}
		
		/**
		 * Returns the current element.
		 * @return	mixed The current element
		 */
		public function current() {
			if ($this->_current === false)
				$this->next();
			return $this->_current;
		}
		
		/**
		 * Returns the key of the current element (the line number)
		 * @return	integer The current key
		 */
		public function key() {
			return $this->_current_key;
		}
		
		/**
		 * Returns the next element.
		 * @return	mixed The next element
		 */
		public function next() {
			
			// Open the file if required
			if ($this->_file === false)
				$this->_init();
				
			// Skip the first line if required
			if ($this->_first && $this->_start > 0) {
				$this->_current_key = 0;
				for ($i = 0; $i < $this->_start; $i++) {
					fgetcsv($this->_file, 65536);
					$this->_current_key++;
				}
				$this->_first = false;
			}
				
			// Get the next line
			$this->_current = fgetcsv($this->_file, 65536, $this->_delimeter, $this->_enclosure);
			
			// Convert the current line by using the callback function
			if ($this->_current !== false) {
				$this->_current_key = ($this->_current_key === false ? 1 : ($this->_current_key + 1));
				$this->_current = $this->_convert($this->_current);

			// Reset the key if there are no more lines
			} else {
				$this->_current_key = false;
				
			}
			
			return $this->_current;
		}
		
		/**
		 * Moves back to the first element.
		 */
		public function rewind() {
			$this->_init();
			$this->next();
			return $this->_current;
		}
		
		/**
		 * Changes the start position of the iterator.
		 * @param	integer $position The start position (0 is first)
		 */
		public function start($position) {
			$this->_start = $position + ($this->_header_row ? 1 : 0);
		}
		
		/**
		 * Checks if the current position is valid.
		 * @return	boolean True if valid; false otherwise
		 */
		public function valid() {
			if ($this->_file === false)
				$this->next();
			return ($this->_current !== false);
		}
		
	}
	