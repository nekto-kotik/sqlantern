<?php
/*
The base PHP lib/mysqli implementation for SQLantern by nekto
v1.0.8 beta | 24-03-05

This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023, 2024 Misha Grafski AKA nekto
License: GNU General Public License v3.0
https://github.com/nekto-kotik/sqlantern
https://sqlantern.com/

SQLantern is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
*/

// NOTE . . . — — general functions

function sqlQuote() {
	/*
	https://dev.mysql.com/doc/refman/8.0/en/identifiers.html
	The identifier quote character is the backtick (`).
	If the ANSI_QUOTES SQL mode is enabled, it is also permissible to quote identifiers within double quotation marks.
	The ANSI_QUOTES mode causes the server to interpret double-quoted strings as identifiers. Consequently, when this mode is enabled, string literals must be enclosed within single quotation marks. They cannot be enclosed within double quotation marks.
	
	(But not being able to use double quotes is something I don't expect and regular users don't expect, so I'm not going `ANSI_QUOTES` route just yet.)
	*/
	
	return "`";
	
}

// XXX  

function sqlDisconnect() {
	myDisconnect();
}

// XXX  

function myDisconnect() {
	global $sys;
	
	if (is_object($sys["db"]["link"])) {
		mysqli_close($sys["db"]["link"]);
		//$sys["db"]["link"] = "";	// ???: or the link still extists O_o WHY???!!! Why not destroyed by mysqli_close?
		unset($sys["db"]["link"]);
	}
}

function sqlConnect() {	// connect with credentials
	myConnect();
}

// XXX  

function myConnect() {
	global $sys;
	$cfg = $sys["db"];
	/*
	
	`Port` is a b. to test, because not only PHP tends to connect via _socket_ (ignoring the port argument completely), but also MariaDB/MySQL only listens to local _socket_ by default, as well. So, what seems to be working with remote hosts might not really work locally, and vice versa. Very confusing and frustrating, requiring debugging in shell (checking, configuring, restarting services), which I'm tired of doing on my multiple Linux and FreeBSD machines (having different commands, different config, different versions of everything, etc).
	
	If anyone has a problem with the damn PORT, I hope they can tell me and I'll dive into it again, with a fresh mind.
	
	*/
	
	if (!array_key_exists("link", $sys["db"])) {
		
		//mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);	// enable reports
		mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX);	// enable some reports
		//mysqli_report(MYSQLI_REPORT_OFF);	// didn't help
		// As of PHP 8.1.0, the default setting is MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT. Previously, it was MYSQLI_REPORT_OFF.
		// `MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT` = normal errors are generated instead of exceptions ("traditional" errors; I'd say, oldschool errors)
		
		if (false) {	// shorter connection timeout for faster connection-related bugs reproduction
			// it can only be done this way, because connections are reused by `mysqli_connect` and one can't redefine a timeout for an already initialized connection
			$sys["db"]["link"] = mysqli_init();
			mysqli_options($sys["db"]["link"], MYSQLI_OPT_CONNECT_TIMEOUT, 10);
			mysqli_real_connect(
				$sys["db"]["link"],
				$cfg["host"], $cfg["user"], $cfg["password"], $cfg["dbName"], $cfg["port"]
			)
			or
			//fatalError("CONNECTION FAILED ({$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]})", true);
			fatalError(sprintf(translation("connection-failed-real"), "{$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]}"), true);
		}
		
		else {
			$sys["db"]["link"] = mysqli_connect(
				//$cfg["host"], $cfg["user"], $cfg["password"], $cfg["dbName"]//, $cfg["port"]
				
				// port doesn't work as expected or even described...
				// thanks to `chris at ocproducts dot com` and `paul at mtnlist dot com` in the official documentation comments for clarifying how the fracking port actually works :-(
				//"{$cfg["host"]}:{$cfg["port"]}", $cfg["user"], $cfg["password"], $cfg["dbName"]
				// THE LINE ABOVE IS NOT COMPLETELY RIGHT AND DOESN'T WORK AS I EXPECT
				
				$cfg["host"], $cfg["user"], $cfg["password"], $cfg["dbName"], $cfg["port"]
				// the line above will ignore the port when connecting to `localhost` or `127.0.0.1` and connect to the socket (is there a PHP config value for that? I think there is...), but I don't care anymore at this point, using alternative port works fine with remote connections and that's good enough for me
			) or
			//fatalError("CONNECTION FAILED ({$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]})", true);
			fatalError(
				sprintf(
					translation("connection-failed-real"),
					"{$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]}"
				),
				true
			);
		}
		$setCharset = array_key_exists("setCharset", $sys["db"]) ? $sys["db"]["setCharset"] : SQL_MYSQLI_CHARSET;
		mysqli_set_charset($sys["db"]["link"], $setCharset);
		
		/*
		If the `sql_mode` contains `ONLY_FULL_GROUP_BY`, the "indexes" request causes:
		```
		Expression #3 of SELECT list is not in GROUP BY clause and contains nonaggregated column 'information_schema.statistics.NON_UNIQUE' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by
		```
		But I really need to group by `INDEX_NAME` and at the same time `GROUP_CONCAT(COLUMN_NAME)`, or the indexes list won't be as designed (or I'll have to do it the PHP, which is stupid, since the MySQL can give me the ready-to-use response). I can't use `COLUMN_NAME` in `GROUP BY`, it gives a completely different result.
		So, I remove `ONLY_FULL_GROUP_BY` from `sql_mode` upon every connection (to do it in a non-breaking way).
		
		sqlQuery("
			SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))
		");	// "MySQL anyway removes unwanted commas from the record."
		
		NOTE: Moved to `SQL_RUN_AFTER_CONNECT`, but I'm leaving the reasons here, because they are so long. Things like that will need their own docs in the future, not just comment in the code.
		*/
		$runQueries = json_decode(SQL_RUN_AFTER_CONNECT, true);
		foreach ($runQueries["mysqli"] as $q) {
			sqlQuery($q);
		}
		
	}
}

// XXX  

function sqlQuery( $queryString ) {
	global $sys;
	
	if (isset($sys["db"]["queryStringProcessor"]))	// TODO . . . do I really want to add query processing on this project? will be pretty hard to manage for the users (me included)...
		$queryString = $sys["db"]["queryStringProcessor"]($queryString);
	
	sqlConnect();
	
	$res = mysqli_query($sys["db"]["link"], $queryString);
	if ($res === false) {
		// SQL errors are not translated and sent as is
		$trimmed = htmlspecialchars(trim($queryString));
		fatalError(
			implode(
				"<br>",
				[
					htmlspecialchars(mysqli_error($sys["db"]["link"])),
					"--",
					"{$trimmed}"
				]
			)
		);
	}
	
	return $res;
}

// XXX  

function sqlArray( $queryString ) {
	global $sys;
	
	$res = sqlQuery($queryString);
	
	//precho(["res" => $res, "gettype" => gettype($res), ]);
	//precho(["res" => $res, "gettype" => gettype($res), "get_class" => get_class($res), ]);
	
	// `mysqli_query` returns `false` on failure.
	// For successful queries which produce a result set, such as SELECT, SHOW, DESCRIBE or EXPLAIN, mysqli_query() will return a mysqli_result object.
	// For other successful queries, mysqli_query() will return true.
	
	/*
	FIXME ... This architecture makes no sense on this project (unlike the source where I took it from).
	
	This MUST be modified to reading UNBUFFERED result line-by-line.
	Because now if a user requests some big result with like `LIMIT 2000000`, MySQL eats all the RAM and "goes away" (it tries to read the whole result into memory, I assume), screwing up the whole server, not just this one client. PHP limits do not apply. And there is also a considerable delay while that happens.
	While it usually wouldn't fit into PHP limits anyway, even ignoring the browsers' ability to work with those amounts of data.
	
	But when I read unbuffered results line-by-line, MySQL hardly consumes any RAM (for one line, I suppose), and as I'm filling a PHP array, it will throw a fatal error on RAM limit overflow in PHP.
	It's a per-connection problem without any server-wide effects, RAM is fine, MySQL won't "go away", and there also must be a much shorter delay while this happens.
	
	I'll even consider new "max result rows" and "max result size" configurable options, to not freeze the browser and possibly even client's device trying to process an insanely large server response (sometimes even just reading it can be problematic).
	*/
	
	$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used directly just as is
	
	$answer = [];
	if ($res === true) {	// INSERT, UPDATE, DELETE, TRUNCATE, etc
		$affected = $sys["db"]["link"]->affected_rows;
		// this is to basically not write "affected_rows: 0" on TRUNCATE, which can be misleading
		// it also writes "executed" if DELETE is run on an empty table, but that's an acceptable side-effect, in my mind
		$answer[] = $affected ? ["affected_rows" => $numberFormat($affected)] : ["state" => "executed"];
	}
	elseif (mysqli_num_rows($res)) {
		// don't expect `mysqli_fetch_all` to be available, ALWAYS go the oldschool way (PHP 5 without MySQL ND support)
		while ($row = mysqli_fetch_assoc($res)) {
			$answer[] = $row;
		}
	}
	else {
		$answer = null;
	}
	
	return $answer;
}

// XXX  

function sqlRow( $queryString ) {
	$res = sqlQuery($queryString);
	return mysqli_num_rows($res) ? mysqli_fetch_assoc($res) : null;
}

// XXX  

function sqlEscape( $str ) {
	global $sys;
	//sqlConnect();	// required for mysqli_real_escape_string
	return mysqli_real_escape_string($sys["db"]["link"], (string) $str);
}

// NOTE . . . — — requests

