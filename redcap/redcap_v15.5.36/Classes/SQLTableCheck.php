<?php



/**
 * SQLTableCheck
 * This class provides methods for evaluating tables or parts of tables that are missing
 * from among REDCap's MySQL tables, and provides output to fix those tables.
 */
class SQLTableCheck
{

	// Constructor
	public function __construct()
	{
		// Make sure sql_mode isn't set to ANSI_QUOTES
		db_query("SET SQL_MODE=TRADITIONAL");
	}

	/**
	 * OBTAIN "CREATE TABLE" STATEMENT FOR ALL REDCAP TABLES
	 * Return array with table name as array key.
	 */
	public function get_create_table_for_all_tables()
	{
		$tables = array();
		// Get CREATE TABLE statement of all "redcap_" tables
		$sql = "show tables like 'redcap\_%'";
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) {
			$q2 = db_query("show create table `{$row[0]}`");
			$row2 = db_fetch_assoc($q2);
			$tables[$row2['Table']] = $row2['Create Table'];
		}
		// Sort tables alphabetically for consistency
		ksort($tables);
		// Return tables
		return $tables;
	}


	/**
	 * Determine if we're using utf8mb4 encoding or utf8 in the tables
	 */
	public static function using_utf8mb4()
	{
		$sql = "show create table redcap_config";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		return (strpos($row['Create Table'], 'utf8mb4_unicode_ci') !== false);
	}


	/**
	 * BUILD ARRAY OF CURRENT TABLE STRUCTURE AND DO A DIFF OF IT WITH INSTALL.SQL AND INSTALL_DATA.SQL
	 */
	public function build_table_fixes($autoFix=false, $forceConvertMb4=false)
	{
		// Auto fix it?
		if ($autoFix) {
			$autoFixSuccess = Upgrade::autoFixTables();
			if ($autoFixSuccess !== false) return '';
		}
		## PARSE INSTALL.SQL
		// Obtain install.sql from Resources/sql/ directory
		$install_sql = file_get_contents(APP_PATH_DOCROOT . "Resources/sql/install.sql");
		// Replace all \r\n with just \n for compatibility
		$install_sql = str_replace("\r\n", "\n", $install_sql);
		// Obtain a version of install.sql from current table structure
		$this_install_sql = $this->build_install_file_from_tables($forceConvertMb4);
        // Remove any /* mariadb-5.3 */ inline comments for datetime and time fields
        $this_install_sql = preg_replace('/\s?\/\*.*?\*\//s', '', $this_install_sql);
        // Vanderbilt-specific: Replace special PRIMARY key on redcap_log_event
        $this_install_sql = str_replace('PRIMARY KEY (`log_event_id`,`project_id`)', 'PRIMARY KEY (`log_event_id`)', $this_install_sql);
		// Determine if we're using utf8mb4 encoding or utf8 in the tables
		if (!self::using_utf8mb4() && !$forceConvertMb4) {
			// Replace all references of utf8mb4 encoding with utf8 in the install files for consistency
			$install_sql = str_replace("utf8mb4", "utf8", $install_sql);
			// $this_install_sql = str_replace("utf8mb4", "utf8", $this_install_sql); // This was removed because it created false negatives in specific cases where the db table collation isn't correct.
			// Only utf8mb4 encoded tables will have a 191-char limit in keys, so for backwards compatibility, remove it from install.sql file during comparison
			$install_sql = str_replace("`(191)", "`", $install_sql);
		}
		// Array for placing the table differences
		$diff_tables = $diff_fks = array();
		// Parse the install SQL files into an array with table attributes
		$install_tables = $this->parse_install_sql($install_sql);
		$this_install_tables = $this->parse_install_sql($this_install_sql);
		// Loop through install.sql array and note anything missing or different from it
		foreach ($install_tables as $table=>$attr) {
			// If table is missing
			if (!isset($this_install_tables[$table])) {
				$diff_tables[] = $attr['create_table'];
				if (isset($attr['create_table_fks']) && $attr['create_table_fks'] != '') {
					$diff_fks[] = $attr['create_table_fks'];
				}
				// Go to next loop since there's nothing else to do here
				continue;
			}
			// Check all fields
			if ($attr['fields'] !== $this_install_tables[$table]['fields']) {
				// Get table-level collation to use when column-level collation is missing from SHOW CREATE TABLE output
				// (affects MariaDB versions 10.3.37+, 10.4.27+, 10.5.18+, 10.6.11+, 10.7.7+, 10.8.6+, 10.9.4+, 10.10.2+, 10.11.0+)
				$string = $this_install_tables[$table]['create_table']; // last line of SHOW CREATE TABLE output
				// example: ") ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci";
				if (preg_match('/COLLATE=(\w+)/', $string, $collation_match) === 1) {
					// Replace utf8mb3 with utf8 for legacy compatibility
					$collation = str_replace("utf8mb3", "utf8", $collation_match[1]);
					// Should consider spelling out utf8 as utf8mb3 soon, as the utf8_* naming is gradually being deprecated for utf8mb3_*.
				} else {
					$collation = false;
				}
				// Loop through fields
				$prev_field = null;
				foreach ($attr['fields'] as $field=>$line) {
                    // Replace duplicate collation
                    $line = str_replace(" COLLATE utf8_unicode_ci COLLATE utf8_unicode_ci", " COLLATE utf8_unicode_ci", $line);
                    $line = str_replace(" COLLATE utf8mb3_unicode_ci COLLATE utf8mb3_unicode_ci", " COLLATE utf8mb3_unicode_ci", $line);
                    $line = str_replace(" COLLATE utf8mb4_unicode_ci COLLATE utf8mb4_unicode_ci", " COLLATE utf8mb4_unicode_ci", $line);
					// If field is missing
					if (!isset($this_install_tables[$table]['fields'][$field])) {
						$diff_tables[] = "ALTER TABLE `$table` ADD $line" . ($prev_field == null ? "" : " AFTER `$prev_field`;");
					}
					// If field is different
					elseif ($line != $this_install_tables[$table]['fields'][$field]) {

						// For MariaDB versions released after Sep 2022
						// ...Add table-level collation (charset implied) to each column definition if collation isn't given in SHOW CREATE TABLE output						
						// Regex tested here -- https://regex101.com/r/7kyOla/6
 						if ((strpos($this_install_tables[$table]['fields'][$field]," COLLATE ") === false) && !empty($collation)) { 
							$collFind = "/(`$field` (enum\(.+\) |(var|)char\(\d{1,4}\) |(long|medium|tiny|)text |set\(.+\) ))/";
							$collRepl = "$1COLLATE $collation ";
							$this_install_tables[$table]['fields'][$field] = preg_replace($collFind, $collRepl, $this_install_tables[$table]['fields'][$field]);
                        }
						
						// MySQL 8 will return " int " instead of " int(X) " for all integer types, so we'll have to insert the length "(X)" manually for conformity with install.sql
						$numeric_types = array("tinyint", "smallint", "mediumint", "bigint", "int");
						foreach ($numeric_types as $numericType) {
							// Search for "int ", which should only show up in MySQL 8 (other versions would have "int(" instead)
							$thisSearch = "`$field` $numericType ";
							if (strpos($this_install_tables[$table]['fields'][$field], $thisSearch) !== false) {
								// Insert the int length manually into the "show create table" results by pulling it from install.sql
								list ($thisReplace, $nothing) = explode(") ", $line, 2);
								$thisReplace .= ") "; // Append ending to use as full replacement
								$this_install_tables[$table]['fields'][$field] = str_replace($thisSearch, $thisReplace, $this_install_tables[$table]['fields'][$field]);
							}
						}
						// check again...
						if ($this_install_tables[$table]['fields'][$field] != $line) {
							$diff_tables[] = "ALTER TABLE `$table` CHANGE `$field` $line;";
						}
					}
					// Set for next loop
					$prev_field = $field;
				}
			}
			// Check primary key
			if ($attr['pk'] != $this_install_tables[$table]['pk']) {
				// If primary key is missing
				if ($this_install_tables[$table]['pk'] == '' && $attr['pk'] != '') {
					$diff_tables[] = "ALTER TABLE `$table` ADD {$attr['pk']};";
				}
				// If primary key is different
				else {
					// In case this table's PK is used as an FK in another table, check if so, and generate DROP FOREIGN KEY and then ADD FOREIGN KEY for it
					$addFKs = [];
					list ($nothing, $formattedPK) = explode("(", $attr['pk'], 2);
					$thesePKs = (strpos($formattedPK, ",") !== false) ? explode(",", $formattedPK) : [$formattedPK];
					foreach ($thesePKs as &$thisPK) {
						$thisPK = substr($thisPK, 1);
						$thisPK = '"'.substr($thisPK, 0, strpos($thisPK, "`")).'"';
					}
					$sql = "select concat(\"ALTER TABLE `$table` DROP FOREIGN KEY \", constraint_name, \";\") as dropsql, constraint_name
							from information_schema.KEY_COLUMN_USAGE 
							where CONSTRAINT_SCHEMA = '{$GLOBALS['db']}' and TABLE_NAME = '$table' 
							and referenced_column_name is not null and COLUMN_NAME IN (".implode(", ", $thesePKs).")";
					$q = db_query($sql);
					if ($q) {
						$removeFKquery = db_result($q, 0, "dropsql");
						$fkName = db_result($q, 0, "constraint_name");
						if ($removeFKquery != '') {
							// Add drop FK to array
							$diff_tables[] = $removeFKquery;
							// Obtain the line of the FK definition from "show create table" so we know how to re-add it afterward
							$sql = "show create table `$table`";
							$q = db_query($sql);
							if ($q) {
								$createTable = explode("\n", db_fetch_assoc($q)['Create Table']);
								foreach ($createTable as $thisline) {
									$thisline = trim($thisline);
									if (strpos($thisline, "CONSTRAINT `$fkName`") === 0) {
										$thisline = trim(str_replace("CONSTRAINT `$fkName`", "", $thisline));
										$addFKs[] = "ALTER TABLE `$table` ADD ".$thisline.";";
										break;
									}
								}
							}
						}
					}
                    // If table has an extra, unexpected primary key, then ignore it for institutions that have been
                    // told by their IT folks that they need to add PKs to all tables for clustering/replication purposes.
                    $dropPK = true;
                    if ($this_install_tables[$table]['pk'] != '') {
                        // Get name of PK in current db table, if any
                        $pk_field = str_replace(["PRIMARY KEY"," ","(",")","`"], [""], $this_install_tables[$table]['pk']);
                        // If PK is an extra field, do not drop it
                        $dropPK = !($pk_field != '' && isset($this_install_tables[$table]['fields'][$pk_field]) && !isset($attr['fields'][$pk_field]));
                    }
                    // Drop and add PK
                    if ($dropPK) {
                        $diff_tables[] = "ALTER TABLE `$table` DROP INDEX `PRIMARY`;";
                        if ($attr['pk'] != '') {
                            $diff_tables[] = "ALTER TABLE `$table` ADD {$attr['pk']};";
                        }
                    }
					// Re-add FKs
					if (!empty($addFKs)) {
						$diff_tables = array_merge($diff_tables, $addFKs);
					}
				}
			}
			// Check unique keys
			if ($attr['uks'] !== $this_install_tables[$table]['uks']) {
				// Loop through uks
				foreach ($attr['uks'] as $key=>$line) {
					// If key is missing
					if (!isset($this_install_tables[$table]['uks'][$key])) {
						// If key already exists as a normal key, then drop the key first
						if (isset($this_install_tables[$table]['keys'][$key])) {
							$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						}
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
					// If key is different
					elseif ($line != $this_install_tables[$table]['uks'][$key]) {
						$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
				}
			}
			// Check keys
			if ($attr['keys'] !== $this_install_tables[$table]['keys']) {
				// Loop through uks
				foreach ($attr['keys'] as $key=>$line) {
					// If key is missing
					if (!isset($this_install_tables[$table]['keys'][$key])) {
						// If key already exists as a unique key, then drop the unique key first
						if (isset($this_install_tables[$table]['uks'][$key])) {
							$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						}
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
					// If key is different
					elseif ($line != $this_install_tables[$table]['keys'][$key]) {
						$diff_tables[] = "ALTER TABLE `$table` DROP INDEX `$key`;";
						$diff_tables[] = "ALTER TABLE `$table` ADD $line;";
					}
				}
			}
			// Check foreign keys
			if ($attr['fks'] !== $this_install_tables[$table]['fks']) {
				// Loop through uks
				foreach ($attr['fks'] as $key=>$line) {
                    $this_line = $this_install_tables[$table]['fks'][$key] ?? null;
                    // Format FK line to be compatible with certain MySQL versions (e.g., "ON DELETE NO ACTION ON UPDATE NO ACTION" might be omitted in the "create table" statement for MySQL 8)
                    if ($this_line !== null && strpos($this_line, " ON DELETE ") === false) {
                        $this_line = str_replace("` (`$key`)", "` (`$key`) ON DELETE NO ACTION", $this_line);
                    }
                    if ($this_line !== null && strpos($this_line, " ON UPDATE ") === false) {
                        $this_line .= " ON UPDATE NO ACTION";
                    }
					// If key is missing
					if ($this_line === null) {
						$diff_fks[] = "ALTER TABLE `$table` ADD $line;";
					}
					// If key is different
					elseif ($line != $this_line) {
						// Since $key is probably not the name of the FK (since they are often auto-named when created), determine this FK name
						$true_fk_name = $this->get_FK_from_field($table, $key);
						// Add drop/add FK commands
						if ($true_fk_name !== false) {
							$diff_fks[] = "ALTER TABLE `$table` DROP FOREIGN KEY `$true_fk_name`;";
							$diff_fks[] = "ALTER TABLE `$table` ADD $line;";
						}
					}
				}
			}
		}

		## PARSE INSTALL_DATA.SQL
		// Now obtain SQL for any missing rows for tables in install_data.sql
		$install_data_sql_fixes = $this->parse_install_data_sql();

		// Remove any tables beginning with redcap_ztemp_, which are just temp tables
		$temp_tables = array();
		foreach (array_keys($this->get_create_table_for_all_tables()) as $table) {
			if (strpos($table, "redcap_ztemp_20") === 0) {
				$temp_tables[] = "DROP TABLE IF EXISTS `$table`;";
			}
		}

		// Merge all SQL together and return as SQL
		$sql = trim(implode("\n", array_merge($diff_tables, $diff_fks, $install_data_sql_fixes, $temp_tables)));
		// Correct the 'drop primary key' syntax because it's slightly different
		$sql = str_replace("DROP INDEX `PRIMARY`;", "DROP PRIMARY KEY;", $sql);

		// Generate SQL to add primary keys to all tables, if desired (for clustering or replication needs)
		if ($this->usingPrimaryKeyColumns()) {
			$sql .= trim($this->suggestSqlPrimaryKeyAllTables());
		}

		return $sql;
	}


	/**
	 * OBTAIN FOREIGN KEY NAME USING THE FIELD THAT IT REFERENCES IN THE TABLE
	 * NOTE: This will query the existing table that exists in the REDCap database to determine this.
	 * If FALSE is returned, then the FK does not exist.
	 */
	public function get_FK_from_field($table, $column)
	{
		// Find all foreign keys from redcap_mobile_app_log, then delete them, then re-add them (except for log_event_id)
		$sql2 = "SHOW CREATE TABLE `$table`";
		$q = db_query($sql2);
		if ($q && db_num_rows($q) == 1)
		{
			// Get the 'create table' statement to parse
			$result = db_fetch_array($q);
			// Set as lower case to prevent case sensitivity issues
			$createTableStatement = strtolower($result[1]);
			## REMOVE ALL EXISTING FOREIGN KEYS
			// Set regex to pull out strings
			$regex = "/(constraint `)([a-zA-Z0-9_]+)(` foreign key \(`)([a-zA-Z0-9_]+)(`\))/";
			// Do regex
			preg_match_all($regex, $createTableStatement, $matches);
			if (isset($matches[0]) && !empty($matches[0]))
			{
				// Find the foreign key by column
				foreach ($matches[4] as $this_key=>$this_fk)
				{
					if ($this_fk == $column) {
						return $matches[2][$this_key];
					}
				}
			}
		}
		// If we got this far, then we didn't find the FK. So return false.
		return false;
	}


	/**
	 * PARSE "INSERT" STATEMENTS IN INSTALL_DATA.SQL AND PLACE PIECES INTO ARRAY FOR COMPARISON WITH CURRENT ROWS
	 * The table name will be the array key
	 */
	private function parse_install_data_sql()
	{
		// Obtain install_data.sql from Resources/sql/ directory
		$install_data_sql = file_get_contents(APP_PATH_DOCROOT . "Resources/sql/install_data.sql");
		// Replace all \r\n with just \n for compatibility
		$install_data_sql = str_replace("\r\n", "\n", $install_data_sql);
		// Set table name that we're currently on
		$current_table = null;
		// Set insert statement prefix for this table
		$current_table_insert = null;
		// Array that holds table attributes
		$tables = array();
		// Array that holds SQL fixes
		$sql_fixes = array();
		// Loop through file line by line to parse it into an array
		foreach (explode("\n", $install_data_sql) as $line) {
			// Trim it
			$line = trim($line);
			// If blank or a comment, then ignore
			if ($line == '' || substr($line, 0, 3) == '-- ') continue;
			// If first line of table (insert into), then capture table name
			if (strtolower(substr($line, 0, 12)) == 'insert into ') {
				// Get table name
				$current_table = trim(str_replace("`", "", substr($line, 12, strpos($line, " ", 12)-12)));
				// Get insert table prefix
				$tables[$current_table]['insert'] = $line;
				// Detect first field in "insert into" line
				$pos_first_paren = strpos($line, "(")+1;
				$first_field = trim(str_replace("`", "", substr($line, $pos_first_paren, strpos($line, ",")-$pos_first_paren)));
				// Get fields in current table in database
				$tables[$current_table]['fields_current'] = $this->get_current_table_fields($current_table, $first_field);
			}
			// Secondary table line (data row)
			elseif (substr($line, 0, 1) == '(') {
				// Get the value of the first field in the "values" part of the query
				if (substr($line, 1, 1) == "'") {
					$value = trim(substr($line, 2, strpos($line, ",")-3));
				} else {
					$value = trim(substr($line, 1, strpos($line, ",")-1));
				}
				// Convert any double apostrophes to single ones (due to escaping in SQL)
				$value = str_replace("''", "'", $value);
				// If value does not exist in fields_current, then add to fields_missing
				if (!in_array($value, $tables[$current_table]['fields_current'])) {
					// Remove comma and semi-colon on the end
					if (substr($line, -1) == ',' || substr($line, -1) == ';') {
						$line = substr($line, 0, -1);
					}
					// Add to fixes array
					$sql_fixes[] = $tables[$current_table]['insert'] . " $line;";
				}
			} else {
				// Unknown
				continue;
			}
		}
		// Return string of SQL fix statements
		return $sql_fixes;
	}


	/**
	 * RETURN ARRAY OF FIELDS IN DESIRED COLUMN OF DESIRED TABLE
	 */
	private function get_current_table_fields($current_table, $field_name)
	{
		$fields = array();
		$sql = "select $field_name from $current_table";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$fields[] = $row[$field_name];
		}
		return $fields;
	}


	/**
	 * PARSE "CREATE TABLE" STATEMENT AND PLACE PIECES INTO ARRAY
	 * The table name will be the array key with sub-array keys "fields", "pk", "uks", "fks", and "keys"
	 */
	private function parse_install_sql($install_sql)
	{
		// Set table name that we're currently on
		$current_table = null;
		// Array that holds table attributes
		$tables = array();
		// Capture the full "create table" and "alter table" statements to keep in case we need the whole thing
		$create_table = $alter_table = null;
		// Some syntax is not capitalized in earlier versions of MySQL, so replace with capitalized versions
		$orig_syntax = array(' auto_increment', ' default ', ' collate ', ' character set ');
		$repl_syntax = array();
		foreach ($orig_syntax as $i) $repl_syntax[] = strtoupper($i);
		// Loop through file line by line to parse it into an array
		foreach (explode("\n", $install_sql) as $line) {
			// If blank, then ignore
			if ($line == '') continue;
			// Check if we're beginning a new table with CREATE TABLE
			if (substr($line, 0, 13) == 'CREATE TABLE ') {
				$create_table = "$line\n";
				$current_table = trim(str_replace('`', '', substr($line, 13, -2)));
				$tables[$current_table] = array("fields"=>array(), "pk"=>'', "uks"=>array(), "keys"=>array(), "fks"=>array(),
					"create_table"=>"", "create_table_fks"=>"");
			}
			// Check if we're beginning a new FK with ALTER TABLE
			elseif (substr($line, 0, 12) == 'ALTER TABLE ') {
				$alter_table = "$line\n";
				$current_table = trim(str_replace('`', '', substr($line, 12)));
			}
			// If a foreign key
			elseif (substr($line, 0, 16) == 'ADD FOREIGN KEY ') {
				$alter_table .= "$line\n";
				if (substr($line, -1) == ';') {
					$tables[$current_table]['create_table_fks'] = trim($alter_table);
				}
				$key_name = trim(str_replace("`", "", substr($line, 17, strpos($line, "`", 19)-17)));
				$line = substr($line, 4, -1);
				$tables[$current_table]['fks'][$key_name] = $line;
			}
			// If a primary key
			elseif (substr($line, 0, 12) == 'PRIMARY KEY ') {
				// Some versions of MySQL might put 2 spaces after "key", so remove the extra space
				$line = str_replace('PRIMARY KEY  ', 'PRIMARY KEY ', $line);
				// Add line
				$create_table .= "$line\n";
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$tables[$current_table]['pk'] = $line;
			}
			// If a unique key
			elseif (substr($line, 0, 11) == 'UNIQUE KEY ') {
				$create_table .= "$line\n";
				$key_name = trim(str_replace("`", "", substr($line, 11, strpos($line, " ", 11)-11)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$tables[$current_table]['uks'][$key_name] = $line;
			}
			// If a normal index
			elseif (substr($line, 0, 4) == 'KEY ') {
				$create_table .= "$line\n";
				$key_name = trim(str_replace("`", "", substr($line, 4, strpos($line, " ", 4)-4)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$tables[$current_table]['keys'][$key_name] = $line;
			}
			// Last line of "create table"
			elseif (substr($line, 0, 2) == ') ') {
				$create_table .= $line;
				$tables[$current_table]['create_table'] = $create_table;
			}
			// Table field
			else {
				// Some syntax is not capitalized in earlier versions of MySQL, so replace with capitalized versions
				$line = str_replace($orig_syntax, $repl_syntax, $line);
				// Add line
				$create_table .= "$line\n";
				if (!empty($line) && strlen($line) > 2) {
                    $field_name = trim(str_replace("`", "", substr($line, 0, strpos($line, "`", 2))));
                    if (substr($line, -1) == ',') $line = substr($line, 0, -1);
                    $tables[$current_table]['fields'][$field_name] = $line;
                }
			}
		}
		// Return array of table attributes
		return $tables;
	}


	/**
	 * GET "CREATE TABLE" STATEMENT AND SEPARATE FOREIGN KEY ALTER TABLES FROM IT
	 */
	private function split_create_table_fks($table_name, $create_table_statement)
	{
		// Make sure all line breaks are \n and not \r
		$create_table_statement = str_replace(array("\r\n", "\r", "\n\n"), array("\n", "\n", "\n"), trim($create_table_statement));
		// Remove auto_increment number
		if (stripos($create_table_statement, "auto_increment")) {
			$create_table_statement = preg_replace("/(\s+)(AUTO_INCREMENT)(\s*)(=)(\s*)(\d+)(\s+)/", " ", $create_table_statement);
		}
		// Place all SQL into strings, segregating create table statements and foreign key statements
		$create_table = $foreign_keys = $primary_key = $unique_keys = "";
		$foreign_key_array = $unique_key_array = $key_array = array();
		// Separate statement into separate lines
		$create_array = explode("\n", $create_table_statement);
		// Check each line
		foreach ($create_array as $line)
		{
			// Trim the line
			$line = trim($line);
			// If a foreign key
			if (substr($line, 0, 11) == 'CONSTRAINT ') {
				// Format the line
				$fkword_pos = strpos($line, "FOREIGN KEY ");
				$fkline = trim(substr($line, $fkword_pos));
				if (substr($fkline, -1) == ',') $fkline = substr($fkline, 0, -1);
				$fkline = "ADD ".$fkline;
				// Isolate the field names
				$first_paren_pos = strpos($fkline, "(")+1;
				$fk_field = trim(str_replace("`", "", substr($fkline, $first_paren_pos, strpos($fkline, ")")-$first_paren_pos)));
				// Add FK line to FK array
				$foreign_key_array[$fk_field] = $fkline;
			}
			// If a primary key
			elseif (substr($line, 0, 12) == 'PRIMARY KEY ') {
				$primary_key = $line;
			}
			// If a unique key
			elseif (substr($line, 0, 11) == 'UNIQUE KEY ') {
				$key_name = trim(str_replace("`", "", substr($line, 11, strpos($line, " ", 11)-11)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$unique_key_array[$key_name] = $line;
			}
			// If a normal index
			elseif (substr($line, 0, 4) == 'KEY ') {
				$key_name = trim(str_replace("`", "", substr($line, 4, strpos($line, " ", 4)-4)));
				if (substr($line, -1) == ',') $line = substr($line, 0, -1);
				$key_array[$key_name] = $line;
			}
			// Table field
			else {
				$create_table .= "\n$line";
			}
		}
		// Format strings
		$create_table = $this->remove_comma_from_create_table(trim($create_table).";");
		// Insert primary key into create_table statement above the last line
		if ($primary_key != '') {
			$last_line_break_pos = strrpos($create_table, "\n");
			$create_table = substr($create_table, 0, $last_line_break_pos) . ",\n$primary_key" . substr($create_table, $last_line_break_pos);
			$create_table = $this->remove_comma_from_create_table($create_table);
		}
		// Sort the UKs for consistency from install to install and insert into create table statement
		if (!empty($unique_key_array)) {
			ksort($unique_key_array);
			$last_line_break_pos = strrpos($create_table, "\n");
			$create_table = substr($create_table, 0, $last_line_break_pos) . ",\n" . implode(",\n", $unique_key_array) . substr($create_table, $last_line_break_pos);
			$create_table = $this->remove_comma_from_create_table($create_table);
		}
		// Sort the keys for consistency from install to install and insert into create table statement
		if (!empty($key_array)) {
			ksort($key_array);
			$last_line_break_pos = strrpos($create_table, "\n");
			$create_table = substr($create_table, 0, $last_line_break_pos) . ",\n" . implode(",\n", $key_array) . substr($create_table, $last_line_break_pos);
			$create_table = $this->remove_comma_from_create_table($create_table);
		}
		// Sort the FKs for consistency from install to install
		if (!empty($foreign_key_array)) {
			ksort($foreign_key_array);
			$foreign_keys = "ALTER TABLE `$table_name`\n".implode(",\n", $foreign_key_array).";";
		}
		// Return the strings
		return array($create_table, $foreign_keys);
	}


	/**
	 * REMOVE COMMA FROM END OF SECOND-TO-LAST LINE OF "CREATE TABLE" STATEMENT
	 */
	private function remove_comma_from_create_table($create_table)
	{
		$create_array = explode("\n", $create_table);
		$second_to_last_key = count($create_array)-2;
		if (substr($create_array[$second_to_last_key], -1) == ',') {
			$create_array[$second_to_last_key] = substr($create_array[$second_to_last_key], 0, -1);
			$create_table = implode("\n", $create_array);
		}
		return $create_table;
	}


	/**
	 * BUILD INSTALL.SQL FILE FROM "SHOW CREATE TABLE" OF ALL REDCAP TABLES
	 */
	public function build_install_file_from_tables($forceConvertMb4=false, $addCollationToGoldStandard=false, $removePkId=true)
	{
		// Set "show create table" to quote the table names with backticks
		db_query("SET SESSION sql_quote_show_create = 1");
		// Place all SQL into strings, segregating create table statements and foreign key statements
		$create_table = $foreign_keys = array();
		// Get create table statement for all tables
		$tables = $this->get_create_table_for_all_tables();
		// Loop through each table and get CREATE TABLE sql
		foreach ($tables as $table=>$create_table_sql) {
			// Get CREATE TABLE statement with separate FK piece
			$this_create_table = $this->split_create_table_fks($table, $create_table_sql);
            // Remove any rows containing `pk_id` (the optional auto-incrementing Primary Key)
            if ($removePkId && strpos($this_create_table[0], "`pk_id`") !== false) {
                $this_table = [];
                foreach (explode("\n", $this_create_table[0]) as $this_row) {
                    if (strpos($this_row, "`pk_id`") === false) {
                        $this_table[] = $this_row;
                    }
                }
                $this_create_table[0] = implode("\n", $this_table);
            }
			// Add SQL to arrays
			$create_table[] = $this_create_table[0];
			if ($this_create_table[1] != '') {
				$foreign_keys[] = $this_create_table[1];
			}
		}
		// Build SQL file
		$sql = implode("\n\n", $create_table) . "\n\n" . implode("\n\n", $foreign_keys);
		// Replace all \r\n with just \n for compatibility
		$sql = str_replace("\r\n", "\n", $sql);
		// Because MySQl 8.0+ introduces "CHARACTER SET utf8XXXX" and "ZEROFILL" for field definitions, remove this for compatibility
		$sql = str_replace(" CHARACTER SET utf8mb4", "", $sql);
		$sql = str_replace(" CHARACTER SET utf8mb3", "", $sql);
		$sql = str_replace(" CHARACTER SET utf8", "", $sql);
		$sql = str_replace(" ZEROFILL", "", $sql);
		// Because MariaDB 10.2+ uses the syntax "DEFAULT 0" instead of "DEFAULT '0'", compensate for that
		$sql = preg_replace("/( DEFAULT )(\d+)/", "$1'$2'", $sql);
		// Because MariaDB 10.2+ adds "DEFAULT NULL" to column definitions for TEXT and BLOB types that have default null, compensate for that
		// by adding it across the board (because it's much easier to remove it than to figure out where to insert it).
		$sql = str_replace("text COLLATE utf8mb4_unicode_ci", "text COLLATE utf8mb4_unicode_ci DEFAULT NULL", $sql);
		$sql = str_replace("text COLLATE utf8mb3_unicode_ci", "text COLLATE utf8mb3_unicode_ci DEFAULT NULL", $sql);
		$sql = str_replace("text COLLATE utf8_unicode_ci", "text COLLATE utf8_unicode_ci DEFAULT NULL", $sql);
		$sql = str_replace("` longblob", "` longblob DEFAULT NULL", $sql);
		$sql = str_replace("` blob", "` blob DEFAULT NULL", $sql);
		$sql = str_replace("NOT NULL DEFAULT current_timestamp()", "NOT NULL DEFAULT CURRENT_TIMESTAMP", $sql);
		// Because MariaDB 10.6.1+ now reports character sets and collations in 'utf8' columns and tables as 'utf8mb3', remove mb3 for backward compatibility to match install sql.
		$sql = str_replace(" COLLATE utf8mb3_unicode_ci", " COLLATE utf8_unicode_ci", $sql);
		$sql = str_replace(" COLLATE utf8mb3_bin", " COLLATE utf8_bin", $sql);
		$sql = str_replace("=utf8mb3", "=utf8", $sql);
		// Fix any duplicates caused in replacements above
		$sql = str_replace("DEFAULT NULL DEFAULT NULL", "DEFAULT NULL", $sql);
		$sql = str_replace("DEFAULT NULLDEFAULT NULL", "DEFAULT NULL", $sql);
		$sql = str_replace("DEFAULT NULLNOT NULL", "NOT NULL", $sql);
		$sql = str_replace("DEFAULT NULL NOT NULL", "NOT NULL", $sql);
		// If a table is missing COLLATE in its "create table" statement, auto-add it
		$sql = str_replace(" CHARSET=utf8mb4;", " CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", $sql);
		$sql = str_replace(" CHARSET=utf8;", " CHARSET=utf8 COLLATE=utf8_unicode_ci;", $sql);
        // Do not include ROW_FORMAT settings (we'll deal with this via Configuration Check page separately)
		$sql = str_replace([" ROW_FORMAT=COMPACT", " ROW_FORMAT=DYNAMIC", " ROW_FORMAT=REDUNDANT", " ROW_FORMAT=COMPRESSED"], "", $sql);
        // If we're building the gold standard install.sql on a system where "COLLATE utf8mb4_unicode_ci " does not exist in "show create table" results
        // (because using newer MariaDB version), then manually add COLLATE to specific column types in install.sql.
        if ($addCollationToGoldStandard) {
            // Add "COLLATE utf8mb4_unicode_ci " to all columns with the following data type: char, varchar, longtext, mediumtext, enum
            $sql = preg_replace("/(` )(char\(\d{1,4}\)|varchar\(\d{1,4}\)|longtext|mediumtext|text|enum\(.+\))( )/", "` $2 COLLATE utf8mb4_unicode_ci ", $sql);
	        // Remove some false positives for utf8mb4_bin
	        $sql = str_replace("COLLATE utf8mb4_unicode_ci COLLATE utf8mb4_bin ", "COLLATE utf8mb4_bin ", $sql);
            // Remove some false positives where we want to keep latin1 collation
            $sql = str_replace("COLLATE utf8mb4_unicode_ci CHARACTER SET latin1 ", "CHARACTER SET latin1 ", $sql);
        }
		// Return SQL
		return trim($sql);
	}


	/**
	 * DETECT IF INNODB ENGINE IS ENABLED IN MYSQL. Return boolean.
	 */
	public function innodb_enabled()
	{
		$q = db_query("SHOW ENGINES");
		while ($row = db_fetch_assoc($q)) {
			if ($row['Engine'] == 'InnoDB') {
				return (strtoupper($row['Support']) != 'NO');
			}
		}
		return false;
	}


	/**
	 * FILTER/SET THE COLLATION PROPERLY FOR A BLOCK OF SQL QUERIES (UTF8 VS UTF8MB4, BASED ON CONFIG SETTING)
	 */
	public static function filterSqlCollation($sql)
	{
		// If db is using UT8 instead of UTF8MB4, then remove MB4 from SQL
		if (!self::using_utf8mb4()) {
			$sql = str_replace("utf8mb4", "utf8", $sql);
			// Only utf8mb4 encoded tables will have a 191-char limit in keys, so for backwards compatibility, remove it from SQL
			$sql = str_replace("`(191)", "`", $sql);
		}
		return $sql;
	}

	// Parse a block of multiple SQL queries, and return them as individual queries in an array
	public static function parseMultipleSqlQueries($sql)
	{
		//START_OF_PARSER
		$iCur = 0;            //Current character pointer inside the SQL content
		$iInside = 0;         //The context, in which the pointer is currently located (is the pointer inside a
		//comment, an SQL query, or deeper into an SQL query value?)
		$sBuffer = "";        //The buffer of the next individual query
		$aQueries = array();  //The list of queries
		$sFileContents = $sql;
		while($iCur < strlen($sFileContents)) {

			switch ($iInside) {
				case 0: //Inside query-context
					//Change context: Comments beginning with --
					if(substr($sFileContents, $iCur, 2) === "--") {
						$iCur++;
						$iInside = 2;

						//Change context: Comments beginning with /*
					} elseif(substr($sFileContents, $iCur, 2) === "/*") {
						$iCur++;
						$iInside = 3;

						//Change context: Comments beginning with #
					} elseif(substr($sFileContents, $iCur, 1) === "#") {
						$iInside = 2;

						//Separator for a new query
					} elseif(substr($sFileContents, $iCur, 1) === ";") {
						$aQueries[] = trim($sBuffer); //$sBuffer;  //Add current buffer to a unique array query item
						$sBuffer = "";  //Start a new buffer

						//Change context: query values opened with '
					} elseif(substr($sFileContents, $iCur, 1) === "'") {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 1;

						//Change context: query values opened with "
					} elseif(substr($sFileContents, $iCur, 1) === '"') {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 4;

						//Not a special character
					} else {
						$sBuffer .= substr($sFileContents, $iCur, 1);
					}
					break;

				case 1: //Inside value-context, ending with '

					//Escaping character found within the query-value
					if(substr($sFileContents, $iCur, 1) === "\\") {
						$sBuffer .= substr($sFileContents, $iCur, 2);
						$iCur++;  //Skip next char

						//The ending character for the query-value is found
					} elseif(substr($sFileContents, $iCur, 1) === "'") {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 0;

						//Not a special character
					} else {
						$sBuffer .= substr($sFileContents, $iCur, 1);
					}
					break;

				case 4: //Inside value-context, ending with "

					//Escaping character found within the query-value
					if(substr($sFileContents, $iCur, 1) === "\\") {
						$sBuffer .= substr($sFileContents, $iCur, 2);
						$iCur = $iCur + 1;  //Skip next char

						//The ending character for the query-value is found
					} elseif(substr($sFileContents, $iCur, 1) === '"') {
						$sBuffer .= substr($sFileContents, $iCur, 1);
						$iInside = 0;

						//Not a special character
					} else {
						$sBuffer .= substr($sFileContents, $iCur, 1);
					}
					break;

				case 2: //Inside comment-context, ending with newline

					//A two-character newline is found, signalling the end of the comment
					if(substr($sFileContents, $iCur, 2) === "\r\n") {
						$iCur++;
						$iInside = 0;

						//A single-character newline is found, signalling the end of the comment
					} elseif(substr($sFileContents, $iCur, 1) === "\n" || substr($sFileContents, $iCur, 1) === "\r") {
						$iInside = 0;
					}
					break;

				case 3: //Inside comment-context, ending with */

					//A two-character */ is found, signalling the end of the comment
					if(substr($sFileContents, $iCur, 2) === "*/") {
						$iCur++;
						$iInside = 0;
					}
					break;

				default:
					break;
			}
			$iCur++;
		}
		//END_OF_PARSER
		return $aQueries;
	}
    
    // Find all table columns whose data needs to be converted
    public static function getRedcapColsToConvert()
    {
        // Set REDCap table column whose data should NOT be converted (not including any columns that include "%field_name%", which will always be ignored)
        // (2023) Note regarding emails - Email addresses can contain accented characters, but not sure if this is allowed everywhere in REDCap.
        // https://gmail.googleblog.com/2014/08/a-first-step-toward-more-global-email.html
        // Will keep the pre-existing email fields in this blocklist but will refrain from adding more.
        $columnsNotConvertData = [
            "redcap_auth" => ["password", "password_salt", "password_answer", "password_reset_key"],
            "redcap_auth_history" => ["password"],
            "redcap_sendit_recipients" => [], // Ignore whole table
            "redcap_sessions" => [], // Ignore whole table
            "redcap_surveys_emails_recipients" => [], // Ignore whole table
            "redcap_surveys_erase_twilio_log" => [], // Ignore whole table
            "redcap_user_information" => [], // Ignore whole table
            "redcap_instrument_zip" => [], // Ignore whole table
            "redcap_instrument_zip_origins" => ["server_name"],
            "redcap_ehr_access_tokens" => [], // Ignore whole table
            "redcap_crons_history" => [], // Ignore whole table
            "redcap_crons" => [], // Ignore whole table
            "redcap_dashboard_ip_location_cache" => [], // Ignore whole table
            "redcap_ehr_fhir_logs" => [], // Ignore whole table
            "redcap_external_modules_downloads" => [], // Ignore whole table
            "redcap_external_modules" => [], // Ignore whole table
            "redcap_history_version" => [], // Ignore whole table
            "redcap_ip_cache" => [], // Ignore whole table
            "redcap_ip_banned" => [], // Ignore whole table
            "redcap_validation_types" => ["legacy_value", "regex_js", "regex_php", "validation_name"],
            "redcap_ddp_preview_fields" => [], // Ignore whole table
            "redcap_ddp_log_view" => [], // Ignore whole table
            "redcap_ddp_log_view_data" => [], // Ignore whole table
            "redcap_ddp_mapping" => [], // Ignore whole table
            "redcap_ddp_records_data" => [], // Ignore whole table
            "redcap_edocs_metadata" => ["stored_name", "mime_type", "file_extension"],
            "redcap_ehr_datamart_revisions" => [], // Ignore whole table
            "redcap_metadata" => ["field_phi", "element_type", "element_validation_type", "element_validation_checktype", "grid_name", "video_url", "element_validation_min", "element_validation_max"],
            "redcap_metadata_temp" => ["field_phi", "element_type", "element_validation_type", "element_validation_checktype", "grid_name", "video_url", "element_validation_min", "element_validation_max"],
            "redcap_metadata_archive" => ["field_phi", "element_type", "element_validation_type", "element_validation_checktype", "grid_name", "video_url", "element_validation_min", "element_validation_max"],
            "redcap_messages" => ["stored_url"],
            "redcap_mobile_app_devices" => ["uuid"],
            "redcap_events_metadata" => ["external_id"],
            "redcap_external_links" => ["link_url"],
            "redcap_docs" => ["docs_type", "docs_rights"],
            "redcap_library_map" => ["promis_key", "promis_battery_key"],
            "redcap_external_module_settings" => ["key", "type"],
            "redcap_external_modules_log" => ["ip"],
            "redcap_external_modules_log_parameters" => ["name"],
            "redcap_todo_list" => ["request_to", "todo_type", "action_url", "status"],
            "redcap_surveys" => ["theme_text_buttons", "theme_bg_page", "theme_text_title", "theme_bg_title", "theme_text_sectionheader", "theme_bg_sectionheader", "theme_text_question", "theme_bg_question", "survey_expiration", "confirmation_email_from", "text_to_speech_language"],
            "redcap_surveys_themes" => ["theme_text_buttons", "theme_bg_page", "theme_text_title", "theme_bg_title", "theme_text_sectionheader", "theme_bg_sectionheader", "theme_text_question", "theme_bg_question"],
            "redcap_user_rights" => [],
            "redcap_surveys_short_codes" => [],
            "redcap_two_factor_response" => [],
            "redcap_user_roles" => ["data_entry", "external_module_config"],
            "redcap_surveys_response" => ["return_code", "results_code"],
            "redcap_surveys_queue_hashes" => ["hash"],
            "redcap_reports" => ["hash", "short_url", "dynamic_filter1", "dynamic_filter2", "dynamic_filter3"], // dynamic filters are field names only
            "redcap_actions" => ["custom_text"],
            "redcap_surveys_pdf_archive" => ["ip"],
            "redcap_project_dashboards" => ["hash", "short_url"],
            "redcap_surveys_participants" => ["hash", "legacy_hash", "access_code", "access_code_numeral", "participant_email", "participant_phone"],
            "redcap_log_event" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event2" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event3" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event4" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event5" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event6" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event7" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event8" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_event9" => ["user", "sql_log", "object_type", "page", "ip", "description"],
            "redcap_log_view" => ["ip", "browser_name", "browser_version", "page", "full_url"],
            "redcap_log_view_old" => [ "ip", "browser_name", "browser_version", "page", "full_url"],
            "redcap_alerts_sent_log" => ["email_from", "email_to", "phone_number_to", "email_cc", "email_bcc"],
            "redcap_alerts" => ["email_from", "email_to", "phone_number_to", "email_cc", "email_bcc", "email_attachment_variable", "email_failed", "sendgrid_template_id"],
            "redcap_outgoing_email_sms_log" => ["sender", "recipients", "email_cc", "email_bcc", "attachment_doc_ids", "hash", "instrument"],
            "redcap_projects" => ["project_name", "completed_by", "log_event_table", "__SALT__", "auth_meth", "project_language", "is_child_of", "project_contact_email", "headerlogo", "order_id_by", "project_pi_email", "project_irb_number", "secondary_pk", "data_entry_trigger_url", "twilio_account_sid", "twilio_auth_token", "twilio_voice_language", "mosio_api_key", "sendgrid_project_api_key"],
            "redcap_cde_cache" => ["tinyId"],
            "redcap_cde_field_mapping" => ["tinyId", "publicId", "questionId"],
            "redcap_events_calendar" => ["event_time"],
            "redcap_multilanguage_metadata" => ["type", "name"], // field name - ASCII only
            "redcap_multilanguage_metadata_temp" => ["type", "name"], // field name - ASCII only
            "redcap_multilanguage_ui" => ["item"], // field name - ASCII only
            "redcap_multilanguage_ui_temp" => ["item"], // field name - ASCII only
            "redcap_mycap_projectfiles" => ["project_code"],
            "redcap_mycap_syncissues" => ["project_code", "resolved_by"],
            "redcap_pub_articles" => ["pub_id"],
            "redcap_sendit_docs" => ["doc_type"],
            "redcap_mycap_aboutpages" => ["identifier"], // looks like a GUID
            "redcap_mycap_contacts" => ["identifier","phone_number"],
            "redcap_mycap_links" => ["identifier", "link_icon"],
            "redcap_mycap_messages" => ["processed_by"],
            "redcap_mycap_participants" => ["push_notification_ids"],
            "redcap_mycap_projects" => ["code", "hmac_key"],
            "redcap_mycap_tasks" => ["question_format", "card_display", "schedule_relative_to", "schedule_type", "schedule_frequency", "schedule_ends"], // Possible values given in table comments, all ASCII
            "redcap_mycap_themes" => ["primary_color", "light_primary_color", "accent_color", "dark_primary_color", "light_bg_color", "theme_type", "system_type"], // Color codes and systematic values, all ASCII
            "redcap_surveys_phone_codes" => ["phone_number", "twilio_number", "access_code"],
            "redcap_folders" => ["foreground", "background"]
        ];
        // Loop through all REDCap tables
        $redcapTables = SQLTableCheck::getRedcapTables();
        $redcapCols = [];
        $allColsAllTables = [];
        $nonProjectTables = [];
        foreach ($redcapTables as $table) {
            $allColsAllTables[$table] = getTableColumns($table);
            // Ignore table?
            if (isset($columnsNotConvertData[$table]) && empty($columnsNotConvertData[$table])) {
                continue;
            }
            // Get columns and add any text/varchars to array (always ignore fields named '%field_name%')
            // (All of the fixed length char fields are either hashes or uuid's.)
            $sql2 = "SHOW COLUMNS FROM `$table` WHERE `Field` not like '%field_name%' and `Field` not like '%form_name%' and `Field` not like '%user%' 
				 and `Field` not like '%unique%' and `Field` not like '%session_id%' and `Field` not like '%\_field%' and `Field` not like '%\lang_id%'
				  and `Field` not like '%\instrument%' and `Field` not like '%hash'";

            $q2 = db_query($sql2);
            while ($row2 = db_fetch_assoc($q2)) {
                $colType = strtolower($row2['Type']);
                $colName = $row2['Field'];
                // Only convert data for text/varchars
                if ((in_array($colType, ['text', 'mediumtext', 'longtext']) || strpos($colType, "varchar") === 0)
                    // Skip this field if we are ignoring it manually
                    && !(isset($columnsNotConvertData[$table]) && in_array($colName, $columnsNotConvertData[$table]))
                ) {
                    $redcapCols[$table][] = $colName;
                }
            }
        }
        ksort($redcapCols);
        foreach ($redcapCols as $table => $cols) {
            $allCols = $allColsAllTables[$table];
            // classify as non-project-table if table is missing project/event/survey id and doesn't match the following strings.
            // all tables that DO match the following strings will stay in redcapCols (will not be moved to nonProjectTables).
            if (!(array_key_exists('project_id', $allCols) || array_key_exists('survey_id', $allCols) || array_key_exists('event_id', $allCols)
                || strpos($table, "_surveys") !== false || strpos($table, "_randomization") !== false || strpos($table, "_alerts") !== false
                || strpos($table, "_data_quality") !== false || strpos($table, "_external_modules") !== false
            )) {
                // Move table from $redcapCols to $nonProjectTables
                $nonProjectTables[$table] = $redcapCols[$table];
                unset($redcapCols[$table]);
            }
        }
        return [$redcapCols, $allColsAllTables, $nonProjectTables];
    }
    
    // Return all redcap tables as an array
    public static function getRedcapTables()
    {
        $redcapTables = [];
        $q = db_query("select `table_name` from information_schema.tables where `table_schema` = database() and `table_type` = 'BASE TABLE' and table_name like 'redcap\_%'");
        while ($row = db_fetch_array($q)) {
            $redcapTables[] = $row[0];
        }
        return $redcapTables;
    }
    
    // Get Xth occurrence of a character
    public static function strposX($haystack, $needle, $number=0)
    {
        return strpos($haystack, $needle, (($number > 1) ? (SQLTableCheck::strposX($haystack, $needle, $number - 1) + strlen($needle)) : 0));
    }
    
    // Generate SQL to update a table's data
    public static function generateUpdateSql($allColsAllTables, $table, $col, $pids=[], $sids=[], $eids=[], $pids_include=true, $null_pids_include=false, $forceSkipTablesNoPidSidEid=false)
    {
        $tablesNoPidSidEid = ['redcap_alerts_sent_log', 'redcap_data_quality_resolutions', 'redcap_external_modules_log_parameters',
                              'redcap_randomization_allocation', 'redcap_surveys_response', 'redcap_surveys_scheduler_queue', 'redcap_surveys_themes'];
        $tablesPossibleNullPid = ['redcap_external_links', 'redcap_external_module_settings', 'redcap_external_modules_log', 'redcap_external_modules_log_parameters', 'redcap_messages_threads',
                                  'redcap_multilanguage_config', 'redcap_multilanguage_ui', 'redcap_edocs_metadata ', 'redcap_outgoing_email_sms_log', 'redcap_pub_matches', 'redcap_todo_list']; // Deal with redcap_log_event separately
        // For expediency, skip for Vanderbilt
        if (!isVanderbilt()) {
            $tablesPossibleNullPid[] = 'redcap_log_view';
        }
        // Get table columns
        $allCols = $allColsAllTables[$table];
        // If the column defaults to NULL, add extra check to ignore NOT NULLs to make the query faster
        $sqlNull = ($allCols[$col] === null) ? "`$col` is not null and " : "";
        // Build the query to return
        $sql = "update ignore `$table`
	set `$col` = if((length(@value_converted:=convert(binary convert(`$col` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`$col`) - char_length(replace(`$col`,'?','')))) > 0)), `$col`, @value_converted)
	where {$sqlNull}length(`$col`) > char_length(`$col`)";
        if ($pids === null) {
            // Skip table if it has no PID column
            if (in_array($table, $tablesNoPidSidEid)) return "";
            // Skip table if the table is only used by projects, thus project_id will never be null
            if (!in_array($table, $tablesPossibleNullPid)) return "";
            $sql .= " and project_id is null";
        } elseif (is_array($pids) && !empty($pids)) {
            // Project Tables - namely 2nd Half of Step 2A ("Active Projects"), and Step 2B ("Inactive Projects")
            // Detect if the db table contains project_id, survey_id, or event_id field so we know which to use in WHERE clause
            if (array_key_exists('project_id', $allCols)) {
                // project_id
                if ($pids_include) {
                    // Vanderbilt only: For Step 2 alt, skip the log_view, outgoing_email_sms_log, and log_eventX tables for expediency
                    if (isVanderbilt()) {
                        if (in_array($table, ['redcap_log_view', 'redcap_log_view_old', 'redcap_outgoing_email_sms_log'])) return "";
                        if (strpos($table, 'redcap_log_event') === 0) return "";
                    }
                    if ($null_pids_include) {
                        $sql .= " and (project_id is null or project_id in (".implode(",", $pids)."))";
                    } else {
                        $sql .= " and project_id in (".implode(",", $pids).")";
                    }
                } else {
                    $sql .= " and project_id is not null and project_id not in (".implode(",", $pids).")";
                }
            } elseif (array_key_exists('survey_id', $allCols) && empty($sids) && $pids_include) {
                // If project has no surveys, then skip this survey_id-related table
                return "";
            } elseif (array_key_exists('survey_id', $allCols) && !empty($sids)) {
                // survey_id
                if (!$pids_include) $sql .= " and survey_id is not null";
                $sql .= " and survey_id ".($pids_include ? "in" : "not in")." (".implode(",", $sids).")";
            } elseif (array_key_exists('event_id', $allCols) && empty($eids) && $pids_include) {
                // If project has no events (how?), then skip this event_id-related table
                return "";
            } elseif (array_key_exists('event_id', $allCols) && !empty($eids)) {
                // event_id
                if (!$pids_include) $sql .= " and event_id is not null";
                $sql .= " and event_id ".($pids_include ? "in" : "not in")." (".implode(",", $eids).")";
            } elseif (($forceSkipTablesNoPidSidEid || !$pids_include) && in_array($table, $tablesNoPidSidEid)) {
                // For specific tables that don't have project_id, survey_id, or event_id, skip them here for the inactive
                // project SQL block so that we don't add the same PID-less query for both active and inactive project SQL blocks
                // For accounting clarity, note that these tables...
                // -  *Are already* included in the "active" projects block - since $pids_include = true, thus bypassing all elseif conditions and returning the $sql.
                // -  Were excluded from the "non-project" array - where an if condition prevented these tables from getting moved into the non project tables array.
                // -  Will not be included in the "inactive" projects array - since $pids_include is False and $table is in the $NoPidSidEid array.
                return "";
            }
        }
        $sql .= ";\n";
        return $sql;
    }
    
    // Wrap SQL block in commands (db set, transactions, etc.) to let it run without issues
    public static function wrapSqlDbFlags($sql, $transaction=false, $comment="SQL to execute", $disableFkChecks=false, $setDataFixConfigFlagComplete=false)
    {
        $fkc1 = $disableFkChecks ? "SET FOREIGN_KEY_CHECKS = 0;\n" : "";
        $fkc2 = $disableFkChecks ? "SET FOREIGN_KEY_CHECKS = 1;\n" : "";
        $transaction1 = $transaction ? "SET AUTOCOMMIT = 0;\nSET NAMES utf8mb4;\nBEGIN;\n" : "";
        $transaction2 = $transaction ? "COMMIT;\nSET AUTOCOMMIT = 1;" : "";
        $setDataFixConfigFlag = "";
        if ($setDataFixConfigFlagComplete !== false && isset($GLOBALS[$setDataFixConfigFlagComplete])) {
            $setDataFixConfigFlag = "-- Set config flag to show that we have completed this step\nreplace into redcap_config (field_name, value) values ('$setDataFixConfigFlagComplete', '1');\n";
        }
        return "-- $comment
USE `{$GLOBALS['db']}`;
SET SESSION SQL_SAFE_UPDATES = 0;
{$transaction1}{$fkc1}{$sql}{$setDataFixConfigFlag}{$fkc2}{$transaction2}";
    }

    // Unicode Transformation for database tables: Single project conversion
    public static function convertSingleProject($singlePid)
    {
        $skipTables = [];
        $skipTables2 = [];
        $singlePidSurveyIds = [];
        $singlePidEventIds = [];
        // Get table columns
        list ($redcapCols, $allColsAllTables, $nonProjectTables) = SQLTableCheck::getRedcapColsToConvert();
        if (isinteger($singlePid)) {
            // Get all survey_ids for this PID
            $sql = "select survey_id from redcap_surveys where project_id = $singlePid";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $singlePidSurveyIds[] = $row['survey_id'];
            }
            // Get all event_ids for this PID
            $sql = "select event_id from redcap_events_arms a, redcap_events_metadata e where a.arm_id = e.arm_id and a.project_id = $singlePid";
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $singlePidEventIds[] = $row['event_id'];
            }
            // Get the project's data table name so that we can skip the others to save time
            $skipTables = \Records::getDataTables();
            $thisPidDataTable = \Records::getDataTable($singlePid);
            foreach ($skipTables as $key=>$table) {
                if ($table == $thisPidDataTable) unset($skipTables[$key]);
            }
            // Get the project's log_event table name so that we can skip the others to save time
            $skipTables2 = \Logging::getLogEventTables();
            $thisPidLogEventTable = \Logging::getLogEventTable($singlePid);
            foreach ($skipTables2 as $key=>$table) {
                if ($table == $thisPidLogEventTable) unset($skipTables2[$key]);
            }
        }
        // Loop through all columns/tables
        $singlePidSql = "";
        foreach ($redcapCols as $table => $cols) {
            if ($singlePid !== null && isset($nonProjectTables[$table])) continue;
            if (in_array($table, $skipTables) || in_array($table, $skipTables2)) continue;
            foreach ($cols as $col) {
                $singlePidSql .= SQLTableCheck::generateUpdateSql($allColsAllTables, $table, $col, ($singlePid === null ? null : [$singlePid]), $singlePidSurveyIds, $singlePidEventIds, true, false, true);
            }
        }
        // Set the project-level setting for this project
        if (isinteger($singlePid)) {
            $singlePidSql .= "update `redcap_projects` set `project_db_character_set` = 'utf8mb4', `project_db_collation` = 'utf8mb4_unicode_ci' where `project_id` = $singlePid;\n";
        }
        // Non project table data conversions
        else {
            $singlePidSql .= "
update ignore `redcap_data_quality_resolutions`
	set `comment` = if((length(@value_converted:=convert(binary convert(`comment` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`comment`) - char_length(replace(`comment`,'?','')))) > 0)), `comment`, @value_converted)
	where `comment` is not null and length(`comment`) > char_length(`comment`);
update ignore `redcap_auth_questions`
    set `question` = if((length(@value_converted:=convert(binary convert(`question` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`question`) - char_length(replace(`question`,'?','')))) > 0)), `question`, @value_converted)
    where `question` is not null and length(`question`) > char_length(`question`);
update ignore `redcap_cde_cache`
    set `publicId` = if((length(@value_converted:=convert(binary convert(`publicId` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`publicId`) - char_length(replace(`publicId`,'?','')))) > 0)), `publicId`, @value_converted)
    where `publicId` is not null and length(`publicId`) > char_length(`publicId`);
update ignore `redcap_cde_cache`
    set `steward` = if((length(@value_converted:=convert(binary convert(`steward` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`steward`) - char_length(replace(`steward`,'?','')))) > 0)), `steward`, @value_converted)
    where `steward` is not null and length(`steward`) > char_length(`steward`);
update ignore `redcap_cde_cache`
    set `question` = if((length(@value_converted:=convert(binary convert(`question` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`question`) - char_length(replace(`question`,'?','')))) > 0)), `question`, @value_converted)
    where `question` is not null and length(`question`) > char_length(`question`);
update ignore `redcap_cde_cache`
    set `choices` = if((length(@value_converted:=convert(binary convert(`choices` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`choices`) - char_length(replace(`choices`,'?','')))) > 0)), `choices`, @value_converted)
    where `choices` is not null and length(`choices`) > char_length(`choices`);
update ignore `redcap_config`
    set `value` = if((length(@value_converted:=convert(binary convert(`value` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`value`) - char_length(replace(`value`,'?','')))) > 0)), `value`, @value_converted)
    where `value` is not null and length(`value`) > char_length(`value`);
