<?php
/*
The base PHP lib/pgsql implementation for SQLantern by nekto
v1.0.3 alpha | 24-01-01

This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023 Misha Grafski AKA nekto
License: GNU General Public License v3.0
https://github.com/nekto-kotik/sqlantern
https://sqlantern.com/

SQLantern is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
*/


/*

https://www.php.net/manual/en/book.pgsql.php

*/



// test area:
if (false) {
	
	$dbconn = pg_connect("host=192.168.1.115 dbname=checkprice user=liana password=renversable")
	or die("Could not connect: " . pg_last_error());
	// databases: `checkprice`, `chatpoint`
	// tables: checkprice.texts, checkprice.text_history
	
	pg_query($dbconn, "SET CLIENT_ENCODING TO 'UTF-8'");
	
	//$query = "SELECT * FROM texts";
	//$query = "SHOW TABLES";
	
	$query = "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog', 'information_schema')";	// SHOW TABLES
	
	//$query = "SELECT datname FROM pg_database";	// SHOW DATABASES
	
	$result = pg_query($dbconn, $query) or die("Query failed: " . pg_last_error());
	
	$rows = [];
	while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		foreach ($line as $col_value) {
		}
		$rows[] = $col_value;
	}
	
	var_dump(["rows" => $rows, ]);
	
	
	pg_free_result($result);
	
	
	pg_close($dbconn);
	
	die();
}


// XXX  

function sqlQuote() {
	/*
	https://www.prisma.io/dataguide/postgresql/short-guides/quoting-rules
	
	> Double quotes are used to indicate identifiers within the database, which are objects like tables, column names, and roles. In contrast, single quotes are used to indicate string literals.
	
	https://www.postgresql.org/docs/current/sql-syntax-lexical.html
	
	> There is a second kind of identifier: the delimited identifier or quoted identifier. It is formed by enclosing an arbitrary sequence of characters in double-quotes ("). A delimited identifier is always an identifier, never a key word. So "select" could be used to refer to a column or table named “select”, whereas an unquoted select would be taken as a key word and would therefore provoke a parse error when used where a table or column name is expected.
	
	> Quoting an identifier also makes it case-sensitive, whereas unquoted names are always folded to lower case. For example, the identifiers FOO, foo, and "foo" are considered the same by PostgreSQL, but "Foo" and "FOO" are different from these three and each other. (The folding of unquoted names to lower case in PostgreSQL is incompatible with the SQL standard, which says that unquoted names should be folded to upper case. Thus, foo should be equivalent to "FOO" not "foo" according to the standard. If you want to write portable applications you are advised to always quote a particular name or never quote it.)
	*/

	return "\"";
	
}

// XXX  

function sqlDisconnect() {
	global $sys;
	
	if (is_object($sys["db"]["link"])) {
		pg_close($sys["db"]["link"]);
		unset($sys["db"]["link"]);
	}
}

// XXX  

function sqlConnect() {
	global $sys;
	
	if (isset($sys["db"]["link"])) {	// only go further if not connected yet
		return;
	}
	
	$cfg = $sys["db"];
	
	$cfg["dbName"] = $cfg["dbName"] ? $cfg["dbName"] : SQL_POSTGRES_CONNECTION_DATABASE;
	
	$passwordStr = str_replace(
		["'", "\\", ],
		["\\'", "\\\\", ],
		$cfg["password"]
	);
	
	$sys["db"]["link"] = pg_connect(
		/*
		To write an empty value or a value containing spaces, surround it with single quotes, e.g., keyword = 'a value'. Single quotes and backslashes within the value must be escaped with a backslash, i.e., \' and \\.
		
		also:
		`$dbconn5 = pg_connect("host=localhost options='--client_encoding=UTF8'");`
		
		Connection without specifying a database does not work...
		
		This is insane...
		https://stackoverflow.com/questions/42112781/how-to-connect-to-a-postgres-database-without-specifying-a-database-name-in-pdo
		`I would normally use template1 for this, because in theory the postgres database might have been dropped, and it is dangerous to connect to the template0 database in case you accidentally change it. Since template1 is used as the default template when creating databases, it would most likely exist.`
		
		*/
		"host='{$cfg["host"]}' user='{$cfg["user"]}' password='{$passwordStr}' dbname='{$cfg["dbName"]}'"
		//"{$cfg["host"]}:{$cfg["port"]}", $cfg["user"], $cfg["password"], $cfg["dbName"]
	)
		or
	//fatalError("CONNECTION DENIED FOR {$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]}", true);
	fatalError(sprintf(translation("connection-failed-real"), "{$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]}"), true);
	
	$sys["db"]["setCharset"] = "utf8";
	$setCharset = isset($sys["db"]["setCharset"]) ? $sys["db"]["setCharset"] : SQL_POSTGRES_CHARSET;
	pg_query($sys["db"]["link"], "SET CLIENT_ENCODING TO '{$setCharset}'");
}

