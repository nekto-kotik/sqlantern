<?php
/*
This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023 Misha Grafski AKA nekto
License: GNU General Public License v3.0
https://github.com/nekto-kotik/sqlantern
https://sqlantern.com/

SQLantern is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
*/

$configName = __DIR__ . "/config.sys.php";
if (file_exists($configName)) {
	require_once $configName;
}

$defaults = [
	/*
	DON'T CHANGE THESE VALUES IN THIS FILE
	
	If you need to change any of the defaults below, don't change them here, but create a file `config.sys.php` in the same directory as this file, and define different values there, like that:
	```
	define("SQL_DEFAULT_HOST", "127.0.0.1");
	define("SQL_MULTIHOST", true);
	```
	
	`config.sys.php` is not shipped with SQLantern, which means updates DON'T erase/change your configuration.
	*/
	
	"SQL_ROWS_PER_PAGE" => 30,
	
	"SQL_DEFAULT_HOST" => "localhost",
	// be aware that it's "localhost" by default, and not, say, "127.0.0.1"
	// the host can be local or remote, there are no limitations
	
	"SQL_DEFAULT_PORT" => 3306,
	// which port to use when port is not listed in `login`
	// use `5432` to connect to PostgreSQL by default
	// or set a non-standard value here if needed (which also needs a custom value in `SQL_PORTS_TO_DRIVERS`)
	
	"SQL_PORTS_TO_DRIVERS" => json_encode([	// `json_encode` for PHP 5.6 compatibility!
		3306 => "php-mysqli.php",
		5432 => "php-pgsql.php",
	]),
	// define `SQL_PORTS_TO_DRIVERS` in `config.sys.php` with your non-standard ports ADDED, if needed
	// do not remove the standard ports, copy the value from above, and don't remove `json_encode`!!!
	// the project is initially shipped with `mysqli` and `pgsql` drivers
	
	"SQL_MYSQLI_CHARSET" => "UTF8MB4",
	"SQL_POSTGRES_CHARSET" => "UTF8",
	// ??? . . . do I even need to change the charset to anything else any time at all, ever ???
	// I don't have any good ideas how to make it convenient in a per-table or at least in a per-database way, if I have to (I mean, in the configuration)
	
	"SQL_RUN_AFTER_CONNECT" => json_encode([
		"mysqli" => [
			"SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))",
			// removing `ONLY_FULL_GROUP_BY` is REQUIRED for the built-in table indexes request to work at all
			// "MySQL anyway removes unwanted commas from the record."
		],
		"pgsql" => [
		],
	]),
	// queries to run immediately after connection (for e.g. desired session variables, like `group_concat_max_len`)
	// every database module has it's own set of queries, as the typical queries here are very database-system-specific
	// `json_encode` is used for PHP 5.6 compatibility, see detailed comment about `SQL_INCOMING_DATA` below
	
	"SQL_DISPLAY_DATABASE_SIZES" => false,
	// seeing the whole database sizes in the database list is very useful, but very often it's unbearably slow, so here's the option to enable it, but it's disabled by default
	
	"SQL_NUMBER_FORMAT" => "builtInNumberFormat",
	// a function name, which you can redefine to use your own
	// used only for number of rows, number of pages, and number of unique values (in MariaDB/MySQL indexes list)!
	// the function itself cannot be written right here as an anonymous function, only a function name, because constants can only store "simple values", unfortunately...
	// NOTE . . . `SQL_NUMBER_FORMAT` will be removed in Version 2, because the number format will be customizable in config and in visual front side settings !!!
	
	"SQL_BYTES_FORMAT" => "builtInBytesFormat",
	// the same as above, but for bytes
	// only used for databases' sizes and tables' sizes!
	
	"SQL_FAST_TABLE_ROWS" => true,
	// Defines, which logic to use to get the number or rows in each table (for the lists of tables of a database, the "tables panel")
	// It's only implemented in the `mysqli` driver!
	// When `false`, a slow logic is used: a `SELECT COUNT(*)` is run for each table, giving the accurate number of rows, but it's almost always VERY slow.
	// When `true`, the fast logic is used: the number of rows is taken from "information_schema.tables" (which is exact for MyISAM, but often extremely wrong for InnoDB), and an additional check is run for the small tables to get rid of false-zero and false-non-zero situations. And `SELECT COUNT(*)` on small tables is fast, so it's a mix of sources ("information_schema.tables" and "COUNT(*)").
	// The default `true` is really fast and usually precise _enough_.
	// Read comments in `function sqlListTables` in `php-mysqli.php` for very detailed info and rationale.
	
	"SQL_SIZES_FLEXIBLE_UNITS" => true,
	// when `true`, the units of size will be flexible, and there will be a mix of different units in every list (`KB`, `MB`, `GB`), to show the most accurate value in the shortest way
	// when `false`, _the biggest size in the list_ will be used for unit of all other sizes, e.g. everything will be in `MB` if the biggest table or database fits the `MB` unit best
	// `false` makes the sizes pleasantly uniform and allows comparing them visually easier and seeing biggest tables faster, but it's really a matter of taste and habit, thus the toggle
	// `false` may also make the sizes' list full of zeroes when there is huge size difference
	
	"SQL_MULTIHOST" => false,
	// if `false`: will only connect to one (default) host
	// if `true`: will try to connect to any host
	// default is `false`, because otherwise a copy might be easily used for a DDOS attack on other servers out of the box, which is undesired
	// note that is has nothing to do with default host being local or remote, the default host can be remote well and fine
	// it only limits connections to one default host, or allows it to any host (local or remote)
	
	"SQL_ALLOW_EMPTY_PASSWORDS" => false,	// please, be responsible and enable empty passwords only if you're absolutely secure on an offline or IP/password-protected location
	
	"SQL_DEFAULT_SHORTEN" => true,
	// shorten long values by default or not (there is a toggle for that under each query anyway, and also a visual front side setting, but this sets the default behaviour)
	// note that values are shortened on server side
	
	"SQL_SHORTENED_LENGTH" => 200,
	// this is the length long values are shortened to
	
	"SQL_SESSION_NAME" => "SQLANTERN_SESS_ID",
	// it may sound far stretched, but configuring different `SQL_SESSION_NAME` allows using multiple instances of SQLantern in subdirectories on the same domain (with e.g. different drivers, host limitations, etc), with possibility to separate access to them by IP, for example (on the web server level)
	// official README contains some examples
	
	"SQL_COOKIE_NAME" => "sqlantern_client",
	// cookie name to store logins and passwords
	// this is security-related: server-side SESSION contains cryptographic keys, while client-side COOKIE contains encrypted logins and passwords, thus leaking one side doesn't compromise your logins or passwords
	// encryption keys are random for every login and every password
	
	"SQL_DEDUPLICATE_COLUMNS" => true,
	// deduplicate columns with the same names, see function `deduplicateColumnNames` for details
	
	"SQL_CIPHER_METHOD" => "aes-256-cbc",	// encryption method to use for logins and passwords protection
	"SQL_CIPHER_KEY_LENGTH" => 32,	// encryption key length, in bytes (32 bytes = 256 bits)
	
	"SQL_POSTGRES_CONNECTION_DATABASE" => "postgres",	// PostgreSQL-specific: an initial connection database (a required field!)
	
	"SQL_INCOMING_DATA" =>
		$_POST ?	// POST priority
		json_encode($_POST) :
		(
			$_GET ?	// GET (only for EventSource progress monitor, because EventSource is only GET...)
			json_encode($_GET) :
			file_get_contents("php://input")	// standard fetch requests
		)
	,	// a workaround-override for integrations with enforced connection/database limitation; this won't be documented, but you can see how it's used in the OpenCart and Joomla integrations
	/*
	I initially had `json_decode` right here in the array, reading `php://input`, but had to move it lower in the code, because:
	Although PHP 5.6 allowed defining array constants (http://php.net/migration56.new-features), they could only be defined with `const`, not with `define` (which is used here, below), and only PHP 7.0 allowed `define` constants with array values (see http://php.net/manual/en/migration70.new-features.php).
	And I want to keep PHP 5.6 compatibility for as long as I can, it's a really important feature to me, hence the change.
	*/
	
	"SQL_FALLBACK_LANGUAGE" => "en",	// there is only a handful of scenarios when that comes into play, basically when front-end didn't send any language (not even a real scenario, only possible if that's a hack or a human error), and at the same time there is no fitting browser-sent default language (which is absolutely real, of course)
	// even so, I still think the fallback language must be a configurable server-side parameter for flexibility sake, so here it is
	
	"SQL_VERSION" => "1.9.5 beta",	// 24-01-12
	// Beware that DB modules have their own separate versions!
];

