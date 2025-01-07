<?php
/*
The base PHP lib/pgsql implementation for SQLantern by nekto
v1.0.12 alpha | 25-01-07

This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023, 2024, 2025 Misha Grafski AKA nekto
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
	
	$dbconn = pg_connect("host=192.168.1.115 dbname=*** user=*** password=***")
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
	
	if (array_key_exists("link", $sys["db"])) {	// only go further if not connected yet
		return;
	}
	
	$cfg = $sys["db"];
	
	$cfg["dbName"] = $cfg["dbName"] ? $cfg["dbName"] : SQLANTERN_POSTGRES_CONNECTION_DATABASE;
	
	$passwordStr = str_replace(
		["'", "\\", ],
		["\\'", "\\\\", ],
		$cfg["password"]
	);
	
	$sys["db"]["link"] = pg_connect(
		/*
		To write an empty value or a value containing spaces, surround it with single quotes, e.g., keyword = 'a value'. Single quotes and backslashes within the value must be escaped with a backslash, i.e., \' and \\.
		
		> Also this:
		`$dbconn5 = pg_connect("host=localhost options='--client_encoding=UTF8'");`
		
		Hm, connection without specifying a database does not work...
		This is insane...
		https://stackoverflow.com/questions/42112781/how-to-connect-to-a-postgres-database-without-specifying-a-database-name-in-pdo
		`I would normally use template1 for this, because in theory the postgres database might have been dropped, and it is dangerous to connect to the template0 database in case you accidentally change it. Since template1 is used as the default template when creating databases, it would most likely exist.`
		
		*/
		"host='{$cfg["host"]}' port={$cfg["port"]} user='{$cfg["user"]}' password='{$passwordStr}' dbname='{$cfg["dbName"]}'"
		//"{$cfg["host"]}:{$cfg["port"]}", $cfg["user"], $cfg["password"], $cfg["dbName"]
	)
		or
	fatalError(sprintf(translation("connection-failed-real"), "{$cfg["user"]}@{$cfg["host"]}:{$cfg["port"]}"), true);
	
	/*
	I'd like to add real errors here, but as far as I can see `pg_connect` only returns `false` and there is no synonim of `mysqli_connect_error` :-(
	*/
	
	$sys["db"]["setCharset"] = "utf8";
	$setCharset = isset($sys["db"]["setCharset"]) ? $sys["db"]["setCharset"] : SQLANTERN_POSTGRES_CHARSET;
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
	else {
		return null;
	}
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
	//$query = "SELECT datname AS \"Database\" FROM pg_database ORDER BY datname ASC";	// SHOW DATABASES
	
	$query = "
		SELECT
			datname AS \"Database\",
			COALESCE(pg_catalog.shobj_description(oid, 'pg_database'), '') AS comment_str
		FROM pg_database
		ORDER BY datname ASC
	";
	
	// double quotes for it to stay "Database", not converted to "database"
	// FIXME . . . list only those the user can read, it apparently lists more...
	
	$databases = sqlArray($query);
	
	if (getSetting("display_databases_sizes")) {
		$bytesFormat = SQLANTERN_BYTES_FORMAT;	// constants can't be used directly as functions
		
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
		
		// is THIS slow?! < 3Gb database with 10M rows says it's not
		$sizes = sqlArray("
			SELECT
				d.datname AS name,
				pg_catalog.pg_get_userbyid(d.datdba) AS owner,
				CASE
					WHEN pg_catalog.has_database_privilege(d.datname, 'CONNECT')
					THEN pg_catalog.pg_database_size(d.datname)
					ELSE 0
				END AS size
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
	
	// if any database has a comment, add a "comments" column
	$filtered = array_filter(
		$databases,
		function ($d) {
			return trim($d["comment_str"]);
		}
	);
	$addComments = $filtered ? true : false;
	
	foreach ($databases as &$d) {
		if ($addComments) {
			if ($d["comment_str"]) {
				$d["Comment"] = [
					"type" => "comment",
					"comment" => $d["comment_str"],
				];
			}
			else {
				$d["Comment"] = "";
			}
		}
		unset($d["comment_str"]);
	}
	unset($d);
	
	return $databases;
}

// XXX  

function sqlListTables() {
	global $sys;
	/*
	$query = "
		SELECT tablename AS Table
		FROM pg_catalog.pg_tables
		-- WHERE table_type ???
		WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
	";	// SHOW TABLES
	*/
	
	// schemas with tables (this query doesn't list schemas which don't have tables):
	$schemas = sqlArray("
		SELECT DISTINCT schemaname
		FROM pg_catalog.pg_tables
		WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
	");
	$addSchema = 1;	// always add schema to table names if > 1 schemas, only custom case the "there is one schema, and it's listed in search_path" condition
	if ($schemas && (count($schemas) == 1)) {	// `sqlArray` still returns NULL when no results, can't just always `count` it
		$schemaName = $schemas[0]["schemaname"];
		$check = sqlRow("SHOW search_path");
		$searchPaths = str_replace("\"\$user\"", $sys["db"]["user"], $check["search_path"]);
		$paths = array_map("trim", explode(",", $searchPaths));	// split by commas and trim each part
		if (in_array($schemaName, $paths)) {
			$addSchema = 0;
		}
		//precho(["paths" => $paths, ]);
	}
	//precho(["schemas" => $schemas, "addSchema" => $addSchema, ]); die();
	// public
	
	/*
	$query = "
		SELECT
			tables_views.*,
			COALESCE(descr.description, '') AS comment_str
			-- '' AS comment_str
		FROM (
			SELECT
				CONCAT(CASE WHEN {$addSchema} = 0 THEN '' ELSE CONCAT(schemaname, '.') END, tablename) AS \"Table\",
				'table' AS type
			FROM pg_catalog.pg_tables
			-- WHERE table_type ???
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
			-- -- --
			UNION ALL
			-- -- --
			SELECT
				CONCAT(CASE WHEN {$addSchema} = 0 THEN '' ELSE CONCAT(schemaname, '.') END, viewname) AS \"Table\",
				'view' AS type
			FROM pg_catalog.pg_views
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
			-- -- --
			UNION ALL
			-- -- --
			SELECT
				CONCAT(CASE WHEN {$addSchema} = 0 THEN '' ELSE CONCAT(schemaname, '.') END, matviewname) AS \"Table\",
				'view' AS type
			FROM pg_catalog.pg_matviews
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
		) AS tables_views
		LEFT JOIN pg_catalog.pg_description AS descr
			-- this didn't work with case-sensitive table names like `Artist`
			-- ON descr.objoid = tables_views.\"Table\"::regclass
			-- this looks complicated, but it works
			ON descr.objoid = CONCAT('\"', REPLACE(tables_views.\"Table\", '.', '\".\"'), '\"')::regclass
		
		ORDER BY tables_views.\"Table\" ASC
	";
	*/
	
	$query = "
		SELECT
			CONCAT(
				CASE WHEN {$addSchema} = 0 THEN '' ELSE CONCAT(relnamespace::regnamespace, '.') END,
				relname
			) AS \"Table\",
			CASE
				WHEN relkind IN ('r', 'p') THEN 'table'
				-- Materialized Views are displayed the same Views, no visual difference
				WHEN relkind IN ('v', 'm') THEN 'view'
			END AS type,
			COALESCE(pg_catalog.obj_description(oid), '') AS comment_str
		FROM pg_catalog.pg_class
		WHERE 	relkind IN ('r', 'v', 'm', 'p')
				AND relnamespace::regnamespace NOT IN ('pg_catalog', 'information_schema')
		-- ORDER BY relnamespace::regnamespace ASC, relname ASC
		-- ORDER BY \"Table\" ASC, relname ASC
		ORDER BY \"Table\" ASC
	";
	// `obj_description` info: https://www.postgresql.org/docs/current/functions-info.html#FUNCTIONS-INFO-COMMENT-TABLE
	
	$tables = sqlArray($query);
	$views = [];
	
	if ($tables) {
		$numberFormat = SQLANTERN_NUMBER_FORMAT;	// constants cannot be used directly just as is
		$bytesFormat = SQLANTERN_BYTES_FORMAT;
		
		// multiple people on the internet are mass-suggesting the XML query below to get the accurate number of rows
		// as far as I understood, it is not compatible with PosgreSQL < 9.4, but like seriously, 9.4 is 2015, come on
		// WAIT! although released in the end of 2014, EOL of 9.4 was in 2020, derp...
		$rows = sqlArray("
			SELECT
				-- table_name,
				CONCAT(CASE WHEN {$addSchema} = 0 THEN '' ELSE CONCAT(table_schema, '.') END, table_name) AS table_name,
				(
					xpath('/row/count/text()', query_to_xml('SELECT COUNT(*) from '||format('%I.%I', table_schema, table_name), true, true, ''))
				)[1]::text::int AS rows_count
			FROM information_schema.tables
			-- WHERE table_schema = 'public'
			WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
		");
		//var_dump(["rows" => $rows, ]);
		$rowsTablesNames = array_column($rows ? $rows : [], "table_name");
		
		// a great source: https://wiki.postgresql.org/wiki/Disk_Usage
		// I don't know if that's a slow way on big databases :-(
		$sizes = sqlArray("
			SELECT
				c.oid,
				-- nspname AS table_schema,
				-- relname AS table_name,
				CONCAT(CASE WHEN {$addSchema} = 0 THEN '' ELSE CONCAT(nspname, '.') END, relname) AS table_name,
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
		
		// if any table has a comment, add a "comments" column
		$filtered = array_filter(
			$tables,
			function ($t) {
				return trim($t["comment_str"]);
			}
		);
		$addComments = $filtered ? true : false;
		
		foreach ($tables as &$t) {
			$k = array_search($t["Table"], $rowsTablesNames);
			$t["Rows"] = ($k !== false) ? $numberFormat((int) $rows[$k]["rows_count"]) : "";
			
			$k = array_search($t["Table"], $sizesTablesNames);
			$t["Size"] = ($k !== false) ? $bytesFormat((int) $sizes[$k]["total_bytes"], $maxSize) : "???";
			
			if ($addComments) {
				if ($t["comment_str"]) {
					$t["Comment"] = [
						"type" => "comment",
						"comment" => $t["comment_str"],
					];
				}
				else {
					$t["Comment"] = "";
				}
			}
			
			if ($t["type"] == "view") {
				$views[] = $t["Table"];
				$t["Size"] = "";
			}
			unset($t["type"], $t["comment_str"]);
		}
		unset($t);
	}
	
	return [
		"tables" => $tables,
		"views" => $views,
	];
}

// XXX  

function sqlDescribeTable( $databaseName, $tableName ) {
	/*
	There is no mention of the database in this function's requests, because, as far as I understand, the database in the connection limits those results to the, well, the database in the connection.
	This means I cannot read other databases tables when connected to a database, but that's not a problem really, just a PosgreSQL feature. If I understand it correctly.
	*/
	
	/*
	Schema OID (`relnamespace`) is a strange thing. One of my servers has `oid` in `SELECT * FROM  pg_catalog.pg_namespace`, and another doesn't. So, I had to find a workaround.
	
	According to:
	https://dba.stackexchange.com/questions/216506/where-to-find-the-oid-of-a-newly-created-schema
	there are `'{schema}'::regnamespace::oid`
	and `to_regnamespace('{schema}')::oid`
	`::regnamespace::oid` triggers exception if a schema does not exist
	`to_regnamespace` returns NULL is a schema does not exist, but it's PostgreSQL 9.5+
	This code never runs with schemas that don't exist, so I don't need to treat exceptions, and the code will have deeper compatibility.
	*/
	
	// If there is only one schema in the database, use the table name as is, even if it contains dots (e.g. "Table.dot.2000" is table "Table.dot.2000" in the single schema "public").
	// If there are multiple schemas, split the provided table name by dots, because it's "schema.table" (E.g. "public.Table.dot.2000" is in fact table "Table.dot.2000" in schema "public" in this case).
	
	/*
	Problem: 1 schema in the database + table name containing dots.
		Code here will not be a problem, but the initial request `SELECT * FROM "Dotted"."name.2000"` will.
		Basically, front side must know if schemas are added to table names.
	Solution: Leave it unsupported as of now. Let's see if anyone has a real problem with it first.
	*/
	
	// schemas with tables in current database:
	$schemas = sqlArray("
		SELECT DISTINCT schemaname
		FROM pg_catalog.pg_tables
		WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
	");
	if (count($schemas) == 1) {	// one schema, use `$tableName`
		$schema = sqlEscape($schemas[0]["schemaname"]);
		$table = $tableName;
	}
	else {	// multiple schemas, the value before that first dot is the schema name
		$parts = explode(".", $tableName);
		$schema = array_shift($parts);
		$table = implode(".", $parts);
	}
	// Beware that `$tableName` comes SQL-escaped already!
	// I see no problems with breaking it by "." here afterwards. Though dot CAN be a part of a table name, they are not special symbols and don't need any special treatment. 
	$schemaNameSql = $schema;
	$tableNameSql = $table;
	
	$structure = sqlArray("
		SELECT
			attr.attname AS \"Field\",
			pg_catalog.format_type(attr.atttypid, attr.atttypmod) AS \"Type\",
			-- CASE WHEN attr.attnotnull IS TRUE THEN 'NO' ELSE 'YES' END AS \"Null\",
			-- pg_catalog.pg_get_expr(attrdef.adbin, attrdef.adrelid, true) AS \"Default\"
			'' AS \"Key\"
		
		FROM pg_catalog.pg_attribute AS attr
		LEFT JOIN pg_catalog.pg_attrdef AS attrdef
			ON 	(attr.attrelid = attrdef.adrelid
				AND attr.attnum = attrdef.adnum)
		LEFT JOIN pg_catalog.pg_type AS pg_type
			ON attr.atttypid = pg_type.oid
		
		LEFT JOIN (
			SELECT oid, relname, relnamespace
			FROM pg_catalog.pg_class
			WHERE relnamespace = '{$schemaNameSql}'::regnamespace::oid
		) AS catalog
			ON catalog.oid = attr.attrelid
		
		WHERE 	attr.attnum > 0
				AND NOT attr.attisdropped
				AND catalog.relname = '{$tableNameSql}'
		ORDER BY attr.attnum ASC
	");
	/*
	I wonder if I should use double quotes inside single quotes here as well, like in the indexes below.
	Maybe I'm just lucky that it works in my tests, LOL.
	*/
	
	/*
	MariaDB/MySQL have "Key" in response to "DESCRIBE {table}"/"SHOW COLUMNS FROM {table}", and the possible values are:
	PRI, UNI, MUL (and of course empty value)
	and I don't fully agree with their logic... so I'm not sure if I should even keep them as is in the MariaDB/MySQL driver, and I definitely shouldn't add them as such here.
	https://dev.mysql.com/doc/refman/8.0/en/show-columns.html
	
	Maybe I'll start following pgAdmin here and set `PK` and whatever else it uses...
	
	For now, I want "Field", "Type", "Key" in structure, the same way MariaDB/MySQL driver lists it.
	And in indexes: "Index", "Columns", "Unique", and "Cardinality" if it is a thing.
	Maybe add something PostgreSQL-specific?
	
	<del>"Key" should be PRI, UNI, KEY</del>
	If PRI, it's always unique, but not vice versa.
	
	Indexes-related documentation:
	https://www.postgresql.org/docs/current/catalog-pg-index.html
	https://www.postgresql.org/docs/current/catalog-pg-class.html
	
	By the way, BOOL columns work even _WITHOUT_ checking `IS TRUE`.
	
	Looking at `usda.nut_data`...
	psql lists index columns separated by a comma and uses PRIMARY KEY:
	Indexes:
		"nut_data_pkey" PRIMARY KEY, btree (ndb_no, nutr_no)
		"nut_data_deriv_cd_idx" btree (deriv_cd)
		"nut_data_nutr_no_idx" btree (nutr_no)
		"nut_data_src_cd_idx" btree (src_cd)
	Foreign-key constraints:
		"nut_data_deriv_cd_fkey" FOREIGN KEY (deriv_cd) REFERENCES deriv_cd(deriv_cd)
		"nut_data_ndb_no_fkey" FOREIGN KEY (ndb_no) REFERENCES food_des(ndb_no)
		"nut_data_nutr_no_fkey" FOREIGN KEY (nutr_no) REFERENCES nutr_def(nutr_no)
		"nut_data_src_cd_fkey" FOREIGN KEY (src_cd) REFERENCES src_cd(src_cd)
	Referenced by:
		TABLE "datsrcln" CONSTRAINT "datsrcln_ndb_no_fkey" FOREIGN KEY (ndb_no, nutr_no) REFERENCES nut_data(ndb_no, nutr_no)
	
	pgAdmin has a visual toggle `Primary Key?`, which is enabled for 2 columns: `ndb_no` and `nutr_no`.
	I want to note the question mark in `Primary Key?`, it's an interesting touch.
	It doesn't list `nut_data_pkey` in "indexes" list, confusingly (well, to me).
	What about `usda.datsrcln`?
	Same, there is a triple primary key, which is not listed in "indexes"... so, psql and pgAdmin don't agree what to list among indexes, that's amazing... :-(
	Oh, found it!
	The "Constraints" tab in the table properties popup has sub-tabs: "Primary key", "Foreign key", etc.
	I can finally see, at last: columns of indexes are comma-separated and even without a space between them (same with Primary Key and Foreign Key):
	`ndb_no,nutr_no,datasrc_id`
	At that when even the built-in `indexdef` displays them with a space, what's wrong with you, pgAdmin? :-(
	
	Adminer + `usda.nut_data`:
	Indexes
	PRIMARY	ndb_no, nutr_no
	INDEX	deriv_cd
	INDEX	nutr_no
	INDEX	src_cd
	...comma-separated, with a space
	...foreign keys look bad, IMHO - I don't get them :-(
	
	Adminer + `usda.datsrcln`:
	Indexes
	PRIMARY	ndb_no, nutr_no, datasrc_id
	INDEX	datasrc_id
	...comma-separated, with a space
	
	So, psql and Adminer agree in full, while pgAdmin took a different route: Primary Key not listed among indexes, and no space between columns.
	
	I see 3 type of indexes in Adminer:
	PRIMARY, UNIQUE, INDEX
	With "INDEX" being kind of excessive, I don't agree with it.
	I'm leaning towards: PRIMARY, UNIQUE, [empty]
	And that's for marking them in structure actually, maybe with MULTI, like MariaDB/MySQL do, but that's not really informative. Because a column can be a separate index and a member of multi-key index at the same time, can't tell that from the structure side.
	
	All right, I finally have a decision.
	I don't like that MUL means any key in MySQL, single- or multi-column.
	A single-column index gets MUL, and a one of multi-column index gets MUL, as well.
	It's strange.
	I think I want PRI, UNI (if not PRI), KEY and MUL
	And if a column is both a single-column key and a part of multi-column key, it should get MUL.
	It's all in the structure, not in indexes.
	Indexes only get Unique and Primary (and MySQL's SHOW INDEXES doesn't have a Primary column, what the heck...)
	*/
	
	// the query below took quite some time to construct
	/*
	Show all indexes from `public`:
	SELECT
		c.relnamespace::regnamespace AS schema_name,
		i.indrelid::regclass AS table_name,
		i.indexrelid::regclass AS index_name,
		CASE WHEN i.indisprimary THEN 1 ELSE 0 END AS primary,
		CASE WHEN i.indisunique THEN 1 ELSE 0 END AS unique,
		i.indkey,
		i.indkey::smallint[] AS ind_array,
		attr.attname,
		CASE WHEN i.indisprimary THEN 'PRI' WHEN i.indisunique THEN 'UNI' ELSE 'KEY' END AS "Key"
	FROM pg_index AS i
	JOIN pg_class AS c
		ON c.oid = i.indrelid
	JOIN pg_catalog.pg_attribute AS attr
		ON	attr.attrelid = c.oid
			AND attr.attnum = ANY(i.indkey::smallint[])
	-- WHERE 	i.indrelid = 'data_src'::regclass
	-- 		AND c.relnamespace = 'public'::regnamespace
	WHERE c.relnamespace = 'public'::regnamespace
	*/
	$indexesRaw = sqlArray("
		SELECT
			-- cls.relnamespace::regnamespace AS schema_name,
			-- idx.indrelid::regclass AS table_name,
			idx.indexrelid::regclass AS index_name,
			CASE WHEN idx.indisprimary THEN 1 ELSE 0 END AS primary,
			CASE WHEN idx.indisunique THEN 1 ELSE 0 END AS unique,
			idx.indkey,
			idx.indkey::smallint[] AS ind_array,
			attr.attname
			-- CASE WHEN idx.indisprimary THEN 'PRI' WHEN idx.indisunique THEN 'UNI' ELSE 'KEY' END AS \"Key\"
		FROM pg_index AS idx
		JOIN pg_class AS cls
			ON cls.oid = idx.indrelid
		JOIN pg_catalog.pg_attribute AS attr
			ON	attr.attrelid = cls.oid
				AND attr.attnum = ANY(idx.indkey::smallint[])
		WHERE 	idx.indrelid = '\"{$schemaNameSql}\".\"{$tableNameSql}\"'::regclass
				AND cls.relnamespace = '\"{$schemaNameSql}\"'::regnamespace
	");
	/*
	Important note about the query above:
	- using only table name is not safe
	- using only single quotes is not safe
	- using table name with schema name ("schema.table") is the same as only table name
	- using double quotes INSIDE single quotes is safe!!!
	- but only when using table name with schema name ("schema.table") in double quotes INSIDE single quotes!!!
	
	Table name might internally be `employeedepartmenthistory`, or `humanresources.employeedepartmenthistory`, or `"HumanResources"."EmployeeDepartmentHistory"` (in double quotes!), or `humanresources."EmployeeDepartmentHistory"` (partially in double quotes!).
	So, that's fun.
	
	And real indexes have names like `humanresources."PK_EmployeeDepartmentHistory_BusinessEntityID_StartDate_Departm"` in adapted AdventureWorks. (Although I assume the adaptation is not ideal, because the schemas and tables should have probably been mixed-case, as well. And columns, they are also all lowercase.)
	*/
	//precho($indexesRaw); die();
	
	$indexes = [];
	$indexNames = $indexesRaw ? array_unique(array_column($indexesRaw, "index_name")) : [];	// the query above retuns multiple rows for each index, so this is an acceptable workaround, in my mind
	
	foreach ($indexNames as $i) {
		$filtered = array_values(array_filter(	// `array_values` to use `filtered[0]` below, otherwise `array_filter` preserves original keys
			$indexesRaw,
			function ($v) use ($i) {
				return $v["index_name"] == $i;
			}
		));
		//$indexColumns = explode(" ", $filtered[0]["indkey"]);
		//var_dump($indexColumns);
		$indexes[] = [
			"Index" => $i,
			"Columns" => implode(
				SQLANTERN_INDEX_COLUMNS_CONCATENATOR,
				// this was a completely wrong idea, columns' names are right here in `$filtered` already actually, and the indkey doesn't follow the logic I expected from it
				/*array_column(
					array_filter(
						$structure,
						function ($k) use ($indexColumns) {
							return in_array($k + 1, $indexColumns);
						},
						ARRAY_FILTER_USE_KEY
					),
					"Field"
				)*/
				array_column($filtered, "attname")
			),
			"Primary" => $filtered[0]["primary"] ? "yes" : "no",
			"Unique" => $filtered[0]["unique"] ? "yes" : "no",
		];
	}
	
	/*
	Add foreign keys to the end of the list.
	
	The query I started with was:
	SELECT
		pg_constraint.connamespace::regnamespace AS schema_name,
		pg_constraint.conrelid::regclass AS table_name,
		pg_constraint.conname AS key_name,
		pg_constraint.confrelid::regclass AS "ref_table_???",
		pg_constraint.conkey::smallint[] AS ind_array,
		-- conindid
		' . . . ' AS sprtr,
		attr.*
	FROM pg_constraint
	INNER JOIN pg_catalog.pg_attribute AS attr
		ON 	attr.attrelid = pg_constraint.confrelid
			AND attr.attnum = ANY(pg_constraint.confkey::smallint[])
	WHERE contype = 'f'
	ORDER BY conrelid::regclass ASC
	
	Multi-column keys are easy to find visually in this query results, they have more than 1 value in `ind_array` (comma-separated).
	
	This didn't work as I hoped, using arrays and ANY duplicated the rows:
	SELECT
		-- pg_constraint.connamespace::regnamespace AS schema_name,
		-- pg_constraint.conrelid::regclass AS table_name,
		pg_constraint.conname AS key_name,
		-- pg_constraint.confrelid::regclass AS ref_table,
		-- ' ? ' || '...' || 18740::regclass || STRING_AGG(attr.attname, ' [x] ') AS attr_names
		CONCAT(pg_constraint.confrelid::regclass, '.', STRING_AGG(local_col.attname, ' [x] ')) AS loc_fields,
		CONCAT(pg_constraint.confrelid::regclass, '.', STRING_AGG(foreign_col.attname, ' [x] ')) AS ext_fields
		-- pg_constraint.conkey::smallint[] AS ind_array,
		-- conindid
		-- ' . . . ' AS sprtr,
		-- attr.*
	FROM pg_constraint
	INNER JOIN pg_catalog.pg_attribute AS local_col
		ON 	local_col.attrelid = pg_constraint.conrelid
			AND local_col.attnum = ANY(pg_constraint.conkey::smallint[])
	INNER JOIN pg_catalog.pg_attribute AS foreign_col
		ON 	foreign_col.attrelid = pg_constraint.confrelid
			AND foreign_col.attnum = ANY(pg_constraint.confkey::smallint[])
	WHERE 	pg_constraint.connamespace = 'public'::regnamespace
			AND pg_constraint.conrelid = 'datsrcln'::regclass
			AND contype = 'f'
	-- GROUP BY schema_name, table_name, key_name, pg_constraint.confrelid
	GROUP BY pg_constraint.conname, pg_constraint.confrelid
	-- ORDER BY conrelid::regclass ASC
	ORDER BY key_name ASC
	
	And I ended with the following monstrosity, which I probably should be ashamed of...
	SELECT
		-- pg_constraint.connamespace::regnamespace AS schema_name,
		-- pg_constraint.conrelid::regclass AS table_name,
		pg_constraint.conname AS key_name,
		-- pg_constraint.confrelid,
		-- pg_constraint.confrelid::regclass AS ref_table,
		-- pg_constraint.conkey::smallint[] AS ind_array,
		-- pg_constraint.confkey::smallint[] AS ref_ind_array,
		-- loc_columns.*,
		-- ref_columns.*
		loc_columns.loc_fields,
		CONCAT(pg_constraint.confrelid::regclass, '.', ref_columns.ref_fields)
	FROM pg_constraint
	INNER JOIN (
		SELECT
			pg_constraint.conname,
			STRING_AGG(attr.attname, ' [x] ') AS loc_fields
		FROM pg_constraint
		INNER JOIN pg_catalog.pg_attribute AS attr
			ON 	attr.attrelid = pg_constraint.conrelid
				AND attr.attnum = ANY(pg_constraint.conkey::smallint[])
		WHERE 	pg_constraint.connamespace = 'public'::regnamespace
				AND pg_constraint.conrelid = 'datsrcln'::regclass
				AND pg_constraint.contype = 'f'
		GROUP BY pg_constraint.conname
	) AS loc_columns
		ON loc_columns.conname = pg_constraint.conname
	INNER JOIN (
		SELECT
			pg_constraint.conname,
			STRING_AGG(attr.attname, ' [x] ') AS ref_fields
		FROM pg_constraint
		INNER JOIN pg_catalog.pg_attribute AS attr
			ON 	attr.attrelid = pg_constraint.confrelid
				AND attr.attnum = ANY(pg_constraint.confkey::smallint[])
		WHERE 	pg_constraint.connamespace = 'public'::regnamespace
				AND pg_constraint.conrelid = 'datsrcln'::regclass
				AND pg_constraint.contype = 'f'
		GROUP BY pg_constraint.conname
	) AS ref_columns
		ON ref_columns.conname = pg_constraint.conname
	WHERE 	pg_constraint.connamespace = 'public'::regnamespace
			AND pg_constraint.conrelid = 'datsrcln'::regclass
			AND pg_constraint.contype = 'f'
	ORDER BY conrelid::regclass ASC
	
	I found that ref table contains schema when needed, PostgreSQL does it automatically in `::regclass`.
	
	NOTE . . . I want to understand this one day:
	`UNNEST((select array_agg(attname) from pg_attribute where attrelid = c.conrelid and array[attnum] <@ c.conkey)) as referencing_col,`
	
	Like here:
	https://stackoverflow.com/questions/72679453/postgresql-sql-script-to-get-a-list-of-all-foreign-key-references-to-a-table
	
	
	FIXME . . . AdventureWorks.sales.salesorderdetail has `columns` listed in wrong order: "productid + specialofferid" instead of "specialofferid + productid".
	Foreign reference is correct: "sales.specialofferproduct.specialofferid + productid".
	That's a known issue, but for an unknown reason :-(
	pgAdmin lists them correctly, albeit very differently: "(specialofferid, productid) -> (specialofferid, productid)"
	My query to continue with it:
	SELECT
		pg_constraint.connamespace::regnamespace AS schema_name,
		pg_constraint.conrelid::regclass AS table_name,
		pg_constraint.conname AS key_name,
		pg_constraint.confrelid::regclass AS "ref_table_???",
		pg_constraint.conkey::smallint[] AS ind_array,
		-- conindid
		' . . . ' AS sprtr,
		attr.attname,
		pg_constraint.*
	FROM pg_constraint
	INNER JOIN pg_catalog.pg_attribute AS attr
		ON 	attr.attrelid = pg_constraint.confrelid
			AND attr.attnum = ANY(pg_constraint.confkey::smallint[])
	WHERE 	contype = 'f'
			AND pg_constraint.conrelid = '"sales"."salesorderdetail"'::regclass
	ORDER BY conrelid::regclass ASC
	
	I have very few multi-column foreign keys at hand to work it out.
	*/
	
	$indexConcatenator = sqlEscape(SQLANTERN_INDEX_COLUMNS_CONCATENATOR);
	$foreign = sqlArray("
		SELECT
			-- pg_constraint.connamespace::regnamespace AS schema_name,
			-- pg_constraint.conrelid::regclass AS table_name,
			pg_constraint.conname AS key_name,
			-- pg_constraint.confrelid,
			-- pg_constraint.confrelid::regclass AS ref_table,
			-- pg_constraint.conkey::smallint[] AS ind_array,
			-- pg_constraint.confkey::smallint[] AS ref_ind_array,
			-- loc_columns.*,
			-- ref_columns.*
			loc_columns.loc_fields AS columns,
			CONCAT(pg_constraint.confrelid::regclass, '.', ref_columns.ref_fields) AS ref
		FROM pg_constraint
		INNER JOIN (
			SELECT
				pg_constraint.conname,
				STRING_AGG(attr.attname, '{$indexConcatenator}') AS loc_fields
			FROM pg_constraint
			INNER JOIN pg_catalog.pg_attribute AS attr
				ON 	attr.attrelid = pg_constraint.conrelid
					AND attr.attnum = ANY(pg_constraint.conkey::smallint[])
			WHERE 	pg_constraint.connamespace = '\"{$schemaNameSql}\"'::regnamespace
					AND pg_constraint.conrelid = '\"{$schemaNameSql}\".\"{$tableNameSql}\"'::regclass
					AND pg_constraint.contype = 'f'
			GROUP BY pg_constraint.conname
		) AS loc_columns
			ON loc_columns.conname = pg_constraint.conname
		INNER JOIN (
			SELECT
				pg_constraint.conname,
				STRING_AGG(attr.attname, '{$indexConcatenator}') AS ref_fields
			FROM pg_constraint
			INNER JOIN pg_catalog.pg_attribute AS attr
				ON 	attr.attrelid = pg_constraint.confrelid
					AND attr.attnum = ANY(pg_constraint.confkey::smallint[])
			WHERE 	pg_constraint.connamespace = '\"{$schemaNameSql}\"'::regnamespace
					AND pg_constraint.conrelid = '\"{$schemaNameSql}\".\"{$tableNameSql}\"'::regclass
					AND pg_constraint.contype = 'f'
			GROUP BY pg_constraint.conname
		) AS ref_columns
			ON ref_columns.conname = pg_constraint.conname
		WHERE 	pg_constraint.connamespace = '\"{$schemaNameSql}\"'::regnamespace
				AND pg_constraint.conrelid = '\"{$schemaNameSql}\".\"{$tableNameSql}\"'::regclass
				AND pg_constraint.contype = 'f'
		ORDER BY conrelid::regclass ASC
	");
	
	if ($foreign) {
		// indexes get an additional column if the table has foreign keys
		foreach ($indexes as &$i) {
			$i["Foreign reference"] = "";
		}
		unset($i);
		
		foreach ($foreign as $f) {
			$indexes[] = [
				"Index" => $f["key_name"],
				"Columns" => $f["columns"],
				"Primary" => "",	// "n/a" looks bad, IMHO
				"Unique" => "",	// same thing
				"Foreign reference" => $f["ref"],
			];
		}
	}
	
	
	$keysLabels = json_decode(SQLANTERN_KEYS_LABELS, true);
	foreach ($structure as &$s) {
		$filtered = array_filter(
			$indexesRaw ? $indexesRaw : [],
			function ($v) use ($s) {
				return $v["attname"] == $s["Field"];
			}
		);
		$k = array_search(1, array_column($filtered, "primary"));
		if ($k !== false) {	// "primary" overwhelms everything else
			$s["Key"] = $keysLabels["primary"];
		}
		else {
			if (count($filtered) == 1) {	// only one filtered index doesn't by itself mean it's a single-column key yet...
				$singleKey = array_pop($filtered);
				$columnsInKey = array_filter(
					$indexesRaw,
					function ($v) use ($singleKey) {
						return $v["index_name"] == $singleKey["index_name"];
					}
				);
				if (count($columnsInKey) == 1) {	// only a combination of "in one key" + "only one column in the key" means it's a single-column key
					if ($singleKey["unique"]) {
						$s["Key"] = $keysLabels["unique"];
					}
					else {
						$s["Key"] = $keysLabels["single"];
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
	}
	unset($s);
	
	// <del>apparently, there is no such thing as "unique" or "cardinality" in PostgreSQL...</del>
	// <del>I should really look deeper into it, I find it hard to believe Postgres doesn't show that important info
	// but I also know indexes here are very different from MySQL</del>
	// I haven't found "cardinality" yet, but I've solved the "unique" and "primary" puzzles.
	/*
	"indexes" => sqlArray("
		SELECT
			indexname AS \"Index\",
			'' AS \"Columns\",
			indexdef AS \"Indexdef\"
		FROM pg_indexes
		WHERE 	schemaname = '{$schemaNameSql}'
				AND tablename = '{$tableNameSql}'
		
	"),
	*/
	
	return [
		"structure" => $structure,
		"indexes" => $indexes,
	];
}

// XXX  

function sqlRunQuery( $query, $onPage, $page, $fullTexts ) {
	global $sys;
	
	$res = [];
	$numberFormat = SQLANTERN_NUMBER_FORMAT;	// constants cannot be used directly just as is
	
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
	/*
	FIXME . . . `--` in PostgreSQL can be in the middle of a line, not only at the start!
	It doesn't matter in this function, but it is important if I end up parsing queries.
	```
	In PostgreSQL, a comment started with -- symbol is similar to a comment starting with # symbol. When using the -- symbol, the comment must be at the end of a line in your SQL statement with a line break after it. This method of commenting can only span a single line within your SQL and must be at the end of the line.
	```
	
	FIXME . . . Does `#` also mark a one-line comment???
	*/
	
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
	$firstQueryWordLower = mb_strtolower($words ? $words[0] : "", "UTF-8");
	
	if ($firstQueryWordLower == "select") {
		// If the query is an obvious `SELECT` without `LIMIT`, add the default `LIMIT`
		// If the query has `LIMIT`, don't add one, it's the human's decision to see the amount of rows they need
		
		// `LIMIT` in PostgreSQL can be `LIMIT`, `LIMIT ... OFFSET ...`, or `OFFSET ... FETCH ...`
		// SQLantern only supports `LIMIT`/`LIMIT ... OFFSET ...` detection, as of now
		// and it is only detected if it is the last clause in the query, making `FOR` not supported
		
		/*
		`LIMIT x, y` or `LIMIT x OFFSET y` must be the last line of the query.
		There exist other legit queries, with `into_option` or `FOR` after `LIMIT`, but this tool **does not support those queries**.
		The reason to check for the `LIMIT` is to add pagination if `LIMIT` is not specified.
		
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
			($countWords > 5)	// at least give me `SELECT {x} FROM {y} LIMIT {z}`
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
		//precho(["words" => $words, $words[$countWords - 2], "setLimit" => $setLimit ? "true" : "false", ]); die();
		
		if ($setLimit) {
			/*
			Problem: If there is a syntax error in query, the error with COUNT is displayed, which is confusing.
			Solution: Run `EXPLAIN` the given query first. If `EXPLAIN` fails, the query has an error. In this case I can run the raw query just to display that error. If `EXPLAIN` works, I can go on with COUNT.
			The nice thing is `EXPLAIN` doesn't (shouldn't) take the same time as running the query, so there's only a small and neglectable time/resources loss.
			*/
			if (!pg_query($sys["db"]["link"], "EXPLAIN {$query}")) {
				sqlQuery($query);	// sqlQuery will output the error
				die("Line " . __LINE__);	// just in case...
			}
			
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
		}
		
		$useQuery = $query . ($setLimit ? "\n LIMIT {$onPage} OFFSET {$offset}" : "");	// add LIMIT sometimes
		// LIMIT must be added _on the next line_, because otherwise it will be ignored, if the last query line is commented out by `-- ` (LIMIT will just be added to the comment)
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
		
		$resultSize = 0;
		$rowNumber = 1;	// human-style row numbers for the error message
		
		// associative way, losing columns with duplicate names:
		/*while ($row = pg_fetch_array($dbResult, null, PGSQL_ASSOC)) {
			$res["rows"][] = $row;
		}*/
		// listing all columns, even if they have the same name:
		while ($row = pg_fetch_array($dbResult, null, PGSQL_NUM)) {
			/*
			Trim the values to max length (if set) as they are read, to use less RAM overall (go higher-lower, higher-lower, instead of higher-higher-higher-higher).
			*/
			foreach ($row as &$v) {	// columns in row
				if (is_null($v)) {	// leave NULL as is
					continue;
				}
				
				// BLOB and other BINARY data is not JSON compatible and MUST be treated, unfortunately
				if (json_encode($v) === false) {	// this proved to be the fastest way < takes additional RAM though :-( 
					$v = ["type" => "blob", ];	// TODO . . . download BINARY/BLOB
					continue;
				}
				
				if ($fullTexts == "false") {
					// SQL_DEFAULT_SHORTEN tells the front-end the default toggle state
					// but after that this and only _this front-end toggle_ tells me to shorten or not
					$v =
						(mb_strlen($v ? $v : "") > SQLANTERN_SHORTENED_LENGTH) ?
						mb_substr($v, 0, SQLANTERN_SHORTENED_LENGTH) . "[...]" : $v
					;
				}
			}
			unset($v);
			
			$fixedRow = [];
			foreach ($row as $fieldIdx => $v) {
				$fixedRow[$fieldNames[$fieldIdx]] = $v;
			}
			$res["rows"][] = $fixedRow;
			
			// check data size threshold and throw an error if surpassed
			$resultSize += arrayRowBytes($fixedRow);
			if ($resultSize > SQLANTERN_DATA_TOO_BIG) {
				fatalError(sprintf(translation("data-overflow"), $numberFormat($rowNumber)));
			}
			$rowNumber++;
		}
		
		if (!$res["num_rows"]) {
			$res["num_rows"] = count($res["rows"]);
		}
	}
	else {	// not SELECT, as detected by the stupid logic above...
		$affectedRows = pg_affected_rows($dbResult);
		if ($affectedRows) {	// don't confuse users with "affected rows: 0" on TRUNCATE, basically
			$res["rows"] = [
				["affected_rows" => $numberFormat($affectedRows)],
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
	
	$phpTiming = false;
	
	$dbResult = pg_query($sys["db"]["link"], "EXPLAIN (ANALYZE true, FORMAT JSON) {$query}");
	
	if ($dbResult === false) {	// EXPLAIN failed, but it might be a valid EXPLAIN-imcompatible query
		$phpTiming = true;
	}
	else {
		$row = ($dbResult && pg_num_rows($dbResult)) ? pg_fetch_array($dbResult, null, PGSQL_ASSOC) : null;
		$keys = array_keys($row);
		//var_dump(["row" => $row, ]);
		$values = json_decode($row[$keys[0]], true);
		//precho(["row" => $row, "values" => $values, "is_array" => is_array($values[0]), ]); die();
		if (!is_array($values[0])) {
			// Even "Utility statements have no plan structure" returns a JSON array with one value: "Utility Statement"
			// So the `values` are not even empty, but are unusable.
			// e.g. `EXPLAIN REFRESH MATERIALIZED VIEW`
			$phpTiming = true;
		}
		else {	// JSON decoded
			//var_dump(["values" => $values, ]);
			$durationMs = $values[0]["Planning Time"] + $values[0]["Execution Time"];	// `$values[0]` because only one query is analyzed, if I understand it correctly
			return [
				"timeMs" => round($durationMs, 4),
			];
		}
	}
	
	if ($phpTiming) {	// PostgreSQL timing not available, measure approximate duration in PHP
		$timeBefore = microtime(true);	// `hrtime` is better, but it's PHP 7+ (7.3+ even?)
		sqlRow($query);	// try running the query without EXPLAIN; if there is an error in query, `sqlRow` will throw it
		$durationPHP = microtime(true) - $timeBefore;	// if we're here, the query was actually executed correctly, give at least some non-precise measurement...
		return [
			"timeMs" => "n/a (~" . round($durationPHP * 1000, 4) . ")",
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

> People keep mentioning using the shell command to get the statements (`pg_dump`). I'm not doing this: not only that is a disaster in itself, SQLantern will often NOT have `pg_dump` available locally at all, because working with remote servers (like I personally always do).

https://dba.stackexchange.com/questions/254183/postgresql-equivalent-of-mysql-show-create-xxx

> And devs don't even realize the problem and ask for use-cases, I mean seriously, how detached one must be...

https://www.postgresql.org/message-id/CAFEN2wxg0Vtj1gvk6Ms0L2CAutbycyxHZPiZSpW7eLsBc6VGnA%40mail.gmail.com

> Export/Import in pgsql shifts to Version 2 or beyond.
<del>And I might have to switch to PDO for unbuffered results, that needs separate testing.</del> it works as is, so that's good


Here's a good way, but the function is pretty massive:
https://github.com/MichaelDBA/pg_get_tabledef
And I can't use it just like that, there's no way I'm adding functions into user's databases (and not only from the ethical and professional standpoint, but the user might just even not have rights to create or replace functions LOL).

Here's a list of functions:
https://stackoverflow.com/questions/2593803/how-to-generate-the-create-table-sql-statement-for-an-existing-table-in-postgr?rq=3
But I suspect I can make this logic more compact in PHP actually.

Thoughts:
- I'll make a relatively simple (not all-covering) CREATE TABLE and call it alpha version
- if anyone has problems, I'll dive deeper into it, but not before that

Useful search:
postgresql "ddl" table

*/

if (false) {

function sqlImport( $importId, &$txt ) {
	global $sys;
}

function sqlExport( $options ) {
	global $sys;
	
	// FIXME . . . Do not `CONCAT` below, use the built-in function for full compatibility!
	$tablesRaw = sqlArray("
		SELECT
			CONCAT('\"', relnamespace::regnamespace, '\".\"', relname, '\"') AS name,
			CASE
				WHEN relkind IN ('r', 'p') THEN 'table'
				-- Materialized Views are displayed the same Views, no visual difference
				WHEN relkind IN ('v') THEN 'view'
				WHEN relkind IN ('m') THEN 'matview'
			END AS type,
			COALESCE(pg_catalog.obj_description(oid), '') AS comment_str
		FROM pg_catalog.pg_class
		WHERE 	relkind IN ('r', 'v', 'm', 'p')
				AND relnamespace::regnamespace NOT IN ('pg_catalog', 'information_schema')
		ORDER BY name ASC
	");
	$views = array_column(
		array_filter(
			$tablesRaw,
			function ($t) {
				return $t["type"] == "view";
			}
		),
		"name"
	);
	$matViews = array_column(
		array_filter(
			$tablesRaw,
			function ($t) {
				return $t["type"] == "matview";
			}
		),
		"name"
	);
	
	
	$tables = [];
	if (isset($options["tables"])) {	// user-chosen tables/views
		//$tables = $options["tables"];
		
		// schemas with tables in current database:
		$schemas = sqlArray("
			SELECT DISTINCT schemaname
			FROM pg_catalog.pg_tables
			WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
		");
		if ($schemas && (count($schemas) == 1)) {	// one schema, add it internally
			$schemaSql = sqlEscape($schemas[0]["schemaname"]);
			foreach ($options["tables"] as $t) {
				$tableSql = sqlEscape($t);
				$tables[] = "\"{$schemaSql}\".\"{$tableSql}\"";
			}
		}
		elseif ($schemas) {	// many schemas, quote table names properly
			foreach ($options["tables"] as $t) {
				$parts = explode(".", $t);
				$schemaSql = sqlEscape(array_shift($parts));
				$tableSql = sqlEscape(implode(".", $parts));
				$tables[] = "\"{$schemaSql}\".\"{$tableSql}\"";
			}
		}
	}
	else {	// export all tables and views
		/*
		$tables = array_column(
			array_filter(
				$tablesRaw,
				function ($t) {
					return $t["type"] == "table";
				}
			),
			"name"
		);
		*/
		$tables = array_column($tablesRaw, "name");
	}
	
	$onPage = $options["rows"];
	
	$version = SQLANTERN_VERSION;
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
	
	//var_dump(["tables" => $tables, "views" => $views, "matViews" => $matViews, ]);
	//die();
	
	/*
	TODO...
	+ export table values
	...export table structure
	...export views
	...export material views
	...test BLOB export/import
	...test array type < which might be a showstopper actually
	*/
	
	foreach ($tables as $t) {
		$tableSql = sqlEscape($t);
		$isView = in_array($t, $views);
		$isMatView = in_array($t, $matViews);
		
		//echo "Exporting {$t}...\n";
		
		if ($options["structure"]) {
			if ($isView) {
			}
			elseif ($isMatView) {
			}
			else {
				echo "DROP TABLE IF EXISTS {$tableSql};\n\n";	// delete table if exists (not optional)
			}
			
			
			
			
			//?????  $row = sqlRow("SHOW CREATE TABLE `{$tableSql}`");
			
			//echo "{$row["Create Table"]};\n\n";
		}
		elseif ($options["data"] && !($isView || $isMatView)) {	// data without structure
			echo "TRUNCATE {$tableSql};\n\n";	// empty table (not optional)
		}
		
		
		if (!$options["data"] || $isView || $isMatView) {
			continue;
		}
		
		if ($options["transactionData"] && !$options["transactionStructure"]) {	// only data into transaction
			echo "BEGIN;\n\n";
		}
		
		//$memKbNow = round(memory_get_peak_usage(true) / 1024);
		//echo "PHP peak mem before SELECT: {$memKbNow}\n";
		
		$dbResult = pg_query($sys["db"]["link"], "SELECT * FROM {$t}");
		
		//$memKbNow = round(memory_get_peak_usage(true) / 1024);
		//echo "PHP peak mem after SELECT: {$memKbNow}\n";
		
		$fields = [];
		for ($f = 0; $f < pg_num_fields($dbResult); $f++) {
			$fields[] = sqlEscape(pg_field_name($dbResult, $f));
		}
		$fieldsSql = implode("\", \"", $fields);
		
		$valuesSql = [];
		
		//$n = 0;
		while ($row = pg_fetch_array($dbResult, null, PGSQL_NUM)) {
			//$n++;
			//$memKbNow = round(memory_get_peak_usage(true) / 1024);
			//echo "...row {$n}, mem = {$memKbNow}\n";
			//continue;
			$saveValues = [];
			foreach ($row as $column) {
				if (is_null($column)) {
					$useValue = "NULL";
				}
				elseif (json_encode($column) === false) {
					$useValue = "DECODE('" . sqlEscape(base64_encode($column)) . "', 'base64')";
				}
				else {
					$useValue = "'" . sqlEscape($column) . "'";
				}
				$saveValues[] = $useValue;
			}
			$valuesSql[] = implode(", ", $saveValues);	// all values of one row into a prepared string
			unset($saveValues);
			
			if (count($valuesSql) >= $onPage) {
				// limit reached, output INSERT statement
				$allValuesSql = implode("), (", $valuesSql);
				
				$insertSql = "INSERT INTO {$tableSql} (\"{$fieldsSql}\") VALUES ({$allValuesSql});\n\n";
				
				echo $insertSql;
				
				// free some memory:
				unset($allValuesSql, $insertSql);
				
				$valuesSql = [];
			}
		}
		
		if ($valuesSql) {
			$allValuesSql = implode("), (", $valuesSql);
			
			$insertSql = "INSERT INTO {$tableSql} (\"{$fieldsSql}\") VALUES ({$allValuesSql});\n\n";
			
			echo $insertSql;
			
			// free some memory:
			unset($allValuesSql, $insertSql);
		}
		
		$valuesSql = [];
		
		pg_free_result($dbResult);
		
		//$memKbNow = round(memory_get_peak_usage(true) / 1024);
		//echo "PHP peak mem after iteration: {$memKbNow}\n";
		
	}
	
	if ($options["transactionData"] && $options["transactionStructure"]) {	// put everything into transaction
		echo "COMMIT;\n";
	}
}

}

//