function sqlListDb() {
	//return sqlArray("SHOW DATABASES");
	$res = sqlArray("SHOW DATABASES");
	/*
	FIXME . . . MySQL 5.5 @ pi returns the non-alphabetically sorted list - it displays `information_schema` above all other databases.
	So, an additional alphabetical sort must be applied.
	*/
	
	if (SQL_DISPLAY_DATABASE_SIZES) {
		$bytesFormat = SQL_BYTES_FORMAT;	// constants can't be used directly as functions
		$sizes = sqlArray("
			SELECT
				table_schema AS dbName,
				-- can I use one `SUM` below?
				SUM(data_length) + SUM(index_length) AS sizeBytes
			FROM information_schema.tables
			GROUP BY table_schema
		");
		$dbSizes = [];
		foreach ($sizes as $s) {
			$dbSizes[$s["dbName"]] = (int) $s["sizeBytes"];
		}
		
		$maxSize = max($dbSizes);
		
		foreach ($res as &$row) {
			$row["Size"] = $bytesFormat($dbSizes[$row["Database"]], $maxSize);
		}
		unset($row);
	}
	
	return $res;
}

// XXX  

function sqlListTables( $databaseName ) {
	
	$tables = sqlArray("SHOW TABLES");
	$views = [];
	
	if ($tables) {
		//precho(["tables" => $tables, ]);
		// the first key is like `Tables_in_{databaseName}`, which I hate, I should have probably went with `information_schema` from the start and not do this workaround...
		// FIXME . . . the code below is degenerate AF, I _must_ rewrite it to `information_schema` instead of `SHOW TABLES`
		$keys = array_keys($tables[0]);	// first column of the first row, because that's the structure
		$firstKey = array_shift($keys);
		array_walk(
			$tables,
			function(&$v) use ($firstKey) {
				$v["Table"] = $v[$firstKey];
				unset($v[$firstKey]);
			}
		);
		
		/*
		Interesting... is `SHOW TABLES` really alphabetical?
		Because phpMyAdmin lists `mantis_bugnote_*` tables above `mantis_bug_*` tables, but `SHOW TABLES` does not.
		PHP's `array_multisort` agrees with `SHOW TABLES`.
		I wonder what are phpMyAdmin's considerations and what I am missing.
		
		array_multisort(
			array_column($tables, "Table"), SORT_ASC,
			$tables
		);
		*/
		
		$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used directly just as is
		$bytesFormat = SQL_BYTES_FORMAT;
		
		if (!SQL_FAST_TABLE_ROWS) {
			// more accurate, but slower number of rows (not even always noticeably slower)
			foreach ($tables as &$t) {
				$tableNameSql = sqlEscape($t[array_keys($t)[0]]);
				$row = sqlRow("SELECT COUNT(*) AS n FROM `{$tableNameSql}`");
				$t["Rows"] = $row["n"] ? $numberFormat($row["n"]) : "";
			}
			unset($t);
		}
		
		$details = sqlArray("
			SELECT
				table_name AS tableName,
				data_length + index_length AS sizeBytes,
				-- do not laugh at `AS` below: selecting `table_rows` without it returns uppercase OR lowercase on different servers
				-- using `AS` forces it to be lowercase
				-- and it is very strange, because on the same server some _rows_ can have `TABLE_ROWS`, when other have `table_rows` (different column names in different rows!!!), I do not know the reason
				table_rows AS table_rows,
				LOWER(engine) AS engine,
				table_type AS tableType,
				table_comment AS tableComment
			FROM information_schema.tables
			WHERE table_schema = '{$databaseName}'
		", !true);
		$tableDetails = [];
		foreach ($details as $d) {
			$tableDetails[$d["tableName"]] = [
				"size" => (int) $d["sizeBytes"],
				"rows" => (int) $d["table_rows"],
				"engine" => $d["engine"],
				"tableType" => $d["tableType"],
				"comment" => $d["tableComment"],
			];
		}
		$maxSizeBytes = max(array_column($tableDetails, "size"));
		$requestRows = [];
		foreach ($tables as &$t) {
			$detailsRow = $tableDetails[$t["Table"]];
			
			//$t["Comment"] = $detailsRow["comment"];
			
			if ($detailsRow["tableType"] == "VIEW") {
				/*
				https://dev.mysql.com/doc/refman/8.0/en/information-schema-tables-table.html
				`BASE TABLE` for a table, `VIEW` for a view, or `SYSTEM VIEW` for an `INFORMATION_SCHEMA` table.
				The `TABLES` table does not list `TEMPORARY` tables.
				
				https://mariadb.com/kb/en/information-schema-tables-table/
				One of `BASE TABLE` for a regular table, `VIEW` for a view, `SYSTEM VIEW` for Information Schema tables, `SYSTEM VERSIONED` for system-versioned tables, `SEQUENCE` for sequences or, from MariaDB 11.2.0, `TEMPORARY` for local temporary tables.
				*/
				$views[] = $t["Table"];
				// Views have no rows and no size
				$t["Rows"] = "";
				$t["Size"] = "";
				continue;
			}
			
			$tilde = in_array($detailsRow["engine"], ["innodb", ]) ? "~" : "";	// FIXME . . . do other engines have this problem, too?
			if (SQL_FAST_TABLE_ROWS) {
				// faster (sometimes MUCH faster), but inaccurate number of rows
				$rows = $detailsRow["rows"];
				$t["Rows"] = $tilde . ($rows ? $numberFormat($rows) : "");
				if ($tilde && ($rows < 100)) {
					/*
					Unfortunately, if an engine doesn't update number of rows in "information_schema.tables", the number can be BOTH "0" when there are rows OR "not 0" when there are no rows.
					
					I highly, highly suspect that there are internal thresholds and it won't say "2,000,000" when there are "0" rows in a table, but it requires more research.
					
					What matters, it's unreliable to make the condition "if (!$rows) ... then check the precise amount of rows below", as sometimes there is a number of rows reported when in fact there are NONE, which is AS FRUSTRATING as when "0" is not real.
					
					https://dev.mysql.com/doc/refman/8.0/en/information-schema-tables-table.html has this to say <del>on the subject of love</del>:
					```
					The number of rows. Some storage engines, such as MyISAM, store the exact count. For other storage engines, such as InnoDB, this value is an approximation, and may vary from the actual value by as much as 40% to 50%. In such cases, use `SELECT COUNT(*)` to obtain an accurate count.
					```
					Which doesn't help much. The list of engines would be very helpful here, by the way, instead of the vague `such as`...
					
					Also, stating a "40% to 50%" error when it IN FACT sometimes says "2" instead of "0" is plain wrong and harmful, IMHO.
					
					But checking ALL "InnoDB" tables which have large quantity of rows is far too slow, so I could not check them all, I needed some threshold, and I came up with one ("100"). Tables with that amount of rows are small and return the precise number of rows fast. For bigger numbers I don't really care as much (I care, but since it's impossible to get them fast...), I only care about "0 instead of SOMETHING" and "SOMETHING instead of 0" situations. Hopefully my made up threshold covers them.
					*/
					$requestRows[] = $t["Table"];
					
				}
			}
			$t["Size"] = $tilde . $bytesFormat($detailsRow["size"], $maxSizeBytes);
			/*
			https://dev.mysql.com/doc/refman/8.0/en/information-schema-tables-table.html again:
			```
			For MyISAM, DATA_LENGTH is the length of the data file, in bytes.
			For InnoDB, DATA_LENGTH is the approximate amount of space allocated for the clustered index, in bytes. Specifically, it is the clustered index size, in pages, multiplied by the InnoDB page size.
			
			For MyISAM, INDEX_LENGTH is the length of the index file, in bytes.
			For InnoDB, INDEX_LENGTH is the approximate amount of space allocated for non-clustered indexes, in bytes. Specifically, it is the sum of non-clustered index sizes, in pages, multiplied by the InnoDB page size.
			
			For MEMORY tables, the DATA_LENGTH, MAX_DATA_LENGTH, and INDEX_LENGTH values approximate the actual amount of allocated memory. The allocation algorithm reserves memory in large amounts to reduce the number of allocation operations.
			```
			*/
		}
		unset($t);
		
		if (SQL_FAST_TABLE_ROWS) {	// a combined way of getting number of rows: only request for engines/tables expected to have mistakes
			foreach ($tables as &$t) {
				$tableName = $t["Table"];
				if (!in_array($tableName, $requestRows)) {
					continue;
				}
				$tableNameSql = sqlEscape($tableName);
				$row = sqlRow("SELECT COUNT(*) AS n FROM `{$tableNameSql}`");
				$t["Rows"] = $row["n"] ? $numberFormat($row["n"]) : "";
			}
			unset($t);
		}
		
		/*
		
		!!! THE PIECE OF SHOOT BELOW RETURNS WRONG NUMBER OF ROWS !!!
		!!! ON JUST IMPORTED, REANALIZED TABLES, THE NUMBERS ARE DIFFERENT FROM PHPMYADMIN, CAN SHOW 0 WHERE THERE IS 1 ROW !!!
		
		// "As far as I know, row count is not stored for InnoDB tables. This query can give an approximation at most."
		// "Note: TABLE_ROWS may get out of sync with the current table contents, but you can update it by running ANALYZE ;)"
		// "I don't feel like this answer is valuable without ANALYZE first as @shA.t has mentioned."
		
		// but this is instant, while asking every table is often very slow, causing delays (and I usually don't care for exact numbers anyway, except the "show 0 where there is 1 row" situations)
		
		$dbNameSql = sqlEscape($sys["db"]["dbName"]);
		$res = sqlArray(
			"SELECT
				TABLE_NAME,
				TABLE_ROWS
			FROM `information_schema`.`tables`
			WHERE `table_schema` = '{$dbNameSql}'"
		);
		//precho(["res" => $res, ]);
		$tableNames = array_column($res, "TABLE_NAME");
		foreach ($tables as &$t) {
			$tableName = $t[array_keys($t)[0]];
			$k = array_search($tableName, $tableNames);
			$numRows = $res[$k]["TABLE_ROWS"];
			$t["rows"] = $numRows ? number_format($numRows, 0, ".", ",") : "";
		}
		unset($t);
		*/
	}
	
	return [
		"tables" => $tables,
		"views" => $views,
	];
}

// XXX  

function sqlDescribeTable( $databaseName, $tableName ) {
	/*
	DESCRIBE {table}
	is the same as
	SHOW COLUMNS FROM {table}
	but there is more info when
	SHOW FULL COLUMNS FROM {table}
	`FULL` adds potentially useful "Collation" and "Comment" (and "Privileges", which I have no use for)
	*/
	
	$structure = sqlArray("DESCRIBE `{$tableName}`");
	/*
	Question: Is this the same as `SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '{$databaseName}' AND table_name = '{$tableName}'` ???
	Response: As far as I see, everything can be taken from there, including formatted type ("int(10) unsigned"), and of course more.
	*/
	/*
	foreach ($structure as &$s) {
		// I don't really need half of what it tells...
		$s["Key"] .= ($s["Extra"] == "auto_increment") ? " (AI)" : "";
		unset($s["Null"], $s["Default"], $s["Extra"]);
	}
	unset($s);
	*/
	
	/*
	SHOW INDEX FROM `{table}`
	lists indexes in the order they were created
	SELECT ... FROM information_schema.statistics
	lists indexes in some other strange order, and with a completely wrong `SEQ_IN_INDEX` for some reason
	And `SHOW INDEX` cannot be `JOIN`ed or used in a more complex query, so I had to fallback to PHP, in the end.
	
	Documentation about returned columns:
	https://dev.mysql.com/doc/refman/8.0/en/show-index.html
	
	Problem: `SHOW INDEXES`/`SHOW KEYS` doesn't list if a key is primary, when it's name is not "PRIMARY".
		Furthermore, `TABLE_CONSTRAINTS` sometimes lists keys as `UNIQUE` while `DESCRIBE` lists them as `PRI`, so that's also not heplful.
		Seems like multi-column primary keys are always forced to be named "PRIMARY", but I don't have a lot of those around to test it well.
		Now, what IS helpful is that all columns of multi-column primary keys are marked as `PRI` in `DESCRIBE`.
	Solution: Check against "Key" in stucture, and set "primary" if "Key" is "PRI".
	*/
	$res = sqlArray("SHOW INDEX FROM `{$tableName}`");
	// I'm interested in: Non_unique, Key_name, Seq_in_index, Column_name, Cardinality, maybe Index_comment, maybe Index_type
	$indexes = [];
	foreach ($res as $r) {
		if ($r["Seq_in_index"] > 1) {
			continue;
		}
		$columnInStructure = array_values(array_filter(
			$structure,
			function ($v) use ($r) {
				return $v["Field"] == $r["Column_name"];
			}
		));
		$indexes[] = [
			"Index" => $r["Key_name"],
			"Columns" => implode(
				SQL_INDEX_COLUMNS_CONCATENATOR,
				array_column(
					array_filter(
						$res,
						function ($v) use ($r) {
							return $v["Key_name"] == $r["Key_name"];
						}
					),
					"Column_name"
				)
			),
			"Primary" => $columnInStructure[0]["Key"] == "PRI" ? "yes" : "no",
			"Unique" => $r["Non_unique"] ? "no" : "yes",
			"Cardinality" => $r["Cardinality"] ? (int) $r["Cardinality"] : "",	// don't say "0" when it's actually NULL, leave it empty
		];
	}
	
	// change built-in MariaDB/MySQL "Keys" to SQLantern modified keys
	$keysLabels = json_decode(SQL_KEYS_LABELS, true);
	foreach ($structure as &$s) {
		if ($s["Key"] == "PRI") {	// `SHOW INDEX` surprisingly doesn't tell if a key is primary
			$s["Key"] = $keysLabels["primary"];
		}
		else {
			$filtered = array_filter(
				$res ? $res : [],
				function ($v) use ($s) {
					return $v["Column_name"] == $s["Field"];
				}
			);
			
			// MUL is when the column is a part of multiple keys, _or a single multi-column key_
			// That's why so many checks and conditions are made.
			
			if (count($filtered) == 1) {	// only one filtered index doesn't by itself mean it's a single-column key yet...
				$singleKey = array_pop($filtered);
				$columnsInKey = array_filter(
					$res,
					function ($v) use ($singleKey) {
						return $v["Key_name"] == $singleKey["Key_name"];
					}
				);
				if (count($columnsInKey) == 1) {	// only a combination of "in one key" + "only one column in the key" means it's a single-column key
					// the key might be unique
					if ($singleKey["Non_unique"]) {
						$s["Key"] = $keysLabels["single"];
					}
					else {
						$s["Key"] = $keysLabels["unique"];
					}
				}
				else {	// "in one key" + "more than 1 column in the key" = multi
					$s["Key"] = $keysLabels["multi"];
				}
			}
			elseif (count($filtered) > 1) {	// it's a part of multi-column key
				$s["Key"] = $keysLabels["multi"];
			}
		}
		
		if ($s["Extra"] == "auto_increment") {
			$s["Key"] .= " (AI)";
		}
		unset($s["Null"], $s["Default"], $s["Extra"]);
	}
	unset($s);
	
	
	/*
	Add foreign keys to the end of the list.
	
	An important note: "MySQL requires that foreign key columns be indexed; if you create a table with a foreign key constraint but no index on a given column, an index is created."
	The consequence is that creating a foreign key on a non-indexed column creates a new local index as well, and you get both index in the table AND a foreign index, which confusingly have the same name (as far as I see).
	
	The query I started with was:
	SELECT
		-- table_schema, table_name, column_name, constraint_name
		-- *
		table_schema, table_name, column_name, constraint_name,
		referenced_table_schema, referenced_table_name, referenced_column_name
	FROM information_schema.key_column_usage
	WHERE 	table_schema = '{database}'
			AND table_name = '{table}'
			AND referenced_table_schema IS NOT NULL
	
	And my final version (list all foreign keys from the whole database):
	SELECT
		table_name,
		constraint_name,
		GROUP_CONCAT(column_name SEPARATOR ' [x] ') AS columns,
		CONCAT(
			IF(referenced_table_schema != table_schema, CONCAT(referenced_table_schema, '.'), ''),
			referenced_table_name, '.',
			GROUP_CONCAT(referenced_column_name SEPARATOR ' [x] ')
		) AS ref
	FROM information_schema.key_column_usage
	WHERE 	table_schema = '{database}'
			AND referenced_table_schema IS NOT NULL
	GROUP BY table_name, constraint_name
	ORDER BY table_name ASC, constraint_name ASC
	
	NOTE . . . Adminer is the only one I know which lists foreign keys in a table like I do, and it's syntax is:
	Source						Target
	ndb_no, nutr_no				nut_data(ndb_no, nutr_no)
	Which is not bad at all - similar to the creating foreign key syntax.
	I like mine better for now, but I should keep in mind that way of displaying it, too. Maybe I'll like it better one day (or the users).
	*/
	
	$indexConcatenator = sqlEscape(SQL_INDEX_COLUMNS_CONCATENATOR);
	$foreign = sqlArray("
		SELECT
			constraint_name,
			GROUP_CONCAT(column_name SEPARATOR '{$indexConcatenator}') AS columns,
			CONCAT(
				IF(referenced_table_schema != table_schema, CONCAT(referenced_table_schema, '.'), ''),
				referenced_table_name, '.',
				GROUP_CONCAT(referenced_column_name SEPARATOR '{$indexConcatenator}')
			) AS ref
		FROM information_schema.key_column_usage
		WHERE 	table_schema = '{$databaseName}'
				AND table_name = '{$tableName}'
				AND referenced_table_schema IS NOT NULL
		GROUP BY constraint_name
		ORDER BY constraint_name ASC
	");
	
	if ($foreign) {
		// indexes get an additional column if the table has foreign keys
		foreach ($indexes as &$i) {
			$i["Foreign reference"] = "";
		}
		unset($i);
		
		foreach ($foreign as $f) {
			$indexes[] = [
				"Index" => $f["constraint_name"],
				"Columns" => $f["columns"],
				"Primary" => "",	// "n/a" looks bad, IMHO
				"Unique" => "",	// same thing
				"Cardinality" => "",
				"Foreign reference" => $f["ref"],
			];
		}
	}
	
	
	return [
		"structure" => $structure,
		
		/*
		"indexes" => sqlArray("
			SELECT
				INDEX_NAME AS `Index`,
				GROUP_CONCAT(COLUMN_NAME SEPARATOR ' + ') AS Columns,
				IF(NON_UNIQUE = 0, 'yes', 'no') AS `Unique`,
				-- do not display `NULL` in `cardinality`, following phpMyAdmin logic here
				COALESCE(cardinality, '') AS Cardinality
				-- `comment` is something internal, according to https://dev.mysql.com/doc/refman/8.0/en/show-index.html
				-- comment,
				-- index_comment
			FROM information_schema.statistics
			WHERE 	table_schema = '{$databaseName}'
					AND table_name = '{$tableName}'
			GROUP BY INDEX_NAME
		"),
		*/
		"indexes" => $indexes,
	];
}

// XXX  

function sqlRunQuery( $query, $page, $fullTexts ) {
	global $sys;
	
	$res = [];
	$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used directly just as is
	
	//$res["rows"] = sqlArray("SELECT * FROM `{$post["sql"]["table"]}` LIMIT 0,30");
	//$res["rows"] = sqlArray("{$post["sql"]["query"]} LIMIT 0,30");
	
	/*
	
	The code below doesn't process ALL kinds of comments 100% properly and won't work in all situations, that is expected.
	Known issues are:
	- comments inside comments don't get processed properly,
	- start and end of multi-line comments in strings inside comments is not processed (a combination of asterisk and slash, I can't write it literally here inside THESE comments, because PHP doesn't like it; I'm not sure if MySQL/MariaDB actually processes it itself...).
	
	This is by design, to keep the code simple while working with 99.9(9)% real queries.
	
	--
	
	As I later realized, queries like `SELECT '/*I am a just string' FROM table_name` are correctly detected as SELECT, but fail at injecting COUNT.
	So, detecting SELECT is "safe", but further code is "not safe", the number of rows might not be returned.
	This is all not ideal, of course, and I don't like it, but I'm out of short and simple ideas.
	
	--
	
	This logic of discovering the first word could be fully redone by trying to execute the query as unbuffered, and do different things based on num_rows/affected_rows, but it would run slow queries twice slower, which is very undesireable, IMHO. Hence this workaround analyzing query text.
	
	IDEA . . . Implement a logical fork with a constant. One will analyze the query, another will run the query to determine it's type by `num_rows` and `affected_rows`. It will be a completely safe way for the queries I don't use and don't test with. (But significantly slower.)
	Also safe to configure from the front side.
	
	*/
	
	$lines = explode("\n", $query);	// should `\r` be used, too?
	foreach ($lines as &$l) {
		$l = trim($l);	// no whitespaces
	}
	unset($l);
	
	// remove all lines starting with `#` and empty lines
	$lines = array_filter(
		$lines,
		function ($l) {
			return $l && (substr($l, 0, 1) != "#");	// must NOT be empty and must NOT start with `#`
		}
	);
	
	// remove all lines starting with `-- ` (two dashes followed by a white space, one-line comments)
	$lines = array_filter(
		$lines,
		function ($l) {
			$parts = preg_split("/\\s/", $l);
			return $parts[0] != "--";	// must NOT start with `--`
		}
	);
	
	// remove everything between `/*` and `*/`, the long comments
	// disregarding if those symbols are inside a string, that CAN'T matter to find THE FIRST word of the query (can it???)
	$tmpQuery = implode(" ", $lines);
	$parts = explode("/*", $tmpQuery);
	foreach ($parts as $n => &$p) {
		if (!$n)	// first part is not expected to contain `*/`, but all others are
			continue;
		$smallerParts = explode("*/", $p);
		// now, if there are 2 parts, part 1 should be ignored (it's before `*/`)
		// if there is only 1 part... well, that shouldn't happen, actually, but then, well... all right, ignore part 1 anyway, it'll be a non-treated use case
		$p = isset($smallerParts[1]) ? $smallerParts[1] : "";
	}
	unset($p);
	
	$queryWithoutComments = implode(" ", $parts);
	
	$words = preg_split("/\\s/", $queryWithoutComments);	// any whitespace is a delimiter: line break, tab, space
	// but it also leaves "empty words", like two spaces are treated as an "empty word"...
	// as demonstrated by: `var_dump(preg_split("/\\s/", "Words with  multiple     whitespace    characters."));`
	// so, must only leave non-empty words...
	$words = array_values(array_filter($words, function($w) { return $w; }));
	//precho($words); die();
	$firstQueryWordLower = mb_strtolower($words ? $words[0] : "", "UTF-8");
	
	/*
	$words = preg_split("/\\s/", trim($post["raw"]["query"]));	// any whitespace is a delimiter: line break, tab, space
	$firstQueryWordLower = mb_strtolower($words[0], "UTF-8");
	*/
	
	//precho($words); die();
	
	$useQuery = $query;	// run the query exactly as it is, if it's not a SELECT
	
	//if (mb_strtolower(substr($post["raw"]["query"], 0, 6), "UTF-8") == "select") {
	if ($firstQueryWordLower == "select") {
		// select means pagination and ability to change pages
		//precho(["raw_query" => $post["raw"]["query"], "sql_query" => $post["sql"]["query"], ]);
		
		//$setLimit = true;
		$enforcePagination = true;
		// there is either enforced pagination, or no pagination at all (the users have to basically paginate themselves, using `LIMIT` and `OFFSET` manually, if they use `LIMIT`)
		
		/*
		`LIMIT x, y` or `LIMIT x OFFSET y` must be the last line of the query.
		There exist other legit queries, with `into_option` or `FOR` after `LIMIT`, and this detection **does not support those queries**.
		The reason to check if the `LIMIT` is set for the requested query (which becomes a sub-query) is:
		- if a query has LIMIT set already, leave it as is,
		- if it doesn't, add `LIMIT {total_rows}` for `ORDER BY` to work in a sub-query if used (read below about `ORDER BY` in sub-queries)
		(On the other hand, I'm going to remove the sub-query after testing this change...)
		*/
		// replace commas with spaces, to force `LIMIT 20,OFFSET 20` or `LIMIT 20,100` become separate words
		$str = mb_strtolower(str_replace(",", " ", $queryWithoutComments), "UTF-8");	// also make it lowercase
		$words = preg_split("/\\s/", $str);	// !!! REUSING `$words` !!!
		$words = array_values(array_filter($words, function($w) { return $w != ""; }));	// array_values to reset keys, because array_filter keeps keys and breaks trying to address words by `count minus {n}` below, derp, derp, derp...
		$countWords = count($words);
		if (
			($countWords > 5)	// at least give me a `SELECT {x} FROM {y} LIMIT {z}` (which might glitch on synthetic queries, but will in fact work)
			&&
			(
				($words[$countWords - 3] == "limit")	// third word from the end is `LIMIT`, like `LIMIT 10, 100`
				||
				($words[$countWords - 2] == "limit")	// second word from the end is `LIMIT`, like `LIMIT 99`
				||
				(
					($words[$countWords - 4] == "limit")	// fourth word from the end is `LIMIT`
					&&
					($words[$countWords - 2] == "offset")	// and second word from the end is `OFFSET`
				)
			)
		) {	// this is a bit too anal condition, actually, I think...
			//$setLimit = false;
			$enforcePagination = false;
		}
		
		/*
		Problem: If there is a syntax error in query, the error with COUNT is displayed, which is confusing.
		Solution: Run `EXPLAIN` the given query first. If `EXPLAIN` fails, the query has an error. In this case I can run the raw query just to display that error. If `EXPLAIN` works, I can go on with COUNT.
		The nice thing is `EXPLAIN` doesn't (shouldn't) take the same time as running the query, so there's only a small and neglectable time/resources loss.
		*/
		if (!mysqli_query($sys["db"]["link"], "EXPLAIN {$query}")) {	// no need to SQL-escape, I believe
			sqlQuery($query);	// sqlQuery will output the error
			die("Line " . __LINE__);	// just in case...
		}
		
		$onPage = SQL_ROWS_PER_PAGE;
		
		if ($enforcePagination) {
			/*
			Subquery is accurate and fast, but doesn't always work (`SELECT *` with `JOIN`s).
			Injected `COUNT(*)` is accurate and fast, but only if there's no `GROUP BY`.
			Injected `COUNT(*) OVER ()` is accurate, but slow, and not always available (requires correct versions).
			*/
			
			if (true) {
				
				$words = preg_split("/\\s/", $queryWithoutComments);	// !!! REUSING `$words` !!!
				$words = array_values(array_filter($words, function($w) { return $w != ""; }));
				
				// first, try injecting `COUNT(*) OVER ()`
				$counterColumnName = "sqlantern_number_rows_" . time();	// make collisions highly unlikely, without executing the query (which would be just to get the column names and guarantee no collision)...
				
				if (mb_substr($words[1], 0, 1, "UTF-8") == "*") {	// it is `SELECT *`
					$w1 = $words[1];
					
					$words[1] =
						"*, COUNT(*) OVER () AS {$counterColumnName}"
						. mb_substr($w1, 1, mb_strlen($w1, "UTF-8"), "UTF-8")	// in case it's something like "*,table.id,table2.uid"
					;
					$queryCountOver = implode(" ", $words);
					
					$words[1] =
						"*, COUNT(*) AS {$counterColumnName}"
						. mb_substr($w1, 1, mb_strlen($w1, "UTF-8"), "UTF-8")	// in case it's something like "*,table.id,table2.uid"
					;
					$queryCount = implode(" ", $words);
				}
				else {	// not `SELECT *`
					$words[0] = "select COUNT(*) OVER () AS {$counterColumnName},";
					$queryCountOver = implode(" ", $words);
					
					$words[0] = "select COUNT(*) AS {$counterColumnName},";
					$queryCount = implode(" ", $words);
				}
				
				$countSubquery = "SELECT COUNT(*) AS n FROM (
					{$query}
				) AS t";	// query MUST be line-broken to avoid a world of pain
				/*
				If I don't put parenthesis on new lines, queries ending with a commented-out line bug out, like:
				```
				SELECT viewname AS Table
				FROM pg_catalog.pg_views
				WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
				-- ORDER BY schemaname ASC, viewname ASC
				```
				Because the closing parenthesis gets in the commented-out line.
				*/
				
				
				$numRows = -1;
				
				// subquery is fast and accurate, but doesn't work if that's `SELECT *` with `JOIN`s
				$tryQuery = $countSubquery;
				if (mysqli_query($sys["db"]["link"], $tryQuery)) {	// subquery worked!
					$row = sqlRow($tryQuery);	// FIXME . . . additional run
					$numRows = (int) $row["n"];
					$res["count_method"] = "subquery";
				}
				
				
				// injecting `COUNT(*)` is fast and accurate, but only if the query doesn't contain `GROUP BY`
				if ($numRows == -1) {
					if (mb_strpos(mb_strtolower($queryCount, "UTF-8"), "group by") === false) {	// only try if there is no `GROUP BY` (and I'm not going to really analyze the query)
						$tryQuery = $queryCount;
						if (mysqli_query($sys["db"]["link"], $tryQuery)) {	// simple injected `COUNT(*)` worked!
							$row = sqlRow($tryQuery);	// FIXME . . . additional run
							$numRows = (int) $row[$counterColumnName];
							$res["count_method"] = "injected `COUNT(*)`";
						}
					}
				}
				
				// `COUNT(*) OVER ()` is accurate, but very slow with big datasets, and isn't always compatible
				// so, it's the final fallback, and not desirable, in fact
				if ($numRows == -1) {
					$tryQuery = $queryCountOver;
					if (mysqli_query($sys["db"]["link"], $tryQuery)) {	// injected `COUNT(*) OVER ()` worked!
						$row = sqlRow($tryQuery);	// FIXME . . . additional run
						$numRows = (int) $row[$counterColumnName];
						$res["count_method"] = "injected `COUNT(*) OVER ()`";
					}
				}
				
				
				if ($numRows == -1) {
					$res["count_method"] = "failure";
				}
				
				//$res["count_query"] = $tryQuery;
				
				
				// older version with different priority, which wasn't good enough:
				/*
				if (mysqli_query($sys["db"]["link"], $queryCountOver)) {	// `COUNT(*) OVER ()` worked!
					$row = sqlRow($queryCountOver);
					$numRows = (int) $row[$counterColumnName];
					$res["count_method"] = "injected `COUNT(*) OVER ()`";
					$res["count_query"] = $queryCountOver;
				}
				elseif (mysqli_query($sys["db"]["link"], $countSubquery)) {	// subquery worked!
					$row = sqlRow($countSubquery);
					$numRows = (int) $row["n"];
					$res["count_method"] = "subquery";
					//$res["count_query"] = $countSubquery;
				}
				else {	// subquery didn't work, check for `GROUP BY`
					if (mb_strpos(mb_strtolower($queryCount, "UTF-8"), "group by") === false) {	// no `GROUP BY`, run a simple injected `COUNT(*)`
						$row = sqlRow($queryCount);
						$numRows = (int) $row[$counterColumnName];
						$res["count_method"] = "injected `COUNT(*)`";
						//$res["count_query"] = $queryCount;
					}
					else {
						$res["count_method"] = "failure";
						$numRows = -1;
					}
				}
				*/
				
			}
			
			// even older version which wasn't good at all:
			if (false) {
				if (SQL_MYSQLI_COUNT_SUBQUERY_METHOD) {	// slow on big tables... or is it?..
					// a reasonable idea at first sight, it very often results in the `Duplicate column name 'id'` [insert your duplicate field name here] fatal error on "SELECT *" with JOIN (when multiple tables have columns with the same name)
					// e.g. `SELECT * FROM chats_chatters LEFT JOIN chats ON chats.id = chats_chatters.chat_id`
					$row = sqlRow("
						SELECT COUNT(*) AS n
						FROM (
							{$query}
						) AS t
					");
					$numRows = (int) $row["n"];
				}
				else {	// inject COUNT!
					// `SELECT *, table.id` is allowed
					// `SELECT table.id, *` is not allowed
					// so, I can custom-case `SELECT *` and inject `, COUNT` right after it, that'll work
					// or otherwise inject `COUNT, ` right after `SELECT`
					// a query with the injected `COUNT` will return only one row and runs faster than the full query
					
					$counterColumnName = "sqlantern_number_rows_" . time();	// make collisions highly unlikely, without executing the query just to get the column names and guarantee no collision...
					
					/*
					CONFLICTS WITH OUR STANDARD `DISTINCT` QUERY!!!
					
					INACCURATE NUMBER OF ROWS WITH:
					```
					SELECT COUNT(*) AS `rows`, `product_id` FROM `aki_product_special` GROUP BY `product_id` ORDER BY COUNT(*) DESC
					```
					679 vs real 529 pages (subquery method provides 529 accurately)
					
					> hm... it's because of the fracking GROUP BY, I see now...
					any GROUP BY will frack it up, it seems
					
					I think now that subquery should be the first method, and injection a fallback method...
					
					Are there GROUP BY queries that will fail the subquery method?
					*/
					
					$words = preg_split("/\\s/", $queryWithoutComments);	// !!! REUSING `$words` !!!
					$words = array_values(array_filter($words, function($w) { return $w != ""; }));
					if (mb_substr($words[1], 0, 1, "UTF-8") == "*") {	// it is `SELECT *`
						$words[1] =
							"*, COUNT(*) AS {$counterColumnName}"
							. mb_substr($words[1], 1, mb_strlen($words[1], "UTF-8"), "UTF-8")	// in case it's something like "*,table.id,table2.uid"
						;
					}
					else {	// not `SELECT *`
						$words[0] = "select COUNT(*) AS {$counterColumnName},";
					}
					// in both cases, execute the modified query and get number of rows from it
					$row = sqlRow(implode(" ", $words));
					$numRows = (int) $row[$counterColumnName];
				}
			}
			
			
			if ($numRows == -1) {
				$res["num_rows"] = -1;
				$res["num_pages"] = 0;
				$res["error"] = "Getting number of rows failed";
			}
			else {
				$numPages = ceil($numRows / $onPage);
				$res["num_rows"] = $numberFormat($numRows);
				$res["num_pages"] = $numberFormat($numPages);
			}
			
			$humanPage = $page ? $page : 1;
			if (($humanPage < 0) || ($humanPage > $numPages))
				$humanPage = 1;
			$machinePage = $humanPage - 1;
			$res["cur_page"] = $humanPage;
			$offset = $machinePage * $onPage;
		}
		
		//$useQuery = $query . ($setLimit ? " LIMIT {$numRows}" : "");
		$useQuery = $query . ($enforcePagination ? "\n LIMIT {$offset}, {$onPage}" : "");	// add LIMIT sometimes
		// LIMIT must be added _on the next line_, because otherwise it will be ignored, if the last query line is commented out by `-- ` (LIMIT will just be added to the comment)
		
		/*$query = "
			SELECT *
			FROM (
				{$useQuery}
			) AS t
			LIMIT {$offset}, {$onPage}
		";*/
		
		/*$query = "
			{$query}
			LIMIT {$offset}, {$onPage}
		";*/
		
		/*
		Some historical notes concerning initial realization with a subquery:
		
		ORDER BY not working in subquery:
		...
		> This kludge allegedly works: Tack LIMIT 999999999 onto the subquery. That forces the optimizer to use the ORDER BY. Normally (in newer versions of MySQL), the Optimizer simply throws away the ORDER BY in certain subqueries -- because the SQL Standard says that a subquery delivers an unordered set.
		...
		MariaDB 5.5.39 on Linux does not apply the ORDER BY inside the subquery when no LIMIT is supplied. It does however correctly apply the order when a corresponding LIMIT is given
		Without that LIMIT, there isn't a good reason to apply the sort inside the subquery. It can be equivalently applied to the outer query.
		As it turns out, MariaDB has documented this behavior and it is not regarded as a bug:
		https://mariadb.com/kb/en/mariadb/faq/general-faq/why-is-order-by-in-a-from-subquery-ignored/
		--
		A "table" (and subquery in the FROM clause too) is - according to the SQL standard - an unordered set of rows. Rows in a table (or in a subquery in the FROM clause) do not come in any specific order. That's why the optimizer can ignore the ORDER BY clause that you have specified. In fact, SQL standard does not even allow the ORDER BY clause to appear in this subquery (we allow it, because ORDER BY ... LIMIT ... changes the result, the set of rows, not only their order).
		You need to treat the subquery in the FROM clause, as a set of rows in some unspecified and undefined order, and put the ORDER BY on the top-level SELECT.
		--
		*/
	}
	
	$res["real_executed_query"] = $useQuery;
	
	//$res["rows"] = sqlArray($useQuery);
	//$res["rows"] = [];
	
	if (true) {	// newer logic with easy switching to the old logic
		$res["rows"] = [];
		$result = mysqli_query($sys["db"]["link"], $useQuery, MYSQLI_USE_RESULT);
		
		if ($result === true) {	// INSERT, UPDATE, DELETE, TRUNCATE, etc
			$affected = $sys["db"]["link"]->affected_rows;
			// this is to basically not write "affected_rows: 0" on TRUNCATE, which can be misleading
			// it also writes "executed" if DELETE is run on an empty table, but that's an acceptable side-effect, in my mind
			$res["rows"][] = $affected ? ["affected_rows" => $numberFormat($affected)] : ["state" => "executed"];
		}
		elseif ($result === false) {
			$trimmed = htmlspecialchars(trim($useQuery));
			fatalError(
				implode(
					"<br>",
					[
						htmlspecialchars(mysqli_error($sys["db"]["link"])),
						"--",
						"{$trimmed}"
					]
				)
			);
		}
		else {	// cannot use `mysqli_num_rows` with `MYSQLI_USE_RESULT`, but if result is not `true` and not `false`, it must contain the data (is it _always_ so, though?!)
			// MYSQLI_USE_RESULT - returns a mysqli_result object with UNBUFFURED result set
			// that is the key to not run out of memory on every occasion
			
			// I'm adding table name to the columns with identical names here.
			// So, if a user runs `SELECT *` from multiple joined tables, and the result has clashing `products.id` and `products_local.id`, it won't just return one `id`, but both, with table names added to them.
			// Should it be a logical fork? My initial thought, as of writing this, is: "I'm giving you the full possible info. If you want it differently, write the request differently - write all the fields you needed and `AS`es for them, if needed, don't just run `SELECT *`."
			//precho(["fields" => mysqli_fetch_fields($result), ]);
			/*
			name	The name of the column
			orgname	Original column name if an alias was specified
			table	The name of the table this field belongs to (if not calculated)
			orgtable	Original table name if an alias was specified
			*/
			$fields = mysqli_fetch_fields($result);
			$fieldNames = deduplicateColumnNames(
				// `array_column` was originally used below, but although `array_column` is PHP 5.4+, it doesn't work with objects until PHP 7.
				array_map(function( $obj ) { return $obj->name; }, $fields),
				array_map(function( $obj ) { return $obj->table; }, $fields)	// table alias makes sense, IMHO, and using aliased names above, because THEY are what clashes
			);
			
			$resultSize = 0;
			$rowNumber = 1;	// human-style row numbers for the error message
			
			//while ($row = mysqli_fetch_assoc($result)) {	// classic associative result, clashing field names disappear
			while ($row = mysqli_fetch_row($result)) {	// displaying all fields, even if they clash
				foreach ($row as &$v) {	// columns in row
					
					if (is_null($v)) {	// leave NULL as is
						continue;
					}
					
					// BLOB and other BINARY data is not JSON compatible and MUST be treated, unfortunately
					/*
					Thank you `Harry Lewis` for the solution below
					see `https://stackoverflow.com/a/69678887`
					Harry wrote there:
					```
					After a few attempts using ctype_ and various workarounds like removing whitespace chars and checking for empty, I decided I was going in the wrong direction. The following approach uses mb_detect_encoding (with the strict flag!) and considers a string as "binary" if the encoding cannot be detected.
					
					So far i haven't found a non-binary string which returns true, and the binary strings that return false only do so if the binary happens to be all printable characters.
					```
					
					It is slowing the response a tiny bit, but it is neglegtable on any practical amount of rows, even on 1,000 rows it's too small of added delay to care for.
					
					NOTE...
					https://www.php.net/manual/en/function.mb-detect-encoding.php
					"Major undocumented breaking change since 8.1.7"
					"The documentation is no longer correct for php8.1 and mb_detect_encoding no longer supports order of encodings"
					(not applicable for our use case)
					*/
					
					// unfortunately, the smart code below took 6.5s+ on dev with 30 x 2Mb strings, and lost to json_encode test, which is 1.5s on dev with 30 x 2Mb strings
					// and with real files being bigger, `mb_detect_encoding` really loses it to `json_encode`
					// of course, it's not a REAL treatment and should be rewritten one day, it's currently just to send the JSON response properly, nothing more
					/*
					if (!mb_detect_encoding($v ? $v : "", null, true)) {	// this takes a long time if the data is long, as in my `gibberish` test table...
						//$v = "[BINARY, NON-PRESENTABLE]";
						$v = ["type" => "blob", ];
						continue;
					}
					*/
					
					if (json_encode($v) === false) {	// this proved to be the fastest way < takes additional RAM though :-(
						$v = ["type" => "blob", ];	// TODO . . . download BINARY/BLOB
						continue;
					}
					
					if ($fullTexts == "false") {
						// SQL_DEFAULT_SHORTEN tells the front-end the default toggle state
						// but after that this and only _this front-end toggle_ tells me to shorten or not
						$v =
							(mb_strlen($v ? $v : "") > SQL_SHORTENED_LENGTH) ?
							mb_substr($v, 0, SQL_SHORTENED_LENGTH) . "[...]" : $v
						;
					}
				}
				unset($v);
				
				//$res["rows"][] = $row;	// classic associative result, losing the fields which clash
				$fixedRow = [];
				foreach ($row as $fieldIdx => $v) {
					$fixedRow[$fieldNames[$fieldIdx]] = $v;
				}
				$res["rows"][] = $fixedRow;
				
				// check data size threshold and throw an error if surpassed
				$resultSize += arrayRowBytes($fixedRow);
				if ($resultSize > SQL_DATA_TOO_BIG) {
					fatalError(sprintf(translation("data-overflow"), $numberFormat($rowNumber)));
				}
				
				$rowNumber++;
			}
		}
	}
	
	//precho(["resultSize" => $resultSize, "SQL_DATA_TOO_BIG" => SQL_DATA_TOO_BIG, ]); die();
	
	
	if (isset($enforcePagination) && !$enforcePagination) {
		$res["num_rows"] = count($res["rows"]);
		//$res["num_pages"] = 1;
		//$res["cur_page"] = 1;
	}
	
	
	
	
	if (false) {	// not deleting the old logic just yet...
		// BLOB and other BINARY data is not JSON compatible and MUST be treated, unfortunately
		foreach ($res["rows"] as &$row) {	// rows
			foreach ($row as &$v) {	// columns in row
				/*
				Thank you `Harry Lewis` for the solution below
				see `https://stackoverflow.com/a/69678887`
				Harry wrote there:
				```
				After a few attempts using ctype_ and various workarounds like removing whitespace chars and checking for empty, I decided I was going in the wrong direction. The following approach uses mb_detect_encoding (with the strict flag!) and considers a string as "binary" if the encoding cannot be detected.
				
				So far i haven't found a non-binary string which returns true, and the binary strings that return false only do so if the binary happens to be all printable characters.
				```
				
				It is slowing the response a tiny bit, but it is neglegtable on any practical amount of rows, even on 1,000 rows it's too small of added delay to care for.
				
				NOTE...
				https://www.php.net/manual/en/function.mb-detect-encoding.php
				"Major undocumented breaking change since 8.1.7"
				"The documentation is no longer correct for php8.1 and mb_detect_encoding no longer supports order of encodings"
				(not applicable for our use case)
				*/
				
				if (!mb_detect_encoding($v ? $v : "", null, true)) {
					//$v = "[BINARY, NON-PRESENTABLE]";
					$v = ["type" => "blob", ];
					continue;
				}
				
				if ($fullTexts == "false") {
					// SQL_DEFAULT_SHORTEN tells the front-end the default toggle state
					// but after that this and only _this front-end toggle_ tells me to shorten or not
					$v =
						(mb_strlen($v ? $v : "") > SQL_SHORTENED_LENGTH) ?
						mb_substr($v, 0, SQL_SHORTENED_LENGTH) . "[...]" : $v
					;
				}
				//$v = "[" . mb_strlen($v) . "]";
			}
		}
		unset($row, $v);
	}
	
	
	
	
	//precho(["sys_db" => $sys["db"]]);
	/*
	if (in_array($firstQueryWordLower, ["update", "delete", "insert", ])) {
		$res["rows"] = [
			["affected_rows" => $sys["db"]["link"]->affected_rows],
		];
	}
	*/
	
	return $res;
}

// XXX  

function sqlQueryTiming( $query ) {
	global $sys;
	
	// doesn't even send response, only profiling info
	
	sqlConnect();
	
	mysqli_query($sys["db"]["link"], "SET @@profiling = 1");	// or "SET SESSION [...]"
	
	$result = mysqli_query($sys["db"]["link"], $query, MYSQLI_USE_RESULT);
	if ($result === false) {
		// SQL errors are not translated and sent as is
		$trimmed = htmlspecialchars(trim($query));
		fatalError(
			implode(
				"<br>",
				[
					htmlspecialchars(mysqli_error($sys["db"]["link"])),
					"--",
					"{$trimmed}"
				]
			)
		);
	}
	//var_dump(gettype($result)); die();
	if (gettype($result) == "object") {	// `FLUSH TABLES` returns `boolean`, and I'm not going to dive into it at the moment
		mysqli_free_result($result);
	}
	
	$tmp = mysqli_query($sys["db"]["link"], "SHOW PROFILES", MYSQLI_USE_RESULT);
	$a = [];
	while ($row = mysqli_fetch_assoc($tmp)) {
		$a[] = $row;
	}
	$lastRow = array_pop($a);	// some servers return only one query, some return a lot (permissions to change "profiling"?), the last is the one we need
	mysqli_free_result($tmp);
	// NOTE: You should always free your result with mysqli_free_result(), when your result object is not needed anymore.
	// @http://php.net/manual/en/mysqli-result.free.php
	unset($tmp);
	// drop profiling history, for Science sake...
	mysqli_query($sys["db"]["link"], "SET @@profiling = 0");
	mysqli_query($sys["db"]["link"], "SET @@profiling_history_size = 0");
	mysqli_query($sys["db"]["link"], "SET @@profiling_history_size = 100");	// Where does `100` come from? Is this default/typical configuration?
	$durationSecs = round((float) $lastRow["Duration"], 20);
	$durationMs = round($durationSecs * 1000, 4);
	
	return [
		"timeMs" => $durationMs,
	];
}

// XXX  

/*
An interesting comment by `Michael` at https://www.php.net/manual/en/mysqli.affected-rows.php#116152

If you need to know specifically whether the WHERE condition of an UPDATE operation failed to match rows, or that simply no rows required updating you need to instead check mysqli::$info.

As this returns a string that requires parsing, you can use the following to convert the results into an associative array.

Object oriented style:

<?php
    preg_match_all ('/(\S[^:]+): (\d+)/', $mysqli->info, $matches); 
    $info = array_combine ($matches[1], $matches[2]);
?>

Procedural style:

<?php
    preg_match_all ('/(\S[^:]+): (\d+)/', mysqli_info ($link), $matches); 
    $info = array_combine ($matches[1], $matches[2]);
?>

You can then use the array to test for the different conditions

<?php
    if ($info ['Rows matched'] == 0) {
        echo "This operation did not match any rows.\n";
    } elseif ($info ['Changed'] == 0) {
        echo "This operation matched rows, but none required updating.\n";
    }

    if ($info ['Changed'] < $info ['Rows matched']) {
        echo ($info ['Rows matched'] - $info ['Changed'])." rows matched but were not changed.\n";
    }
?>

This approach can be used with any query that mysqli::$info supports (INSERT INTO, LOAD DATA, ALTER TABLE, and UPDATE), for other any queries it returns an empty array.

For any UPDATE operation the array returned will have the following elements:

Array
(
    [Rows matched] => 1
    [Changed] => 0
    [Warnings] => 0
)

*/

function testing( $query ) {
	global $sys;
	
	/*
	
	Warn about the following:
	```
	Be sure to not send a set of queries that are larger than max_allowed_packet size on your MySQL server. If you do, you'll get an error like: Mysql Error (1153): Got a packet bigger than 'max_allowed_packet' bytes
	```
	
	SHOW VARIABLES LIKE '%max_allowed_packet%'
	
	It is only 16777216 on `atom`, 16Mb.
	At the same time, `slave_max_allowed_packet` is 1073741824, is this 1Gb?
	
	https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_allowed_packet
	Default Value: 67108864 (64Mb) for 8, 4194304 (4Mb, srsly) for 5.7
	Maximum Value: 1073741824 (1Gb? oh, come on...)
	Scope: Global, Session
	
	https://mariadb.com/docs/skysql-dbaas/ref/mdb/system-variables/max_allowed_packet/
	Product Default Value: 16777216 (16Mb)
	Maximum Value: 1073741824
	Scope: Global, Session (???!!!)
	
	So, this is not an ideal solution for importing databases from files... or hm... can I increase it per session, will I usually/always have right to do so?
	`SET SESSION max_allowed_packet = 1073741824`
	No luck, got: "SESSION variable 'max_allowed_packet' is read-only. Use SET GLOBAL to assign the value"
	
	https://stackoverflow.com/questions/14211241/max-allowed-packet-could-not-be-set-in-mysql-5-5-25
	```
	[...] when we start MySQL server we get a global deafult max_allowed_packet value and when a connection to server is initialized this global deafult max_allowed_packet value is copied to a local max_allowed_packet variable (which is read only) and used by connection for its operations.
	So basically after every server restart you need to enter
	> set global max_allowed_packet=1048576000;
	Drop all the connections and start new connections, so that this new global value is reflected and now you would be able to insert blob.
	```
	
	https://bugs.mysql.com/bug.php?id=107395
	```
	This variable is a bit odd and has separate global and session scopes, the session scope being read-only.
	...
	> (The global value could be less than the session value if the global value is changed after the client connects.)
	So this is not a bug.
	```
	(You must be fracking kidding me...)
	
	*/
	
	$link = $sys["db"]["link"];
	mysqli_multi_query($link, $query);
	
	$res = [
		"rows" => [],
	];
	
	/*
	IDEA . . . I can send progress via EventSource, tracking progress in SESSION (because there's nothing else to send info from one thread to another on this project). Is it good? No. But no matter what anyone thinks (including me, nekto), that'll actually practically work.
	
	I DON'T NEED THE QUERIES, I'LL ONLY MAKE SOME BASIC PROGRESS: "X queries executed, Y rows inserted"
	*/
	
	do {
		/*
		https://www.php.net/manual/en/mysqli.store-result.php
		If mysqli error reporting is enabled (MYSQLI_REPORT_ERROR) and the requested operation fails, a warning is generated. If, in addition, the mode is set to MYSQLI_REPORT_STRICT, a mysqli_sql_exception is thrown instead.
		*/
		if ($result = mysqli_store_result($link)) {
			$res["rows"][] = [
				"num_rows" => mysqli_num_rows($result),
				"affected_rows" => 0,
			];
			/*
			$numRows = mysqli_num_rows($result);	// if I understand it correctly, `num_rows` fill only be non-zero on SELECT (well, with anything selected)
			$res["rows"][] = [
				"num_rows" => $numRows,
				"affected_rows" => $numRows ? 0 : mysqli_affected_rows($link),
			];
			*/
			/*
			https://www.php.net/manual/en/mysqli.affected-rows.php
			`mysqli_affected_rows`
			Returns the number of rows affected by the last INSERT, UPDATE, REPLACE or DELETE query. Works like mysqli_num_rows() for SELECT statements.
			*/
			
			mysqli_free_result($result);
			
		}
		else {
			// https://www.php.net/manual/en/mysqli.store-result.php
			// mysqli_store_result() returns false in case the query didn't return a result set (if the query was, for example an INSERT statement). This function also returns false if the reading of the result set failed. You can check if you have got an error by checking if mysqli_error() doesn't return an empty string, if mysqli_errno() returns a non zero value, or if mysqli_field_count() returns a non zero value. Also possible reason for this function returning false after successful call to mysqli_query() can be too large result set (memory for it cannot be allocated). If mysqli_field_count() returns a non-zero value, the statement should have produced a non-empty result set.
			$res["rows"][] = [
				"num_rows" => 0,
				"affected_rows" => mysqli_affected_rows($link),
			];
		}
		// add `mysqli_more_results()` somewhere here?
		if (mysqli_more_results($link)) {
			// it's only a check if there are MORE queries further down the line, nothing else
			// only usable to track progress
			//$res["rows"][] = ["next" => "---", ];
		}
	} while (mysqli_next_result($link));
	/*
	https://www.php.net/manual/en/mysqli.next-result.php
	If mysqli error reporting is enabled (MYSQLI_REPORT_ERROR) and the requested operation fails, a warning is generated. If, in addition, the mode is set to MYSQLI_REPORT_STRICT, a mysqli_sql_exception is thrown instead.
	*/
	
	return $res;
}

// XXX  

function sqlImportLimits() {
	$row = sqlRow("SHOW VARIABLES LIKE 'max_allowed_packet'");
	return [
		"max_allowed_packet" => ((int) $row["Value"] / 1048576) . "M",
	];
}

// XXX  

function sqlImport( $importId, &$txt ) {
	global $sys;
	/*
	There are multiple possible points of failure on Import:
	- file might be larger than allowed,
	- text might be larger than allowed,
	- browser might run out of memory if a large text inserted into textarea (unfixable),
	- PHP may run out of memory reading the file,
	- MOST LIKELY AND EXPECTED ALL THE TIME: import might be bigger than `max_allowed_packet` in MySQL,
	- and, of course, some incompatibility in commands themselves (wrong mode, wrong version, bad or corrupted file/text).
	
	There are no workarounds for almost anything in this list.
	Multiple warnings and all the relevant information is provided for the user.
	
	Foreign keys checks are disabled during import (not optional).
	*/
	
	$row = sqlRow("SHOW VARIABLES LIKE 'foreign_key_checks'");
	$foreignKeyChecks = $row["Value"];
	$row = sqlRow("SHOW VARIABLES LIKE 'autocommit'");
	$autoCommit = $row["Value"];
	
	sqlQuery("SET SESSION foreign_key_checks = OFF");
	sqlQuery("SET SESSION autocommit = OFF");
	
	session_start();
	$progress = json_decode($_SESSION["import_{$importId}"], true);
	$_SESSION["import_{$importId}"] = json_encode([
		"startedUnix" => $progress["startedUnix"],
		"progress" => translation("import-progress-starting"),
		"finished" => false,
	]);
	session_write_close();
	
	$link = $sys["db"]["link"];
	mysqli_multi_query($link, $txt);
	$txt = "";	// free RAM (import text can be massive)
	
	$queriesRun = 0;
	$rowsAffected = 0;
	$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used directly just as is
	
	/*session_start();
	$_SESSION["import_{$importId}"] = "{\"progress\":\"before `do`\"}";
	session_write_close();*/
	
	
	do {
		/*
		https://www.php.net/manual/en/mysqli.store-result.php
		If mysqli error reporting is enabled (MYSQLI_REPORT_ERROR) and the requested operation fails, a warning is generated. If, in addition, the mode is set to MYSQLI_REPORT_STRICT, a mysqli_sql_exception is thrown instead.
		*/
		if ($result = mysqli_store_result($link)) {
			$res["rows"][] = [
				"num_rows" => mysqli_num_rows($result),
				"affected_rows" => 0,
			];
			
			$rowsAffected += mysqli_num_rows($result);
			
			/*
			$numRows = mysqli_num_rows($result);	// if I understand it correctly, `num_rows` fill only be non-zero on SELECT (well, with anything selected)
			$res["rows"][] = [
				"num_rows" => $numRows,
				"affected_rows" => $numRows ? 0 : mysqli_affected_rows($link),
			];
			*/
			/*
			https://www.php.net/manual/en/mysqli.affected-rows.php
			`mysqli_affected_rows`
			Returns the number of rows affected by the last INSERT, UPDATE, REPLACE or DELETE query. Works like mysqli_num_rows() for SELECT statements.
			*/
			
			mysqli_free_result($result);
			
		}
		else {
			// https://www.php.net/manual/en/mysqli.store-result.php
			// mysqli_store_result() returns false in case the query didn't return a result set (if the query was, for example an INSERT statement). This function also returns false if the reading of the result set failed. You can check if you have got an error by checking if mysqli_error() doesn't return an empty string, if mysqli_errno() returns a non zero value, or if mysqli_field_count() returns a non zero value. Also possible reason for this function returning false after successful call to mysqli_query() can be too large result set (memory for it cannot be allocated). If mysqli_field_count() returns a non-zero value, the statement should have produced a non-empty result set.
			$res["rows"][] = [
				"num_rows" => 0,
				"affected_rows" => mysqli_affected_rows($link),
			];
			
			$rowsAffected += mysqli_affected_rows($link);
		}
		
		if (mysqli_error($link)) {
			break;
		}
		
		$queriesRun++;
		
		session_start();
		$progress = json_decode($_SESSION["import_{$importId}"], true);
		$curMemUsageBytes = memory_get_usage(true);
		$curMemUsageKb = number_format($curMemUsageBytes / 1024, 0, ".", ",");
		$_SESSION["import_{$importId}"] = json_encode([
			"startedUnix" => $progress["startedUnix"],
			"progress" => sprintf(
				translation("import-progress"),// . " /PHP `memory_get_usage` {$curMemUsageKb}Kb/ ",
				$numberFormat($queriesRun),
				$numberFormat($rowsAffected)
			),
			"finished" => false,
		]);
		session_write_close();
		
		// debug:
		//usleep(0.4 * 1000000);	// 1 second = 1000000
		
		// add `mysqli_more_results()` somewhere here?
		if (mysqli_more_results($link)) {
			// it's only a check if there are MORE queries further down the line, nothing else
			// only usable to track progress
			//$res["rows"][] = ["next" => "---", ];
		}
	} while (mysqli_more_results($link) && mysqli_next_result($link));
	/*
	https://www.php.net/manual/en/mysqli.next-result.php
	If mysqli error reporting is enabled (MYSQLI_REPORT_ERROR) and the requested operation fails, a warning is generated. If, in addition, the mode is set to MYSQLI_REPORT_STRICT, a mysqli_sql_exception is thrown instead.
	*/
	
	if (mysqli_errno($link)) {
		echo htmlspecialchars(mysqli_error($link));
		die();
	}
	
	/*session_start();
	$_SESSION["import_{$importId}"] = "{\"progress\":\"before `do`\",\"finished\":true}";
	session_write_close();*/
	
	session_start();
	$progress = json_decode($_SESSION["import_{$importId}"], true);
	$_SESSION["import_{$importId}"] = json_encode([
		"startedUnix" => $progress["startedUnix"],
		"progress" => sprintf(
			translation("import-progress"),
			$numberFormat($queriesRun),
			$numberFormat($rowsAffected)
		),
		"finished" => true,
	]);
	session_write_close();
	
	sqlQuery("SET SESSION foreign_key_checks = {$foreignKeyChecks}");	// `ON` or `OFF` are just set as is, no quotes even
	sqlQuery("SET SESSION autocommit = {$autoCommit}");
	
}

// XXX  

function sqlExport( $options ) {
	global $sys;
	/*
	
	Visual:
	- choice "file / text"
	- choice "Export data + structure / only data / only structure"
	- input "split by rows" with default 1000 (try smaller values if the server runs out of memory)
		it may even run with "split by 1", if the memory limit is small and table contains files
	
	*/
	
	// database must already be selected before
	// options are:
	// - `format`: "file" or "text"
	// ...NO, THAT'LL BE HANDLED ABOVE, IN THE CORE
	// - `tables` (array of strings, optional): list of table names to export
	// - `structure` (bool): export structure
	// - `data` (bool): export data
	// - `rows` (int): split by number of rows
	// - `transaction` (array of strings, optional): "structure", "data"
	
	/*
	File and text will be sent block-by-block, to free up the PHP memory as often as possible ("echo" puts the data on webserver side).
	JS will receive it and fill <textarea>, but not display it.
	After the receiving is finished, JS will send a "was the export successful?" request, which will use some unique ID.
	SESSION will be used to keep the track of success. sqlExport will set success to `true` at the end of the function, after finishing with the data sending.
	
	Candidates for unique ID:
		`$_SERVER["REMOTE_PORT"]` + micro time
		time() + uniqid(mt_rand(), true))
		block id + time() + uniqid(mt_rand(), true))
	*/
	
	// This logic might be naive and backfire, but here's the idea:
	// - if the value only consists of printable chars, use is as an escaped string,
	// - otherwise use escaped base64.
	// Some BLOB/BINARY data will be in string format this way, but that's how I'm treating it in the first initial alpha version of export. No field type check to leave the code simpler.
	
	// Keeping the code as short and simple as possible is a design choice
	
	/*
	Export structure is chosen?
		DROP TABLE IF EXISTS will be added before every CREATE TABLE
	Export data is chosen?
		TRUNCATE TABLE will be added before INSERT
	
	PHP may very well run out of RAM here, easily. The number of rows should be limited, just in case.
	When exported to text (textarea), the BROWSER may very well run out of RAM... not something I can do anything about.
	*/
	
	
	/*
	FIXME . . . Should I LOCK all tables before export against data change while reading/outputting data???
	The lock can happen in-between writes to different tables making the data inconsistent anyway (in non-transactional engines or for operations without a transaction)...
	
	https://stackoverflow.com/questions/18096397/what-happens-to-an-insert-during-a-mysql-backup
	"The tables are locked with READ LOCAL to permit concurrent inserts in the case of MyISAM tables."
	...what???!!!
	My search was: "mysql what happens if data change while dumping data"
	So, I discovered that it's a real problem and it happens, and there's no real way. I think even a dedicated backup-only slave is not a guarantee, because the database doesn't know which queries are related, and which are not.
	Only an InnoDB-only database could possibly be safe to save, but again, only if all the related queries are executed in the transaction, too... so, it depends on both the tables' engine and the code. Transactional engine and perfect transactional code = can make backups. On a dedicated slave, because locking production is bad, mkay!
	*/
	
	
	if (isset($options["tables"])) {
		$tables = $options["tables"];
	}
	else {
		$tablesRaw = sqlArray("SHOW TABLES");
		// the first key is like `Tables_in_{databaseName}`, which I hate, I should have probably went with `information_schema` from the start and not do this workaround...
		// FIXME . . . the code below is degenerate AF, I _must_ rewrite it to `information_schema` instead of `SHOW TABLES`
		$keys = array_keys($tablesRaw[0]);	// first column of the first row, because that's the structure
		$firstKey = array_shift($keys);
		$tables = array_column($tablesRaw, $firstKey);
		
		// ??? can I easily rekey a whole array column?
		
	}
	
	// I need to know which tables are in fact views:
	$viewsRaw = sqlArray("
		SELECT table_name AS name
		FROM information_schema.tables
		WHERE 	table_schema = '{$sys["db"]["dbName"]}'
				AND table_type = 'VIEW'
	");
	$views = $viewsRaw ? array_column($viewsRaw, "name") : [];
	
	/*
	
	Of course, I expect that 1Gb value in a cell + < 1Gb memory available for PHP will cause a fatal error, but that's not fixable from the code standpoint.
	One thing that makes it worse is that 1Gb value in a cell will need MUCH more than 1Gb of RAM, because I'm using `base64_encode`, and RAM will be needed to store both original value and base64 value, even with 1-row-per-insert. But again, there is no solution here in the code for that.
	
	*/
	
	$onPage = $options["rows"];
	
	$version = SQL_VERSION;
	$dateFormat = "Y-m-d H:i";
	$dateStr = date($dateFormat);
	$lines = [
		"SQLantern export",
		"version {$version}",
		"https://sqlantern.com/",
		"",
		"Database: `{$sys["db"]["dbName"]}`",
		"Generated at (server time): {$dateStr}",
	];
	echo "-- " . implode("\n-- ", $lines);
	echo "\n\n";
	
	/*
	Transaction choice is:
	- EVERYTHING into transaction,
	- ONLY DATA into transaction,
	- DON'T USE transaction
	*/
	
	//$transactionData = $options["transaction"] && in_array("data", $options["transaction"]);
	//$transactionStructure = $options["transaction"] && in_array("everything", $options["transaction"]);
	if ($options["transactionData"] && $options["transactionStructure"]) {	// put everything into transaction
		echo "BEGIN;\n\n";
	}
	
	/*
	// non-disableable global transaction didn't work well
	echo "BEGIN;\n\n";
	*/
	
	foreach ($tables as $t) {
		$tableSql = sqlEscape($t);
		$isView = in_array($t, $views);
		
		if ($options["structure"]) {
			$row = sqlRow("SHOW CREATE TABLE `{$tableSql}`");
			if ($isView) {	// Views are dropped differently, and AUTO_INCREMENT is not applicable
				echo "DROP VIEW IF EXISTS `{$tableSql}`;\n\n";	// delete view if exists (not optional)
				echo "{$row["Create View"]};\n\n";
			}
			else {
				echo "DROP TABLE IF EXISTS `{$tableSql}`;\n\n";	// delete table if exists (not optional)
				echo "{$row["Create Table"]};\n\n";
				if (!$options["data"]) {	// reset auto increment if ONLY structure is exported
					// now, `TRUNCATE` vs `ALTER TABLE` to reset the `AUTO_INCREMENT` is not a technical choice, but a matter of taste, IMHO
					// I'm going with `ALTER TABLE` just because it makes more sense to me, it'll be more obvious in the exported dump for a human
					// There can only be 1 auto-increment field and I don't need to specify anything else, am I right???
					echo "ALTER TABLE `{$tableSql}` AUTO_INCREMENT = 1;";
				}
			}
		}
		elseif ($options["data"] && !$isView) {	// data without structure
			echo "TRUNCATE `{$tableSql}`;\n\n";	// empty table (not optional)
		}
		
		if (!$options["data"] || $isView) {
			continue;
		}
		
		if ($options["transactionData"] && !$options["transactionStructure"]) {	// only data into transaction
			echo "BEGIN;\n\n";
		}
		
		$rows = sqlArray("DESCRIBE `{$tableSql}`");
		$fields = array_column($rows, "Field");
		$fieldsSql = implode("`, `", $fields);
		
		$result = mysqli_query($sys["db"]["link"], "SELECT * FROM `{$tableSql}`", MYSQLI_USE_RESULT);
		// MYSQLI_USE_RESULT - returns a mysqli_result object with UNBUFFURED result set
		// that is the key to not run out of memory on every occasion
		
		$valuesSql = [];
		
		while ($row = mysqli_fetch_assoc($result)) {
			//echo "[{$row["id"]}/{$row["bug_id"]}]";
			$saveValues = [];
			foreach ($row as $column) {
				if (is_null($column)) {
					$useValue = "NULL";
				}
				elseif (!mb_detect_encoding($column ? $column : "", "UTF-8", true)) {	// contains non-displayable chars
				//if (!mb_detect_encoding($column ? $column : "", null, true)) {	// contains non-displayable chars
					// FIXME . . . should I use "UTF-8" instead of `null` further above, too?..
					$useValue = "FROM_BASE64('" . sqlEscape(base64_encode($column)) . "')";
				}
				else {
					$useValue = "'" . sqlEscape($column) . "'";
				}
				$saveValues[] = $useValue;
			}
			$valuesSql[] = implode(", ", $saveValues);	// all values of one row into a prepared string
			unset($saveValues);
			
			if (count($valuesSql) >= $onPage) {	// I don't think I can get the number of rows here anyhow (without an additional query, I mean)
				// limit reached, output INSERT statement
				$allValuesSql = implode("), (", $valuesSql);
				
				$insertSql = "INSERT INTO `{$tableSql}` (`{$fieldsSql}`) VALUES ({$allValuesSql});\n\n";
				
				echo $insertSql;
				
				// free some memory:
				unset($allValuesSql, $insertSql);
				
				$valuesSql = [];
			}
		}
		
		if ($valuesSql) {
			$allValuesSql = implode("), (", $valuesSql);
			
			$insertSql = "INSERT INTO `{$tableSql}` (`{$fieldsSql}`) VALUES ({$allValuesSql});\n\n";
			
			echo $insertSql;
			
			// free some memory:
			unset($allValuesSql, $insertSql);
		}
		
		$valuesSql = [];
		
		mysqli_free_result($result);
		
		if ($options["transactionData"] && !$options["transactionStructure"]) {	// only data into transaction
			echo "COMMIT;\n";
		}
	}
	
	/*
	echo "COMMIT;\n";
	*/
	
	if ($options["transactionData"] && $options["transactionStructure"]) {	// put everything into transaction
		echo "COMMIT;\n";
	}
}

//