/*
Constants, which are safe to configure (override) in the front-end:
<del>SQL_ROWS_PER_PAGE</del>
SQL_DEFAULT_PORT (limited to the ports, defined in SQL_PORTS_TO_DRIVERS, and only after a successful connection)
SQL_SET_CHARSET (shouldn't it be per-driver, though??? and I don't really know if it should be configurable, I doubt it)
SQL_RUN_AFTER_CONNECT (only after a successful connection)
SQL_DISPLAY_DATABASE_SIZES
<del>SQL_NUMBER_FORMAT</del> << redo to thousands separator, decimals separator, and maybe number of decimals (sizes and profiler, but maybe not...)
SQL_FAST_TABLE_ROWS
SQL_SIZES_FLEXIBLE_UNITS
SQL_DEFAULT_SHORTEN		// it is safe to be configured, but there is no real sense in making it one
SQL_SHORTENED_LENGTH
SQL_POSTGRES_CONNECTION_DATABASE		// I have no idea how to make it per-server
<del>SQL_MYSQLI_COUNT_SUBQUERY_METHOD</del> << it is deprecated already
SQL_DEDUPLICATE_COLUMNS


My initial thoughts about this:
Introduce `$sys["config"]`, fill it with the values from constants initially (which are defaults or taken from `config.sys.php`), with possible change after starting session.
Front side sends options immediately after changing them and on `list_connections`, because this is the place when session might not exist anymore, surprisingly for the front side (expired session).

"Rows per page" should probably be sent with every request for data, and not even saved in config/session.
`SQL_ROWS_PER_PAGE` will only be used as a fallback if "rows per page" are not sent for any reason (improper manual request, basically, because there's no other reason).

Also, the original constant values should probably be a fallback if an inadequate value is provided by the user.
E.g. `SQL_DEFAULT_PORT` should be used if `$sys["config"]["SQL_DEFAULT_PORT"]` has a bad value.
Or should it be checked sooner, on setting the `$sys["config"]`? I really don't want to create a mess there, and it'll pollute that one simple little place with multiple check-ups...
Also, do I even really care for non-valid values?
Hack an invalid port and get a strange error, do I care?
I actually think I _don't_ care, so no, no fallbacks to original constants.

*/

$configurables = [
	"default_port" => "SQL_DEFAULT_PORT",
	"queries_after_connect" => "SQL_RUN_AFTER_CONNECT",	// for the future
	"database_sizes" => "SQL_DISPLAY_DATABASE_SIZES",
	"fast_rows" => "SQL_FAST_TABLE_ROWS",
	"size_flex_units" => "SQL_SIZES_FLEXIBLE_UNITS",
	"shortened_length" => "SQL_SHORTENED_LENGTH",
	"postgres_connection_database" => "SQL_POSTGRES_CONNECTION_DATABASE",	// it should be per-server, though...
	"deduplicate_columns" => "SQL_DEDUPLICATE_COLUMNS",
];

/*
$sys["config"] = [];
foreach ($configurables as $publicName => $constantName) {
	$sys["config"][$constantName] = constant($constantName);
}

if (array_key_exists("config", $_SESSION)) {
	foreach ($_SESSION["config"] as $optionName => $option) {
		$sys["config"][$optionName] = $option;
	}
}
*/


foreach ($defaults as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}


// some attempts to force longer sessions...
ini_set("session.gc_maxlifetime", 86400);	// some servers have is as low as 10-15 minutes and session gets killed while the browser tab is still open, in the middle of working with the data
session_set_cookie_params(86400);
// s. https://stackoverflow.com/questions/8311320/how-to-change-the-session-timeout-in-php
// (The above doesn't help. Maybe on some servers, but it's definitely not a universal solution.)

// XXX  

function precho( $a ) {	// for science!
	echo "<pre>" . print_r($a, true) . "</pre>";
}

// XXX  