update ignore `redcap_custom_queries`
    set `title` = if((length(@value_converted:=convert(binary convert(`title` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`title`) - char_length(replace(`title`,'?','')))) > 0)), `title`, @value_converted)
    where `title` is not null and length(`title`) > char_length(`title`);
update ignore `redcap_custom_queries`
    set `query` = if((length(@value_converted:=convert(binary convert(`query` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`query`) - char_length(replace(`query`,'?','')))) > 0)), `query`, @value_converted)
    where `query` is not null and length(`query`) > char_length(`query`);
update ignore `redcap_error_log`
    set `error` = if((length(@value_converted:=convert(binary convert(`error` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`error`) - char_length(replace(`error`,'?','')))) > 0)), `error`, @value_converted)
    where `error` is not null and length(`error`) > char_length(`error`);
update ignore `redcap_folders`
    set `name` = if((length(@value_converted:=convert(binary convert(`name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`name`) - char_length(replace(`name`,'?','')))) > 0)), `name`, @value_converted)
    where `name` is not null and length(`name`) > char_length(`name`);
update ignore `redcap_instrument_zip_authors`
    set `author_name` = if((length(@value_converted:=convert(binary convert(`author_name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`author_name`) - char_length(replace(`author_name`,'?','')))) > 0)), `author_name`, @value_converted)
    where `author_name` is not null and length(`author_name`) > char_length(`author_name`);
