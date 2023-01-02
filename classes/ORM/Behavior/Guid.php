<?php

/**
 * @package    Kohana/ORM
 * @author     Koseven Team
 * @copyright  (c) 2016-2018 Koseven Team
 * @license    https://koseven.ga/LICENSE.md
 */
class ORM_Behavior_Guid extends ORM_Behavior {

	/**
	 * Table column for GUID value
	 * @var string
	 */
	protected $_guid_column = 'guid';

	/**
	 * Allow model creaton on guid key only
	 * @var boolean
	 */
	protected $_guid_only = TRUE;

	/**
	 * Constructs a behavior object
	 *
	 * @param   array $config Configuration parameters
	 */
	protected function __construct($config)
	{
		parent::__construct($config);

		$this->_guid_column = Arr::get($config, 'column', $this->_guid_column);
		$this->_guid_only = Arr::get($config, 'guid_only', $this->_guid_only);
	}

	/**
	 * Constructs a new model and loads a record if given
	 *
	 * @param   ORM   $model The model
	 * @param   mixed $id    Parameter for find or object to load
	 */
	public function on_construct($model, $id)
	{
		if (($id !== NULL) AND ! is_array($id) AND ! ctype_digit($id))
		{
			if (UUID::valid($id))
			{
				$model->where($this->_guid_column, '=', $id)->find();

				// Prevent further record loading
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * The model is updated, add a guid value if empty
	 *
	 * @param   ORM   $model The model
	 */
	public function on_update($model)
	{
		$this->create_guid($model);
	}

	/**
	 * A new model is created, add a guid value
	 *
	 * @param   ORM   $model The model
	 */
	public function on_create($model)
	{
		$this->create_guid($model);
	}

	private function create_guid($model)
	{
		$current_guid = $model->get($this->_guid_column);

		// Try to create a new GUID
		$query = DB::select()->from($model->table_name())
			->where($this->_guid_column, '=', ':guid')
			->limit(1);

		while (empty($current_guid))
		{
			$random_bytes = random_bytes(16);
			$random_bytes[6] = chr((ord($random_bytes[6]) & 0x0f) | 0x40);
			$random_bytes[8] = chr((ord($random_bytes[8]) & 0x3f) | 0x80);

			$current_guid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($random_bytes), 4));

			$query->param(':guid', $current_guid);
			if ($query->execute()->get($model->primary_key(), FALSE) !== FALSE)
			{
				Log::instance()->add(Log::NOTICE, 'Duplicate GUID created for '.$model->table_name());
				$current_guid = '';
			}
		}

		$model->set($this->_guid_column, $current_guid);
	}
}