function postProcess( $p = "", $kind = "" ) {
	global $sys;
	// converts all POST values to several arrays:
	// "int", "float", "sql" (escaped), "input" (string, ready to use as a value attribute of an input), "raw" (string)
	//$p = $p ? $p : $_POST;
	
	if (!$p) {
		$p = $_POST;
	}
	
	$root = $kind ? false : true;	// if "kind" not given, this is the first (highest level) run
	
	$availKinds = ["int", "float", "sql", "input", "raw", ];
	
	$res = [];
	if ($p)
		foreach ($p as $key => $v)
			if (is_array($v)) {
				if ($root) {	// only root branches out, we don't need $post["val"]["int"]["q"]["int"] or similar
					// multiple recursive processing isn't a problem, because POST will not be THAT big
					foreach ($availKinds as $k) {
						$res[$k][$key] = $v ? postProcess($v, $k) : [];
					}
				}
				else {
					$res[$key] = $v ? postProcess($v, $kind) : [];
				}
			}
			else {	// primitive values
				$sqlValue = isset($sys["db"]) && isset($sys["db"]["link"]) ? sqlEscape($v) : "";	// only if connection is established
				$inputValue = str_replace(["\"", "'", "<", ">"], ["&quot;", "&#39;", "&lt;", "&gt;"], $v);
				if ($root) {
					$res["int"][$key] = (int) $v;
					$res["float"][$key] = (float) $v;
					$res["sql"][$key] = $sqlValue;
					$res["input"][$key] = $inputValue;
					$res["raw"][$key] = $v;
				}
				else {
					if ($kind == "int") {
						$res[$key] = (int) $v;
					}
					if ($kind == "float") {
						$res[$key] = (float) $v;
					}
					if ($kind == "sql") {
						$res[$key] = $sqlValue;
					}
					if ($kind == "input") {
						$res[$key] = $inputValue;
					}
					if ($kind == "raw") {
						$res[$key] = $v;
					}
				}
			}
	
	return $res;
}

// XXX  

function respond() {
	global $response;
	
	// send version with non-empty `connections`, but not with empty connections
	// this way, only users with working credentials are allowed to know the version
	// although, it might also be obvious from the front-end... but I don't want to make life easier for hackers, every small step filters some of them and is worth taking
	if (isset($response["connections"]) && $response["connections"]) {
		$response["version"] = SQL_VERSION;
	}
	
	// debug:
	//usleep(4 * 1000000);	// 1 second = 1000000
	
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($response);
	//sqlDisconnect();
	die();
}

// XXX  

function fatalError( $msg, $pause = false ) {
	if (function_exists("sqlDisconnect")) {	// there are fatal errors without driver even loaded
		sqlDisconnect();	// would it be faster to not disconnect and let die by itself? does it really matter? :-D
	}
	if ($pause) {
		sleep(2);	// assume bad credentials and hacks, limit bruteforce speed
	}
	die($msg ? "<h2>{$msg}</h2>" : "");
}

// XXX  

function builtInNumberFormat( $n ) {
	return number_format($n, 0, ".", ",");
}

// XXX  

function deduplicateColumnNames( $columns, $tables ) {
	/*
	It's typical to use associative arrays with SQL data in PHP, and lose some columns if multiple columns with the same name/alias are returned.
	E.g. if a query `SELECT * FROM chats_chatters LEFT JOIN chats ON chats.id = chats_chatters.chat_id` returns multiple `id` columns, only the last `id` column is left, when using `mysqli_fetch_assoc`.
	This is fine for pure PHP usage (you cannot have more than one array column or object property with the same name anyway), but it's different from the native SQL console results, it shows multiple columns with the same name all right.
	And it's sometimes confusing, becase I'm not always sure what is the source table of a column, and often not even aware that there are clashes (which I'd fix by selecting only what I need and maybe aliasing).
	
	On the other hand, displaying multiple columns with the same name (like multiple `id`s) is also not clear enough, IMHO.
	So, I decided to add a table name in parenthesis after the column name, I find it very transparent this way.
	The behaviour can be disabled by setting `SQL_DEDUPLICATE_COLUMNS` to `false`.
	
	By the way, while phpMyAdmin loses such duplicate fields, adminer and pgAdmin don't, kudos to them!
	*/
	if (!SQL_DEDUPLICATE_COLUMNS) {	// don't deduplicate, return as is
		return $columns;
	}
	// I feel like this code is overcomplicated, but I don't have a better idea right now
	$sameNames = [];
	foreach ($columns as $name) {
		$same = array_filter(
			$columns,
			function ($v) use ($name) {
				return $v == $name;
			}
		);
		$sameNames[] = [
			"column" => $name,
			"quantity" => count($same),
		];
	}
	$sameNames = array_unique(	// leave only unique values
		array_column(	// only leave "column"
			array_filter(	// filter names which happen more than once
				$sameNames,
				function ($s) {
					return $s["quantity"] > 1;
				}
			),
			"column"
		)
	);
	foreach ($columns as $colIdx => &$c) {
		if (!$tables[$colIdx]) {	// I'm not going to add/invent anything for THESE queries (e.g. `SELECT 1, 1, 2`)
			continue;
		}
		if (in_array($c, $sameNames)) {
			$c = "{$c} ({$tables[$colIdx]})";
		}
	}
	unset($c);
	
	// note that incoming `$columns` are changed and returned, not a new array
	return $columns;
}

// XXX  

// thanks to `rommel at rommelsantor dot com` and `evgenij at kostanay dot kz` for the smart code below
// see https://www.php.net/manual/de/function.filesize.php#120250
function builtInBytesFormat( $sizeBytes, $maxSize = 0 ) {
	// returns bytes converted to human size
	// can be flexible (size determines unit) or use the unit which fits the MAX size in a list
	// can be user-defined completely (via "config.sys.php") to use a different logic
	
	if (SQL_SIZES_FLEXIBLE_UNITS) {	// the SIZEBYTES determines the factor/multiple
		$factor = floor((strlen($sizeBytes) - 1) / 3);
	}
	else {	// maxSize defines the factor
		$factor = floor((strlen($maxSize) - 1) / 3);
	}
	
	if ($factor) {
		$sz = "KMGTP";
	}
	// 2 decimals are hardcoded!
	$str = sprintf("%.2f", $sizeBytes / pow(1024, $factor));
	/*
	I don't think the result is universal, it's a string, isn't it?..
		f	The argument is treated as a float and presented as a floating-point number (locale aware).
		F	The argument is treated as a float and presented as a floating-point number (non-locale aware).
	
	Also, "393.43MB" becomes "0.38GB", why not "0.39GB"?
	"581.72MB" becomes "0.57GB", why not "0.58GB"?
	"481.15MB" becomes "0.47GB", why not "0.48Gb"?
	Ahhhh... it must be 1024, not 1000... all right, it makes sense.
	*/
	// now, remove ONE trailing zero if any, to leave values like "176.0", but not "176.00"
	$str = (substr($str, -1) == "0") ? substr($str, 0, -1) : $str;
	$str = ($str == "0.0") ? "0" : $str;	// but don't leave "0.0B"... a lof of conditions I have here, hence the ability to replace it with you own logic!
	return $str . @$sz[$factor - 1] . "B";

}