update ignore `redcap_messages`
    set `message_body` = if((length(@value_converted:=convert(binary convert(`message_body` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`message_body`) - char_length(replace(`message_body`,'?','')))) > 0)), `message_body`, @value_converted)
    where `message_body` is not null and length(`message_body`) > char_length(`message_body`);
update ignore `redcap_mycap_projectfiles`
    set `name` = if((length(@value_converted:=convert(binary convert(`name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`name`) - char_length(replace(`name`,'?','')))) > 0)), `name`, @value_converted)
    where `name` is not null and length(`name`) > char_length(`name`);
update ignore `redcap_mycap_syncissues`
    set `participant_code` = if((length(@value_converted:=convert(binary convert(`participant_code` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`participant_code`) - char_length(replace(`participant_code`,'?','')))) > 0)), `participant_code`, @value_converted)
    where `participant_code` is not null and length(`participant_code`) > char_length(`participant_code`);
update ignore `redcap_mycap_syncissues`
    set `payload` = if((length(@value_converted:=convert(binary convert(`payload` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`payload`) - char_length(replace(`payload`,'?','')))) > 0)), `payload`, @value_converted)
    where `payload` is not null and length(`payload`) > char_length(`payload`);
update ignore `redcap_mycap_syncissues`
    set `error_message` = if((length(@value_converted:=convert(binary convert(`error_message` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`error_message`) - char_length(replace(`error_message`,'?','')))) > 0)), `error_message`, @value_converted)
    where `error_message` is not null and length(`error_message`) > char_length(`error_message`);
update ignore `redcap_mycap_syncissues`
    set `resolved_comment` = if((length(@value_converted:=convert(binary convert(`resolved_comment` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`resolved_comment`) - char_length(replace(`resolved_comment`,'?','')))) > 0)), `resolved_comment`, @value_converted)
    where `resolved_comment` is not null and length(`resolved_comment`) > char_length(`resolved_comment`);
update ignore `redcap_page_hits`
    set `page_name` = if((length(@value_converted:=convert(binary convert(`page_name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`page_name`) - char_length(replace(`page_name`,'?','')))) > 0)), `page_name`, @value_converted)
    where `page_name` is not null and length(`page_name`) > char_length(`page_name`);