// XXX  

function sqlQuery( $queryString ) {
	global $sys;
	sqlConnect();
	$res = pg_query($sys["db"]["link"], $queryString);
	if ($res === false) {
		// As of PHP 8.1.0, using the default connection is deprecated.
		// Before that, when `connection` is `null` (the first and only possible argument), the default connection was used. The default connection is the last connection made by pg_connect() or pg_pconnect().
		// Another 8.1.0 change: "The `connection` parameter expects an `PgSql\Connection` instance now; previously, a `resource` was expected."
		$errorArgument = version_compare(phpversion(), "8.1.0", "<=") ? null : $sys["db"]["link"];
		$trimmed = htmlspecialchars(trim($queryString));
		fatalError(
			implode(
				"<br>",
				[
					htmlspecialchars(pg_last_error($errorArgument)),
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
	$res = sqlQuery($queryString); 
	
	if (pg_num_rows($res)) { 
		$answer = [];
		while ($row = pg_fetch_array($res, null, PGSQL_ASSOC)) {
			$answer[] = $row;
		}
		pg_free_result($res);
		return $answer;
	}
	else
		return null;
}

// XXX  

function sqlRow( $queryString ) {
	$res = sqlQuery($queryString);
	// FIXME . . . add `pg_free_result`, don't just return `pg_fetch_array`
	return ($res && pg_num_rows($res)) ? pg_fetch_array($res, null, PGSQL_ASSOC) : null;
}

// XXX  

function sqlEscape( $str ) {
	global $sys;
	return pg_escape_string($sys["db"]["link"], (string) $str);
}

// XXX  

function sqlListDb() {
	$query = "SELECT datname AS \"Database\" FROM pg_database ORDER BY datname ASC";	// SHOW DATABASES
	// double quotes for it to stay "Database", not converted to "database"
	// FIXME . . . list only those the user can read, it apparently lists all...
	
	$databases = sqlArray($query);
	
	if (SQL_DISPLAY_DATABASE_SIZES) {
		$bytesFormat = SQL_BYTES_FORMAT;	// constants can't be used directly as functions
		
		// is this slow?!
		/*
		$row = sqlArray("
			SELECT
				SUM(pg_total_relation_size(c.oid)) AS total_bytes
			FROM pg_class AS c
			LEFT JOIN pg_namespace AS n
				ON n.oid = c.relnamespace
			WHERE	nspname NOT IN ('pg_catalog', 'information_schema')
					AND relkind = 'r'
		");*/
		// wait... it's for the database I'm connected to... how to do all databases???
		
		// is THIS slow?!
		$sizes = sqlArray("
			SELECT
				d.datname AS Name,
				pg_catalog.pg_get_userbyid(d.datdba) AS Owner,
				CASE
					WHEN pg_catalog.has_database_privilege(d.datname, 'CONNECT')
					THEN pg_catalog.pg_database_size(d.datname)
					ELSE 0
				END AS Size
			FROM pg_catalog.pg_database AS d
		");
		$sizesNames = array_column($sizes, "name");
		$maxSize = max(array_column($sizes, "size"));
		
		foreach ($databases as &$d) {
			$k = array_search($d["Database"], $sizesNames);
			$d["Size"] = ($k !== false) ? $bytesFormat((int) $sizes[$k]["size"], $maxSize) : "";
		}
		unset($d);
	}
	
	return $databases;
}

// XXX  

function sqlListTables() {
	/*
	$query = "
		SELECT tablename AS Table
		FROM pg_catalog.pg_tables
		-- WHERE table_type ???
		WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
	";	// SHOW TABLES
	*/
	
	$query = "
		SELECT *
		FROM (
			SELECT
				tablename AS \"Table\", 'table' AS type
			FROM pg_catalog.pg_tables
			-- WHERE table_type ???
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
			-- -- --
			UNION ALL
			-- -- --
			SELECT
				viewname AS \"Table\", 'view' AS type
			FROM pg_catalog.pg_views
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
		) AS tables_views
		ORDER BY tables_views.\"Table\" ASC
	";
	
	$tables = sqlArray($query);
	$views = [];
	
	if ($tables) {
		$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used directly just as is
		$bytesFormat = SQL_BYTES_FORMAT;
		
		// multiple people on the internet are mass-suggesting the XML query below to get the accurate number of rows
		// as far as I understood, it is not compatible with PosgreSQL < 9.4, but like seriously, 9.4 is 2015, come on
		$rows = sqlArray("
			SELECT
				table_name,
				(
					xpath('/row/count/text()', query_to_xml('SELECT COUNT(*) from '||format('%I.%I', table_schema, table_name), true, true, ''))
				)[1]::text::int AS rows_count
			FROM information_schema.tables
			WHERE table_schema = 'public'
		");
		//var_dump(["rows" => $rows, ]);
		$rowsTablesNames = array_column($rows, "table_name");
		
		// a great source: https://wiki.postgresql.org/wiki/Disk_Usage
		// I don't know if that's a slow way on big databases :-(
		$sizes = sqlArray("
			SELECT
				c.oid,
				nspname AS table_schema,
				relname AS table_name,
				c.reltuples AS row_estimate,
				pg_total_relation_size(c.oid) AS total_bytes,
				pg_indexes_size(c.oid) AS index_bytes,
				pg_total_relation_size(reltoastrelid) AS toast_bytes
			FROM pg_class AS c
			LEFT JOIN pg_namespace AS n
				ON n.oid = c.relnamespace
			WHERE	nspname NOT IN ('pg_catalog', 'information_schema')
					AND relkind = 'r'
		");
		$sizesTablesNames = array_column($sizes, "table_name");
		$maxSize = max(array_column($sizes, "total_bytes"));
		
		foreach ($tables as &$t) {
			$k = array_search($t["Table"], $rowsTablesNames);
			$t["Rows"] = ($k !== false) ? $numberFormat((int) $rows[$k]["rows_count"]) : "";
			
			$k = array_search($t["Table"], $sizesTablesNames);
			$t["Size"] = ($k !== false) ? $bytesFormat((int) $sizes[$k]["total_bytes"], $maxSize) : "???";
			
			if ($t["type"] == "view") {
				$views[] = $t["Table"];
				$t["Size"] = "";
			}
			unset($t["type"]);
		}
		unset($t);
	}
	
	//return $tables;
	return [
		"tables" => $tables,
		"views" => $views,
	];
}

// XXX  

function sqlDescribeTable( $databaseName, $tableName ) {
	$tableNameSql = sqlEscape($tableName);
	return [
		/*
		There is no mention of the database in the following requests, because, as far as I understand, the database in the connection limits those results to the, well, the database in the connection.
		This means I cannot read other databases tables when connected to a database, but that's not a problem really, just a PosgreSQL feature. If I understand it correctly.
		*/
		"structure" => sqlArray("
			SELECT
				attr.attname AS \"Field\",
				pg_catalog.format_type(attr.atttypid, attr.atttypmod) AS \"Type\"
				-- CASE WHEN attr.attnotnull IS TRUE THEN 'NO' ELSE 'YES' END AS \"Null\",
				-- pg_catalog.pg_get_expr(attrdef.adbin, attrdef.adrelid, true) AS \"Default\"
			
			FROM pg_catalog.pg_attribute AS attr
			LEFT JOIN pg_catalog.pg_attrdef AS attrdef 
				ON 	(attr.attrelid = attrdef .adrelid
					AND attr.attnum = attrdef .adnum)
			LEFT JOIN pg_catalog.pg_type AS pg_type
				ON attr.atttypid = pg_type.oid
			
			LEFT JOIN (
				SELECT oid, relname, relnamespace
				FROM pg_catalog.pg_class
			) AS catalog
				ON catalog.oid = attr.attrelid
			
			WHERE 	attr.attnum > 0
					AND NOT attr.attisdropped
					AND catalog.relname = '{$tableNameSql}'
			ORDER BY attr.attnum ASC
		"),
		
		// apparently, there is no such thing as "unique" or "cardinality" in PostgreSQL...
		// I should really look deeper into it, I find it hard to believe Postgres doesn't show that important info
		// but I also know indexes here are very different from MySQL
		"indexes" => sqlArray("
			SELECT
				indexname AS \"index\",
				'' AS columns,
				indexdef
			FROM pg_indexes
			WHERE tablename = '{$tableNameSql}'
		"),
	];
}

// XXX  

function sqlRunQuery( $query, $page, $fullTexts ) {
	global $sys;
	
	$res = [];
	$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used directly just as is
	
	// First, try to detect `SELECT`, the commenting rules are similar to MySQL:
	// `-- comments here`, `/* comment here */`
	// this is a primitive detection, but it must work for 99.9% of cases
	// I'm not installing an SQL parsing library (adding to the complexity and dependencies)
	
	$lines = explode("\n", $query);	// should `\r` be used, too?
	foreach ($lines as &$l) {
		$l = trim($l);	// no whitespaces
	}
	unset($l);
	
	// remove all lines starting with `-- ` (two dashes followed by a white space, one-line comments), and empty lines
	$lines = array_filter(
		$lines,
		function ($l) {
			$parts = preg_split("/\\s/", $l);
			return $l && ($parts[0] != "--");	// must NOT start with `--`
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
	$words = array_filter($words, function($w) { return $w; });
	$firstQueryWordLower = mb_strtolower($words[0], "UTF-8");
	
	if ($firstQueryWordLower == "select") {
		// If the query is an obvious `SELECT` without `LIMIT`, add the default `LIMIT`
		// If the query has `LIMIT`, don't add one, it's the human's decision to see the amount of rows they need
		
		// `LIMIT` in PostgreSQL can be `LIMIT`, `LIMIT ... OFFSET ...`, or `OFFSET ... FETCH ...`
		// SQLantern only supports `LIMIT`/`LIMIT ... OFFSET ...` detection, as of now
		// and it is only detected if it is the last clause in the query, making `FOR` not supported
		
		/*
		`LIMIT x, y` or `LIMIT x OFFSET y` must be the last line of the query.
		There exist other legit queries, with `into_option` or `FOR` after `LIMIT`, but this tool **does not support those queries**.
		The reason to check if the `LIMIT` is to add paginattion if `LIMIT` is not specified.
		
		FIXME . . . looks like Postgres only supports `LIMIT ... OFFSET ...`, and no `LIMIT ..., ...`!
				make the code below simpler!!!
		
		*/
		
		// replace commas with spaces, to force `LIMIT 20,OFFSET 20` or `LIMIT 20,100` become separate words
		$str = mb_strtolower(str_replace(",", " ", $queryWithoutComments), "UTF-8");	// also make it lowercase
		$words = preg_split("/\\s/", $str);
		$words = array_values(array_filter($words, function($w) { return $w; }));	// array_values to reset indexes, because array_filter keeps indexes and makes no sense trying to address words by `count minus {n}` below, derp, derp, derp...
		
		$setLimit = true;
		$countWords = count($words);
		if (
			($countWords > 5)	// at least give me a `SELECT {x} FROM {y} LIMIT {z}` query (which might glitch on synthetic queries, but not on real)
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
			$setLimit = false;
		}
		
		/*
		Problem: If there is a syntax error in query, the error with COUNT is displayed, which is confusing.
		Solution: Run `EXPLAIN` the given query first. If `EXPLAIN` fails, the query has an error. In this case I can run the raw query just to display that error. If `EXPLAIN` works, I can go on with COUNT.
		The nice thing is `EXPLAIN` doesn't (shouldn't) take the same time as running the query, so there's only a small and neglectable time/resources loss.
		*/
		if (!pg_query($sys["db"]["link"], "EXPLAIN {$query}")) {
			sqlQuery($query);	// sqlQuery will output the error
			die("Line " . __LINE__);	// just in case...
		}
		
		$onPage = SQL_ROWS_PER_PAGE;
		$countQuery = "
			SELECT COUNT(*) AS n FROM (
				{$query}
			) AS t
		";
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
		
		$dbResult = pg_query($sys["db"]["link"], $countQuery);
		if ($dbResult === false) {	// the query is good, but COUNT failed
			//$row = ["n" => -1];	// could not COUNT, same as in mysqli
			/*
			I could only see this when running multiple statements at a time, like:
			```
			SELECT * FROM tmp_person WHERE id < 100;
			SELECT * FROM tmp_person WHERE id > 100;
			```
			Which SQLantern doesn't support anyway... so, I think I should fail, and not return anything, to align with the one-query policy.
			*/
			$trimmed = htmlspecialchars(trim($query));
			fatalError(
				implode(
					"<br>",
					[
						"Internal SQLantern failure",
						"--",
						"{$trimmed}"
					]
				)
			);
		}
		else {
			$row = pg_fetch_array($dbResult, null, PGSQL_ASSOC);
		}
		
		$numRows = (int) $row["n"];
		$numPages = ceil($numRows / $onPage);
		$res["num_rows"] = $numberFormat($numRows);
		$res["num_pages"] = $numberFormat($numPages);
		
		$humanPage = $page ? $page : 1;
		if (($humanPage < 0) || ($humanPage > $numPages))
			$humanPage = 1;
		$machinePage = $humanPage - 1;
		$res["cur_page"] = $humanPage;
		$offset = $machinePage * $onPage;
		
		$useQuery = $query . ($setLimit ? " LIMIT {$onPage} OFFSET {$offset}" : "");	// add LIMIT sometimes
	}
	else {
		$useQuery = $query;
	}
	
	$res["real_executed_query"] = $useQuery;
	$res["rows"] = [];
	
	// I cannot really use sqlArray here, with the new logic in mind...
	// If the result has `num_rows`, it might have pagination, run the query for pagination
	// If the result has `affected_rows`, return the number of affected rows, no matter the type of query
	
	// FIXME . . . NO, that MUST be handled in sqlArray actually, just like it is in `mysqli` now
	
	$dbResult = pg_query($sys["db"]["link"], $useQuery);
	/*
	WHAAAT???
	"When multiple statements are passed to the function, they are automatically executed as one transaction, unless there are explicit BEGIN/COMMIT commands included in the query string. However, using multiple transactions in one function call is not recommended."
	*/
	
	//var_dump(["useQuery" => $useQuery, "dbResult" => $dbResult, ]);
	
	
	// unlike mysqli, `pg_query` cannot return `true`, only `false` or result
	if ($dbResult === false) {
		// As of PHP 8.1.0, using the default connection is deprecated.
		// Before that, when `connection` is `null` (the first and only possible argument), the default connection was used. The default connection is the last connection made by pg_connect() or pg_pconnect().
		// Another 8.1.0 change: "The `connection` parameter expects an `PgSql\Connection` instance now; previously, a `resource` was expected."
		$errorArgument = version_compare(phpversion(), "8.1.0", "<=") ? null : $sys["db"]["link"];
		$trimmed = htmlspecialchars(trim($useQuery));
		fatalError(
			implode(
				"<br>",
				[
					htmlspecialchars(pg_last_error($errorArgument)),
					"--",
					"{$trimmed}"
				]
			)
		);
	}
	
	// `pg_num_rows` always exists, it seems like
	// `pg_affected_rows` also seems to always exist
	// so the logic "what query is this" is not as simple as I hoped...
	
	//var_dump(["pg_num_rows" => pg_num_rows($dbResult), ]); die();
	
	if (($firstQueryWordLower == "select") || pg_num_rows($dbResult)) {	// Some queries return rows, even without being a `SELECT`, e.g. `SHOW search_path`. But `SELECT`s are treated specifically, to show "0 rows" when there are no results, and not just a confusing "executed".
		
		$fields = [];
		$tables = [];
		for ($f = 0; $f < pg_num_fields($dbResult); $f++) {
			$fields[] = pg_field_name($dbResult, $f);
			$tableName = pg_field_table($dbResult, $f);	// this is different from `mysqli_fetch_fields`: mysqli lists table OR alias in `table`, but pgsql only returns the real table name, alias is never to be seen, and this might be confusing; there's nothing I can do about it
			$tables[] = $tableName ? $tableName : "";
		}
		$fieldNames = deduplicateColumnNames($fields, $tables);
		
		// associative way, losing columns with duplicate names:
		/*while ($row = pg_fetch_array($dbResult, null, PGSQL_ASSOC)) {
			$res["rows"][] = $row;
		}*/
		// listing all columns, even if they have the same name:
		while ($row = pg_fetch_array($dbResult, null, PGSQL_NUM)) {
			$fixedRow = [];
			foreach ($row as $fieldIdx => $v) {
				$fixedRow[$fieldNames[$fieldIdx]] = $v;
			}
			$res["rows"][] = $fixedRow;
		}
		
		foreach ($res["rows"] as &$row) {	// rows
			foreach ($row as &$v) {	// columns in row
				if (is_null($v)) {	// leave NULL as is
					continue;
				}
				
				// BLOB and other BINARY data is not JSON compatible and MUST be treated, unfortunately
				if (json_encode($v) === false) {	// this proved to be the fastest way 
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
		}
		unset($row, $v);
	}
	else {	// not SELECT, as detected by the stupid logic above...
		$affectedRows = pg_affected_rows($dbResult);
		if ($affectedRows) {	// don't confuse users with "affected rows: 0" on TRUNCATE, basically
			$res["rows"] = [
				["affected_rows" => $affectedRows],
			];
		}
		else {	// "executed" is not ideal and a bit confusing, too, but that's what it is at this point
			$res["rows"] = [
				["state" => "executed"],
			];
		}
	}
	
	pg_free_result($dbResult);
	
	return $res;
}

// XXX  

function sqlQueryTiming( $query ) {
	global $sys;
	/*
	This was very helpful:
	https://www.postgresql.org/docs/current/sql-explain.html
	*/
	
	//$row = sqlRow("EXPLAIN (ANALYZE true, FORMAT JSON) {$query}");
	
	$dbResult = pg_query($sys["db"]["link"], "EXPLAIN (ANALYZE true, FORMAT JSON) {$query}");
	if ($dbResult === false) {	// EXPLAIN failed, but it might be a valid EXPLAIN-imcompatible query
		$timeBefore = microtime(true);	// `hrtime` is better, but it's PHP 7+ (7.3+ even?)
		sqlRow($query);	// try running the query without EXPLAIN; if there is an error in query, `sqlRow` will throw it
		$durationPHP = microtime(true) - $timeBefore;	// if we're here, the query was actually executed correctly, give at least some non-precise measurement...
		return [
			"timeMs" => "n/a (~" . round($durationPHP * 1000, 4) . ")",
		];
	}
	else {
		$row = ($dbResult && pg_num_rows($dbResult)) ? pg_fetch_array($dbResult, null, PGSQL_ASSOC) : null;
		$keys = array_keys($row);
		//var_dump(["row" => $row, ]);
		$values = json_decode($row[$keys[0]], true);
		//var_dump(["values" => $values, ]);
		$durationMs = $values[0]["Planning Time"] + $values[0]["Execution Time"];	// `$values[0]` because only one query is analyzed, if I understand it correctly
		return [
			"timeMs" => round($durationMs, 4),
		];
	}
}

// XXX  

/*

https://serverfault.com/questions/231952/is-there-an-equivalent-of-mysqls-show-create-table-in-postgres

CONCERNING `SHOW CREATE TABLE`

I realize I'm a bit late to this party, but this was the first result to my Google Search so I figured I'd answer with what I came up with.

You can get pretty far toward a solution with this query to get the columns:
```
SELECT *
FROM information_schema.columns
WHERE table_schema = 'YOURSCHEMA' AND table_name = 'YOURTABLE'
ORDER BY ordinal_position;
```

And then this query for most common indexes:

```
SELECT c.oid, c.relname, a.attname, a.attnum, i.indisprimary, i.indisunique
FROM pg_index AS i, pg_class AS c, pg_attribute AS a
WHERE i.indexrelid = c.oid AND i.indexrelid = a.attrelid AND i.indrelid = 'YOURSCHEMA.YOURTABLE'::regclass
ORDER BY" => "c.oid, a.attnum
```

Then it is a matter of building out the query string(s) in the right format.

> More...

https://stackoverflow.com/questions/62258841/generate-create-table-statements-in-postgresql

> People keep mentioning using the shell command to get the statements (`pg_dump`). I'm not doing this: not only that is a disaster, the program will often NOT have `pg_dump` available locally at all, because working with remote servers (like I usually do).

https://dba.stackexchange.com/questions/254183/postgresql-equivalent-of-mysql-show-create-xxx

> And devs don't even realize the problem and ask for use-cases, I mean seriously, how detached one must be...

https://www.postgresql.org/message-id/CAFEN2wxg0Vtj1gvk6Ms0L2CAutbycyxHZPiZSpW7eLsBc6VGnA%40mail.gmail.com

> Export/Import in pgsql shifts to Version 2 or beyond.
And I might have to switch to PDO for unbuffered results, that needs separate testing.

*/

/*
function sqlExport( $options ) {
	global $sys;
	
	if (isset($options["tables"])) {
		$tables = $options["tables"];
	}
	else {
		$query = "
			SELECT tablename AS Table
			FROM pg_catalog.pg_tables
			-- WHERE table_type ???
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
		";	// SHOW TABLES
		$tablesRaw = sqlArray($query);
		$tables = array_column($tablesRaw, "table");
	}
	
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
	
	if ($options["transactionData"] && $options["transactionStructure"]) {	// put everything into transaction
		echo "BEGIN;\n\n";
	}
	
	foreach ($tables as $t) {
		$tableSql = sqlEscape($t);
		
		if ($options["structure"]) {
			echo "DROP TABLE IF EXISTS `{$tableSql}`;\n\n";	// delete table if exists (not optional)
			
			
			
			
			?????  $row = sqlRow("SHOW CREATE TABLE `{$tableSql}`");
			
			
			
			
			
			echo "{$row["Create Table"]};\n\n";
		}
		elseif ($options["data"]) {	// data without structure
			echo "TRUNCATE `{$tableSql}`;\n\n";	// empty table (not optional)
		}
		
	}
	
	if ($options["transactionData"] && $options["transactionStructure"]) {	// put everything into transaction
		echo "COMMIT;\n";
	}
}
*/

//