// XXX  

function translation( $key = "???" ) {
	global $sys;
	// load translation if not yet loaded
	// detect browser-side language if language is not set (which is broken interaction with the browser/user actually)
	// if browser language is not set or is bad, fallback to default
	
	//$_SERVER["HTTP_ACCEPT_LANGUAGE"] = "kr-GB; ...";	// debug
	
	$translationsDir = __DIR__ . "/../translations";
	
	if (!$sys["language"]) {	// there is only one situation when that's possible: there are no parameters, which means manual request (tinkering)
		// try default browser language
		if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
			// an example of the `Accept-Language` header: `en-GB,en;q=0.9,en-US;q=0.8,ru;q=0.7,uk;q=0.6`
			// FIXME . . . I should iterate through all languages, not just only try the first one, derp!
			$test = mb_strtolower(mb_substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2));
			if (preg_match("/[a-z]{2}/", $test) && file_exists("{$translationsDir}/{$test}.json")) {	// translation must also exist
				$sys["language"] = $test;
			}
		}
		// if browser language not set or sent, or is not valid, fallback to the configurable server-side parameter
		if (!$sys["language"]) {
			$sys["language"] = SQL_FALLBACK_LANGUAGE;
		}
	}
	else {
		// FIXME . . . check for a wild `$sys["language"]`, which doesn't have a translation file (tinkering or broken copy)
		// fallback to default language
	}
	
	if (!isset($sys["translation"])) {	// translations not yet loaded
		$translation = json_decode(file_get_contents("{$translationsDir}/{$sys["language"]}.json"), true);
		//var_dump(file_get_contents(__DIR__ . "/../translations/{$sys["language"]}.json"));
		//var_dump([$sys["language"], $translation, ]);
		$sys["translation"] = $translation["back-end"];
	}
	
	return isset($sys["translation"][$key]) ? $sys["translation"][$key] : "Translation not found: \"{$key}\"";	// write a `key` of a missing translation, to find and fix it easily; there is NO adequate way to make THIS line multi-lingual... I mean, I could make a configurable constant for that, but seriously... it's only for developers/tranlators to signal a missing text...
}

// XXX  