update ignore `redcap_pub_articles`
    set `title` = if((length(@value_converted:=convert(binary convert(`title` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`title`) - char_length(replace(`title`,'?','')))) > 0)), `title`, @value_converted)
    where `title` is not null and length(`title`) > char_length(`title`);
update ignore `redcap_pub_articles`
    set `volume` = if((length(@value_converted:=convert(binary convert(`volume` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`volume`) - char_length(replace(`volume`,'?','')))) > 0)), `volume`, @value_converted)
    where `volume` is not null and length(`volume`) > char_length(`volume`);
update ignore `redcap_pub_articles`
    set `issue` = if((length(@value_converted:=convert(binary convert(`issue` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`issue`) - char_length(replace(`issue`,'?','')))) > 0)), `issue`, @value_converted)
    where `issue` is not null and length(`issue`) > char_length(`issue`);
update ignore `redcap_pub_articles`
    set `pages` = if((length(@value_converted:=convert(binary convert(`pages` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`pages`) - char_length(replace(`pages`,'?','')))) > 0)), `pages`, @value_converted)
    where `pages` is not null and length(`pages`) > char_length(`pages`);
update ignore `redcap_pub_articles`
    set `journal` = if((length(@value_converted:=convert(binary convert(`journal` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`journal`) - char_length(replace(`journal`,'?','')))) > 0)), `journal`, @value_converted)
    where `journal` is not null and length(`journal`) > char_length(`journal`);
