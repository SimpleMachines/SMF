<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

/**
 * This class provides a standard way of displaying lists for SMF.
 */
class ItemList implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'createList',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The list's identifier string.
	 */
	public string $id;

	/**
	 * @var string
	 *
	 * The title of this list. Optional.
	 */
	public string $title;

	/**
	 * @var int
	 *
	 * The number of columns in this list.
	 */
	public int $num_columns;

	/**
	 * @var string
	 *
	 * CSS width for the table. Optional.
	 */
	public string $width;

	/**
	 * @var string
	 *
	 * Message to show when the list is empty.
	 */
	public string $no_items_label;

	/**
	 * @var string
	 *
	 * CSS class for the empty list message.
	 * E.g.: 'centertext', 'lefttext', etc.
	 */
	public string $no_items_align;

	/**
	 * @var array
	 *
	 * Info about the HTML form for this list.
	 */
	public array $form;

	/**
	 * @var array
	 *
	 * Table headers.
	 */
	public array $headers = [];

	/**
	 * @var array
	 *
	 * Table rows for the items in the list.
	 */
	public array $rows = [];

	/**
	 * @var array
	 *
	 * Additional rows to wrap around this lis.
	 */
	public array $additional_rows = [];

	/**
	 * @var string
	 *
	 * Any JavaScript to add for this list.
	 */
	public string $javascript;

	/**
	 * @var string
	 *
	 * The page index for navigating this list.
	 */
	public string $page_index;

	/**
	 * @var array
	 *
	 * Info about how this list is sorted.
	 */
	public array $sort = [];

	/**
	 * @var int
	 *
	 * Where the current page of the list begins.
	 */
	public int $start = 0;

	/**
	 * @var string
	 *
	 * URL parameter that indicates where to start current page of the list.
	 */
	public string $start_var_name = 'start';

	/**
	 * @var int
	 *
	 * How many items to put on each page of the list.
	 */
	public int $items_per_page = 0;

	/**
	 * @var int
	 *
	 * How many items are in the entire list.
	 */
	public int $total_num_items;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * The list options passed to the constructor.
	 */
	protected array $options = [];

	/**
	 * @var string
	 *
	 * Value for the ORDER BY clause.
	 */
	protected string $db_sort = '1=1';

	/**
	 * @var array
	 *
	 * The items retrieved from the database.
	 */
	protected array $items = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Create a new list.
	 *
	 * @param array $options An array of options for the list - 'id', 'columns', 'items_per_page', 'get_count', etc.
	 */
	public function __construct(array $options)
	{
		// Make sure that we have everything we need.
		if (!$this->checkOptions($options)) {
			return;
		}

		IntegrationHook::call('integrate_' . $options['id'], [&$options]);

		// Check again just in case a mod screwed up.
		if (!$this->checkOptions($options)) {
			return;
		}

		$this->id = $options['id'];
		$this->options = $options;
		unset($options);

		// Make it easy to access this list.
		Utils::$context[$this->id] = $this;

		$this->setBasics();
		$this->setSort();
		$this->setStartAndItemsPerPage();
		$this->buildHeaders();
		$this->getItems();
		$this->buildRows();
		$this->buildForm();
		$this->buildAdditionalRows();
		$this->buildPageIndex();

		// Make sure the template is loaded.
		Theme::loadTemplate('GenericList');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @param array $options Same as for the constructor.
	 * @return object An instance of this class.
	 */
	public static function load(array $options): object
	{
		return new self($options);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks whether $options provides enough data to make a list.
	 *
	 * @param array $options Same as for the constructor.
	 * @return bool Whether we can build a list with this data.
	 */
	protected function checkOptions(array $options): bool
	{
		$have_what_we_need = isset($options['id']);
		$have_what_we_need &= isset($options['columns']) && is_array($options['columns']);
		$have_what_we_need &= empty($options['default_sort_col']) || isset($options['columns'][$options['default_sort_col']]);
		$have_what_we_need &= !isset($options['form']) || isset($options['form']['href']);

		if (!isset($options['get_count']['value'])) {
			$have_what_we_need &= empty($options['items_per_page']) || (isset($options['get_count']['function'], $options['base_href']) && is_numeric($options['items_per_page']));
		}

		return $have_what_we_need;
	}

	/**
	 * Handles some list properties that can be set directly from the options.
	 */
	protected function setBasics(): void
	{
		if (!empty($this->options['start_var_name'])) {
			$this->start_var_name = $this->options['start_var_name'];
		}

		// We know the amount of columns, might be useful for the template.
		$this->num_columns = count($this->options['columns']);

		if (!empty($this->options['width'])) {
			$this->width = $this->options['width'];
		}

		// The title is currently optional.
		if (isset($this->options['title'])) {
			$this->title = $this->options['title'];
		}

		// Wanna say something nice in case there are no items?
		if (isset($this->options['no_items_label'])) {
			$this->no_items_label = $this->options['no_items_label'];
			$this->no_items_align = $this->options['no_items_align'] ?? '';
		}

		// Add an option for inline JavaScript.
		if (isset($this->options['javascript'])) {
			$this->javascript = $this->options['javascript'];
		}
	}

	/**
	 * Figure out the sort.
	 */
	protected function setSort(): void
	{
		if (empty($this->options['default_sort_col'])) {
			return;
		}

		$this->options['request_vars']['sort'] = $this->options['request_vars']['sort'] ?? 'sort';
		$this->options['request_vars']['desc'] = $this->options['request_vars']['desc'] ?? 'desc';

		if (isset($_REQUEST[$this->options['request_vars']['sort']], $this->options['columns'][$_REQUEST[$this->options['request_vars']['sort']]], $this->options['columns'][$_REQUEST[$this->options['request_vars']['sort']]]['sort'])) {
			$this->sort = [
				'id' => $_REQUEST[$this->options['request_vars']['sort']],
				'desc' => isset($_REQUEST[$this->options['request_vars']['desc']]) && isset($this->options['columns'][$_REQUEST[$this->options['request_vars']['sort']]]['sort']['reverse']),
			];
		} else {
			$this->sort = [
				'id' => $this->options['default_sort_col'],
				'desc' => (!empty($this->options['default_sort_dir']) && $this->options['default_sort_dir'] == 'desc') || (!empty($this->options['columns'][$this->options['default_sort_col']]['sort']['default']) && substr($this->options['columns'][$this->options['default_sort_col']]['sort']['default'], -4, 4) == 'desc') ? true : false,
			];
		}

		// Set the database column sort.
		$this->db_sort = $this->options['columns'][$this->sort['id']]['sort'][$this->sort['desc'] ? 'reverse' : 'default'];
	}

	/**
	 * Figures out $this->start and $this->items_per_page.
	 * If $this->items_per_page > 0, also figures out $this->total_num_items.
	 */
	protected function setStartAndItemsPerPage(): void
	{
		// In some cases the full list must be shown, regardless of the amount of items.
		if (empty($this->options['items_per_page'])) {
			return;
		}

		// First get an impression of how many items to expect.
		if (isset($this->options['get_count']['value']) && (is_int($this->options['get_count']['value']) || ctype_digit($this->options['get_count']['value']))) {
			$this->total_num_items = $this->options['get_count']['value'];
		} else {
			if (isset($this->options['get_count']['file'])) {
				require_once $this->options['get_count']['file'];
			}

			$call = Utils::getCallable($this->options['get_count']['function']);

			$params = $this->options['get_count']['params'] ?? [];

			$this->total_num_items = call_user_func_array($call, array_values($params));
		}

		// Default the start to the beginning...sounds logical.
		$this->start = isset($_REQUEST[$this->start_var_name]) ? (int) $_REQUEST[$this->start_var_name] : 0;
		$this->items_per_page = $this->options['items_per_page'];
	}

	/**
	 * Creates the page index, if necessary.
	 */
	protected function buildPageIndex(): void
	{
		if (empty($this->total_num_items) || $this->total_num_items <= $this->items_per_page) {
			return;
		}

		$this->page_index = new PageIndex($this->options['base_href'] . (empty($this->sort) ? '' : ';' . $this->options['request_vars']['sort'] . '=' . $this->sort['id'] . ($this->sort['desc'] ? ';' . $this->options['request_vars']['desc'] : '')) . ($this->start_var_name != 'start' ? ';' . $this->start_var_name . '=%1$d' : ''), $this->start, $this->total_num_items, $this->items_per_page, $this->start_var_name != 'start');
	}

	/**
	 * Builds the table headers for this list.
	 */
	protected function buildHeaders(): void
	{
		// Prepare the headers of the table.
		foreach ($this->options['columns'] as $column_id => $column) {
			$this->headers[] = [
				'id' => $column_id,
				'label' => isset($column['header']['eval']) ? eval($column['header']['eval']) : ($column['header']['value'] ?? ''),
				'href' => empty($this->options['default_sort_col']) || empty($column['sort']) ? '' : $this->options['base_href'] . ';' . $this->options['request_vars']['sort'] . '=' . $column_id . ($column_id === $this->sort['id'] && !$this->sort['desc'] && isset($column['sort']['reverse']) ? ';' . $this->options['request_vars']['desc'] : '') . (empty($this->start) ? '' : ';' . $this->start_var_name . '=' . $this->start),
				'sort_image' => empty($this->options['default_sort_col']) || empty($column['sort']) || $column_id !== $this->sort['id'] ? null : ($this->sort['desc'] ? 'down' : 'up'),
				'class' => $column['header']['class'] ?? '',
				'style' => $column['header']['style'] ?? '',
				'colspan' => $column['header']['colspan'] ?? '',
			];
		}
	}

	/**
	 * Builds the table rows for the list items.
	 */
	protected function buildRows(): void
	{
		// Loop through the list items to be shown and construct the data values.
		foreach ($this->items as $item_id => $list_item) {
			$cur_row = [];

			foreach ($this->options['columns'] as $column_id => $column) {
				$cur_data = [];

				// A value straight from the database?
				if (isset($column['data']['db'])) {
					$cur_data['value'] = $list_item[$column['data']['db']];
				}
				// Take the value from the database and make it HTML safe.
				elseif (isset($column['data']['db_htmlsafe'])) {
					$cur_data['value'] = Utils::htmlspecialchars($list_item[$column['data']['db_htmlsafe']]);
				}
				// Using sprintf is probably the most readable way of injecting data.
				elseif (isset($column['data']['sprintf'])) {
					$params = [];

					foreach ($column['data']['sprintf']['params'] as $sprintf_param => $htmlsafe) {
						$params[] = $htmlsafe ? Utils::htmlspecialchars($list_item[$sprintf_param]) : $list_item[$sprintf_param];
					}

					$cur_data['value'] = vsprintf($column['data']['sprintf']['format'], $params);
				}
				// The most flexible way probably is applying a custom function.
				elseif (isset($column['data']['function'])) {
					$cur_data['value'] = call_user_func_array($column['data']['function'], [$list_item]);
				}
				// A modified value (inject the database values).
				elseif (isset($column['data']['eval'])) {
					$cur_data['value'] = eval(preg_replace('~%([a-zA-Z0-9\-_]+)%~', '$list_item[\'$1\']', $column['data']['eval']));
				}
				// A literal value.
				elseif (isset($column['data']['value'])) {
					$cur_data['value'] = $column['data']['value'];
				}
				// Empty value.
				else {
					$cur_data['value'] = '';
				}

				// Allow for basic formatting.
				if (!empty($column['data']['comma_format'])) {
					$cur_data['value'] = Lang::numberFormat($cur_data['value']);
				} elseif (!empty($column['data']['timeformat'])) {
					$cur_data['value'] = Time::create('@' . $cur_data['value'])->format();
				}

				// Set a style class for this column?
				if (isset($column['data']['class'])) {
					$cur_data['class'] = $column['data']['class'];
				}

				// Fully customized styling for the cells in this column only.
				if (isset($column['data']['style'])) {
					$cur_data['style'] = $column['data']['style'];
				}

				// Add the data cell properties to the current row.
				$cur_row[$column_id] = $cur_data;
			}

			// Maybe we wat set a custom class for the row based on the data in the row itself
			if (isset($this->options['data_check'])) {
				if (isset($this->options['data_check']['class'])) {
					$this->rows[$item_id]['class'] = $this->options['data_check']['class']($list_item);
				}

				if (isset($this->options['data_check']['style'])) {
					$this->rows[$item_id]['style'] = $this->options['data_check']['style']($list_item);
				}
			}

			// Insert the row into the list.
			$this->rows[$item_id]['data'] = $cur_row;
		}
	}

	/**
	 * Gets the list items from the database.
	 */
	protected function getItems(): void
	{
		if (!empty($this->options['get_items']['value']) && is_array($this->options['get_items']['value'])) {
			$this->items = $this->options['get_items']['value'];
		} else {
			// Get the file with the function for the item list.
			if (isset($this->options['get_items']['file'])) {
				require_once $this->options['get_items']['file'];
			}

			$call = Utils::getCallable($this->options['get_items']['function']);

			$items = call_user_func_array($call, array_merge([$this->start, $this->items_per_page, $this->db_sort], empty($this->options['get_items']['params']) ? [] : $this->options['get_items']['params']));

			$this->items = empty($items) ? [] : $items;
		}
	}

	/**
	 * In case there's a form, share it with the template context.
	 */
	protected function buildForm(): void
	{
		if (!isset($this->options['form'])) {
			return;
		}

		$this->form = $this->options['form'];

		if (!isset($this->form['hidden_fields'])) {
			$this->form['hidden_fields'] = [];
		}

		// Always add a session check field.
		$this->form['hidden_fields'][Utils::$context['session_var']] = Utils::$context['session_id'];

		// Will this do a token check?
		if (isset($this->options['form']['token'])) {
			$this->form['hidden_fields'][Utils::$context[$this->options['form']['token'] . '_token_var']] = Utils::$context[$this->options['form']['token'] . '_token'];
		}

		// Include the starting page as hidden field?
		if (!empty($this->form['include_start']) && !empty($this->start)) {
			$this->form['hidden_fields'][$this->start_var_name] = $this->start;
		}

		// If sorting needs to be the same after submitting, add the parameter.
		if (!empty($this->form['include_sort']) && !empty($this->sort)) {
			$this->form['hidden_fields']['sort'] = $this->sort['id'];

			if ($this->sort['desc']) {
				$this->form['hidden_fields']['desc'] = 1;
			}
		}
	}

	/**
	 * A list can sometimes need a few extra rows above and below.
	 */
	protected function buildAdditionalRows(): void
	{
		if (!isset($this->options['additional_rows'])) {
			return;
		}

		foreach ($this->options['additional_rows'] as $row) {
			if (empty($row)) {
				continue;
			}

			// Supported row positions: top_of_list, after_title,
			// above_column_headers, below_table_data, bottom_of_list.
			if (!isset($this->additional_rows[$row['position']])) {
				$this->additional_rows[$row['position']] = [];
			}

			$this->additional_rows[$row['position']][] = $row;
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ItemList::exportStatic')) {
	ItemList::exportStatic();
}

?>