function encryptString( $encryptWhat, $encryptWith ) {
	$ivLength = openssl_cipher_iv_length(SQL_CIPHER_METHOD);
	$iv = substr($encryptWith, 0, $ivLength);
	$key = substr($encryptWith, $ivLength);
	return openssl_encrypt($encryptWhat, SQL_CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
}

// XXX  

function decryptString( $decryptWhat, $decryptWith ) {
	$ivLength = openssl_cipher_iv_length(SQL_CIPHER_METHOD);
	$iv = substr($decryptWith, 0, $ivLength);
	$key = substr($decryptWith, $ivLength);
	return openssl_decrypt($decryptWhat, SQL_CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
}

// XXX  

function saveConnections() {
	global $connections;
	// saves encrypted connections to the browser-side COOKIE
	
	$con = [];
	
	// passwords are encrypted already, but logins aren't, do it here
	// SESSION is expected to have the same number of keys for connections, as COOKIE has connections (and they are expected to be in the same order)
	// if SESSION is overdue, both must be empty, which is taken care by a different piece of code
	foreach ($connections as $connectionK => $c) {
		$loginJson = json_encode([
			"name" => $c["name"],
			"host" => $c["host"],
			"port" => $c["port"],
			"login" => $c["login"],
		]);
		$con[] = [
			"login" => encryptString($loginJson, $_SESSION["connections"][$connectionK]["login"]),
			"password" => $c["password"],
		];
	}
	
	//var_dump(["name" => SQL_COOKIE_NAME, "value" => base64_encode(serialize($con)), ]);
	
	// JSON turned out to be text-only, completely unable to handle binary data, you live and learn...
	// so, `serialize` it is...
	// (I expected it to handle anything with some prefix.)
	setcookie(SQL_COOKIE_NAME, base64_encode(serialize($con)), 0, "/");
	// ??? . . . do I care that it's a mix of `JSON`, `serialize` and `base64` formats?
}

// XXX  

function getSessionUniqueId() {
	// returns a current internal guaranteed unique ID and increments it, so it's always collision-free
	// new session = new IDs (which MIGHT cause collisions, but it's not handled currently)
	
	session_start();
	if (!isset($_SESSION["id"])) {	// internal guaranteed unique ID
		$_SESSION["id"] = 1;
	}
	$returnId = $_SESSION["id"];
	$_SESSION["id"]++;
	session_write_close();
	
	return $returnId;
}

// XXX  

function loadDriverByPort( $port ) {
	global $sys;
	/*
	`port` is ALWAYS set internally, even if it is not used in the login string (the default port value is used in this case). It is never empty/unset.
	This way `port` can ALWAYS be reliably used to select the database driver.
	
	Fallback to the default driver if no definition for the port found.
	*/
	$drivers = json_decode(SQL_PORTS_TO_DRIVERS, true);
	
	if (!isset($drivers[$port])) {
		// unknown port is treated as port scanning, thus a vague delayed "CONNECTION FAILED" message
		// (and it is a real connection failure indeed, if you ask me)
		fatalError(
			sprintf(
				translation("connection-failed-real"),
				"{$sys["db"]["user"]}@{$sys["db"]["host"]}:{$sys["db"]["port"]}"
			),
			true
		);
	}
	
	$driverName = $drivers[$port];
	require_once __DIR__ . "/{$driverName}";
	
	// leave only short "mysqli" or "pgsql" to send to the front side
	$sys["driver"] = str_replace(
		["php-", ".php", ],
		["", "", ],
		$driverName
	);
}


$sys = [];
$response = [];


error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
//error_reporting(E_ALL);	// uncomment to debug Notices and Warnings
ini_set("display_errors", "1");

// one way of isolating sessions, but a bad one for multiple reasons; left as an illustration:
//session_save_path(realpath(__DIR__ . "/../.tmp"));

// running on a dedicated subdomain with the default PHP session cookie name is fine (usually `PHPSESSID`), but running in a directory of an existing website is terrible because of shared session data: SQLantern can destroy website's sessions, and website can destroy SQLantern's session
// so, using a different cookie name to store an isolated session is REQUIRED for uninterrupted work without surprises
session_name(SQL_SESSION_NAME);

$ok = session_start();

/*
> SESSION DURATION

$check = session_get_cookie_params();
$response["sessionLifetime"] = $check["lifetime"];

Crap...
Does `session_regenerate_id` start a new timer?
Does `session_destroy` create a new session immediately with the old timer, with it expiring sooner?
Or with a new timer, so it is enough to prolong the session?
If I can't prolong the session this way, then how?!

https://www.php.net/manual/en/function.session-regenerate-id.php

> SOLUTION
I can delete SESSION cookie completely, just like I delete another cookie. Not just call `session_regenerate_id`. Or better both.
This way a new session will be created cleanly on the next code run (and not when the `session_regenerate_id` is called or even have inherited life), and the new timer will be correct.
*/

if (!array_key_exists("connections", $_SESSION)) {	// this is a new session
	$_SESSION["started"] = time();	// write down when it started
	$_SESSION["connections"] = [];
}


// have decrypted connections at hand in memory for multiple operations below (except for passwords, only one of which is decrypted at a time, for the chosen connection)
$connections = [];

if (isset($_COOKIE[SQL_COOKIE_NAME])) {
	$connections = unserialize(base64_decode($_COOKIE[SQL_COOKIE_NAME]));
	if (count($connections) == count($_SESSION["connections"])) {
		// decrypting in place...
		foreach ($connections as $connectionK => &$c) {
			$json = decryptString($c["login"], $_SESSION["connections"][$connectionK]["login"]);
			$tmp = json_decode($json, true);
			$c["name"] = $tmp["name"];
			$c["host"] = $tmp["host"];
			$c["port"] = $tmp["port"];
			$c["login"] = $tmp["login"];
		}
		unset($c);
	}
	else {	// some desync happened, a cookie got deleted probably, consider it "no connections"
		$_SESSION["connections"] = [];
		setcookie(SQL_COOKIE_NAME, "", 0, "/");	// remove cookie completely
	}
}
else {	// no cookie, reset SESSION connections
	$_SESSION["connections"] = [];
}

//precho(["connections" => $connections, "_SESSION_connections" => $_SESSION["connections"], ]);


//var_dump(["ok" => $ok, "_COOKIE" => $_COOKIE, "php_input" => file_get_contents("php://input"), "session_name" => session_name(), "session_save_path" => session_save_path(), "_POST" => $_POST, ]); die();
//var_dump(["_SESSION" => $_SESSION, ]);


$_POST = json_decode(SQL_INCOMING_DATA, true);

// debug:
//precho(["_POST" => $_POST, ]); die();
//precho(json_encode(["_POST" => $_POST, "_POST_too" => $_POST, ])); die();
//echo "... ??? " . json_encode(["_POST" => $_POST, "_POST_too" => $_POST, ]); die();

$post = postProcess();

if (!$post["raw"]) {
	header("{$_SERVER["SERVER_PROTOCOL"]} 404 Not Found", true, 404);	// don't accidentally reveal SQLantern to search engines
	header("Content-Type: text/html; charset=utf-8");
	echo translation("request-without-parameters");
	die();
}

$sys["language"] = isset($post["raw"]["language"]) ? $post["raw"]["language"] : "";

// NOTE _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _

if (array_key_exists("add_connection", $post["raw"])) {	// NOTE . . . add_connection
	/*
	>>> THINKING HAT ON
	Now, about password protection.
	If the connections are fully stored at client, and server session only has a key, you'll need both parts.
	But having client's part will allow to try bruteforce, because you might guess/know the connection names.
	So, storing only passwords at client is also an option.
	But then the server session will have connection names, which have logins, which is bad, as well.
	Might make cross-encryption: client has encrypted passwords, server has encrypted logins, and they store keys to each other.
	But that's kind of overwhelming, isn't it?
	
	CONS to saving login AND password in one single string (like JSON):
	- the structure tells when to stop the bruteforce (JSON or even a property name inside it),
	- while if only the password is stored, you never know when to stop, because adequate passwords aren't telling anything.
	
	Hm... all right, I'm thinking two different encryptions for logins and passwords now, that'll make things much, much harder to crack.
	Even better: a pair of random keys for every connection, one for connection name and another one for the password.
	Yes, that'll work.
	<<< THINKING HAT OFF
	
	So...
	- PHP session at server contains keys, a bunch of them, but all of them are useless without client's cookies
	- cookie storage at client contains encrypted logins + host and passwords.
	
	Encrypted values are stored in browser, because browser data is more likely to contain stored passwords anyway, and server-side SESSION stealing is the more critical thing I'm really fighting here.
	Storing only keys on the server makes session completely useless, because it doesn't even contain the data to decrypt, you only have keys.
	And if the browser is compromised, it's a big problem anyway.
	Also imagine the following situation: one server, multiple logins and databases; stealing passwords from multiple SESSIONS compromises many logins/password, while stealing data from browser compromises only one connection (usually/expected).
	*/
	
	if (!$post["raw"]["password"] && !SQL_ALLOW_EMPTY_PASSWORDS) {	// empty password sent with empty password disbled
		die(translation("empty-passwords-not-allowed"));
	}
	
	// login string format is "login@host:port", with "host" and "port" being optional
	$parts = explode(":", $post["raw"]["login"]);	// "rootik@192.168.1.1:3000" to ["rootik@192.168.1.1", "3000"]
	$port = (count($parts) == 1) ? SQL_DEFAULT_PORT : (int) array_pop($parts);	// use default port if no port provided
	
	$parts = explode("@", array_pop($parts));	// "rootik@192.168.1.1" to ["rootik", "192.168.1.1"]
	$host = (count($parts) == 1) ? SQL_DEFAULT_HOST : array_pop($parts);	// use last part of default value
	
	$login = array_pop($parts);
	
	if (!SQL_MULTIHOST && ($host != SQL_DEFAULT_HOST)) {	// multi-host is forbidden, and a non-default host is given
		// non-JSON response is the error catching logic, as of now
		die(translation("only-one-host-allowed"));
	}
	
	session_write_close();	// if I don't close session here, one hung connection (which happens when e.g. a server is offline) blocks ALL requests on the same domain until that hung connection times out, because of PHP sessions logic
	// this is also the only place, I believe, where this needs to be done, because other requests go after the global `session_write_close()` below and don't lock the PHP session
	
	$sys["db"] = [
		"host" => $host,
		"port" => $port,
		"user" => $login,
		"password" => $post["raw"]["password"],
		"dbName" => null,
	];
	loadDriverByPort($port);
	sqlConnect();	// only add to connections if connection successful; this line will cause fatal error and the code will not proceed if it is not
	
	session_start();	// reopen the session
	
	$portInNameStr = ($port == SQL_DEFAULT_PORT) ? "" : ":{$port}";	// only list non-default ports in connection names, as it's a bit annoying otherwise
	$connectionName = "{$login}@{$host}{$portInNameStr}";
	
	// don't duplicate connections, remove one here if the same connection already exists, that takes care of a connection currently in use after a password change
	$k = array_search($connectionName, array_column($connections, "name"));	// `array_column` is PHP 5.5.0+
	if ($k !== false) {
		unset($connections[$k]);
		unset($_SESSION["connections"][$k]);
		// and reset indexes just to keep it neat
		$connections = array_values($connections);
		$_SESSION["connections"] = array_values($_SESSION["connections"]);
	}
	
	
	$ivLength = openssl_cipher_iv_length(SQL_CIPHER_METHOD);
	// not using `random_bytes` to keep the code down to PHP 5.6: `random_bytes` is PHP 7+
	$iv = openssl_random_pseudo_bytes($ivLength);	// `iv` is "initialization vector", just for my information
	// keys actually combine IV and key, to store them in one string
	$loginKey = $iv . openssl_random_pseudo_bytes(SQL_CIPHER_KEY_LENGTH);
	$passwordKey = $iv . openssl_random_pseudo_bytes(SQL_CIPHER_KEY_LENGTH);
	
	// keys go to the server SESSION, encrypted values go to the browser COOKIE
	$_SESSION["connections"][] = [
		"login" => $loginKey,
		"password" => $passwordKey,
	];
	
	// "login" in COOKIE actually stores "name", "host" and "port"
	// and "password" stores only password
	// but that's taken care of in `saveConnections`, and `$connections` in memory have all the information raw (except for the password, which is decrypted in RAM for the ONE used connection)
	// that looks pretty safe to me...
	
	$connections[] = [
		"name" => $connectionName,
		"host" => $host,
		"port" => $port,
		"login" => $login,
		"password" => encryptString($post["raw"]["password"], $passwordKey),
	];
	
	saveConnections();
	
	$response["latest_connection"] = $connectionName;
	
	//precho(["_SESSION_connections" => $_SESSION["connections"], "connections" => $connections, ]);
	
}


if (array_key_exists("forget_connection", $post["raw"])) {	// NOTE . . . forget_connection
	$k = array_search($post["raw"]["forget_connection"], array_column($connections, "name"));
	if ($k !== false) {
		unset($_SESSION["connections"][$k]);
		unset($connections[$k]);
		
		if (!$_SESSION["connections"]) {	// all connections removed, end the session here
			setcookie(SQL_COOKIE_NAME, "", 0, "/");	// remove server-side storage cookie completely
			session_destroy();
			setcookie(session_name(), "");	// FIXME . . . is session cookie always `/`? I doubt it...
			//precho(["session_status" => session_status(), ]);
			// looks like `session_destroy` closes session or something... `session_status` is "1", which is "_NONE"
			session_start();
			session_regenerate_id(true);
			$_SESSION["connections"] = [];	// don't trigger "it's a new session" logic near `session_start()`, leave the timer as is
		}
		else {
			$_SESSION["connections"] = array_values($_SESSION["connections"]);
			$connections = array_values($connections);
			saveConnections();
		}
	}
	$response["result"] = "success";
	respond();
}

if (isset($post["raw"]["list_connections"])) {	// NOTE . . . list_connections
	$connections = array_column($connections, "name");
	natsort($connections);
	$response["connections"] = array_values($connections);	// `array_values`, because `natsort` preserves keys, and it becomes an object in JSON (peculiarly to me)
	$response["default_full_texts"] = !SQL_DEFAULT_SHORTEN;	// if shorten by default, `full texts` must be `off`, and vice versa
	respond();
}

if (isset($post["raw"]["list_config"])) {	// NOTE . . . list_config
	// list of languages on server, <del>ports-to-drivers</del> (don't reveal ports!!!), number format, default rows on page, available styles, <del>postgre connection database</del> (don't reveal it!), queries after connect
	$response["languages"] = [];
	$files = glob(__DIR__ . "/../translations/*.json");
	foreach ($files as $f) {
		//$info = pathinfo($f);
		//$response["languages"][] = $info["filename"];
		$response["languages"][] = basename($f, ".json");
	}
	respond();
}

if (isset($post["raw"]["save_config"])) {	// NOTE . . . save_config
	//...
}

session_write_close();

// actions with connections

//$con = $_SESSION["connections"][0];	// use the first connection by default
$k = array_search($post["raw"]["connection_name"], array_column($connections, "name"));
if ($k !== false) {
	$con = $connections[$k];
}
else {
	//$response = ["array_column" => array_column($_SESSION["connections"], "name"), "post_connection_name" => $post["raw"]["connection_name"], "k" => $k];
	// FIXME . . .
	// FIXME . . . . . . THIS BECOMES AN ENDLESS LOOP WITH A SESSION BUT NO COOKIE
	// FIXME . . .
	//die("<h2>CONNECTION DENIED FOR {$post["raw"]["connection_name"]}</h2>");
	die(sprintf(translation("connection-failed-fake"), $post["raw"]["connection_name"]));
}

//$response["post"] = $post; respond();

$sys["db"] = [
	"host" => $con["host"],
	"port" => $con["port"],
	"user" => $con["login"],
	//"password" => $con["password"],
	"password" => decryptString($con["password"], $_SESSION["connections"][$k]["password"]),
	"dbName" => isset($post["raw"]["database_name"]) ? $post["raw"]["database_name"] : "",
];

//precho(["sys_db" => $sys["db"], "_SESSION_connections" => $_SESSION["connections"], "connections" => $connections, ]);

loadDriverByPort($sys["db"]["port"]);

sqlConnect();	// connection is enforced
$post = postProcess();	// because there was no SQL connection and no sqlEscape function before

if (isset($post["raw"]["list_db"])) {	// NOTE . . . list_db
	$response["databases"] = sqlListDb();
	$response["quote"] = sqlQuote();	// the "identifier quote character" is different in MariaDB/MySQL and PostgreSQL
	//precho(["response" => $response, ]); die();
}

//precho(["response" => $response, ]); die();

if (isset($post["raw"]["list_tables"])) {	// NOTE . . . list_tables
	
	$res = sqlListTables($post["sql"]["database_name"]);
	
	$response["tables"] = $res["tables"];
	$response["views"] = $res["views"];
	
	$response["driver"] = $sys["driver"];
	
	$response["export_import"] = function_exists("sqlExport") && function_exists("sqlImport");
	
	// Isn't the code below a bit overcomplicated AF? :-(
	$limitStr = function( $src, $limit, $value ) {
		return str_replace(
			["{source}", "{limit}", "{value}", ],
			[$src, $limit, $value, ],
			translation("import-server-limit")
		);
	};
	
	$limits = [];
	
	if (function_exists("sqlImportLimits")) {
		// MySQL has a package limit, but Posgres doesn't, so that one is an optional warning...
		$sqlLimits = sqlImportLimits();
		foreach ($sqlLimits as $var => $value) {
			$limits[] = $limitStr("SQL", $var, $value);
		}
	}
	
	$phpVars = ["post_max_size", "upload_max_filesize", "memory_limit", ];
	foreach ($phpVars as $varName) {
		$limits[] = $limitStr("PHP", $varName, ini_get($varName));
	}
	
	$response["import_limits"] = sprintf(
		translation("import-server-limits"),
		implode("<br>", $limits)
	);
	
}

if (isset($post["raw"]["describe_table"])) {	// NOTE . . . describe_table
	$res = sqlDescribeTable($post["sql"]["database_name"], $post["sql"]["table_name"]);
	$response["structure"] = $res["structure"] ? $res["structure"] : [];	// it can be NULL, return empty array anyway
	
	// format the "cardinality" ifany
	$numberFormat = SQL_NUMBER_FORMAT;	// constants cannot be used as function names directly
	foreach ((array) $res["indexes"] as &$row) {
		foreach ($row as $key => &$value) {
			// check for non-empty `$value`, because the value can be empty, instead of NULL/0
			if ($value && (mb_strtolower($key) == "cardinality")) {
				$value = $numberFormat($value);
			}
		}
	}
	unset($row, $value);
	
	$response["indexes"] = $res["indexes"];
}

if (isset($post["raw"]["query"])) {	// NOTE . . . query
	// cannot use ["sql"]["query"], because it converts line breaks to literal "\n" strings in requests, must use "raw"
	$query = trim(trim($post["raw"]["query"]), ";");	// allow queries ending with `;` (but it will not run multiple queries anyway)
	// (and remove any white space first, derp)
	//precho(["query" => $query, ]); die();
	
	$page = isset($post["int"]["page"]) ? (int) $post["int"]["page"] : 0;
	
	$res = sqlRunQuery($query, $page, $post["raw"]["full_texts"]);
	
	// debug "processing":
	//sleep(2);
	
	/*
	$response["real_executed_query"] = $res["real_executed_query"];
	$response["num_rows"] = $res["num_rows"];
	$response["num_pages"] = $res["num_pages"];
	$response["cur_page"] = $res["cur_page"];
	$response["rows"] = $res["rows"];
	*/
	$response = array_merge($response, $res);
}

if (isset($post["raw"]["query_timing"])) {	// NOTE . . . query_timing
	$query = trim(trim($post["raw"]["query_timing"]), ";");	// allow queries ending with `;` (but will not run multiple queries)
	$res = sqlQueryTiming($query);
	$response["time"] = $res["timeMs"];
}

if (isset($post["raw"]["export_database"])) {	// NOTE . . . export_database
	$options = [
		//"format" => "text", // no-no-no, it should be handled outside
		"structure" => in_array($post["raw"]["what"], ["data_structure", "structure"]),
		"data" => in_array($post["raw"]["what"], ["data_structure", "data"]),
		"transactionData" => in_array($post["raw"]["transaction"], ["data", "everything"]),
		"transactionStructure" => ($post["raw"]["transaction"] == "everything"),
		"rows" => (int) $post["int"]["rows"],
	];
	if (isset($post["raw"]["tables"])) {
		$options["tables"] = json_decode($post["raw"]["tables"], true);	// just an array of strings is expected
	}
	
	ini_set("max_execution_time", 0); // == set_time_limit(0)
	//ini_set("memory_limit", "1G");
	
	if ($post["raw"]["format"] == "file") {
		// force further echoes into download
		
		//header("Content-Description: File Transfer");	// Why do I keep seeing this header in examples? It must not change anything or add any value. Is it just a case of some thoughtlessly widely copied example?
		
		header("Content-Type: application/sql");
		/*
		```
		The official answer according to IANA is application/sql.
		However, since lots of people don't bother to read documentation you might also want to accept `text/sql`, `text/x-sql` and `text/plain`.
		```
		Source: https://stackoverflow.com/questions/14268401/sql-file-extension-type
		*/
		
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: 0");
		header("Content-Disposition: attachment; filename=\"{$sys["db"]["dbName"]}.sql\"");
	}
	
	sqlExport($options);
	die();	// custom case: the response is not JSON
}

if (isset($post["raw"]["import_get_id"])) {	// NOTE . . . import_get_id
	$response["import_id"] = getSessionUniqueId();
	session_start();
	$_SESSION["import_{$response["import_id"]}"] = json_encode([
		"startedUnix" => time(),
		"progress" => translation("import-progress-uploading"),
		"finished" => false,
	]);
	session_write_close();
}

if (isset($post["raw"]["import_database"])) {	// NOTE . . . import_database
	// import in fact executes any set queries, but it's "import_database" to be in line with "export_database"
	ini_set("max_execution_time", 0); // == set_time_limit(0)
	
	//var_dump(["_FILES" => $_FILES, ]); die();
	//var_dump(translation("import-progress")); die();
	
	$importSql = $_FILES["import_file"]["tmp_name"] ? file_get_contents($_FILES["import_file"]["tmp_name"]) : $post["raw"]["import"];	// auto-detect file or text
	// ^ ^ ^ can run out or memory here
	
	sqlImport((int) $post["int"]["import_id"], $importSql);	// `$importSql` is in fact passed by reference to save RAM
	
	//$response["import"] = $post["raw"]["import"];
	$response["import_database"] = "ok";
}



/*
The code below is a momument to my failed attempts to monitor progress in EventSource.
It didn't work for me here, although exactly the same approach apparently works for other people on the internet.
E.g.: https://stackoverflow.com/questions/31636465/javascript-eventsource-to-update-import-progress-in-a-php-file

Leaving it, because I want to look into it again later, that's so much better than polling.

My search when I abandoned this idea:
php eventsource refresh session site:stackoverflow.com
*/
if (false && isset($post["raw"]["__NOT__import_progress"])) {	// NOTE . . . __NOT__import_progress
	// establishes an EventSource connection, which reports an import progress periodically
	
	ini_set("max_execution_time", 0); // == set_time_limit(0)
	//ini_set("output_buffering", "Off");
	
	// Set file mime type event-stream
	header("Content-Type: text/event-stream");
	header("Cache-Control: no-cache");
	
	$response["finished"] = false;
	
	$importId = (int) $post["int"]["import_id"];
	if (!$importId) {
		$response["error"] = translation("import-bad-id");
		respond();
	}
	if (!$_SESSION["import_{$importId}"]) {
		$response["error"] = translation("import-id-not-found");;
		respond();
	}
	
	/*
	
	The same idea is used here as I'm trying to implement, but I can't do it, while the author could... :-(
	https://stackoverflow.com/questions/31636465/javascript-eventsource-to-update-import-progress-in-a-php-file
	
	*/
	
	// read session, report progress, close session, etc
	
	//sleep(10);	// test if SESSION is updated by `sqlImport`
	
	$n = 0;
	
	while (true) {
		//unset($_SESSION);
		/*
		Caution: Do NOT unset the whole $_SESSION with unset($_SESSION) as this will disable the registering of session variables through the $_SESSION superglobal.
		
		Use $_SESSION = [] to unset all session variables even if the session is not active.
		*/
		//$_SESSION = [];
		
		session_start();	// just read the current values in session
		//session_reset();
		/*
		I can't find it in the official docs, but this seems to be true and it makes sense in almost all cases:
		"session_start() cannot be called once an output has started"
		How the hell do I reread the session in EventSource, though...
		*/
		session_abort();
		//session_write_close();
		
		$progress = json_decode($_SESSION["import_{$importId}"], true);
		
		$sendThis = [
			//"import_id" => $importId,
			//"session" => print_r($_SESSION["import_{$importId}"], true),
			//"session_test" => $_SESSION["test"],
			
			"state" => sprintf(
				translation("import-progress-timer"),
				$progress["progress"],
				time() - $progress["startedUnix"]
			),
			"finished" => $progress["finished"],
		];
		
		//session_write_close();
		
		echo str_pad( 
			"event: message\n"
			. "data: " . json_encode($sendThis) . "\n\n\n",
			4096,
			" "
		);
		// the 4K problem is something I don't know how to solve better, especially without access to the server configuration (think shared hosting), so I'm afraid this "solution" stays here for quite some time or forever
		
		// Flush buffer (force sending data to client)
		//ob_end_flush();
		ob_flush();	// flushing overrides PHP buffer, but apparently not web-server buffer
		flush();
		
		usleep(2 * 1000000);	// 1 second = 1000000
		if (connection_aborted()) {
			break;
		}
		
		//die();
		
		$n++;
		if ($n > 10) {
			$sendThis = ["finished" => true];
			echo str_pad( 
				"event: message\n"
				. "data: " . json_encode($sendThis) . "\n\n\n",
				4096,
				" "
			);
			ob_flush();	// flushing overrides PHP buffer, but apparently not web-server buffer
			flush();
		}
	}
	
	die();
}


if (isset($post["raw"]["import_progress"])) {	// NOTE . . . import_progress
	$importId = (int) $post["int"]["import_id"];
	if (!$importId) {
		$response["error"] = translation("import-bad-id");
		respond();
	}
	if (!$_SESSION["import_{$importId}"]) {
		$response["error"] = translation("import-id-not-found");;
		respond();
	}
	$progress = json_decode($_SESSION["import_{$importId}"], true);
	$response["state"] = sprintf(
		translation("import-progress-timer"),
		$progress["progress"],
		time() - $progress["startedUnix"]
	);
	$response["finished"] = $progress["finished"];
}


/*
if (isset($post["raw"]["save_storage"])) {	// NOTE . . . save_storage
	// browser storage is inpersistent, and I don't want to open the can of worms of storing it in the database
	// I prefer another, smaller can of worms, and store it to server disk instead
	// this solution is far from ideal, but is more universal, portable, and safe enough, in my opinion
	
	// it will in fact store anything thrown at it (any string, to be precise), it's content-agnostic and primitive
	
	// data in encrypted, and password is the encryption key
	// password hash is the array key, in case multiple storages are saved (use simple passwords = potentitally share your storage with strangers)
	// saving and restoring storage is only available for users with valid connections (so, having at least one correct database password + using additional password = protection)
	// one more reason not to allow remote hosts on an unprotected copy (IP or password-protected directory/domain): someone could just connect to their own database and then brute force the copies of storage
	
	// I could write a more robust "password sets seed to generate random bytes", but it wouldn't add any more security, as I'll use password as a starting point anyway, so there's no need to wrap it more
	
	$password = $post["raw"]["password"];
	$hash = md5($password);
	// `password_hash`? Can I always safely use the result of `password_hash` as an array key? Maybe after base64?
	// IDEA . . . I can encrypt password using the password itself for random bytes, too (or part of it), and md5 this new value, and if anyone decrypts this md5, it doesn't help them to know the password and decrypt the data
}

if (isset($post["raw"]["restore_storage"])) {	// NOTE . . . restore_storage
}
*/


//sleep(2);

respond();

//