update ignore `redcap_pub_articles`
    set `journal_abbrev` = if((length(@value_converted:=convert(binary convert(`journal_abbrev` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`journal_abbrev`) - char_length(replace(`journal_abbrev`,'?','')))) > 0)), `journal_abbrev`, @value_converted)
    where `journal_abbrev` is not null and length(`journal_abbrev`) > char_length(`journal_abbrev`);
update ignore `redcap_pub_authors`
    set `author` = if((length(@value_converted:=convert(binary convert(`author` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`author`) - char_length(replace(`author`,'?','')))) > 0)), `author`, @value_converted)
    where `author` is not null and length(`author`) > char_length(`author`);
update ignore `redcap_pub_mesh_terms`
    set `mesh_term` = if((length(@value_converted:=convert(binary convert(`mesh_term` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`mesh_term`) - char_length(replace(`mesh_term`,'?','')))) > 0)), `mesh_term`, @value_converted)
    where `mesh_term` is not null and length(`mesh_term`) > char_length(`mesh_term`);
update ignore `redcap_pub_sources`
    set `pubsrc_name` = if((length(@value_converted:=convert(binary convert(`pubsrc_name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`pubsrc_name`) - char_length(replace(`pubsrc_name`,'?','')))) > 0)), `pubsrc_name`, @value_converted)
    where `pubsrc_name` is not null and length(`pubsrc_name`) > char_length(`pubsrc_name`);
update ignore `redcap_queue`
    set `key` = if((length(@value_converted:=convert(binary convert(`key` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`key`) - char_length(replace(`key`,'?','')))) > 0)), `key`, @value_converted)
    where `key` is not null and length(`key`) > char_length(`key`);
update ignore `redcap_queue`
    set `description` = if((length(@value_converted:=convert(binary convert(`description` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`description`) - char_length(replace(`description`,'?','')))) > 0)), `description`, @value_converted)
    where `description` is not null and length(`description`) > char_length(`description`);
update ignore `redcap_queue`
    set `message` = if((length(@value_converted:=convert(binary convert(`message` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`message`) - char_length(replace(`message`,'?','')))) > 0)), `message`, @value_converted)
    where `message` is not null and length(`message`) > char_length(`message`);
update ignore `redcap_reports_fields`
    set `limiter_value` = if((length(@value_converted:=convert(binary convert(`limiter_value` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`limiter_value`) - char_length(replace(`limiter_value`,'?','')))) > 0)), `limiter_value`, @value_converted)
    where `limiter_value` is not null and length(`limiter_value`) > char_length(`limiter_value`);
update ignore `redcap_sendit_docs`
    set `doc_name` = if((length(@value_converted:=convert(binary convert(`doc_name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`doc_name`) - char_length(replace(`doc_name`,'?','')))) > 0)), `doc_name`, @value_converted)
    where `doc_name` is not null and length(`doc_name`) > char_length(`doc_name`);
update ignore `redcap_sendit_docs`
    set `doc_orig_name` = if((length(@value_converted:=convert(binary convert(`doc_orig_name` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`doc_orig_name`) - char_length(replace(`doc_orig_name`,'?','')))) > 0)), `doc_orig_name`, @value_converted)
    where `doc_orig_name` is not null and length(`doc_orig_name`) > char_length(`doc_orig_name`);
update ignore `redcap_twilio_error_log`
    set `error_message` = if((length(@value_converted:=convert(binary convert(`error_message` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`error_message`) - char_length(replace(`error_message`,'?','')))) > 0)), `error_message`, @value_converted)
    where `error_message` is not null and length(`error_message`) > char_length(`error_message`);
update ignore `redcap_validation_types`
    set `validation_label` = if((length(@value_converted:=convert(binary convert(`validation_label` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`validation_label`) - char_length(replace(`validation_label`,'?','')))) > 0)), `validation_label`, @value_converted)
    where `validation_label` is not null and length(`validation_label`) > char_length(`validation_label`);
update ignore `redcap_user_information`
    set `user_firstname` = if((length(@value_converted:=convert(binary convert(`user_firstname` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`user_firstname`) - char_length(replace(`user_firstname`,'?','')))) > 0)), `user_firstname`, @value_converted)
    where `user_firstname` is not null and length(`user_firstname`) > char_length(`user_firstname`);
update ignore `redcap_user_information`
    set `user_lastname` = if((length(@value_converted:=convert(binary convert(`user_lastname` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`user_lastname`) - char_length(replace(`user_lastname`,'?','')))) > 0)), `user_lastname`, @value_converted)
    where `user_lastname` is not null and length(`user_lastname`) > char_length(`user_lastname`);
update ignore `redcap_user_information`
    set `user_comments` = if((length(@value_converted:=convert(binary convert(`user_comments` using latin1) using utf8mb4)) <= char_length(@value_converted) or isnull(@value_converted) or (instr(@value_converted, '?') > 0 and (char_length(@value_converted) - char_length(replace(@value_converted,'?','')) - (char_length(`user_comments`) - char_length(replace(`user_comments`,'?','')))) > 0)), `user_comments`, @value_converted)
    where `user_comments` is not null and length(`user_comments`) > char_length(`user_comments`);
";
        }
        return self::wrapSqlDbFlags($singlePidSql, true);
    }

    // Unicode Transformation for database tables: Loop through all projects to convert them one by one
    public static function convertAllProjectsIndividually()
    {
        // Return false if everything has already been completed
        $db_character_set = db_result(db_query("select value from redcap_config where field_name = 'db_character_set'"), 0);
        if ($db_character_set == 'utf8mb4') {
            return false;
        }
        // Convert a max of X projects per cron job
        $maxProjectsThisJob = 300;
        // Stop if we spent more than X seconds processing these (to give other cron jobs a chance to run)
        $maxProcessTime = 240;
        // Calculate total processing time (rounded to seconds)
        $start_time = microtime(true);
        // Gather all PIDs to convert
        $pids = [];
        $pidsConverted = [];
        $pidsError = [];
        $sql = "select project_id from redcap_projects where project_db_character_set is null 
                order by project_id limit $maxProjectsThisJob";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $pids[] = $row['project_id'];
        }
        // If there are no more projects to convert, then convert all data in tables where project_id is null
        if (empty($pids)) {
            // Get the conversion SQL
            $conversionSQL = "replace into redcap_config (field_name, value) values ('db_fix_data_project_active', '1');\n"
                           . "replace into redcap_config (field_name, value) values ('db_fix_data_project_inactive', '1');\n";
            $conversionSQL .= self::convertSingleProject(null);
            $conversionSQL .= "replace into redcap_config (field_name, value) values ('db_fix_data_nonproject', '1');\n";
            // Execute the conversion SQL
            db_multi_query($conversionSQL);
            // Manually add redcap_log_event and redcap_log_view where PROJECT_ID IS NULL
            if (!isVanderbilt()) { // For expediency, skip for Vanderbilt
                list ($redcapCols, $allColsAllTables, $nonProjectTables) = SQLTableCheck::getRedcapColsToConvert();
                $sqlFixTableData4  = SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_event", "pk", [0]); // redcap_log_event project_id's are not nullable, default to 0
                $sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_event", "data_values", [0]);
                $sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_event", "change_reason", [0]);
                $sqlFixTableData4 = SQLTableCheck::wrapSqlDbFlags($sqlFixTableData4, true, "", false);
                db_multi_query($sqlFixTableData4);
            }
            // Set flags to completed
            $sqlFixFinal = "replace into redcap_config (field_name, value) values ('db_fix_data_extra', '1');\n"
                         . "UPDATE `redcap_config` SET `value` = 'utf8mb4' WHERE `field_name` = 'db_character_set';\n"
                         . "UPDATE `redcap_config` SET `value` = 'utf8mb4_unicode_ci' WHERE `field_name` = 'db_collation';\n"
                         . "UPDATE `redcap_crons` SET `cron_enabled` = 'DISABLED' WHERE `cron_name` = 'UnicodeFixProjectLevel';\n";
            db_multi_query($sqlFixFinal);
            // Log all the SQL
            Logging::logEvent($conversionSQL."\n".(isset($sqlFixTableData4) ? $sqlFixTableData4."\n" : "").$sqlFixFinal, "redcap_config", "MANAGE", "", "", "Unicode transformation (last step: non-project data)", "", "", null, false);
            // Return false to denote that everything has been completed
            return false;
        }
        // Loop through each project and convert its data
        else {
            foreach ($pids as $pid) {
                // Get the conversion SQL
                $conversionSQL = self::convertSingleProject($pid);
                // Execute the conversion SQL
                db_multi_query($conversionSQL);
                // Confirm that the project was converted
                $q = db_query("select 1 from redcap_projects where project_db_character_set is null and project_id = $pid");
                if (db_num_rows($q)) {
                    $pidsError[] = $pid;
                } else {
                    $pidsConverted[] = $pid;
                }
                // Log all the SQL
                Logging::logEvent($conversionSQL, "redcap_projects", "MANAGE", $pid, "project_id = $pid", "Unicode transformation (PID $pid)", "", "", null, false); // Force this into the log_event table instead of the project's log_eventX table (for easier review after the fact)
                // Calculate current processing time (rounded to seconds)
                $total_time = round((microtime(true) - $start_time));
                if ($total_time > $maxProcessTime) return count($pidsConverted);
            }
        }
        // Finished this batch
        return count($pidsConverted);
    }

    // Generate SQL to add primary keys to all tables, if desired (for clustering or replication needs)
    public function suggestSqlPrimaryKeyAllTables()
    {
        $tables = $this->get_create_table_for_all_tables();
        $suggestedSql = "";
        foreach ($tables as $table=>$createTableSql) {
            if (strpos($createTableSql, "PRIMARY KEY (`") === false) {
                $suggestedSql .= "ALTER TABLE `$table` ADD `pk_id` BIGINT(13) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`pk_id`);\n";
            }
        }
        return $suggestedSql;
    }

    // Return boolean if already using the primary key feature for all tables (for clustering or replication needs)
    public function usingPrimaryKeyColumns()
    {
	    // Obtain a version of install.sql from current table structure
	    $install_sql_tables = $this->build_install_file_from_tables(false, false, false);
		return strpos($install_sql_tables, "`pk_id`") !== false;
    }

}
